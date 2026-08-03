<?php

namespace App\Support;

/**
 * Regiones y comunas de Chile.
 *
 * Alimenta los selectores de la ficha de sitio y normaliza lo que llega por
 * importación (donde la gente escribe «ohiggins», «O'HIGGINS» o «Sexta Región»).
 */
class DpaChile
{
    private static ?array $datos = null;

    /** ['Región' => ['Comuna', …]] */
    public static function todo(): array
    {
        return self::$datos ??= require resource_path('data/dpa_chile.php');
    }

    /** @return string[] */
    public static function regiones(): array
    {
        return array_keys(self::todo());
    }

    /** @return string[] */
    public static function comunas(?string $region): array
    {
        $region = self::normalizarRegion($region);

        return $region ? self::todo()[$region] : [];
    }

    /** Región oficial a la que pertenece una comuna, o null si no la reconoce. */
    public static function regionDeComuna(?string $comuna): ?string
    {
        if (!$comuna) return null;

        $buscado = self::clave($comuna);

        foreach (self::todo() as $region => $comunas) {
            foreach ($comunas as $c) {
                if (self::clave($c) === $buscado) return $region;
            }
        }

        return null;
    }

    /** Devuelve el nombre oficial de la región, tolerando variantes de escritura. */
    public static function normalizarRegion(?string $valor): ?string
    {
        if (!$valor) return null;

        $buscado = self::clave($valor);

        foreach (self::regiones() as $region) {
            if (self::clave($region) === $buscado) return $region;
        }

        // Formas cortas de uso diario: «O'Higgins», «Metropolitana», «Aysén»…
        $corto = self::claveCorta($valor);
        if ($corto === '') return null;

        foreach (self::regiones() as $region) {
            $clave = self::claveCorta($region);
            if ($clave !== '' && (str_contains($clave, $corto) || str_contains($corto, $clave))) {
                return $region;
            }
        }

        return null;
    }

    /** Devuelve el nombre oficial de la comuna, tolerando variantes de escritura. */
    public static function normalizarComuna(?string $valor): ?string
    {
        if (!$valor) return null;

        $buscado = self::clave($valor);

        foreach (self::todo() as $comunas) {
            foreach ($comunas as $c) {
                if (self::clave($c) === $buscado) return $c;
            }
        }

        return null;
    }

    /** Minúsculas sin tildes, apóstrofes ni espacios, para comparar. */
    private static function clave(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $t);
    }

    /**
     * Clave sin las palabras de relleno con que se nombran las regiones
     * («Región del Maule» → «maule»). Solo para regiones: en comunas borraría
     * partes significativas del nombre («La Cruz», «Los Andes»).
     */
    private static function claveCorta(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $t = preg_replace('/\b(region|de|del|la|las|los|el|y|general|carlos|ibanez|campo|bernardo|libertador)\b/', ' ', $t);

        return preg_replace('/[^a-z0-9]/', '', $t);
    }
}
