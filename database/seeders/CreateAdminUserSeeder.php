<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User; // Asegúrate de importar el modelo User

class CreateAdminUserSeeder extends Seeder
{
    public function run()
    {
        // Verificar si el usuario admin ya existe
        $adminEmail = 'fhenriquez@verfrut.cl';
        if (!User::where('email', $adminEmail)->exists()) {
            User::create([
                'name' => 'Felipe Henriquez',
                'email' => $adminEmail,
                'password' => Hash::make('Fhrqz.2025'), // Encriptar la contraseña
            ]);
        }
    }
}