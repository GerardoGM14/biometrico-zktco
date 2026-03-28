<template>
  <div class="border rounded-lg p-4 bg-gray-50">
    <div class="flex items-center gap-2 mb-4">
      <span
        class="w-3 h-3 rounded-full"
        :class="{
          'bg-green-500': estadoLector === 'conectado',
          'bg-red-500': estadoLector === 'desconectado',
          'bg-yellow-500': estadoLector === 'capturando',
        }"
      ></span>
      <span class="text-sm font-medium" :class="{
        'text-green-700': estadoLector === 'conectado',
        'text-red-700': estadoLector === 'desconectado',
        'text-yellow-700': estadoLector === 'capturando',
      }">
        {{ mensajeEstado }}
      </span>
      <span v-if="modoBridge" class="text-xs text-gray-400 ml-2">({{ modoBridge }})</span>
    </div>

    <div v-if="modo === 'registro'" class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-1">Seleccionar dedo:</label>
      <select v-model="dedoSeleccionado" class="w-full border rounded px-3 py-2">
        <option v-for="(nombre, indice) in dedos" :key="indice" :value="Number(indice)">
          {{ nombre }}
        </option>
      </select>
    </div>

    <div
      class="w-40 h-48 mx-auto border-2 border-dashed rounded-lg flex items-center justify-center mb-4 transition-all duration-300"
      :class="{
        'border-gray-300 bg-white': estadoLector === 'conectado',
        'border-red-300 bg-red-50': estadoLector === 'desconectado',
        'border-yellow-400 bg-yellow-50 animate-pulse': estadoLector === 'capturando',
        'border-green-400 bg-green-50': capturaExitosa,
      }"
    >
      <div class="text-center">
        <svg v-if="!capturaExitosa" xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <p class="text-xs text-gray-500 mt-2">
          {{ capturaExitosa ? 'Captura exitosa' : 'Coloque su dedo' }}
        </p>
      </div>
    </div>


    <div v-if="calidad > 0" class="mb-4">
      <div class="flex justify-between text-sm mb-1">
        <span>Calidad:</span>
        <span :class="{
          'text-red-600': calidad < 40,
          'text-yellow-600': calidad >= 40 && calidad < 70,
          'text-green-600': calidad >= 70,
        }">{{ calidad }}%</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div
          class="h-2 rounded-full transition-all duration-500"
          :class="{
            'bg-red-500': calidad < 40,
            'bg-yellow-500': calidad >= 40 && calidad < 70,
            'bg-green-500': calidad >= 70,
          }"
          :style="{ width: calidad + '%' }"
        ></div>
      </div>
    </div>

    <div class="flex gap-2">
      <button
        @click="iniciarCaptura"
        :disabled="estadoLector === 'desconectado' || estadoLector === 'capturando'"
        class="flex-1 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
      >
        {{ estadoLector === 'capturando' ? 'Capturando...' : 'Capturar Huella' }}
      </button>
      <button
        v-if="estadoLector === 'desconectado'"
        @click="conectarBridge"
        class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"
      >
        Reintentar
      </button>
    </div>


    <p v-if="error" class="text-red-600 text-sm mt-2">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modo: {
    type: String,
    default: 'registro', // 'registro' o 'identificacion'
  },
})

const emit = defineEmits(['captura', 'error'])

// Bridge WebSocket config
const BRIDGE_URL = 'ws://127.0.0.1:8081'


const estadoLector = ref('desconectado')
const capturaExitosa = ref(false)
const calidad = ref(0)
const error = ref('')
const dedoSeleccionado = ref(2) 
const modoBridge = ref('') // 'sdk' o 'simulacion'


let ws = null
let resolveCaptura = null 

const dedos = {
  1: 'Pulgar Derecho',
  2: 'Indice Derecho',
  3: 'Medio Derecho',
  6: 'Pulgar Izquierdo',
  7: 'Indice Izquierdo',
  8: 'Medio Izquierdo',
}

const mensajeEstado = computed(() => {
  switch (estadoLector.value) {
    case 'conectado': return 'Lector conectado y listo'
    case 'desconectado': return 'Lector no encontrado - Verifique que el Bridge este ejecutandose'
    case 'capturando': return 'Esperando huella... Coloque su dedo en el lector'
    default: return ''
  }
})


function conectarBridge() {
  error.value = ''

  if (ws) {
    ws.close()
    ws = null
  }

  try {
    ws = new WebSocket(BRIDGE_URL)

    ws.onopen = () => {
      console.log('[Fingerprint] Conectado al Bridge')
      ws.send(JSON.stringify({ action: 'status' }))
    }

    ws.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data)
        manejarRespuesta(data)
      } catch (err) {
        console.error('[Fingerprint] Error parseando respuesta:', err)
      }
    }

    ws.onclose = () => {
      console.log('[Fingerprint] Desconectado del Bridge')
      estadoLector.value = 'desconectado'
      modoBridge.value = ''
    }

    ws.onerror = () => {
      estadoLector.value = 'desconectado'
      error.value = 'No se pudo conectar con el Bridge en el puerto 8081. Verifique que este ejecutandose (npm start en Modules/Biometric/Bridge/).'
      emit('error', error.value)
    }
  } catch (err) {
    estadoLector.value = 'desconectado'
    error.value = 'Error al crear conexion WebSocket.'
    emit('error', error.value)
  }
}

function manejarRespuesta(data) {
  switch (data.action) {
    case 'status':
      modoBridge.value = data.mode || ''
      if (data.connected) {
        estadoLector.value = 'conectado'
        error.value = ''
      } else {
        estadoLector.value = 'desconectado'
        if (data.mode === 'simulacion') {
          estadoLector.value = 'conectado'
        } else {
          error.value = data.message || 'Lector no detectado'
        }
      }
      break

    case 'capture':
      if (data.success && data.template) {
        capturaExitosa.value = true
        calidad.value = data.quality || 80
        estadoLector.value = 'conectado'

        emit('captura', {
          template: data.template,
          calidad: calidad.value,
          dedo_indice: dedoSeleccionado.value,
        })

        setTimeout(() => {
          capturaExitosa.value = false
        }, 3000)
      } else {
        estadoLector.value = 'conectado'
        error.value = data.message || 'Error en la captura. Retire el dedo e intente nuevamente.'
        emit('error', error.value)
      }

      if (resolveCaptura) {
        resolveCaptura(data)
        resolveCaptura = null
      }
      break

    case 'match':
      if (resolveCaptura) {
        resolveCaptura(data)
        resolveCaptura = null
      }
      break

    case 'error':
      error.value = data.message || 'Error desconocido del Bridge'
      estadoLector.value = 'conectado'
      emit('error', error.value)
      break
  }
}

function iniciarCaptura() {
  if (!ws || ws.readyState !== WebSocket.OPEN) {
    error.value = 'No hay conexion con el Bridge. Presione Reintentar.'
    return
  }

  error.value = ''
  capturaExitosa.value = false
  calidad.value = 0
  estadoLector.value = 'capturando'

  ws.send(JSON.stringify({ action: 'capture' }))

  setTimeout(() => {
    if (estadoLector.value === 'capturando') {
      estadoLector.value = 'conectado'
      error.value = 'Tiempo de espera agotado. No se detecto una huella.'
      emit('error', error.value)
    }
  }, 15000)
}

let intervaloVerificacion = null

onMounted(() => {
  conectarBridge()
  intervaloVerificacion = setInterval(() => {
    if (!ws || ws.readyState !== WebSocket.OPEN) {
      conectarBridge()
    } else {
      ws.send(JSON.stringify({ action: 'status' }))
    }
  }, 10000)
})

onUnmounted(() => {
  if (intervaloVerificacion) {
    clearInterval(intervaloVerificacion)
  }
  if (ws) {
    ws.close()
  }
})
</script>
