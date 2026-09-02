<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhcp_scopes', function (Blueprint $table) {
            $table->id();
            $table->string('scope_id', 20)->unique();          // Ej: 10.1.10.0
            $table->string('nombre', 150)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->string('subnet_mask', 20)->nullable();
            $table->string('rango_inicio', 20)->nullable();
            $table->string('rango_fin', 20)->nullable();
            $table->string('estado', 20)->default('Active');
            $table->unsignedInteger('total_direcciones')->default(0);
            $table->unsignedInteger('en_uso')->default(0);
            $table->unsignedInteger('libres')->default(0);
            $table->decimal('porcentaje_uso', 5, 2)->default(0);
            $table->unsignedInteger('reservas_count')->default(0);
            $table->timestamp('actualizado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dhcp_reservas', function (Blueprint $table) {
            $table->id();
            $table->string('scope_id', 20)->index();
            $table->string('ip', 20)->unique();
            $table->string('mac', 40)->nullable();
            $table->string('nombre', 150)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->timestamp('ultima_actividad')->nullable();   // última vez vista activa
            $table->boolean('visto_activa')->default(false);     // estado en el último snapshot
            $table->timestamp('lease_expira')->nullable();       // LeaseExpiryTime del último snapshot
            $table->timestamp('primera_vez_visto')->nullable();
            $table->boolean('activa')->default(true);            // sigue existiendo en el DHCP
            $table->timestamps();

            $table->index(['scope_id', 'ultima_actividad']);
        });

        Schema::create('dhcp_importaciones', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recibido_at')->nullable();
            $table->timestamp('generado_at')->nullable();        // cuándo el script tomó el snapshot
            $table->unsignedInteger('scopes_count')->default(0);
            $table->unsignedInteger('reservas_count')->default(0);
            $table->unsignedInteger('reservas_activas')->default(0);
            $table->string('origen_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhcp_importaciones');
        Schema::dropIfExists('dhcp_reservas');
        Schema::dropIfExists('dhcp_scopes');
    }
};
