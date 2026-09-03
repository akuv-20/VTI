<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buzones que quedan fuera del análisis de uso.
 *
 * Los buzones compartidos y funcionales no registran inicio de sesión propio
 * —se acceden por delegación desde otra cuenta—, así que el indicador de "nunca
 * activado" los marca a todos como sin uso, y es falso.
 *
 * Graph puede distinguirlos con mailboxSettings/userPurpose, pero eso exige el
 * permiso MailboxSettings.Read, que la aplicación no tiene. Mientras no se
 * otorgue, la lista se mantiene a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buzones_excluidos', function (Blueprint $table) {
            $table->id();
            $table->string('upn')->unique();
            $table->string('motivo');
            $table->boolean('activo')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buzones_excluidos');
    }
};
