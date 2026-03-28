<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->tinyInteger('dedo_indice')->comment('1=pulgar_der, 2=indice_der, 3=medio_der, 6=pulgar_izq, 7=indice_izq, 8=medio_izq');
            $table->longText('template')->comment('Template de huella en base64 del ZKBioID SDK');
            $table->integer('calidad')->default(0)->comment('Calidad de captura 0-100');
            $table->timestamps();

            $table->unique(['persona_id', 'dedo_indice']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_data');
    }
};
