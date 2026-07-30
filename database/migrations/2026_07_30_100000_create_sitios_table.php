<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitios', function (Blueprint $table) {
            $table->id();

            // ── Identificación ───────────────────────────────────────────────
            $table->string('codigo', 30)->nullable();       // prefijo numérico usado en CheckMK (2, 3, 19…)
            $table->string('nombre');
            $table->string('tipo', 20);                     // planta | campo | datacenter | oficina
            $table->string('estado_enlace', 20)->default('sin_enlace'); // avance del enlazamiento
            $table->string('empresa')->nullable();
            $table->boolean('activo')->default(true);

            // ── Ubicación ────────────────────────────────────────────────────
            $table->string('region')->nullable();
            $table->string('comuna')->nullable();
            $table->string('direccion')->nullable();
            $table->text('acceso')->nullable();             // cómo llegar: portón, referencias, ruta
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();

            // ── Enlace / ISP ─────────────────────────────────────────────────
            $table->string('enlace_tipo', 20)->nullable();  // fibra | ptp | starlink | 4g | satelital | ninguno
            $table->string('isp')->nullable();
            $table->string('ancho_banda', 60)->nullable();
            $table->string('ip_publica', 60)->nullable();
            $table->string('num_servicio', 80)->nullable();
            $table->date('fecha_instalacion')->nullable();

            // ── Red interna ──────────────────────────────────────────────────
            $table->string('subred', 60)->nullable();
            $table->string('vlan', 60)->nullable();
            $table->string('gateway', 60)->nullable();
            $table->boolean('vpn')->default(false);
            $table->string('vpn_detalle')->nullable();

            // ── Infraestructura física ───────────────────────────────────────
            $table->string('gabinete')->nullable();
            $table->string('ups_modelo')->nullable();
            $table->unsignedSmallInteger('ups_autonomia_min')->nullable();
            $table->boolean('generador')->default(false);
            $table->boolean('puesta_tierra')->default(false);
            $table->boolean('climatizacion')->default(false);

            // ── Operación ────────────────────────────────────────────────────
            $table->decimal('superficie_ha', 10, 2)->nullable(); // campos
            $table->string('especies')->nullable();              // campos
            $table->unsignedSmallInteger('usuarios_cant')->nullable();
            $table->unsignedSmallInteger('pcs_cant')->nullable();
            $table->boolean('estacional')->default(false);
            $table->string('temporada', 80)->nullable();         // ej: "octubre a marzo"

            // ── Datacenter ───────────────────────────────────────────────────
            $table->unsignedSmallInteger('racks_cant')->nullable();
            $table->unsignedSmallInteger('racks_u_usados')->nullable();
            $table->unsignedSmallInteger('racks_u_totales')->nullable();

            // ── Contactos ────────────────────────────────────────────────────
            $table->string('encargado_nombre')->nullable();
            $table->string('encargado_telefono', 60)->nullable();
            $table->string('encargado_email')->nullable();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();

            // ── Vínculos y notas ─────────────────────────────────────────────
            $table->foreignId('mapa_id')->nullable()->constrained('mapas_red')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamp('levantado_at')->nullable();   // última actualización en terreno
            $table->foreignId('levantado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tipo', 'estado_enlace']);
            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitios');
    }
};
