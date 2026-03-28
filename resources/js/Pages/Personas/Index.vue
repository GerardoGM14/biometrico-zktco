<template>
  <div class="p-4">
    <h2 class="text-2xl font-bold mb-4 text-gray-800">Listado de Personal</h2>

    <div class="flex justify-between items-center mb-4">
      <button
        @click="abrirModalNuevo"
        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
      >
        + Agregar Personal
      </button>

      <div>
        Buscar:
        <input
          type="text"
          v-model="searchValue"
          placeholder="Buscar por nombre..."
          class="border rounded px-3 py-2 w-64"
        />
      </div>
    </div>

    <EasyDataTable
      v-model:server-options="serverOptions"
      :headers="headers"
      :items="items"
      :server-items-length="serverItemsLength"
      :loading="loading"
      buttons-pagination
      :search-value="searchValue"
      :search-fields="searchFields"
      table-class-name="customize-table"
      show-index
      header-text="Encabezado"
      no-data-text="No hay registros"
      search-placeholder="Buscar..."
      rows-per-page-message="Filas por página"
      rows-of-page-message="de"
    >
      <template #item-actions="item">
        <div class="flex space-x-2">
          <button @click="abrirModalEditar(item)" class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
            Editar
          </button>
          <button
            class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600"
            @click="deleteItem(item)"
          >
            Eliminar
          </button>
          <button 
            @click="toggleTabla('dependientes', item.id)"
            class="bg-purple-500 text-white px-2 py-1 rounded hover:bg-purple-600"
          >
            Asignar Dependiente
          </button>
          <button 
            @click="toggleTabla('datosBancarios', item.id)"
            class="bg-yellow-500 text-white px-2 py-1 rounded hover:bg-yellow-600"
          >
            Datos Bancarios
          </button>
          <button 
            @click="toggleTabla('tallasEPP', item.id)"
            class="bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600"
          >
            Tallas EPP
          </button>
        </div>
      </template>
    </EasyDataTable>

    <!-- Sección para mostrar las tablas secundarias -->
    <div v-if="tablaActiva" class="mt-6">
      <div class="flex justify-between items-center mb-3">
        <h3 class="text-xl font-semibold">{{ tituloTablaActiva }}</h3>
        <button 
          @click="abrirModalSecundario()"
          class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600"
        >
          + Agregar {{ tituloTablaActiva }}
        </button>
      </div>

      <!-- Tablas secundarias -->
      <EasyDataTable
        v-if="tablaActiva"
        :headers="headersSecundarios"
        :items="itemsSecundarios"
        table-class-name="customize-table"
        show-index
      >
        <template #item-actions="item">
          <div class="flex space-x-2">
            <button 
              @click="abrirModalSecundario(item)"
              class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600"
            >
              Editar
            </button>
            <button 
              @click="eliminarItemSecundario(item)"
              class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600"
            >
              Eliminar
            </button>
          </div>
        </template>
      </EasyDataTable>

    </div>

    <!-- Modal Unificado -->
    <ModalFormularioPersonal
      v-if="mostrarModal"
      :persona="personaSeleccionada"
      :roles="roles"
      @close="cerrarModal"
      @personalGuardado="manejarGuardado"
    />

    <!-- Modales -->
    <ModalFormularioPersonal
      v-if="mostrarModal"
      :persona="personaSeleccionada"
      :roles="roles"
      @close="cerrarModal"
      @personalGuardado="manejarGuardado"
    />

    <FormularioDependiente
      v-if="mostrarModalSecundario && tablaActiva === 'dependientes'"
      :dependiente="itemSecundarioSeleccionado"
      :persona-id="personaIdActiva"
      @close="mostrarModalSecundario = false"
      @guardado="manejarGuardadoSecundario"
    />

    <FormularioDatosBancarios
      v-if="mostrarModalSecundario && tablaActiva === 'datosBancarios'"
      :dato-bancario="itemSecundarioSeleccionado"
      :persona-id="personaIdActiva"
      @close="mostrarModalSecundario = false"
      @guardado="manejarGuardadoSecundario"
    />

    <FormularioTallasEPP
      v-if="mostrarModalSecundario && tablaActiva === 'tallasEPP'"
      :talla-epp="itemSecundarioSeleccionado"
      :persona-id="personaIdActiva"
      @close="mostrarModalSecundario = false"
      @guardado="manejarGuardadoSecundario"
    />

  </div>
</template>

<script setup>
import { ref, defineProps, watch } from 'vue'
import EasyDataTable from 'vue3-easy-data-table'
import 'vue3-easy-data-table/dist/style.css'
import ModalFormularioPersonal from './ModalFormularioPersonal.vue'
import FormularioDependiente from './FormularioDependiente.vue'
import FormularioDatosBancarios from './FormularioDatosBancarios.vue'
import FormularioTallasEPP from './FormularioTallasEPP.vue'
import Swal from 'sweetalert2'
import axios from 'axios'

// Props desde el servidor
const props = defineProps({
  personas: Array,
  roles: Array
})

// Reactividad
const mostrarModal = ref(false)
const searchValue = ref('')
const personaSeleccionada = ref(null)

//Para modales de las otras tablas
const mostrarModalSecundario = ref(false)
const itemSecundarioSeleccionado = ref(null)
const itemsSecundarios = ref([])
const headersSecundarios = ref([])

// Campos a buscar
const searchFields = ['nombre', 'apellido', 'numero_documento', 'email', 'numero_celular']
const serverItemsLength = ref(0)
const loading = ref(false)

// Headers de la tabla
const headers = [
  { text: 'Nombre', value: 'nombre' },
  { text: 'Apellidos', value: 'apellido' },
  { text: 'Tipo Doc.', value: 'tipoDoc' },
  { text: 'N° Documento', value: 'numero_documento' },
  { text: 'Correo', value: 'email' },
  { text: 'Celular', value: 'numero_celular' },
  { text: 'Remuneración', value: 'remuneracion' },
  { text: 'Acciones', value: 'actions', sortable: false },
]

// Configuración para tablas secundarias
const tablaActiva = ref(null)
const personaIdActiva = ref(null)
const tituloTablaActiva = ref('')

// Configuración de headers por tipo
const headersConfig = {
  dependientes: [
    { text: 'Nombre', value: 'nombre' },
    { text: 'Documento', value: 'documento' },
    { text: 'Fecha Nacimiento', value: 'fecha_nacimiento' },
  ],
  datosBancarios: [
    { text: 'Cuenta BCP', value: 'cta_bcp' },
    { text: 'CCI', value: 'cci' },
  ],
  tallasEPP: [
    { text: 'Talla Zapatos', value: 'talla_zapatos' },
    { text: 'Talla Chaleco', value: 'talla_chaleco' },
    { text: 'Talla Pantalón', value: 'talla_pantalon' },
    { text: 'Talla Casaca', value: 'talla_casaca' },
    { text: 'Casco', value: 'casco' },
  ]
}

const serverOptions = ref({
  page: 1,
  rowsPerPage: 10,
})
  
// Convertimos las personas en una ref reactiva
const items = ref([])

// Función para alternar entre tablas secundarias
const toggleTabla = async (tipo, personaId) => {
  if (tablaActiva.value === tipo && personaIdActiva.value === personaId) {
    tablaActiva.value = null
    personaIdActiva.value = null
    return
  }

  tablaActiva.value = tipo
  personaIdActiva.value = personaId
  tituloTablaActiva.value = tipo === 'dependientes' ? 'Dependientes' 
    : tipo === 'datosBancarios' ? 'Datos Bancarios' 
    : 'Tallas EPP'
  headersSecundarios.value = headersConfig[tipo]

  try {
    const endpoint = tipo === 'dependientes' ? 'dependientes'
      : tipo === 'datosBancarios' ? 'datos-bancarios'
      : 'tallas-epp'
    
    const response = await axios.get(`/${endpoint}/${personaId}`)
    itemsSecundarios.value = response.data

    // Formatear fechas si es la tabla de dependientes
    itemsSecundarios.value = tipo === 'dependientes' 
      ? response.data.map(dep => ({
          ...dep,
          fecha_nacimiento: dep.fecha_nacimiento ? dep.fecha_nacimiento.split('T')[0] : null
        }))
      : response.data
  } catch (error) {
    console.error(`Error al cargar ${tipo}:`, error)
    Swal.fire('Error', `No se pudieron cargar los ${tituloTablaActiva.value}`, 'error')
  }
}

// Función que carga datos desde el servidor
const fetchPersonas = async () => {
  loading.value = true
  try {
    const { page, rowsPerPage } = serverOptions.value
    const response = await axios.get('/personas', {
      params: {
        page,
        rowsPerPage,
        search: searchValue.value
      }
    })
    items.value = response.data.data
    serverItemsLength.value = response.data.total
  } catch (err) {
    console.error('Error al cargar datos:', err)
  } finally {
    loading.value = false
  }
}

// Carga inicial
fetchPersonas()

// Reaccionar a cambios en la paginación o búsqueda
watch([serverOptions, searchValue], fetchPersonas, { deep: true })

// Función para abrir modal en modo creación
const abrirModalNuevo = () => {
  personaSeleccionada.value = null
  mostrarModal.value = true
}

// Función para abrir modal en modo edición
const abrirModalEditar = (persona) => {
  personaSeleccionada.value = { ...persona }
  mostrarModal.value = true
}

// Función para cerrar modal
const cerrarModal = () => {
  mostrarModal.value = false
  personaSeleccionada.value = null
}

// Manejar guardado desde el modal
const manejarGuardado = ({ accion, data }) => {
  if (accion === 'creado') {
    items.value.push(data)
  } else if (accion === 'actualizado') {
    const index = items.value.findIndex(p => p.id === data.id)
    if (index !== -1) {
      items.value[index] = data
    }
  }
  cerrarModal()
}

// Función para eliminar
const deleteItem = (item) => {
  Swal.fire({
    title: '¿Estás seguro?',
    text: `Esta acción eliminará al usuario "${item.nombre}" permanentemente.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/personas/${item.id}`)
        .then(() => {
          items.value = items.value.filter(i => i.id !== item.id)
          Swal.fire('¡Eliminado!', 'El registro ha sido eliminado.', 'success')
        })
        .catch(err => {
          console.error('Error eliminando:', err)
          Swal.fire('Error', 'Ocurrió un error al intentar eliminar.', 'error')
        })
    }
  })
}

// Función para abrir modal secundario
const abrirModalSecundario = (item = null) => {
  itemSecundarioSeleccionado.value = item
  mostrarModalSecundario.value = true
}

// Función para manejar guardado secundario
const manejarGuardadoSecundario = async ({ accion, data, personaId }) => {
  try {
    // Actualización optimista (sin esperar respuesta del servidor)
    if (accion === 'creado') {
      itemsSecundarios.value.push(data);
    } else if (accion === 'actualizado') {
      const index = itemsSecundarios.value.findIndex(i => i.id === data.id);
      if (index !== -1) {
        itemsSecundarios.value[index] = data;
      }
    }
    
    // Forzar actualización reactiva
    itemsSecundarios.value = [...itemsSecundarios.value];
    
    // Opcional: Recargar datos desde el servidor para asegurar consistencia
    const endpoint = tablaActiva.value === 'dependientes' ? 'dependientes'
      : tablaActiva.value === 'datosBancarios' ? 'datos-bancarios'
      : 'tallas-epp';
    
    const response = await axios.get(`/${endpoint}/${personaId}`);
    itemsSecundarios.value = response.data;
    
    mostrarModalSecundario.value = false;
  } catch (error) {
    console.error('Error actualizando datos:', error);
  }
};

// Función para eliminar item secundario
const eliminarItemSecundario = (item) => {
  Swal.fire({
    title: '¿Estás seguro?',
    text: `Esta acción eliminará este registro permanentemente.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        const endpoint = tablaActiva.value === 'dependientes' ? 'dependientes'
          : tablaActiva.value === 'datosBancarios' ? 'datos-bancarios'
          : 'tallas-epp'
        
        await axios.delete(`/${endpoint}/${item.id}`)
        itemsSecundarios.value = itemsSecundarios.value.filter(i => i.id !== item.id)
        Swal.fire('¡Eliminado!', 'El registro ha sido eliminado.', 'success')
      } catch (error) {
        console.error('Error eliminando:', error)
        Swal.fire('Error', 'Ocurrió un error al intentar eliminar.', 'error')
      }
    }
  })
}
</script>

<style scoped>
.customize-table {
  --easy-table-header-font-size: 14px;
  --easy-table-body-row-font-size: 13px;
  --easy-table-border: 1px solid #ddd;
}
</style>