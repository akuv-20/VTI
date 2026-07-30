<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class SitioFoto extends Model
{
    protected $table = 'sitio_fotos';

    protected $guarded = ['id'];

    protected $casts = [
        'portada' => 'boolean',
    ];

    public const CATEGORIAS = [
        'entorno'   => 'Entorno / acceso',
        'rack'      => 'Rack / gabinete',
        'antena'    => 'Antena / torre',
        'tablero'   => 'Tablero eléctrico',
        'equipo'    => 'Equipo / etiqueta',
        'diagrama'  => 'Diagrama / plano',
        'otro'      => 'Otro',
    ];

    public function fotable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path && Storage::disk('public')->exists($this->path)
            ? Storage::url($this->path)
            : null;
    }

    public function getThumbUrlAttribute(): ?string
    {
        if ($this->thumb_path && Storage::disk('public')->exists($this->thumb_path)) {
            return Storage::url($this->thumb_path);
        }

        return $this->url; // sin miniatura: cae a la imagen completa
    }

    public function getCategoriaLabelAttribute(): ?string
    {
        return $this->categoria ? (self::CATEGORIAS[$this->categoria] ?? $this->categoria) : null;
    }
}
