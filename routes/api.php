<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacturaController;

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