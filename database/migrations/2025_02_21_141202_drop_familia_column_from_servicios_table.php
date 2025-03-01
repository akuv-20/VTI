<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('servicios', function (Blueprint $table) {
            // Eliminar el campo familia
            $table->dropColumn('familia');
        });
    }

    public function down()
    {
        Schema::table('servicios', function (Blueprint $table) {
            // Revertir los cambios: agregar el campo familia nuevamente
            $table->string('familia')->nullable()->after('id_familia');
        });
    }
};