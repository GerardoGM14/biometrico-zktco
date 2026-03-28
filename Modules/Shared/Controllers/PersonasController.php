<?php

namespace Modules\Shared\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Shared\Models\Personal;
use Illuminate\Support\Facades\Hash;
use Modules\Shared\Models\Roles;
use Illuminate\Http\RedirectResponse;
use App\Helpers\AuditLogger;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Shared\Models\Dependiente;
use Modules\Shared\Models\PersonaBanco;
use Modules\Shared\Models\PersonaEPP;
use Illuminate\Validation\Rule;
use Modules\Shared\Models\AsignacionGuardia;

class PersonasController extends Controller
{
    public function index(Request $request)
    {
        if ($request->wantsJson()) {
            $query = Personal::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('apellido', 'like', "%{$search}%")
                      ->orWhere('numero_documento', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('numero_celular', 'like', "%{$search}%");
                });
            }

            $perPage = $request->input('rowsPerPage', 10);
            $personas = $query->paginate($perPage);

            return response()->json($personas);
        }

        return Inertia::render('Personas/Index', [
            'personas' => []
        ]);
    }

    public function listar(Request $request)
    {
        $limit = $request->input('limit', 5);
        $id = $request->input('id');

        $query = Personal::select('id', 'nombre', 'apellido');

        if ($id) {
            $specific = $query->clone()->where('id', $id)->first();
            if ($specific) {
                $results = $query->where('id', '!=', $id)->limit($limit-1)->get();
                return $results->prepend($specific);
            }
        }
        
        return $query->limit($limit)->get();
    }

    public function buscar(Request $request)
    {
        $search = $request->input('q');

        $resultados = Personal::where('nombre', 'LIKE', "%{$search}%")
            ->orWhere('apellido', 'LIKE', "%{$search}%")
            ->limit(20)
            ->get();

        return response()->json($resultados);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
            'apellido' => 'required|string',
            'tipoDoc' => 'required|string|max:20',
            'numero_documento' => [
                'required',
                'string',
                Rule::unique('personas')->whereNull('deleted_at') // 👈 Ignora soft deleted
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('personas')->whereNull('deleted_at') // 👈 Ignora soft deleted
            ],
            'password' => 'required|string|min:8',
            'rol_id' => 'required|exists:roles,id',
            'fecha_nacimiento' => 'nullable|date',
            'remuneracion' => 'nullable|numeric',
            'tipo_aportacion' => 'nullable|string|max:20',
            'numero_celular' => 'nullable|string',
            'grado_instruccion' => 'nullable|string',
        ]);
    
        $validated['password'] = Hash::make($validated['password']);
        
        try {
            $personal = Personal::create($validated);
            AuditLogger::logCreate($personal);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return response()->json([
                    'success' => false,
                    'message' => 'El personal ya existe en la base de datos'
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar en base de datos'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error inesperado'
            ], 500);
        }
        
        return response()->json(['message' => 'Personal creado', 'personal' => $personal]);
    }

    public function obtenerRoles()
    {
        $roles = Roles::select('id', 'nombre')->get();
        return response()->json($roles);
    }

    public function destroy($id)
    {
        $persona = \Modules\Shared\Models\Personal::findOrFail($id);

        // localizar la interfaz "permisos_usuario"
        $permIface = \Modules\Shared\Models\VistasInterfaces::where(function($q){
            $q->where('route', 'like', '%permisos_usuario%')
            ->orWhere('slug', 'like', '%permisos_usuario%')
            ->orWhere('name', 'like', '%Permisos%')
            ->orWhere('name', 'like', '%permisos%');
        })->first();

        if ($permIface) {
            $permIfaceId = intval($permIface->id);

            // verificar si esta persona tiene can_show = 1 para esa vista
            $has = DB::table('interfaz_usuario_permisos')
                ->where('personal_id', $id)
                ->where('vista_interfaz_id', $permIfaceId)
                ->where('can_show', 1)
                ->exists();

            if ($has) {
                // contar usuarios ACTVOS con can_show = 1 para esa vista
                $countActive = DB::table('interfaz_usuario_permisos AS iup')
                    ->join('personas AS p', 'p.id', '=', 'iup.personal_id')
                    ->where('iup.vista_interfaz_id', $permIfaceId)
                    ->where('iup.can_show', 1)
                    ->whereNull('p.deleted_at')
                    ->distinct('iup.personal_id')
                    ->count('iup.personal_id');

                // si es el unico => bloquear eliminación
                if ($countActive <= 1) {
                    return response()->json([
                        'message' => 'No se puede eliminar este usuario: es el único con acceso a la interfaz de gestión de permisos.',
                        'blocked' => [
                            ['id' => $permIfaceId, 'name' => $permIface->name]
                        ]
                    ], 422);
                }
            }
        }

        // audit + soft delete
        AuditLogger::logDelete($persona);
        $persona->delete();

        return response()->json(['message' => 'Personal eliminado correctamente']);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'tipoDoc' => 'required|string|max:20',
            'numero_documento' => [
                'required',
                'string',
                Rule::unique('personas')->ignore($id)->whereNull('deleted_at') // 👈 Ignora soft deleted
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('personas')->ignore($id)->whereNull('deleted_at') // 👈 Ignora soft deleted
            ],
            'rol_id' => 'required|exists:roles,id',
            'fecha_nacimiento' => 'nullable|date',
            'remuneracion' => 'nullable|numeric',
            'tipo_aportacion' => 'nullable|string|max:20',
            'numero_celular' => 'nullable|string',
            'grado_instruccion' => 'nullable|string',
        ]);

        $personal = Personal::findOrFail($id);
        $originalData = clone $personal;

        $personal->nombre = $request->nombre;
        $personal->apellido = $request->apellido;
        $personal->tipoDoc = $request->tipoDoc;
        $personal->numero_documento = $request->numero_documento;
        $personal->email = $request->email;
        $personal->rol_id = $request->rol_id;
        $personal->fecha_nacimiento = $request->fecha_nacimiento;
        $personal->remuneracion = $request->remuneracion;
        $personal->tipo_aportacion = $request->tipo_aportacion;
        $personal->numero_celular = $request->numero_celular;
        $personal->grado_instruccion = $request->grado_instruccion;

        if ($request->filled('password')) {
            $personal->password = Hash::make($request->password);
        }

        $personal->save();
        AuditLogger::logUpdate($originalData, $personal);

        return response()->json(['message' => 'Personal actualizado correctamente', 'personal' => $personal]);
    }

    public function getDependientes($persona_id)
    {
        $dependientes = Dependiente::where('persona_id', $persona_id)->get();
        return response()->json($dependientes);
    }

    public function storeDependiente(Request $request)
    {
        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'nombre' => 'required|string',
            'documento' => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date'
        ]);

        $dependiente = Dependiente::create($validated);
        return response()->json($dependiente);
    }

    public function updateDependiente(Request $request, $id)
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
            'documento' => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date'
        ]);

        $dependiente = Dependiente::findOrFail($id);
        $dependiente->update($validated);
        return response()->json($dependiente);
    }

    public function destroyDependiente($id)
    {
        $dependiente = Dependiente::findOrFail($id);
        $dependiente->delete();
        return response()->json(['message' => 'Dependiente eliminado']);
    }

    public function getDatosBancarios($persona_id)
    {
        $datos = PersonaBanco::where('persona_id', $persona_id)->get();
        return response()->json($datos);
    }

    public function storeDatosBancarios(Request $request)
    {
        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'cta_bcp' => 'nullable|string',
            'cci' => 'nullable|string'
        ]);

        $datos = PersonaBanco::create($validated);
        return response()->json($datos);
    }

    public function updateDatosBancarios(Request $request, $id)
    {
        $validated = $request->validate([
            'cta_bcp' => 'nullable|string',
            'cci' => 'nullable|string'
        ]);

        $datos = PersonaBanco::findOrFail($id);
        $datos->update($validated);
        return response()->json($datos);
    }

    public function destroyDatosBancarios($id)
    {
        $datos = PersonaBanco::findOrFail($id);
        $datos->delete();
        return response()->json(['message' => 'Datos bancarios eliminados']);
    }

    public function getTallasEPP($persona_id)
    {
        $tallas = PersonaEPP::where('persona_id', $persona_id)->get();
        return response()->json($tallas);
    }

    public function storeTallasEPP(Request $request)
    {
        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'talla_zapatos' => 'nullable|string',
            'talla_chaleco' => 'nullable|string',
            'talla_pantalon' => 'nullable|string',
            'talla_casaca' => 'nullable|string',
            'casco' => 'nullable|string'
        ]);

        $tallas = PersonaEPP::create($validated);
        return response()->json($tallas);
    }

    public function updateTallasEPP(Request $request, $id)
    {
        $validated = $request->validate([
            'talla_zapatos' => 'nullable|string',
            'talla_chaleco' => 'nullable|string',
            'talla_pantalon' => 'nullable|string',
            'talla_casaca' => 'nullable|string',
            'casco' => 'nullable|string'
        ]);

        $tallas = PersonaEPP::findOrFail($id);
        $tallas->update($validated);
        return response()->json($tallas);
    }

    public function destroyTallasEPP($id)
    {
        $tallas = PersonaEPP::findOrFail($id);
        $tallas->delete();
        return response()->json(['message' => 'Tallas EPP eliminadas']);
    }

    public function getGuardiaActiva($id)
    {
        try {
            $persona = Personal::findOrFail($id);
            
            $asignacion = AsignacionGuardia::where('persona_id', $id)
                ->orderBy('fecha_inicio', 'desc')
                ->with('guardia')
                ->first();

            return response()->json([
                'persona_id' => $persona->id,
                'guardia_id' => $asignacion ? $asignacion->guardia_id : null,
                'guardia' => $asignacion ? $asignacion->guardia : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error obteniendo guardia'], 500);
        }
    }

}