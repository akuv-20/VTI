<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrega_factura_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_entrega')->constrained('entregas_facturas')->cascadeOnDelete();
            $table->foreignId('id_factura')->unique()->constrained('facturas')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_factura_items');
    }
};
