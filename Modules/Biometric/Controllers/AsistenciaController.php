<?php

namespace Modules\Biometric\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Biometric\Models\Asistencia;
use Modules\Shared\Models\Personal;
use Inertia\Inertia;
use Carbon\Carbon;

class AsistenciaController extends Controller
{
    public function control()
    {
        return Inertia::render('Biometric/AsistenciaControl');
    }

    public function reporte(Request $request)
    {
        return Inertia::render('Biometric/AsistenciaReporte');
    }

    public function marcar(Request $request)
    {
        $request->validate([
            'persona_id' => 'required|exists:personas,id',
        ]);

        $hoy = Carbon::today()->toDateString();
        $ahora = Carbon::now()->format('H:i:s');

        $asistenciaHoy = Asistencia::where('persona_id', $request->persona_id)
            ->where('fecha', $hoy)
            ->first();

        if (!$asistenciaHoy) {
            $estado = 'puntual';

            $horaLimite = '08:00:00';
            if ($ahora > $horaLimite) {
                $estado = 'tardanza';
            }

            $asistencia = Asistencia::create([
                'persona_id' => $request->persona_id,
                'fecha' => $hoy,
                'hora_entrada' => $ahora,
                'tipo_marcacion' => 'entrada',
                'estado' => $estado,
            ]);

            $persona = Personal::select('id', 'nombre', 'apellido')->find($request->persona_id);

            return response()->json([
                'tipo' => 'entrada',
                'message' => "Entrada registrada para {$persona->nombre} {$persona->apellido}",
                'hora' => $ahora,
                'estado' => $estado,
                'persona' => $persona,
                'asistencia' => $asistencia,
            ]);
        }

        if ($asistenciaHoy->hora_salida) {
            return response()->json([
                'message' => 'Ya registró entrada y salida el día de hoy.',
                'tipo' => 'completo',
            ], 422);
        }

        $asistenciaHoy->update([
            'hora_salida' => $ahora,
            'tipo_marcacion' => 'salida',
        ]);

        $persona = Personal::select('id', 'nombre', 'apellido')->find($request->persona_id);

        return response()->json([
            'tipo' => 'salida',
            'message' => "Salida registrada para {$persona->nombre} {$persona->apellido}",
            'hora' => $ahora,
            'persona' => $persona,
            'asistencia' => $asistenciaHoy,
        ]);
    }

    public function listar(Request $request)
    {
        $query = Asistencia::with('persona:id,nombre,apellido,numero_documento');

        if ($request->filled('fecha_inicio')) {
            $query->where('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->where('fecha', '<=', $request->fecha_fin);
        }

        if ($request->filled('persona_id')) {
            $query->where('persona_id', $request->persona_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $query->orderBy('fecha', 'desc')->orderBy('hora_entrada', 'desc');

        $perPage = $request->input('rowsPerPage', 15);
        $asistencias = $query->paginate($perPage);

        return response()->json($asistencias);
    }
}
