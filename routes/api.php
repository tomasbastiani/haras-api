<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\GastosNotificacionesController;
use App\Http\Controllers\AdminMailController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ImportadorController;
use App\Http\Controllers\FcmController;
use App\Http\Controllers\TurneroController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ReclamoController;
use App\Http\Controllers\PaqueteController;
use App\Http\Controllers\PaqueteriaUsuarioController;
use App\Http\Controllers\MensajeriaController;
use App\Http\Controllers\CanalController;
use App\Http\Controllers\MensajeController;
use App\Http\Controllers\MensajeriaMiembroController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [AuthController::class, 'login']);

// Rutas que usan los vecinos (y también el admin). Antes eran públicas: sin
// login se podían leer los lotes de cualquiera, cambiar el email de un lote,
// crear usuarios o subir/borrar archivos. Ojo: varias todavía toman el email
// de la URL o del body y no del token (pendiente, ver CLAUDE.md).
Route::middleware('auth:sanctum')->group(function () {
    // Perfil (Profile.vue)
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/mis-lotes/{email}', [FacturaController::class, 'getLotesPorEmail']);
    // Profile.vue (el vecino cambia el email de su lote) y EditUser.vue (admin)
    Route::post('/update-email-lote', [FacturaController::class, 'updateEmailLote']);
    Route::post('/create-user', [FacturaController::class, 'createUserIfNotExists']);
    Route::get('/verificar-email/{email}', [FacturaController::class, 'verificarEmail']);

    // Gastos comunes del vecino (GastosComunes.vue)
    Route::get('/gastoscomunes/{email}', [FacturaController::class, 'buscarPorDni']);
    Route::get('/gastos/pdf/{numero}/{nlote}', [FacturaController::class, 'verPDF']);

    // Archivos (Files.vue: el vecino sube, lista, descarga y borra los suyos)
    Route::get('/lotes-por-user/{email}', [FacturaController::class, 'getLotesPorUser']);
    Route::get('/cartas', [FacturaController::class, 'getCartas']);
    Route::post('/archivos', [ArchivoController::class, 'store']);
    Route::delete('/archivos/{id}', [ArchivoController::class, 'destroy']);
    Route::get('/archivos/{id}/download', [ArchivoController::class, 'download']);
    Route::get('/archivos/user/{user}', [ArchivoController::class, 'indexByUser']);
});

// Administración de gastos comunes, usuarios y archivos. Sólo admin. Antes eran
// públicas: sin login se podía bajar la lista de emails de todos los vecinos o
// crear, modificar y borrar períodos enteros de expensas.
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // GastosComunes.vue (bloque admin) y Listado.vue
    Route::get('/facturas-todas', [FacturaController::class, 'listarTodos']);
    Route::get('/gastos/periodos', [FacturaController::class, 'obtenerPeriodos']);
    Route::post('/gastos/agregar', [FacturaController::class, 'agregarGasto']);
    Route::delete('/gastos/eliminar/{numero}', [FacturaController::class, 'eliminarPorPeriodo']);
    Route::post('/update-gasto', [FacturaController::class, 'updateGasto']);
    // EditUser.vue y NotificationsCenter.vue
    Route::get('/emails-por-lote', [FacturaController::class, 'obtenerEmailsPorLote']);
    // Todos los archivos de todos. Ninguna pantalla lo usa hoy.
    Route::get('/archivos', [ArchivoController::class, 'index']);
});
// Formulario de contacto. Con login: sin él, cualquiera podía usar el servidor
// para mandar mails desde nuestro dominio (to/subject/message vienen del body).
Route::middleware('auth:sanctum')->post('/enviar-contacto', [ContactoController::class, 'enviar']);

// Importadores de gastos comunes / morosos y aviso masivo por mail. Sólo admin:
// las listas tienen email, nombre, lote y CVU de cada vecino, el import las
// reemplaza enteras, y el aviso manda un mail a todos. Sin esta puerta
// cualquiera podía leerlas, pisarlas o usar el servidor para mandar mails.
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/importar-gastos', [ImportadorController::class, 'importarGastos']);
    Route::get('/importar-gastos/plantilla', [ImportadorController::class, 'plantillaGastos']);
    Route::post('/importar-morosos', [ImportadorController::class, 'importarMorosos']);
    Route::get('/gastoscomunes', [ImportadorController::class, 'obtenerGastos']);
    Route::get('/morosos', [ImportadorController::class, 'obtenerMorosos']);
    Route::post('/gastos/notificar', [GastosNotificacionesController::class, 'notificar']);
});


// Mail personalizado masivo. Sólo admin: antes estaba abierto y cualquiera, sin
// cuenta, podía mandar a una lista ilimitada de direcciones con asunto y texto
// libres desde nuestro dominio.
Route::middleware(['auth:sanctum', 'admin'])->post('/admin/enviar-mail-personalizado', [AdminMailController::class, 'sendCustomMail']);
// Progreso de un envío masivo encolado (gastos comunes o personalizado).
Route::middleware(['auth:sanctum', 'admin'])->get('/admin/envios-masivos/{id}', [AdminMailController::class, 'estadoEnvio'])->whereNumber('id');

// Notificaciones / FCM: requieren sesión válida (token Sanctum). El usuario se
// identifica por el token, nunca por un email enviado en el body (evita IDOR).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/fcm-token', [FcmController::class, 'saveToken']);
    Route::post('/notifications', [FcmController::class, 'getNotifications']);
    Route::post('/notifications/read', [FcmController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [FcmController::class, 'markAllAsRead']);

    Route::middleware('admin')->group(function () {
        Route::post('/fcm-test', [FcmController::class, 'sendTest']);
        Route::post('/fcm-send', [FcmController::class, 'sendNotification']);
        Route::get('/notifications/read-log', [FcmController::class, 'readLog']);
    });
});

// Chatbot de reclamos. Todo bajo sesión: el propietario sale del token de
// Sanctum, nunca de un email en el body (mismo criterio que FCM).
Route::middleware('auth:sanctum')->group(function () {
    // 12 mensajes por minuto: suficiente para conversar, corta el abuso de una
    // API que se paga por token.
    Route::middleware('throttle:12,1')->group(function () {
        Route::post('/chat/mensaje', [ChatbotController::class, 'mensaje']);
    });
    Route::get('/chat/historial', [ChatbotController::class, 'historial']);
    Route::post('/chat/reiniciar', [ChatbotController::class, 'reiniciar']);

    // Reclamos
    Route::get('/reclamos/mios', [ReclamoController::class, 'mios']);
    Route::get('/reclamos', [ReclamoController::class, 'index']);
    Route::post('/reclamos/{id}/tomar', [ReclamoController::class, 'tomar']);
    Route::post('/reclamos/{id}/resolver', [ReclamoController::class, 'resolver']);
});

// Paquetería. Todo bajo sesión: el titular sale del token de Sanctum, nunca de
// un email en el body (mismo criterio que FCM y reclamos).
Route::middleware('auth:sanctum')->group(function () {
    // Vecino
    Route::get('/paquetes/mios', [PaqueteController::class, 'mios']);
    Route::post('/paquetes/{id}/confirmar', [PaqueteController::class, 'confirmar']);
    Route::post('/paquetes/{id}/desconocer', [PaqueteController::class, 'desconocer']);

    // Oficina de paquetería
    Route::get('/paqueteria/acceso', [PaqueteController::class, 'acceso']);
    Route::get('/paquetes', [PaqueteController::class, 'index']);
    Route::post('/paquetes', [PaqueteController::class, 'store']);
    Route::post('/paquetes/{id}/devolver', [PaqueteController::class, 'devolver']);
    Route::post('/paquetes/{id}/observacion', [PaqueteController::class, 'observar']);

    // Cerrar entrega. Throttle bajo: el PIN es de 6 dígitos y el bloqueo por
    // intentos es por paquete, así que esto corta el barrido sobre muchos
    // paquetes a la vez desde una misma terminal.
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/paquetes/{id}/entregar', [PaqueteController::class, 'entregar']);
    });

    // Compartidas (el controlador resuelve si es titular, operario o admin)
    Route::get('/paquetes/{id}', [PaqueteController::class, 'show']);
    Route::get('/paquetes/{id}/firma', [PaqueteController::class, 'firma']);
    Route::get('/paquetes/{id}/foto', [PaqueteController::class, 'foto']);
    Route::get('/paquetes/{id}/entrega-foto', [PaqueteController::class, 'entregaFoto']);

    // Alta/baja de cuentas de portería. Sólo admin: acá se otorga el permiso.
    Route::middleware('admin')->group(function () {
        Route::get('/admin/paqueteria/usuarios', [PaqueteriaUsuarioController::class, 'index']);
        Route::get('/admin/paqueteria/usuarios/buscar', [PaqueteriaUsuarioController::class, 'buscar']);
        Route::post('/admin/paqueteria/usuarios', [PaqueteriaUsuarioController::class, 'store']);
        Route::patch('/admin/paqueteria/usuarios/{id}', [PaqueteriaUsuarioController::class, 'update']);
        Route::post('/admin/paqueteria/usuarios/{id}/password', [PaqueteriaUsuarioController::class, 'resetPassword']);
    });
});

// Mensajería interna (chat privado del personal).
//
// Doble puerta: `auth:sanctum` da la identidad y `mensajeria` el permiso. Este
// último se chequea en CADA request y no una vez en el login, porque las
// sesiones de esta app no expiran solas: validar sólo al entrar le dejaría el
// chat abierto a un empleado dado de baja hasta que cerrara sesión.
//
// El autor de todo sale del token, nunca de un user_id del body (mismo criterio
// que FCM, reclamos y paquetería).
// Consulta de acceso. Va SIN el middleware `mensajeria` porque la contesta para
// cualquier usuario logueado (sí/no, nunca 403): la llama el armado del menú, y
// es lo que mantiene sincronizado el flag de localStorage en sesiones que no
// expiran nunca. Mismo rol que /paqueteria/acceso.
Route::middleware('auth:sanctum')->get('/mensajeria/acceso', [MensajeriaController::class, 'acceso']);

Route::middleware(['auth:sanctum', 'mensajeria'])->prefix('mensajeria')->group(function () {
    // Foto completa al entrar, y el latido del tiempo real.
    Route::get('/bootstrap', [MensajeriaController::class, 'bootstrap']);
    Route::get('/sync', [MensajeriaController::class, 'sync']);
    Route::get('/directorio', [MensajeriaController::class, 'directorio']);
    // Sólo el total de no leídos, para el badge del navbar. Es el endpoint más
    // liviano del módulo: lo llama cada pantalla del portal, no sólo el chat.
    Route::get('/no-leidos', [MensajeriaController::class, 'noLeidos']);

    // Canales
    Route::post('/canales', [CanalController::class, 'store']);
    Route::post('/canales/directo', [CanalController::class, 'directo']);
    Route::get('/canales/{id}', [CanalController::class, 'show']);
    Route::post('/canales/{id}/miembros', [CanalController::class, 'agregarMiembros']);
    Route::delete('/canales/{id}/miembros/{userId}', [CanalController::class, 'quitarMiembro']);
    Route::post('/canales/{id}/archivar', [CanalController::class, 'archivar']);
    Route::post('/canales/{id}/silenciar', [CanalController::class, 'silenciar']);

    // Mensajes
    Route::get('/canales/{id}/mensajes', [MensajeController::class, 'index']);
    Route::post('/canales/{id}/leido', [MensajeController::class, 'leido']);
    Route::patch('/mensajes/{id}', [MensajeController::class, 'update']);
    Route::delete('/mensajes/{id}', [MensajeController::class, 'destroy']);

    // Adjuntos. Este endpoint es la única puerta a los archivos: viven en el
    // disco `local`, que no se sirve por HTTP, y acá se chequea el canal.
    Route::get('/adjuntos/{id}', [MensajeController::class, 'adjunto']);

    // Envío. Throttle alto: en una conversación viva la gente escribe seguido, y
    // esto no paga por token como el chatbot de reclamos. Está sólo para que una
    // sesión enloquecida no pueda inundar la tabla.
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/canales/{id}/mensajes', [MensajeController::class, 'store']);
    });
});

// Alta/baja de empleados habilitados en la mensajería. Sólo admin: acá se otorga
// el permiso, así que va fuera del grupo anterior (un admin que todavía no es
// miembro tiene que poder darse de alta a sí mismo).
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/mensajeria/miembros', [MensajeriaMiembroController::class, 'index']);
    Route::get('/admin/mensajeria/miembros/buscar', [MensajeriaMiembroController::class, 'buscar']);
    Route::post('/admin/mensajeria/miembros', [MensajeriaMiembroController::class, 'store']);
    Route::patch('/admin/mensajeria/miembros/{userId}', [MensajeriaMiembroController::class, 'update']);
    Route::post('/admin/mensajeria/miembros/{userId}/password', [MensajeriaMiembroController::class, 'resetPassword']);
    // Corrige el caso del email con varias filas en `users`: mueve el acceso a la
    // fila que realmente autentica.
    Route::post('/admin/mensajeria/miembros/{userId}/mover-a-login', [MensajeriaMiembroController::class, 'moverALogin']);
});

// Turnero (SUM / Quincho). Con login: antes cualquiera sin cuenta podía
// reservar, cancelar o listar los turnos de todos.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/turnero/canchas', [TurneroController::class, 'canchas']);
    Route::get('/turnero/disponibilidad', [TurneroController::class, 'disponibilidad']);
    Route::post('/turnero/reservar', [TurneroController::class, 'reservar']);
    // Turnero.vue (vecino) y TurneroAdmin.vue (admin) usan la misma ruta.
    Route::post('/turnero/cancelar/{id}', [TurneroController::class, 'cancelar']);
    Route::get('/turnero/mis-turnos', [TurneroController::class, 'misTurnos']);
});
Route::middleware(['auth:sanctum', 'admin'])->get('/turnero/admin/turnos', [TurneroController::class, 'adminTurnos']);

// Limitar un poco el spam en forgot
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/password/forgot', [ForgotPasswordController::class, 'sendResetLink']);
});

Route::post('/password/reset', [ForgotPasswordController::class, 'resetPassword']);

// ⚠️ RUTA TEMPORAL PARA MIGRACIÓN DE PASSWORDS
// Visitar: https://harassantamaria.com.ar/api/public/index.php/api/run-migration-hash-2025
// LUEGO DE USAR, ELIMINAR ESTA RUTA POR SEGURIDAD.
Route::get('/run-migration-hash-2025', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('auth:hash-default');
        return "Resultado: <pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});