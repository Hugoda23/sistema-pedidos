<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', mb_strtolower($datos['email']))->first();

        if (! $user || ! Hash::check($datos['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
        }

        if (! $user->activo) {
            throw ValidationException::withMessages(['email' => 'Tu usuario está desactivado. Habla con el administrador.']);
        }

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => self::perfil($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => self::perfil($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    /** Lo que el frontend necesita para armar el menú y ocultar botones. */
    public static function perfil(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'telefono' => $user->telefono,
            'roles' => $user->getRoleNames()->values(),
            'permisos' => $user->getAllPermissions()->pluck('name')->sort()->values(),
        ];
    }
}
