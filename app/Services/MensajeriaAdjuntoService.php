<?php

namespace App\Services;

use App\Models\Adjunto;
use App\Models\Mensaje;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guardado de los adjuntos de la mensajería.
 *
 * Todo va al disco `local` (no accesible por HTTP). Las imágenes se reescalan y
 * se vuelven a codificar antes de guardarse, lo que además de ahorrar disco y
 * tiempo de carga tiene un efecto que acá importa: reencodear con GD descarta
 * los metadatos EXIF del original, o sea la geolocalización que los celulares
 * meten en cada foto. En un chat de trabajo nadie espera estar compartiendo las
 * coordenadas de donde estaba parado.
 */
class MensajeriaAdjuntoService
{
    /**
     * Procesa y guarda los archivos de un mensaje.
     *
     * Se llama DESPUÉS de que el mensaje esté confirmado en la base, y fuera de
     * cualquier transacción: un write a disco no se revierte con un rollback
     * (mismo criterio que PaqueteController al guardar la foto del paquete).
     *
     * @param  UploadedFile[]  $archivos
     * @return Adjunto[]
     */
    public function guardar(Mensaje $mensaje, array $archivos): array
    {
        $guardados = [];

        foreach ($archivos as $archivo) {
            if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                continue;
            }

            try {
                $guardados[] = $this->guardarUno($mensaje, $archivo);
            } catch (\Throwable $e) {
                // Un archivo que falla no tira abajo al resto ni al mensaje: el
                // texto ya está publicado y perderlo por una foto corrupta sería
                // peor que quedarse sin la foto.
                Log::error('Mensajería: no se pudo guardar un adjunto', [
                    'mensaje_id' => $mensaje->id,
                    'nombre'     => $archivo->getClientOriginalName(),
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $guardados;
    }

    protected function guardarUno(Mensaje $mensaje, UploadedFile $archivo): Adjunto
    {
        $mime = (string) $archivo->getMimeType();
        $nombre = $this->nombreLimpio($archivo->getClientOriginalName());

        // Nombre en disco aleatorio, nunca el que trae el archivo: así ningún
        // nombre raro ("../../x", un .php, un unicode invisible) participa de la
        // ruta real.
        $carpeta = 'mensajeria/adjuntos/' . $mensaje->canal_id;
        $ancho = null;
        $alto = null;

        if ($this->esImagenProcesable($mime)) {
            [$binario, $mime, $ancho, $alto] = $this->procesarImagen($archivo, $mime);
            $ext = $mime === 'image/png' ? 'png' : 'jpg';
            $path = $carpeta . '/' . Str::random(32) . '.' . $ext;
            Storage::disk('local')->put($path, $binario);
        } else {
            $ext = strtolower($archivo->getClientOriginalExtension() ?: 'bin');
            $path = $carpeta . '/' . Str::random(32) . '.' . $ext;
            Storage::disk('local')->putFileAs($carpeta, $archivo, basename($path));
        }

        return Adjunto::create([
            'mensaje_id'      => $mensaje->id,
            'path'            => $path,
            'nombre_original' => $nombre,
            'mime'            => $mime,
            'tamano'          => Storage::disk('local')->size($path),
            'ancho'           => $ancho,
            'alto'            => $alto,
            'created_at'      => now(),
        ]);
    }

    /**
     * GIF y WebP quedan afuera del reescalado y se guardan tal cual.
     *
     * Un GIF animado reencodeado con GD pierde la animación y queda un cuadro
     * fijo, que es peor que un archivo grande. WebP depende de cómo se compiló
     * GD, así que tampoco vale arriesgarse.
     */
    protected function esImagenProcesable(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png'], true)
            && extension_loaded('gd');
    }

    /**
     * Reescala, corrige orientación y reencodea.
     *
     * @return array{0: string, 1: string, 2: int, 3: int}  [binario, mime, ancho, alto]
     */
    protected function procesarImagen(UploadedFile $archivo, string $mime): array
    {
        $maxPx = (int) config('mensajeria.adjuntos.imagen_max_px');
        $calidad = (int) config('mensajeria.adjuntos.imagen_calidad');

        $ruta = $archivo->getRealPath();

        $imagen = $mime === 'image/png'
            ? @imagecreatefrompng($ruta)
            : @imagecreatefromjpeg($ruta);

        if (! $imagen) {
            throw new \RuntimeException('GD no pudo leer la imagen');
        }

        // Los celulares no rotan el pixel: guardan la foto como salió del sensor
        // y anotan la orientación en el EXIF. Sin esto, las fotos verticales
        // aparecen acostadas en el chat.
        if ($mime === 'image/jpeg' && extension_loaded('exif')) {
            $imagen = $this->corregirOrientacion($imagen, $ruta);
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        $escala = min(1, $maxPx / max($ancho, $alto));

        if ($escala < 1) {
            $nuevoAncho = max(1, (int) round($ancho * $escala));
            $nuevoAlto = max(1, (int) round($alto * $escala));

            $redimensionada = imagescale($imagen, $nuevoAncho, $nuevoAlto);

            if ($redimensionada) {
                imagedestroy($imagen);
                $imagen = $redimensionada;
                $ancho = $nuevoAncho;
                $alto = $nuevoAlto;
            }
        }

        ob_start();

        if ($mime === 'image/png') {
            // El PNG se conserva como PNG: suele ser una captura de pantalla, y
            // pasarla a JPEG le mete artefactos justo en el texto, que es lo
            // único que se quería mostrar.
            imagesavealpha($imagen, true);
            imagepng($imagen, null, 8);
        } else {
            imagejpeg($imagen, null, $calidad);
        }

        $binario = (string) ob_get_clean();
        imagedestroy($imagen);

        return [$binario, $mime, $ancho, $alto];
    }

    /** Aplica la rotación/espejado que indica el EXIF del original. */
    protected function corregirOrientacion($imagen, string $ruta)
    {
        $exif = @exif_read_data($ruta);
        $orientacion = (int) ($exif['Orientation'] ?? 1);

        if ($orientacion <= 1) {
            return $imagen;
        }

        $rotaciones = [3 => 180, 4 => 180, 5 => -90, 6 => -90, 7 => 90, 8 => 90];

        if (isset($rotaciones[$orientacion])) {
            $rotada = imagerotate($imagen, $rotaciones[$orientacion], 0);

            if ($rotada) {
                imagedestroy($imagen);
                $imagen = $rotada;
            }
        }

        // 2, 4, 5 y 7 vienen además espejados.
        if (in_array($orientacion, [2, 4, 5, 7], true)) {
            imageflip($imagen, IMG_FLIP_HORIZONTAL);
        }

        return $imagen;
    }

    /** Nombre presentable, sin rutas ni caracteres de control. */
    protected function nombreLimpio(?string $nombre): string
    {
        $limpio = trim(preg_replace('/[\x00-\x1F\/\\\\]+/', '', (string) $nombre));

        return $limpio === '' ? 'archivo' : Str::limit($limpio, 200, '');
    }

    /** Borra los archivos de un mensaje y sus filas. Se usa al eliminarlo. */
    public function borrarDe(Mensaje $mensaje): void
    {
        $adjuntos = Adjunto::where('mensaje_id', $mensaje->id)->get();

        foreach ($adjuntos as $adjunto) {
            $adjunto->borrarArchivo();
            $adjunto->delete();
        }
    }
}
