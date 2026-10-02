<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    /**
     * `paqueteria` queda fuera de $fillable a propósito: es un rango, no un
     * dato del perfil. Se asigna explícitamente desde el controlador de admin
     * para que ningún create()/update() masivo pueda otorgarlo por accidente.
     *
     * `admin` no se castea: el front lo compara con === 1 y castearlo a boolean
     * cambiaría el JSON del login.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_rebotado_at' => 'datetime',
        'paqueteria'        => 'boolean',
    ];

    protected static function booted()
    {
        // La marca de rebote es de la dirección, no de la persona: si se le
        // cambia el email, la dirección nueva arranca limpia. Si también
        // rebota, la próxima sincronización con Postmark la vuelve a marcar.
        // (Sólo cubre cambios por Eloquent; un DB::table('users')->update()
        // no pasa por acá.)
        static::updating(function (User $user) {
            if ($user->isDirty('email')) {
                $user->email_rebotado_at = null;
            }
        });
    }

    /**
     * De una lista de direcciones, las que pertenecen a algún usuario marcado
     * como rebotado. Comparación sin mayúsculas ni espacios, en minúscula.
     *
     * @param  string[]  $emails
     * @return string[]
     */
    public static function emailsRebotados(array $emails): array
    {
        $normalizados = array_values(array_unique(array_map(
            fn ($e) => strtolower(trim((string) $e)),
            $emails
        )));

        if (empty($normalizados)) {
            return [];
        }

        return static::query()
            ->whereNotNull('email_rebotado_at')
            ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(email))'), $normalizados)
            ->selectRaw('LOWER(TRIM(email)) as email_norm')
            ->pluck('email_norm')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Cuenta dedicada de paquetería (portería): entra directo a la oficina y no
     * ve gastos comunes, lotes, turnero ni archivos.
     *
     * Distinto de PaqueteriaOperario, que es el permiso aditivo de un vecino
     * que además atiende la oficina.
     */
    public function esPaqueteria(): bool
    {
        return (bool) $this->paqueteria;
    }

    /**
     * La fila de `users` con la que un email inicia sesión.
     *
     * Hace falta porque en esta base `users.email` NO tiene índice único: la
     * migración lo declara, pero nunca se aplicó a la tabla legacy. Hoy hay unos
     * 460 emails repartidos en más de 1000 filas, algunos con 7 u 8 filas.
     *
     * El login toma UNA sola de esas filas, así que cualquier permiso otorgado
     * sobre otra fila del mismo email queda inerte: la sesión nunca va a tener
     * ese id. Es exactamente lo que pasaba al habilitar el chat sobre el id
     * equivocado —se guardaba el permiso y la persona seguía sin ver el módulo—.
     *
     * Este método es el ÚNICO lugar donde se decide cuál es "la" cuenta de un
     * email, y lo usan tanto AuthController@login como el alta de la mensajería,
     * para que no puedan divergir nunca.
     *
     * El `orderBy('id')` es parte del contrato, no un detalle: sin ORDER BY, qué
     * fila devuelve MySQL es indefinido, y el permiso podría quedar apuntando a
     * una fila distinta de la que autentica según el plan de la consulta.
     */
    public static function cuentaDeLogin(string $email): ?self
    {
        return static::where('email', $email)->orderBy('id')->first();
    }

    /** ¿Es esta fila la que se usa para iniciar sesión con su email? */
    public function esCuentaDeLogin(): bool
    {
        $canonica = static::cuentaDeLogin((string) $this->email);

        return $canonica && (int) $canonica->id === (int) $this->id;
    }

    /**
     * Nombre para mostrar en pantalla, con el email como respaldo.
     *
     * Usa `?:` y NO `??` a propósito: en esta base `nombre` no sólo puede ser
     * NULL, también puede ser cadena vacía —hay cuentas así, por ejemplo la de
     * administración—, y `??` no cae al respaldo con `''`. Eso dejaba a esas
     * cuentas sin nombre visible en el directorio de la mensajería, o sea
     * imposibles de encontrar buscándolas.
     */
    public function nombreVisible(): string
    {
        return $this->nombre ?: ($this->email ?: 'Sin nombre');
    }

    public function fcmTokens()
    {
        return $this->hasMany(UserFcmToken::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }
}
