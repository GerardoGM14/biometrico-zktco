<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Models\Personal;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('tipoDoc', 20); // Ej: 'DNI', 'Pasaporte', 'Cédula', 'RUC'
            $table->string('numero_documento')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('rol_id')->constrained('roles');
            $table->date('fecha_nacimiento')->nullable();
            $table->decimal('remuneracion', 15, 2)->nullable();
            $table->string('tipo_aportacion', 20)->nullable();
            $table->string('numero_celular')->nullable();
            $table->string('grado_instruccion')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        
        Personal::create([
            'nombre' => 'Admin',
            'apellido' => 'Silva',
            'email' => 'prueba@admin.com',
            'password' => Hash::make('secret'),
            'tipoDoc' => 'DNI',
            'numero_documento' => '12345678',
            'rol_id' => 1,
            'fecha_nacimiento'=>'1980-01-15',
            'remuneracion'=>1500,
            'tipo_aportacion'=>'AFP',
            'numero_celular'=> '567891234',
            'grado_instruccion'=> 'Universitario',
        ]);

        Personal::create([
            'nombre' => 'Luis',
            'apellido' => 'Silva',
            'email' => 'lvix456@hotmail.com',
            'password' => Hash::make('secret'),
            'tipoDoc' => 'DNI',
            'numero_documento' => '12457896',
            'rol_id' => 2,
            'fecha_nacimiento'=>'2000-01-15',
            'remuneracion'=>1200,
            'tipo_aportacion'=>'AFP',
            'numero_celular'=> '123456789',
            'grado_instruccion'=> 'Universitario',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
