<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wom_plantilla', function (Blueprint $table) {
            $table->decimal('monto', 12, 2)->default(0)->after('id_linea_telefonica');
        });
    }

    public function down(): void
    {
        Schema::table('wom_plantilla', function (Blueprint $table) {
            $table->dropColumn('monto');
        });
    }
};
