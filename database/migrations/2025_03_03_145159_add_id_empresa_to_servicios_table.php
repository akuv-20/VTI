<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->foreignId('id_empresa')->nullable()->constrained('empresas')->onDelete('set null')->after('codigo_servicio');
            $table->dropColumn('empresa');
        });
    }

    public function down()
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropForeign(['id_empresa']);
            $table->dropColumn('id_empresa');
        });
    }
};