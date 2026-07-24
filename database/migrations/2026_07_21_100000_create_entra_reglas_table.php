<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entra_reglas', function (Blueprint $table) {
            $table->id();
            $table->string('campo')->nullable();   // campo Graph evaluado (null en reglas multi-campo)
            $table->string('tipo');                // valores_permitidos | obligatorio | formato_consistente | sin_duplicados | actividad_reciente
            $table->string('etiqueta');            // nombre legible de la regla
            $table->text('descripcion')->nullable();
            $table->json('config')->nullable();    // parámetros propios del tipo
            $table->string('severidad')->default('error'); // error | advertencia
            $table->boolean('solo_habilitados')->default(true); // ignorar cuentas deshabilitadas
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['activa', 'orden']);
        });

        // ── Reglas iniciales (equivalen a lo que estaba hardcodeado) ─────────
        $now = now();
        $base = ['created_at' => $now, 'updated_at' => $now];

        DB::table('entra_reglas')->insert([
            [
                'campo'       => 'country',
                'tipo'        => 'valores_permitidos',
                'etiqueta'    => 'País con código válido',
                'descripcion' => 'El país debe usar el código ISO de 2 letras.',
                'config'      => json_encode(['valores' => ['CL', 'PE', 'AR', 'US', 'ES']]),
                'severidad'   => 'error',
                'orden'       => 10,
            ] + $base,
            [
                'campo'       => 'userType',
                'tipo'        => 'valores_permitidos',
                'etiqueta'    => 'Tipo de cuenta válido',
                'descripcion' => 'Solo se esperan cuentas Member o Guest.',
                'config'      => json_encode(['valores' => ['Member', 'Guest']]),
                'severidad'   => 'error',
                'orden'       => 20,
            ] + $base,
            [
                'campo'       => 'department',
                'tipo'        => 'obligatorio',
                'etiqueta'    => 'Área / Departamento informado',
                'descripcion' => 'Toda cuenta habilitada debe tener departamento.',
                'config'      => null,
                'severidad'   => 'error',
                'orden'       => 30,
            ] + $base,
            [
                'campo'       => 'jobTitle',
                'tipo'        => 'obligatorio',
                'etiqueta'    => 'Cargo informado',
                'descripcion' => 'Toda cuenta habilitada debe tener cargo.',
                'config'      => null,
                'severidad'   => 'advertencia',
                'orden'       => 40,
            ] + $base,
            [
                'campo'       => 'department',
                'tipo'        => 'formato_consistente',
                'etiqueta'    => 'Departamento sin variantes de formato',
                'descripcion' => 'Detecta el mismo valor escrito con distinta capitalización o espacios.',
                'config'      => null,
                'severidad'   => 'advertencia',
                'orden'       => 50,
            ] + $base,
            [
                'campo'       => 'jobTitle',
                'tipo'        => 'formato_consistente',
                'etiqueta'    => 'Cargo sin variantes de formato',
                'descripcion' => 'Detecta el mismo cargo escrito de distintas formas.',
                'config'      => null,
                'severidad'   => 'advertencia',
                'orden'       => 60,
            ] + $base,
            [
                'campo'       => null,
                'tipo'        => 'sin_duplicados',
                'etiqueta'    => 'Personas duplicadas',
                'descripcion' => 'Cuentas distintas que comparten nombre y apellido.',
                'config'      => json_encode(['campos' => ['givenName', 'surname']]),
                'severidad'   => 'advertencia',
                'orden'       => 70,
            ] + $base,
            [
                'campo'       => null,
                'tipo'        => 'actividad_reciente',
                'etiqueta'    => 'Actividad en los últimos 90 días',
                'descripcion' => 'Cuentas habilitadas que no inician sesión hace mucho tiempo.',
                'config'      => json_encode(['dias' => 90]),
                'severidad'   => 'advertencia',
                'orden'       => 80,
            ] + $base,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('entra_reglas');
    }
};
