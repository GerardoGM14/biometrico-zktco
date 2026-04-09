<template>
  <div class="p-4">
    <h2 class="text-2xl font-bold mb-2 text-gray-800">Control de Asistencia</h2>
    <p class="text-gray-600 mb-6">Coloque su dedo en el lector para registrar entrada o salida.</p>

    <div class="max-w-lg mx-auto">
      <!-- Reloj en tiempo real -->
      <div class="text-center mb-6">
        <p class="text-5xl font-mono font-bold text-gray-800">{{ horaActual }}</p>
        <p class="text-lg text-gray-500 mt-1">{{ fechaActual }}</p>
      </div>

      <!-- Componente de captura -->
      <FingerprintCapture
        modo="identificacion"
        @captura="onHuellaCapturada"
        @error="onError"
      />

      <!-- Resultado de la identificacion -->
      <div v-if="resultado" class="mt-6 p-6 rounded-lg text-center transition-all duration-500"
        :class="{
          'bg-green-100 border-2 border-green-400': resultado.success,
          'bg-red-100 border-2 border-red-400': !resultado.success,
        }"
      >
        <template v-if="resultado.success">
          <p class="text-2xl font-bold text-green-800">
            {{ resultado.persona.nombre }} {{ resultado.persona.apellido }}
          </p>
          <p class="text-lg mt-2" :class="{
            'text-blue-600': resultado.tipo === 'entrada',
            'text-orange-600': resultado.tipo === 'salida',
          }">
            {{ resultado.tipo === 'entrada' ? 'ENTRADA' : 'SALIDA' }} registrada
          </p>
          <p class="text-3xl font-mono mt-2">{{ resultado.hora }}</p>
          <span
            v-if="resultado.estado"
            class="inline-block mt-2 px-3 py-1 rounded-full text-sm font-medium"
            :class="{
              'bg-green-200 text-green-800': resultado.estado === 'puntual',
              'bg-yellow-200 text-yellow-800': resultado.estado === 'tardanza',
            }"
          >
            {{ resultado.estado === 'puntual' ? 'Puntual' : 'Tardanza' }}
          </span>
        </template>

        <template v-else>
          <p class="text-lg font-medium text-red-800">{{ resultado.message }}</p>
        </template>
      </div>

      <!-- Ultimas marcaciones del dia -->
      <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-700 mb-3">Ultimas marcaciones de hoy</h3>
        <div v-if="ultimasMarcaciones.length === 0" class="text-gray-400 text-center py-4">
          No hay marcaciones registradas hoy
        </div>
        <div v-else class="space-y-2 max-h-64 overflow-y-auto">
          <div
            v-for="marc in ultimasMarcaciones"
            :key="marc.id"
            class="flex justify-between items-center p-3 bg-gray-50 rounded"
          >
            <div>
              <span class="font-medium">{{ marc.persona?.nombre }} {{ marc.persona?.apellido }}</span>
            </div>
            <div class="text-right">
              <span v-if="marc.hora_entrada" class="text-sm text-blue-600 mr-3">
                Entrada: {{ marc.hora_entrada }}
              </span>
              <span v-if="marc.hora_salida" class="text-sm text-orange-600">
                Salida: {{ marc.hora_salida }}
              </span>
              <span
                class="ml-2 text-xs px-2 py-0.5 rounded-full"
                :class="{
                  'bg-green-100 text-green-700': marc.estado === 'puntual',
                  'bg-yellow-100 text-yellow-700': marc.estado === 'tardanza',
                }"
              >
                {{ marc.estado }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import FingerprintCapture from './FingerprintCapture.vue'

const horaActual = ref('')
const fechaActual = ref('')
const resultado = ref(null)
const ultimasMarcaciones = ref([])

let relojInterval = null

function actualizarReloj() {
  const now = new Date()
  horaActual.value = now.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  fechaActual.value = now.toLocaleDateString('es-PE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
}

async function onHuellaCapturada({ template }) {
  resultado.value = null

  try {
    const identResponse = await axios.post('/biometric/identificar', { template })

    if (!identResponse.data.identificado) {
      resultado.value = {
        success: false,
        message: identResponse.data.message || 'Huella no reconocida',
      }
      return
    }

    const persona = identResponse.data.persona

    const marcResponse = await axios.post('/asistencia/marcar', {
      persona_id: persona.id,
    })

    resultado.value = {
      success: true,
      persona: marcResponse.data.persona,
      tipo: marcResponse.data.tipo,
      hora: marcResponse.data.hora,
      estado: marcResponse.data.estado,
    }

    await cargarMarcacionesHoy()

    setTimeout(() => {
      resultado.value = null
    }, 5000)

  } catch (err) {
    const msg = err.response?.data?.message || 'Error al procesar la marcacion'
    resultado.value = {
      success: false,
      message: msg,
    }

    if (err.response?.status === 422 && err.response?.data?.tipo === 'completo') {
      Swal.fire('Info', msg, 'info')
    }
  }
}

function onError(msg) {
  resultado.value = {
    success: false,
    message: msg,
  }
}

async function cargarMarcacionesHoy() {
  try {
    const hoy = new Date().toISOString().split('T')[0]
    const response = await axios.get('/asistencia/listar', {
      params: {
        fecha_inicio: hoy,
        fecha_fin: hoy,
        rowsPerPage: 20,
      }
    })
    ultimasMarcaciones.value = response.data.data || []
  } catch (err) {
    console.error('Error cargando marcaciones:', err)
  }
}

onMounted(() => {
  actualizarReloj()
  relojInterval = setInterval(actualizarReloj, 1000)
  cargarMarcacionesHoy()
})

onUnmounted(() => {
  if (relojInterval) clearInterval(relojInterval)
})
</script>
