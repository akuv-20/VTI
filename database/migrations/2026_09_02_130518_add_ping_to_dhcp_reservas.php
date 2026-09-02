<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dhcp_reservas', function (Blueprint $table) {
            $table->boolean('visto_ping')->default(false)->after('visto_activa');
            $table->timestamp('ultimo_ping_at')->nullable()->after('visto_ping');
        });
    }

    public function down(): void
    {
        Schema::table('dhcp_reservas', function (Blueprint $table) {
            $table->dropColumn(['visto_ping', 'ultimo_ping_at']);
        });
    }
};
