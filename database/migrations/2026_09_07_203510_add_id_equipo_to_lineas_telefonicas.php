<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lineas_telefonicas', function (Blueprint $table) {
            $table->foreignId('id_equipo')->nullable()->after('id_aparato')
                  ->constrained('equipos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lineas_telefonicas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_equipo');
        });
    }
};
