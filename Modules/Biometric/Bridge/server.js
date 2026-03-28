/**
 * Bridge WebSocket para ZKTeco Live20R
 *
 * Este servicio corre en la PC donde esta conectado el lector USB.
 * Se comunica con el SDK nativo (libzkfp.dll) y expone las funciones
 * via WebSocket para que el frontend Vue pueda usarlas.
 *
 * Puerto: 8081 (WebSocket)
 *
 * Mensajes que acepta (JSON):
 *   { action: "status" }          -> Verifica si el lector esta conectado
 *   { action: "capture" }         -> Inicia captura de huella
 *   { action: "match", t1, t2 }   -> Compara dos templates (score 0-100)
 *
 * Respuestas (JSON):
 *   { action: "status", connected: true/false, deviceName: "..." }
 *   { action: "capture", success: true, template: "base64...", quality: 85 }
 *   { action: "match", score: 85, matched: true/false }
 *   { action: "error", message: "..." }
 *
 * INSTALACION:
 *   1. Instalar ZKFinger SDK (viene en el CD del Live20R o descargar de zkteco.com)
 *   2. Copiar libzkfp.dll y libzkfpErr.dll a esta carpeta (o a C:\Windows\System32)
 *   3. npm install
 *   4. npm start
 */

const WebSocket = require('ws');
const path = require('path');

// ============================================================================
// CONFIGURACION
// ============================================================================
const PORT = 8081;
const MATCH_THRESHOLD = 50; // Score minimo para considerar match (0-100)

// ============================================================================
// INTENTO DE CARGA DEL SDK NATIVO
// ============================================================================
let zkfp = null;
let sdkDisponible = false;
let dbHandle = null;
let deviceHandle = null;

try {
  const ffi = require('ffi-napi');
  const ref = require('ref-napi');

  const int = ref.types.int;
  const voidPtr = ref.refType(ref.types.void);
  const bytePtr = ref.refType(ref.types.byte);
  const intPtr = ref.refType(ref.types.int);

  // Buscar la DLL en varias ubicaciones
  const dllPaths = [
    path.join(__dirname, 'libzkfp.dll'),
    'C:\\Windows\\System32\\libzkfp.dll',
    path.join(__dirname, 'sdk', 'libzkfp.dll'),
  ];

  let dllPath = null;
  for (const p of dllPaths) {
    try {
      require('fs').accessSync(p);
      dllPath = p;
      break;
    } catch (e) {
      continue;
    }
  }

  if (dllPath) {
    zkfp = ffi.Library(dllPath, {
      'ZKFPM_Init': [int, []],
      'ZKFPM_Terminate': [int, []],
      'ZKFPM_GetDeviceCount': [int, []],
      'ZKFPM_OpenDevice': [voidPtr, [int]],
      'ZKFPM_CloseDevice': [int, [voidPtr]],
      'ZKFPM_DBInit': [voidPtr, []],
      'ZKFPM_DBFree': [int, [voidPtr]],
      'ZKFPM_DBMatch': [int, [voidPtr, bytePtr, int, bytePtr, int]],
      'ZKFPM_Acquire': [int, [voidPtr, bytePtr, int, bytePtr, intPtr]],
      'ZKFPM_GetParameters': [int, [voidPtr, int, bytePtr, intPtr]],
    });

    const initResult = zkfp.ZKFPM_Init();
    if (initResult === 0) {
      sdkDisponible = true;
      dbHandle = zkfp.ZKFPM_DBInit();
      console.log('[SDK] ZKFinger SDK inicializado correctamente');
      console.log(`[SDK] Dispositivos detectados: ${zkfp.ZKFPM_GetDeviceCount()}`);
    } else {
      console.log(`[SDK] Error al inicializar SDK: codigo ${initResult}`);
    }
  } else {
    console.log('[SDK] libzkfp.dll no encontrada - Ejecutando en modo SIMULACION');
  }
} catch (err) {
  console.log(`[SDK] No se pudo cargar el SDK nativo: ${err.message}`);
  console.log('[SDK] Ejecutando en modo SIMULACION (para desarrollo sin lector fisico)');
}

// ============================================================================
// FUNCIONES DEL SDK
// ============================================================================

function getStatus() {
  if (!sdkDisponible) {
    return { action: 'status', connected: false, mode: 'simulacion', message: 'SDK no disponible - modo simulacion' };
  }

  const count = zkfp.ZKFPM_GetDeviceCount();
  return {
    action: 'status',
    connected: count > 0,
    deviceCount: count,
    deviceName: 'ZKTeco Live20R',
    mode: 'sdk',
  };
}

function openDevice() {
  if (!sdkDisponible) return false;
  if (deviceHandle) return true;

  const count = zkfp.ZKFPM_GetDeviceCount();
  if (count <= 0) return false;

  deviceHandle = zkfp.ZKFPM_OpenDevice(0);
  return deviceHandle !== null && !deviceHandle.isNull();
}

function captureFingerprint() {
  if (!sdkDisponible) {
    // Modo simulacion: generar template falso para desarrollo
    const fakeTemplate = Buffer.from(
      'SIMULATED_TEMPLATE_' + Date.now() + '_' + Math.random().toString(36).substring(7)
    ).toString('base64');

    return {
      action: 'capture',
      success: true,
      template: fakeTemplate,
      quality: Math.floor(Math.random() * 30) + 70, // 70-100
      mode: 'simulacion',
    };
  }

  if (!openDevice()) {
    return { action: 'capture', success: false, message: 'No se pudo abrir el dispositivo' };
  }

  try {
    const ref = require('ref-napi');
    const templateBuf = Buffer.alloc(2048);
    const templateLen = ref.alloc(ref.types.int, 2048);
    const imgBuf = Buffer.alloc(640 * 480);

    const result = zkfp.ZKFPM_Acquire(deviceHandle, imgBuf, imgBuf.length, templateBuf, templateLen);

    if (result === 0) {
      const actualLen = templateLen.deref();
      const template = templateBuf.slice(0, actualLen).toString('base64');

      return {
        action: 'capture',
        success: true,
        template: template,
        quality: 80,
        mode: 'sdk',
      };
    } else if (result === -7) {
      return { action: 'capture', success: false, message: 'Timeout - No se detecto huella' };
    } else {
      return { action: 'capture', success: false, message: `Error de captura: codigo ${result}` };
    }
  } catch (err) {
    return { action: 'capture', success: false, message: `Error: ${err.message}` };
  }
}

function matchTemplates(template1Base64, template2Base64) {
  if (!sdkDisponible || !dbHandle) {
    // Modo simulacion: comparar strings directamente
    const matched = template1Base64 === template2Base64;
    return {
      action: 'match',
      score: matched ? 100 : 0,
      matched: matched,
      mode: 'simulacion',
    };
  }

  try {
    const t1 = Buffer.from(template1Base64, 'base64');
    const t2 = Buffer.from(template2Base64, 'base64');

    const score = zkfp.ZKFPM_DBMatch(dbHandle, t1, t1.length, t2, t2.length);

    return {
      action: 'match',
      score: score,
      matched: score >= MATCH_THRESHOLD,
      mode: 'sdk',
    };
  } catch (err) {
    return { action: 'match', score: 0, matched: false, message: `Error: ${err.message}` };
  }
}

// ============================================================================
// SERVIDOR HTTP (para que el backend PHP pueda hacer match sin WebSocket)
// ============================================================================
const http = require('http');
const HTTP_PORT = 8082;

const httpServer = http.createServer((req, res) => {
  // CORS para peticiones locales
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  res.setHeader('Content-Type', 'application/json');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  if (req.method === 'POST' && req.url === '/match') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      try {
        const { t1, t2 } = JSON.parse(body);
        if (!t1 || !t2) {
          res.writeHead(400);
          res.end(JSON.stringify({ error: 'Se requieren t1 y t2' }));
          return;
        }
        const result = matchTemplates(t1, t2);
        res.writeHead(200);
        res.end(JSON.stringify(result));
      } catch (err) {
        res.writeHead(500);
        res.end(JSON.stringify({ error: err.message }));
      }
    });
  } else if (req.method === 'GET' && req.url === '/status') {
    res.writeHead(200);
    res.end(JSON.stringify(getStatus()));
  } else {
    res.writeHead(404);
    res.end(JSON.stringify({ error: 'Not found' }));
  }
});

httpServer.listen(HTTP_PORT, () => {
  console.log(`[Bridge] HTTP API iniciada en http://localhost:${HTTP_PORT} (para backend PHP)`);
});

// ============================================================================
// SERVIDOR WEBSOCKET (para el frontend Vue)
// ============================================================================
const wss = new WebSocket.Server({ port: PORT });

console.log(`\n[Bridge] ZKFinger Bridge iniciado en ws://localhost:${PORT}`);
console.log(`[Bridge] Modo: ${sdkDisponible ? 'SDK NATIVO' : 'SIMULACION (desarrollo)'}\n`);

wss.on('connection', (ws) => {
  console.log('[Bridge] Cliente conectado');

  ws.on('message', (data) => {
    try {
      const msg = JSON.parse(data.toString());
      let response;

      switch (msg.action) {
        case 'status':
          response = getStatus();
          break;

        case 'capture':
          response = captureFingerprint();
          break;

        case 'match':
          if (!msg.t1 || !msg.t2) {
            response = { action: 'error', message: 'Se requieren t1 y t2 para comparar' };
          } else {
            response = matchTemplates(msg.t1, msg.t2);
          }
          break;

        default:
          response = { action: 'error', message: `Accion desconocida: ${msg.action}` };
      }

      ws.send(JSON.stringify(response));
    } catch (err) {
      ws.send(JSON.stringify({ action: 'error', message: `Error: ${err.message}` }));
    }
  });

  ws.on('close', () => {
    console.log('[Bridge] Cliente desconectado');
  });
});

// Cleanup al cerrar
process.on('SIGINT', () => {
  console.log('\n[Bridge] Cerrando...');
  if (deviceHandle && sdkDisponible) {
    zkfp.ZKFPM_CloseDevice(deviceHandle);
  }
  if (dbHandle && sdkDisponible) {
    zkfp.ZKFPM_DBFree(dbHandle);
  }
  if (sdkDisponible) {
    zkfp.ZKFPM_Terminate();
  }
  process.exit(0);
});
