<?php

namespace App\Services;

use App\Models\SitioFoto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda fotos de sitios y equipos redimensionadas.
 *
 * Las fotos llegan mayormente desde celulares (3-8 MB, 4000 px de ancho), por lo
 * que se reescalan a ANCHO_MAX y se genera una miniatura para los listados. Con
 * ~65 sitios y varias fotos cada uno, guardar los originales llenaría el disco
 * sin aportar nada: en pantalla nunca se ven a más de 1600 px.
 *
 * Usa GD (disponible en el servidor) y respeta la orientación EXIF, porque las
 * fotos tomadas en vertical con el teléfono llegan rotadas.
 */
class GaleriaFotos
{
    private const ANCHO_MAX  = 1600;
    private const THUMB_MAX  = 400;
    private const CALIDAD    = 82;
    private const DIRECTORIO = 'sitios';

    /**
     * Procesa y asocia una foto al modelo (Sitio o SitioEquipo).
     */
    public function guardar(Model $modelo, UploadedFile $archivo, array $datos = []): SitioFoto
    {
        $base   = self::DIRECTORIO . '/' . Str::random(40);
        $path   = $base . '.jpg';
        $thumb  = $base . '_thumb.jpg';

        $img = $this->abrir($archivo);

        $this->escalarYGuardar($img, $path, self::ANCHO_MAX);
        $this->escalarYGuardar($img, $thumb, self::THUMB_MAX);
        imagedestroy($img);

        // La primera foto del modelo queda como portada.
        $esPrimera = $modelo->fotos()->count() === 0;

        return $modelo->fotos()->create([
            'path'       => $path,
            'thumb_path' => $thumb,
            'categoria'  => $datos['categoria'] ?? null,
            'titulo'     => $datos['titulo'] ?? null,
            'portada'    => $datos['portada'] ?? $esPrimera,
            'orden'      => (int) ($modelo->fotos()->max('orden') + 1),
            'subida_por' => auth()->id(),
        ]);
    }

    /** Elimina la foto y sus archivos del disco. */
    public function eliminar(SitioFoto $foto): void
    {
        foreach ([$foto->path, $foto->thumb_path] as $p) {
            if ($p) Storage::disk('public')->delete($p);
        }
        $foto->delete();
    }

    /* ── Internos ────────────────────────────────────────────────────────── */

    /** Abre la imagen como recurso GD, corrigiendo la orientación EXIF. */
    private function abrir(UploadedFile $archivo)
    {
        $ruta = $archivo->getRealPath();
        $tipo = @exif_imagetype($ruta);

        $img = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG  => @imagecreatefrompng($ruta),
            IMAGETYPE_GIF  => @imagecreatefromgif($ruta),
            IMAGETYPE_WEBP => @imagecreatefromwebp($ruta),
            default        => null,
        };

        if (!$img) {
            throw new \RuntimeException('No se pudo leer la imagen. Usa JPG, PNG, WEBP o GIF.');
        }

        // Fondo blanco para PNG/GIF con transparencia (se guardan como JPG).
        if (in_array($tipo, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            $img = $this->aplanar($img);
        }

        return $this->rotarSegunExif($img, $ruta, $tipo);
    }

    private function aplanar($img)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $plano = imagecreatetruecolor($w, $h);
        imagefill($plano, 0, 0, imagecolorallocate($plano, 255, 255, 255));
        imagecopy($plano, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);

        return $plano;
    }

    private function rotarSegunExif($img, string $ruta, $tipo)
    {
        if ($tipo !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($ruta);
        $ori  = $exif['Orientation'] ?? 1;

        $grados = match ($ori) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($grados !== 0) {
            $rotada = imagerotate($img, $grados, 0);
            imagedestroy($img);
            $img = $rotada;
        }

        // Orientaciones espejadas (2, 4, 5, 7).
        if (in_array($ori, [2, 4, 5, 7], true)) {
            imageflip($img, IMG_FLIP_HORIZONTAL);
        }

        return $img;
    }

    /** Escala manteniendo proporción (sin agrandar) y guarda como JPG. */
    private function escalarYGuardar($img, string $path, int $maxLado): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $escala = min(1, $maxLado / max($w, $h));

        $nw = max(1, (int) round($w * $escala));
        $nh = max(1, (int) round($h * $escala));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $tmp = tempnam(sys_get_temp_dir(), 'foto');
        imagejpeg($dst, $tmp, self::CALIDAD);
        imagedestroy($dst);

        Storage::disk('public')->put($path, file_get_contents($tmp));
        @unlink($tmp);
    }
}
