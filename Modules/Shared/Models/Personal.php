<?php

namespace Modules\Shared\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Biometric\Models\BiometricData;
use Modules\Biometric\Models\Asistencia;

class Personal extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'personas';

    protected $fillable = [
        'nombre',
        'apellido',
        'tipoDoc',
        'numero_documento',
        'email',
        'password',
        'rol_id',
        'fecha_nacimiento',
        'remuneracion',
        'tipo_aportacion',
        'numero_celular',
        'grado_instruccion',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'remuneracion' => 'decimal:2',
    ];

    public function rol()
    {
        return $this->belongsTo(Roles::class, 'rol_id');
    }

    public function huellas()
    {
        return $this->hasMany(BiometricData::class, 'persona_id');
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'persona_id');
    }
}
