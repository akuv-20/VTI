<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Los tokens de GLPI (el App-Token en `configuraciones` y el personal en
 * `users.glpi_api_token`) se guardaban cifrados con la APP_KEY. Desarrollo y
 * producción comparten la base pero no la APP_KEY, así que lo cifrado en un
 * entorno era ilegible en el otro. Desde ahora van sin cifrar; esta migración
 * convierte los que ya estaban guardados.
 *
 * Un valor que no se pueda descifrar con la clave del entorno que corre la
 * migración se borra (queda null) y hay que volver a ingresarlo: es mejor eso
 * que dejar en la base un cifrado que nadie va a poder leer.
 */
return new class extends Migration
{
    /** Devuelve el token en claro, o null si estaba cifrado con otra clave. */
    private function enClaro(?string $valor): ?string
    {
        if ($valor === null || $valor === '') return $valor;

        try {
            return Crypt::decryptString($valor);
        } catch (\Throwable) {
            // ¿Era un cifrado de Laravel (base64 de un JSON con iv/value/mac)?
            $payload = json_decode(base64_decode($valor, true) ?: '', true);

            return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac'])
                ? null      // cifrado con otra APP_KEY: irrecuperable
                : $valor;   // ya estaba en claro
        }
    }

    public function up(): void
    {
        $app = DB::table('configuraciones')->where('clave', 'glpiuni_api_app_token')->value('valor');
        if ($app !== null) {
            DB::table('configuraciones')->where('clave', 'glpiuni_api_app_token')
                ->update(['valor' => $this->enClaro($app), 'updated_at' => now()]);
        }

        DB::table('users')->whereNotNull('glpi_api_token')->get(['id', 'glpi_api_token'])
            ->each(fn($u) => DB::table('users')->where('id', $u->id)
                ->update(['glpi_api_token' => $this->enClaro($u->glpi_api_token)]));
    }

    public function down(): void
    {
        // Sin vuelta atrás: volver a cifrar ataría los tokens a la APP_KEY de
        // un solo entorno, que es justo lo que esta migración resuelve.
    }
};
