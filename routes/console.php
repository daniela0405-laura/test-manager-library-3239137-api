<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

// spec §1 y data-model §9: el admin no lo siembra la base; lo crea el arranque con credenciales de entorno.
Artisan::command('app:ensure-admin', function () {
    $username = mb_strtolower(trim((string) env('ADMIN_USERNAME')));
    $password = (string) env('ADMIN_PASSWORD');

    if ($username === '' || $password === '') {
        $this->error('Faltan ADMIN_USERNAME o ADMIN_PASSWORD en el entorno.');
        return 1;
    }

    if (User::where('username', $username)->exists()) {
        $this->info('El administrador ya existe.');
        return 0;
    }

    User::create([
        'username' => $username,
        'password_hash' => Hash::make($password),
        'role' => 'admin',
    ]);
    $this->info('Administrador creado.'); // nunca se imprime la clave (RNF-3)
    return 0;
})->purpose('Crea el admin inicial desde ADMIN_USERNAME y ADMIN_PASSWORD');