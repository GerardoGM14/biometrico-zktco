<?php

namespace Modules\Biometric\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Biometric\Models\BiometricData;
use Modules\Shared\Models\Personal;
use Inertia\Inertia;

class BiometricController extends Controller
{
    public function index()
    {
        return Inertia::render('Biometric/BiometricRegistro');
    }

    public function getHuellas($personaId)
    {
        $huellas = BiometricData::where('persona_id', $personaId)
            ->select('id', 'persona_id', 'dedo_indice', 'calidad', 'created_at')
            ->get()
            ->map(function ($huella) {
                $huella->nombre_dedo = $huella->nombre_del_dedo;
                return $huella;
            });

        return response()->json($huellas);
    }

    public function registrarHuella(Request $request)
    {
        $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'dedo_indice' => 'required|integer|in:1,2,3,6,7,8',
            'template' => 'required|string',
            'calidad' => 'nullable|integer|min:0|max:100',
        ]);

        $existeEsteDedo = BiometricData::where('persona_id', $request->persona_id)
            ->where('dedo_indice', $request->dedo_indice)
            ->exists();

        if (!$existeEsteDedo) {
            $totalHuellas = BiometricData::where('persona_id', $request->persona_id)->count();
            if ($totalHuellas >= 3) {
                return response()->json([
                    'message' => 'Esta persona ya tiene el máximo de 3 huellas registradas. Elimine una antes de agregar otra.'
                ], 422);
            }
        }

        $huella = BiometricData::updateOrCreate(
            [
                'persona_id' => $request->persona_id,
                'dedo_indice' => $request->dedo_indice,
            ],
            [
                'template' => $request->template,
                'calidad' => $request->calidad ?? 0,
            ]
        );

        return response()->json([
            'message' => 'Huella registrada correctamente',
            'huella' => [
                'id' => $huella->id,
                'persona_id' => $huella->persona_id,
                'dedo_indice' => $huella->dedo_indice,
                'calidad' => $huella->calidad,
                'nombre_dedo' => $huella->nombre_del_dedo,
            ]
        ]);
    }

    public function eliminarHuella($id)
    {
        $huella = BiometricData::findOrFail($id);
        $huella->delete();

        return response()->json(['message' => 'Huella eliminada correctamente']);
    }

    public function identificar(Request $request)
    {
        $request->validate([
            'template' => 'required|string',
        ]);

        $templateCapturado = $request->template;

        $huellas = BiometricData::select('id', 'persona_id', 'dedo_indice', 'template')
            ->get()
            ->makeVisible('template');

        if ($huellas->isEmpty()) {
            return response()->json([
                'identificado' => false,
                'message' => 'No hay huellas registradas en el sistema'
            ], 404);
        }

        $match = null;
        $bestScore = 0;

        foreach ($huellas as $huella) {
            $score = $this->compararTemplates($templateCapturado, $huella->template);

            if ($score > $bestScore) {
                $bestScore = $score;
                $match = $huella;
            }
        }

        if ($match && $bestScore >= 50) {
            $persona = Personal::select('id', 'nombre', 'apellido', 'numero_documento')
                ->find($match->persona_id);

            return response()->json([
                'identificado' => true,
                'persona' => $persona,
                'dedo' => $match->nombre_del_dedo,
                'score' => $bestScore,
            ]);
        }

        return response()->json([
            'identificado' => false,
            'message' => 'Huella no reconocida. Intente de nuevo o registre la huella.'
        ], 404);
    }

    private function compararTemplates(string $template1, string $template2): int
    {
        try {
            $socket = @fsockopen('127.0.0.1', 8081, $errno, $errstr, 2);

            if ($socket) {
                fclose($socket);
                $ch = curl_init('http://127.0.0.1:8082/match');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['t1' => $template1, 't2' => $template2]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 3,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);
                    return $data['score'] ?? 0;
                }
            }
        } catch (\Exception $e) {
        }

        return $template1 === $template2 ? 100 : 0;
    }
}
