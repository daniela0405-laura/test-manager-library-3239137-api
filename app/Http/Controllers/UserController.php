<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

// HU-02: el admin da de alta bibliotecarios. Contrato §3.2.
class UserController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
            'role' => ['required', Rule::in(['librarian'])], // CA-02.3: pedir "admin" responde 422
        ]);

        // CA-02.2: minúsculas y recortado; repetido responde 409, no 422.
        $username = mb_strtolower(trim($data['username']));
        if (User::where('username', $username)->exists()) {
            throw new BusinessException('USERNAME_TAKEN', 'El nombre de usuario ya existe.', 409);
        }

        $user = User::create([
            'username' => $username,
            'password_hash' => Hash::make($data['password']), // RNF-3: solo se guarda el hash
            'role' => 'librarian',
        ]);

        // La respuesta no incluye password ni password_hash.
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ], 201);
    }
}