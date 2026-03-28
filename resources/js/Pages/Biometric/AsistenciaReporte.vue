<template>
  <div class="p-4">
    <h2 class="text-2xl font-bold mb-4 text-gray-800">Reporte de Asistencias</h2>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Fecha inicio:</label>
          <input type="date" v-model="filtros.fecha_inicio" class="w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Fecha fin:</label>
          <input type="date" v-model="filtros.fecha_fin" class="w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Estado:</label>
          <select v-model="filtros.estado" class="w-full border rounded px-3 py-2">
            <option value="">Todos</option>
            <option value="puntual">Puntual</option>
            <option value="tardanza">Tardanza</option>
            <option value="falta">Falta</option>
            <option value="justificado">Justificado</option>
          </select>
        </div>
        <div class="flex items-end">
          <button
            @click="buscar"
            class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
          >
            Buscar
          </button>
        </div>
      </div>
    </div>

    <!-- Resumen -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-green-50 rounded-lg p-4 text-center">
        <p class="text-2xl font-bold text-green-700">{{ resumen.puntuales }}</p>
        <p class="text-sm text-green-600">Puntuales</p>
      </div>
      <div class="bg-yellow-50 rounded-lg p-4 text-center">
        <p class="text-2xl font-bold text-yellow-700">{{ resumen.tardanzas }}</p>
        <p class="text-sm text-yellow-600">Tardanzas</p>
      </div>
      <div class="bg-red-50 rounded-lg p-4 text-center">
        <p class="text-2xl font-bold text-red-700">{{ resumen.faltas }}</p>
        <p class="text-sm text-red-600">Faltas</p>
      </div>
      <div class="bg-blue-50 rounded-lg p-4 text-center">
        <p class="text-2xl font-bold text-blue-700">{{ resumen.justificados }}</p>
        <p class="text-sm text-blue-600">Justificados</p>
      </div>
    </div>

    <!-- Tabla de asistencias -->
    <EasyDataTable
      v-model:server-options="serverOptions"
      :headers="headers"
      :items="items"
      :server-items-length="serverItemsLength"
      :loading="loading"
      buttons-pagination
      table-class-name="customize-table"
      show-index
      no-data-text="No hay registros"
      rows-per-page-message="Filas por pagina"
      rows-of-page-message="de"
    >
      <template #item-persona="item">
        {{ item.persona?.nombre }} {{ item.persona?.apellido }}
      </template>

      <template #item-estado="item">
        <span
          class="px-2 py-1 rounded-full text-xs font-medium"
          :class="{
            'bg-green-100 text-green-800': item.estado === 'puntual',
            'bg-yellow-100 text-yellow-800': item.estado === 'tardanza',
            'bg-red-100 text-red-800': item.estado === 'falta',
            'bg-blue-100 text-blue-800': item.estado === 'justificado',
          }"
        >
          {{ item.estado }}
        </span>
      </template>

      <template #item-hora_entrada="item">
        <span :class="{ 'text-yellow-600 font-medium': item.estado === 'tardanza' }">
          {{ item.hora_entrada || '-' }}
        </span>
      </template>

      <template #item-hora_salida="item">
        {{ item.hora_salida || '-' }}
      </template>
    </EasyDataTable>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import EasyDataTable from 'vue3-easy-data-table'
import 'vue3-easy-data-table/dist/style.css'
import axios from 'axios'

const filtros = ref({
  fecha_inicio: new Date().toISOString().split('T')[0],
  fecha_fin: new Date().toISOString().split('T')[0],
  estado: '',
})

const headers = [
  { text: 'Empleado', value: 'persona', sortable: false },
  { text: 'Fecha', value: 'fecha' },
  { text: 'Entrada', value: 'hora_entrada' },
  { text: 'Salida', value: 'hora_salida' },
  { text: 'Estado', value: 'estado' },
  { text: 'Observacion', value: 'observacion' },
]

const items = ref([])
const serverItemsLength = ref(0)
const loading = ref(false)
const serverOptions = ref({ page: 1, rowsPerPage: 15 })

const resumen = ref({
  puntuales: 0,
  tardanzas: 0,
  faltas: 0,
  justificados: 0,
})

async function buscar() {
  loading.value = true
  try {
    const { page, rowsPerPage } = serverOptions.value
    const response = await axios.get('/asistencia/listar', {
      params: {
        page,
        rowsPerPage,
        ...filtros.value,
      }
    })

    items.value = response.data.data || []
    serverItemsLength.value = response.data.total || 0

    const todos = response.data.data || []
    resumen.value = {
      puntuales: todos.filter(a => a.estado === 'puntual').length,
      tardanzas: todos.filter(a => a.estado === 'tardanza').length,
      faltas: todos.filter(a => a.estado === 'falta').length,
      justificados: todos.filter(a => a.estado === 'justificado').length,
    }
  } catch (err) {
    console.error('Error cargando asistencias:', err)
  } finally {
    loading.value = false
  }
}

watch(serverOptions, buscar, { deep: true })

onMounted(() => {
  buscar()
})
</script>

<style scoped>
.customize-table {
  --easy-table-header-font-size: 14px;
  --easy-table-body-row-font-size: 13px;
  --easy-table-border: 1px solid #ddd;
}
</style>
