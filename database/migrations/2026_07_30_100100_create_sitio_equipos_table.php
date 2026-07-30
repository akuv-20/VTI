<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitio_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitio_id')->constrained('sitios')->cascadeOnDelete();

            $table->string('tipo', 20);                 // switch | ap | ptp | router | firewall | nvr | ups | servidor | otro
            $table->string('nombre');
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('serie')->nullable();
            $table->string('mac', 40)->nullable();
            $table->string('estado', 20)->default('operativo'); // operativo | falla | respaldo | baja

            // ── Red ──────────────────────────────────────────────────────────
            $table->string('ip_gestion', 60)->nullable();
            $table->string('vlan', 60)->nullable();
            $table->string('firmware', 80)->nullable();
            $table->unsignedSmallInteger('puertos_totales')->nullable();
            $table->unsignedSmallInteger('puertos_usados')->nullable();
            $table->boolean('poe')->default(false);
            $table->string('uplink')->nullable();       // a qué equipo/puerto sube

            // ── Ubicación física dentro del sitio ────────────────────────────
            $table->string('zona')->nullable();         // sala, tablero, área (packing, altillo…)
            $table->string('rack_u', 30)->nullable();

            // ── Específicos de enlace punto a punto ──────────────────────────
            $table->string('ptp_frecuencia', 40)->nullable();
            $table->unsignedSmallInteger('ptp_azimut')->nullable();
            $table->decimal('ptp_distancia_km', 6, 2)->nullable();
            $table->string('ptp_par_remoto')->nullable();
            $table->decimal('ptp_altura_m', 6, 2)->nullable();

            // ── Específicos de access point ──────────────────────────────────
            $table->string('ap_ssids')->nullable();
            $table->string('ap_banda', 30)->nullable();
            $table->string('ap_canal', 30)->nullable();

            // ── Monitoreo y comercial ───────────────────────────────────────
            $table->string('host_name')->nullable();    // host en CheckMK
            $table->string('proveedor')->nullable();
            $table->date('fecha_compra')->nullable();
            $table->date('garantia_hasta')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['sitio_id', 'tipo']);
            $table->index('host_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitio_equipos');
    }
};
