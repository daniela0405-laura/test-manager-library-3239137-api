<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

// HU-01: iniciar sesión. Contrato §3.1.
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', mb_strtolower(trim($data['username'])))->first();

        // CA-01.2: el mismo mensaje si falla el usuario o la clave.
        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            throw new BusinessException('INVALID_CREDENTIALS', 'Credenciales inválidas.', 401);
        }

        // CA-01.1: token con vigencia de 8 horas.
        $token = $user->createToken('api', ['*'], now()->addHours(8));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 28800,
            'role' => $user->role,
        ]);
    }
}