<?php

use Illuminate\Support\Facades\Route;
use Modules\Biometric\Controllers\BiometricController;
use Modules\Biometric\Controllers\AsistenciaController;

// RUTAS DE BIOMETRÍA (Registro de huellas)
Route::get('/biometric', [BiometricController::class, 'index'])->name('biometric');
Route::get('/biometric/huellas/{personaId}', [BiometricController::class, 'getHuellas'])->name('biometric.huellas');
Route::post('/biometric/huellas/registrar', [BiometricController::class, 'registrarHuella'])->name('biometric.registrar');
Route::delete('/biometric/huellas/{id}', [BiometricController::class, 'eliminarHuella'])->name('biometric.eliminar');
Route::post('/biometric/identificar', [BiometricController::class, 'identificar'])->name('biometric.identificar');

// RUTAS DE ASISTENCIA
Route::get('/asistencia/control', [AsistenciaController::class, 'control'])->name('asistencia.control');
Route::get('/asistencia/reporte', [AsistenciaController::class, 'reporte'])->name('asistencia.reporte');
Route::post('/asistencia/marcar', [AsistenciaController::class, 'marcar'])->name('asistencia.marcar');
Route::get('/asistencia/listar', [AsistenciaController::class, 'listar'])->name('asistencia.listar');
