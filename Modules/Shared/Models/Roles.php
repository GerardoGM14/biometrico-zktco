<?php

namespace Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'nombre',
    ];

    public function personas()
    {
        return $this->hasMany(Personal::class, 'rol_id');
    }
}
