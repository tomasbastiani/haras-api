<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\GastosNotificacionesController;

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
Route::post('/change-password', [AuthController::class, 'changePassword']);
Route::get('/gastoscomunes/{email}', [FacturaController::class, 'buscarPorDni']);
Route::get('/facturas-todas', [FacturaController::class, 'listarTodos']);
Route::post('/gastos/agregar', [FacturaController::class, 'agregarGasto']);
Route::get('/gastos/periodos', [FacturaController::class, 'obtenerPeriodos']);
Route::delete('/gastos/eliminar/{numero}', [FacturaController::class, 'eliminarPorPeriodo']);
Route::post('/update-gasto', [FacturaController::class, 'updateGasto']);
Route::get('/mis-lotes/{email}', [FacturaController::class, 'getLotesPorEmail']);
Route::post('/update-email-lote', [FacturaController::class, 'updateEmailLote']);
Route::post('/create-user', [FacturaController::class, 'createUserIfNotExists']);
Route::get('/verificar-email/{email}', [FacturaController::class, 'verificarEmail']);
Route::get('/emails-por-lote', [FacturaController::class, 'obtenerEmailsPorLote']);
Route::get('/gastos/pdf/{numero}/{nlote}', [FacturaController::class, 'verPDF']);
Route::post('/enviar-contacto', [ContactoController::class, 'enviar']);

Route::get('/lotes-por-user/{email}', [FacturaController::class, 'getLotesPorUser']);
Route::get('/cartas', [FacturaController::class, 'getCartas']);

// CRUD Archivos
Route::post('/archivos', [ArchivoController::class, 'store']);
Route::get('/archivos', [ArchivoController::class, 'index']);
Route::delete('/archivos/{id}', [ArchivoController::class, 'destroy']);
Route::get('/archivos/{id}/download', [ArchivoController::class, 'download']);
Route::get('/archivos/user/{user}', [ArchivoController::class, 'indexByUser']);


Route::post('/gastos/notificar', [GastosNotificacionesController::class, 'notificar']);

