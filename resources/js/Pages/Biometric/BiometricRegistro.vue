<template>
  <div class="p-4">
    <h2 class="text-2xl font-bold mb-4 text-gray-800">Registro Biometrico</h2>
    <p class="text-gray-600 mb-6">Seleccione un empleado y registre hasta 3 huellas dactilares.</p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">1. Seleccionar Empleado</h3>

        <div class="mb-4">
          <input
            type="text"
            v-model="busqueda"
            @input="buscarPersonas"
            placeholder="Buscar por nombre o documento..."
            class="w-full border rounded px-3 py-2"
          />
        </div>

        <div v-if="personasResultado.length" class="max-h-60 overflow-y-auto border rounded">
          <div
            v-for="persona in personasResultado"
            :key="persona.id"
            @click="seleccionarPersona(persona)"
            class="p-3 hover:bg-blue-50 cursor-pointer border-b last:border-b-0 flex justify-between items-center"
            :class="{ 'bg-blue-100': personaSeleccionada?.id === persona.id }"
          >
            <div>
              <span class="font-medium">{{ persona.nombre }} {{ persona.apellido }}</span>
              <span class="text-sm text-gray-500 ml-2">{{ persona.numero_documento }}</span>
            </div>
          </div>
        </div>

        <div v-if="personaSeleccionada" class="mt-4 p-4 bg-blue-50 rounded-lg">
          <p class="font-semibold text-blue-800">
            {{ personaSeleccionada.nombre }} {{ personaSeleccionada.apellido }}
          </p>
          <p class="text-sm text-blue-600">Doc: {{ personaSeleccionada.numero_documento }}</p>
        </div>

        <div v-if="personaSeleccionada" class="mt-4">
          <h4 class="font-medium text-gray-700 mb-2">
            Huellas registradas ({{ huellasRegistradas.length }}/3):
          </h4>
          <div v-if="huellasRegistradas.length === 0" class="text-sm text-gray-500 italic">
            No tiene huellas registradas
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="huella in huellasRegistradas"
              :key="huella.id"
              class="flex justify-between items-center p-2 bg-gray-50 rounded"
            >
              <div>
                <span class="text-sm font-medium">{{ huella.nombre_dedo }}</span>
                <span class="text-xs text-gray-500 ml-2">Calidad: {{ huella.calidad }}%</span>
              </div>
              <button
                @click="eliminarHuella(huella)"
                class="text-red-500 hover:text-red-700 text-sm"
              >
                Eliminar
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">2. Capturar Huella</h3>

        <div v-if="!personaSeleccionada" class="text-center py-12 text-gray-400">
          <p>Seleccione un empleado primero</p>
        </div>

        <div v-else-if="huellasRegistradas.length >= 3" class="text-center py-12">
          <p class="text-green-600 font-medium">Este empleado ya tiene 3 huellas registradas.</p>
          <p class="text-sm text-gray-500 mt-2">Elimine una huella para registrar una nueva.</p>
        </div>

        <FingerprintCapture
          v-else
          modo="registro"
          @captura="onHuellaCapturada"
          @error="onError"
        />

        <div v-if="mensajeResultado" class="mt-4 p-3 rounded" :class="mensajeClase">
          {{ mensajeResultado }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import FingerprintCapture from './FingerprintCapture.vue'

const busqueda = ref('')
const personasResultado = ref([])
const personaSeleccionada = ref(null)
const huellasRegistradas = ref([])
const mensajeResultado = ref('')
const mensajeClase = ref('')

let timeoutBusqueda = null

function buscarPersonas() {
  clearTimeout(timeoutBusqueda)
  timeoutBusqueda = setTimeout(async () => {
    if (busqueda.value.length < 2) {
      personasResultado.value = []
      return
    }
    try {
      const response = await axios.get('/personas/buscar', {
        params: { q: busqueda.value }
      })
      personasResultado.value = response.data
    } catch (err) {
      console.error('Error buscando personas:', err)
    }
  }, 300)
}

async function seleccionarPersona(persona) {
  personaSeleccionada.value = persona
  mensajeResultado.value = ''
  await cargarHuellas()
}

async function cargarHuellas() {
  if (!personaSeleccionada.value) return
  try {
    const response = await axios.get(`/biometric/huellas/${personaSeleccionada.value.id}`)
    huellasRegistradas.value = response.data
  } catch (err) {
    console.error('Error cargando huellas:', err)
  }
}

async function onHuellaCapturada({ template, calidad, dedo_indice }) {
  try {
    const response = await axios.post('/biometric/huellas/registrar', {
      persona_id: personaSeleccionada.value.id,
      dedo_indice,
      template,
      calidad,
    })

    mensajeResultado.value = response.data.message
    mensajeClase.value = 'bg-green-100 text-green-800'

    await cargarHuellas()

    Swal.fire({
      icon: 'success',
      title: 'Huella registrada',
      text: `${response.data.huella.nombre_dedo} registrado correctamente`,
      timer: 2000,
      showConfirmButton: false,
    })
  } catch (err) {
    mensajeResultado.value = err.response?.data?.message || 'Error al guardar la huella'
    mensajeClase.value = 'bg-red-100 text-red-800'
  }
}

function onError(msg) {
  mensajeResultado.value = msg
  mensajeClase.value = 'bg-red-100 text-red-800'
}

async function eliminarHuella(huella) {
  const result = await Swal.fire({
    title: 'Eliminar huella?',
    text: `Se eliminara la huella: ${huella.nombre_dedo}`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Si, eliminar',
    cancelButtonText: 'Cancelar',
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`/biometric/huellas/${huella.id}`)
      await cargarHuellas()
      Swal.fire('Eliminada', 'La huella ha sido eliminada.', 'success')
    } catch (err) {
      Swal.fire('Error', 'No se pudo eliminar la huella.', 'error')
    }
  }
}
</script>
