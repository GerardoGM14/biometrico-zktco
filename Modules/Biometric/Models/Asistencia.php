<?php

namespace Modules\Biometric\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Models\Personal;

class Asistencia extends Model
{
    protected $table = 'asistencias';

    protected $fillable = [
        'persona_id',
        'fecha',
        'hora_entrada',
        'hora_salida',
        'tipo_marcacion',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function persona()
    {
        return $this->belongsTo(Personal::class, 'persona_id');
    }
}
