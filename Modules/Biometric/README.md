# Modulo Biometrico - ZKTeco Live20R

Modulo de registro de huellas dactilares y control de asistencia para Laravel 12 + Vue 3 + Inertia.js.

## Arquitectura

```
                    ┌──────────────┐
                    │  Live20R USB │
                    └──────┬───────┘
                           │ DLL (libzkfp)
                    ┌──────┴───────┐
                    │ Bridge Node  │  <-- server.js
                    │ Puerto 8081  │  WebSocket (para Vue)
                    │ Puerto 8082  │  HTTP (para Laravel)
                    └──────┬───────┘
                  ┌────────┴────────┐
           ┌──────┴──────┐  ┌───────┴──────┐
           │  Vue 3      │  │  Laravel 12  │
           │ (captura)   │  │ (match 1:N)  │
           └─────────────┘  └──────────────┘
```

## Requisitos

- PHP 8.2+ con extension curl
- Laravel 12
- Vue 3 + Inertia.js
- Node.js 18+ (para el Bridge)
- MariaDB / MySQL
- **ZKFinger SDK** (libzkfp.dll - viene en el CD del Live20R)

## Estructura

```
Modules/Biometric/
  Bridge/
    server.js               -- Bridge Node.js (WebSocket + HTTP)
    package.json
  Controllers/
    BiometricController.php -- Registro de huellas + identificacion 1:N
    AsistenciaController.php-- Marcar entrada/salida + reportes
  Models/
    BiometricData.php       -- Template de huellas (max 3 por persona)
    Asistencia.php          -- Registro diario entrada/salida
  Migrations/
    create_biometric_data_table.php
    create_asistencias_table.php
  Routes/
    biometric.php

Modules/Shared/
  Models/
    Personal.php            -- Modelo de empleados (tabla personas)
    Roles.php

resources/js/Pages/Biometric/
  FingerprintCapture.vue    -- Componente bridge WebSocket con el lector
  BiometricRegistro.vue     -- Registro de huellas por empleado
  AsistenciaControl.vue     -- Pantalla de marcacion con reloj
  AsistenciaReporte.vue     -- Reporte con filtros y resumen
```

## Instalacion

### 1. Base de datos

Copiar los archivos de `Modules/Biometric/Migrations/` a `database/migrations/` y ejecutar:

```bash
php artisan migrate
```

### 2. Registrar rutas en routes/web.php

Agregar los imports:

```php
use Modules\Biometric\Controllers\BiometricController;
use Modules\Biometric\Controllers\AsistenciaController;
```

Y las rutas (dentro del grupo autenticado si aplica):

```php
// RUTAS DE BIOMETRIA
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
```

### 3. Instalar y ejecutar el Bridge

```bash
cd Modules/Biometric/Bridge
npm install
npm start
```

El bridge arranca en:
- **ws://localhost:8081** - WebSocket para el frontend Vue
- **http://localhost:8082** - HTTP para el backend Laravel (matching)

### 4. Configurar el SDK del Live20R

1. Instalar los drivers del Live20R (viene en el CD o en zkteco.com)
2. Copiar `libzkfp.dll` y `libzkfpErr.dll` a la carpeta `Modules/Biometric/Bridge/`
3. Reiniciar el bridge (`npm start`)

**Sin la DLL:** El bridge funciona en **modo simulacion** (genera templates falsos para desarrollo).
**Con la DLL:** El bridge usa el SDK real para captura y matching.

### 5. URLs del sistema

| URL | Descripcion |
|-----|-------------|
| `/biometric` | Registro de huellas por empleado |
| `/asistencia/control` | Pantalla de marcacion (entrada/salida) |
| `/asistencia/reporte` | Reporte de asistencias con filtros |

## Flujo de uso

1. **Registro**: Admin busca empleado -> selecciona dedo -> captura huella via Bridge -> se guarda template en BD
2. **Asistencia**: Empleado pone dedo -> Bridge captura -> Laravel compara 1:N via Bridge -> registra entrada o salida
3. **Reporte**: Filtrar por fechas/estado, ver resumen puntuales/tardanzas/faltas

## Puertos

| Puerto | Protocolo | Quien lo usa | Para que |
|--------|-----------|-------------|----------|
| 8081 | WebSocket | Vue (FingerprintCapture.vue) | Captura de huella, status del lector |
| 8082 | HTTP | Laravel (BiometricController) | Matching de templates 1:N |

## Configuracion

- Hora limite de entrada (tardanza): `08:00:00` (en `AsistenciaController::marcar()`)
- Maximo huellas por persona: `3` (en `BiometricController::registrarHuella()`)
- Score minimo para match: `50` (en `BiometricController::identificar()` y `Bridge/server.js`)
- Intervalo verificacion lector: `10 seg` (en `FingerprintCapture.vue`)

## Modo desarrollo (sin lector fisico)

El bridge arranca en modo simulacion si no encuentra `libzkfp.dll`. En este modo:
- `status` siempre retorna conectado
- `capture` genera templates falsos aleatorios
- `match` compara templates como strings (iguales = 100, diferentes = 0)

Esto permite desarrollar y probar toda la interfaz sin tener el lector conectado.
