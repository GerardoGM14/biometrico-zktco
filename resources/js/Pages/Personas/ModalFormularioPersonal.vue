<template>
  <div class="fixed inset-0 bg-black/30 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md relative max-h-[90vh] overflow-y-auto">
      <button @click="$emit('close')" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700">
        ✕
      </button>
      <h3 class="text-xl font-semibold mb-4">
        {{ persona ? 'Editar Personal' : 'Agregar Nuevo Personal' }}
      </h3>

      <form @submit.prevent="guardarPersonal">
        <!-- Nombre -->
        <div class="mb-3">
          <label class="block">Nombre:</label>
          <input v-model="form.nombre" required class="w-full border px-3 py-2 rounded" />
          <span class="text-red-500 text-sm" v-if="errors.nombre">{{ errors.nombre }}</span>
        </div>

        <!-- Apellido -->
        <div class="mb-3">
          <label class="block">Apellido:</label>
          <input v-model="form.apellido" required class="w-full border px-3 py-2 rounded" />
          <span class="text-red-500 text-sm" v-if="errors.apellido">{{ errors.apellido }}</span>
        </div>

        <!-- Tipo de Documento -->
        <div class="mb-3">
          <label class="block">Tipo de Documento:</label>
          <select v-model="form.tipoDoc" class="w-full border px-3 py-2 rounded">
            <option value="DNI">DNI</option>
            <option value="CE">Carné de Extranjería</option>
            <option value="PAS">Pasaporte</option>
            <option value="RUC">RUC</option>
            <option value="PTP">Permiso Temporal de Permanencia</option>
          </select>
          <span class="text-red-500 text-sm" v-if="errors.tipoDoc">{{ errors.tipoDoc }}</span>
        </div>

        <!-- Número de Documento -->
        <div class="mb-3">
          <label class="block">Número de Documento:</label>
          <input
            v-model="form.numero_documento"
            class="w-full border px-3 py-2 rounded"
            @input="form.numero_documento = form.numero_documento.replace(/\D/g, '')"
          />
          <span class="text-red-500 text-sm" v-if="errors.numero_documento">{{ errors.numero_documento }}</span>
        </div>

        <!-- Email -->
        <div class="mb-3">
          <label class="block">Correo:</label>
          <input type="email" v-model="form.email" required class="w-full border px-3 py-2 rounded" />
          <span class="text-red-500 text-sm" v-if="errors.email">{{ errors.email }}</span>
        </div>

        <!-- Contraseña -->
        <div class="mb-3">
          <label class="block">Contraseña{{ persona ? ' (opcional)' : '' }}:</label>
          <input
            type="password"
            v-model="form.password"
            class="w-full border px-3 py-2 rounded"
            :required="!persona"
          />
          <span class="text-red-500 text-sm" v-if="errors.password">{{ errors.password }}</span>
        </div>

        <!-- Fecha de Nacimiento -->
        <div class="mb-3">
          <label class="block">Fecha de Nacimiento:</label>
          <input
            type="date"
            v-model="form.fecha_nacimiento"
            class="w-full border px-3 py-2 rounded"
          />
        </div>

        <!-- Remuneración -->
        <div class="mb-3">
          <label class="block">Remuneración:</label>
          <input
            type="number"
            step="0.01"
            v-model="form.remuneracion"
            class="w-full border px-3 py-2 rounded"
          />
        </div>

        <!-- Tipo de Aportación -->
        <div class="mb-3">
          <label class="block">Tipo de Aportación:</label>
          <select v-model="form.tipo_aportacion" class="w-full border px-3 py-2 rounded">
            <option value="">Seleccione</option>
            <option value="AFP">AFP</option>
            <option value="ONP">ONP</option>
          </select>
        </div>

        <!-- Número de Celular -->
        <div class="mb-3">
          <label class="block">Número de Celular:</label>
          <input
            v-model="form.numero_celular"
            class="w-full border px-3 py-2 rounded"
            @input="form.numero_celular = form.numero_celular.replace(/\D/g, '')"
          />
        </div>

        <!-- Grado de Instrucción -->
        <div class="mb-3">
          <label class="block">Grado de Instrucción:</label>
          <select v-model="form.grado_instruccion" class="w-full border px-3 py-2 rounded">
            <option value="">Seleccione</option>
            <option value="Primaria">Primaria</option>
            <option value="Secundaria">Secundaria</option>
            <option value="Técnico">Técnico</option>
            <option value="Universitario">Universitario</option>
            <option value="Posgrado">Posgrado</option>
          </select>
        </div>

        <!-- Rol -->
        <div class="mb-4">
          <label class="block text-gray-700">Rol:</label>
          <select
            v-model="form.rol_id"
            class="w-full border rounded px-3 py-2"
            required
          >
            <option disabled value="">Selecciona un rol</option>
            <option v-for="rol in roles" :key="rol.id" :value="rol.id">
              {{ rol.nombre }}
            </option>
          </select>
          <span class="text-red-500 text-sm" v-if="errors.rol_id">{{ errors.rol_id }}</span>
        </div>

        <!-- Botón Guardar -->
        <div class="flex justify-end">
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            {{ persona ? 'Actualizar' : 'Guardar' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted, watch } from 'vue'
import axios from 'axios'

const emit = defineEmits(['close', 'personalGuardado'])
const props = defineProps({
  persona: { type: Object, default: null },
  roles: { type: Array, default: () => [] }
})

// Errores de validación
const errors = reactive({})

const roles = ref([]);

// Formulario reactivo
const form = reactive({
  id: null, 
  nombre: '',
  apellido: '',
  tipoDoc: 'DNI',
  numero_documento: '',
  email: '',
  password: '',
  rol_id: '',
  fecha_nacimiento: null,
  remuneracion: null,
  tipo_aportacion: '',
  numero_celular: '',
  grado_instruccion: ''
})

const getRoles = async () => {
    try {
        const response = await axios.get('/roles/listar');
        roles.value = response.data;
    } catch (error) {
        console.error('Error al obtener roles:', error);
    }
};

// Watcher para props.persona
watch(() => props.persona, (persona) => {
  if (persona) {
    Object.assign(form, {
      id: persona.id,
      nombre: persona.nombre,
      apellido: persona.apellido,
      tipoDoc: persona.tipoDoc,
      numero_documento: persona.numero_documento,
      email: persona.email,
      rol_id: persona.rol_id,
      fecha_nacimiento: persona.fecha_nacimiento,
      remuneracion: persona.remuneracion,
      tipo_aportacion: persona.tipo_aportacion,
      numero_celular: persona.numero_celular,
      grado_instruccion: persona.grado_instruccion
    })
    // No cargamos password para edición
  } else {
    // Limpiar formulario
    Object.assign(form, {
      id: null,
      nombre: '',
      apellido: '',
      tipoDoc: 'DNI',
      numero_documento: '',
      email: '',
      password: '',
      rol_id: '',
      fecha_nacimiento: null,
      remuneracion: null,
      tipo_aportacion: '',
      numero_celular: '',
      grado_instruccion: ''
    })
    // Limpiar errores
    Object.keys(errors).forEach(key => errors[key] = '')
  }
}, { immediate: true })

// Validación
function validarFormulario() {
  let valido = true

  if (!form.nombre) {
    errors.nombre = 'El nombre es obligatorio'
    valido = false
  } else {
    errors.nombre = ''
  }

  if (!form.apellido) {
    errors.apellido = 'El apellido es obligatorio'
    valido = false
  } else {
    errors.apellido = ''
  }

  if (!form.numero_documento) {
    errors.numero_documento = 'El número de documento es obligatorio'
    valido = false
  } else {
    errors.numero_documento = ''
  }

  if (!form.email) {
    errors.email = 'El correo es obligatorio'
    valido = false
  } else if (!/^\S+@\S+\.\S+$/.test(form.email)) {
    errors.email = 'El correo no es válido'
    valido = false
  } else {
    errors.email = ''
  }

  if (!props.persona && !form.password) {
    errors.password = 'La contraseña es obligatoria'
    valido = false
  } else if (form.password && form.password.length < 8) {
    errors.password = 'La contraseña debe tener al menos 8 caracteres'
    valido = false
  } else {
    errors.password = ''
  }

  if (!form.rol_id) {
    errors.rol_id = 'Debes seleccionar un rol'
    valido = false
  } else {
    errors.rol_id = ''
  }

  return valido
}

async function guardarPersonal() {
  if (!validarFormulario()) return

  try {
    let response
    if (props.persona) {
        response = await axios.put(`/personas/${form.id}`, form)
        emit('personalGuardado', { accion: 'actualizado', data: response.data.personal })
    } else {
        response = await axios.post('/personas', form)
        emit('personalGuardado', { accion: 'creado',    data: response.data.personal })
    }
    emit('close')
  } catch (error) {
    if (error.response?.data?.errors) {
      Object.assign(errors, error.response.data.errors)
    } else {
      console.error('Error guardando personal:', error)
    }
  }
}

onMounted(() => {
    getRoles();
});
</script>