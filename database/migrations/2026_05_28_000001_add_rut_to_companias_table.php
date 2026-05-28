<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companias', function (Blueprint $table) {
            $table->string('rut', 15)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('companias', function (Blueprint $table) {
            $table->dropColumn('rut');
        });
    }
};
