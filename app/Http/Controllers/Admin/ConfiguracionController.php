<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $loginBg   = Configuracion::get('login_background');
        $appNombre = Configuracion::get('app_nombre') ?: config('app.name');
        $appLogo   = Configuracion::get('app_logo');

        return view('admin.configuracion.index', compact('loginBg', 'appNombre', 'appLogo'));
    }

    public function update(Request $request)
    {
        // ── Nombre de la aplicación ──────────────────────────────────────
        if ($request->has('app_nombre')) {
            $request->validate([
                'app_nombre' => 'required|string|max:60',
            ], [
                'app_nombre.required' => 'El nombre de la aplicación es obligatorio.',
                'app_nombre.max'      => 'El nombre no puede superar los 60 caracteres.',
            ]);
            Configuracion::set('app_nombre', trim($request->input('app_nombre')));
            return back()->with('success', 'Nombre de la aplicación actualizado.');
        }

        // ── Logo de la aplicación ────────────────────────────────────────
        if ($request->input('eliminar_logo')) {
            $anterior = Configuracion::get('app_logo');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }
            Configuracion::set('app_logo', null);
            return back()->with('success', 'Logo eliminado.');
        }

        if ($request->hasFile('app_logo')) {
            if (!$request->file('app_logo')->isValid()) {
                return back()->withErrors(['app_logo' => 'No se recibió un archivo válido.']);
            }
            $request->validate([
                'app_logo' => 'image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            ], [
                'app_logo.image' => 'El archivo debe ser una imagen.',
                'app_logo.mimes' => 'Solo se permiten JPG, PNG, WebP o SVG.',
                'app_logo.max'   => 'El logo no puede superar 2 MB.',
            ]);

            $anterior = Configuracion::get('app_logo');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }

            $path = $request->file('app_logo')->store('config', 'public');
            if (!$path) {
                return back()->withErrors(['app_logo' => 'Error al guardar el archivo.']);
            }
            Configuracion::set('app_logo', $path);
            return back()->with('success', 'Logo actualizado correctamente.');
        }

        // ── Fondo del login ──────────────────────────────────────────────
        if ($request->input('eliminar_fondo')) {
            $anterior = Configuracion::get('login_background');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }
            Configuracion::set('login_background', null);
            return back()->with('success', 'Imagen de fondo eliminada.');
        }

        if ($request->hasFile('login_background')) {
            if (!$request->file('login_background')->isValid()) {
                return back()->withErrors(['login_background' => 'No se recibió un archivo válido. Verifica que no supere el límite de tamaño del servidor.']);
            }
            $request->validate([
                'login_background' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            ], [
                'login_background.image' => 'El archivo debe ser una imagen.',
                'login_background.mimes' => 'Solo se permiten JPG, PNG o WebP.',
                'login_background.max'   => 'La imagen no puede superar los 10 MB.',
            ]);

            $anterior = Configuracion::get('login_background');
            if ($anterior && Storage::disk('public')->exists($anterior)) {
                Storage::disk('public')->delete($anterior);
            }

            $path = $request->file('login_background')->store('config', 'public');
            if (!$path) {
                return back()->withErrors(['login_background' => 'Error al guardar el archivo.']);
            }
            Configuracion::set('login_background', $path);
            return back()->with('success', 'Imagen de fondo actualizada correctamente.');
        }

        return back()->with('success', 'Configuración guardada.');
    }
}
