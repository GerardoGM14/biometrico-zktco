<?php

namespace Modules\Biometric\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Models\Personal;

class BiometricData extends Model
{
    protected $table = 'biometric_data';

    protected $fillable = [
        'persona_id',
        'dedo_indice',
        'template',
        'calidad',
    ];

    protected $hidden = [
        'template', // No exponer el template en JSON por defecto
    ];

    public function persona()
    {
        return $this->belongsTo(Personal::class, 'persona_id');
    }

    public static function nombresDedos(): array
    {
        return [
            1 => 'Pulgar Derecho',
            2 => 'Índice Derecho',
            3 => 'Medio Derecho',
            6 => 'Pulgar Izquierdo',
            7 => 'Índice Izquierdo',
            8 => 'Medio Izquierdo',
        ];
    }

    public function getNombreDelDedoAttribute(): string
    {
        return self::nombresDedos()[$this->dedo_indice] ?? 'Desconocido';
    }
}
