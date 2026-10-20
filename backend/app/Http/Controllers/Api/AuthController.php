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

    /** Cada quien corrige su nombre, teléfono y contraseña. El correo y el rol los cambia el administrador. */
    public function actualizarPerfil(Request $request): JsonResponse
    {
        $request->merge([
            'telefono' => $request->filled('telefono') ? preg_replace('/[\s\-()]/', '', $request->input('telefono')) : null,
        ]);
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'regex:/^\+?\d{8,15}$/'],
            'password_actual' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'telefono.regex' => 'El teléfono debe tener entre 8 y 15 dígitos.',
            'password_actual.current_password' => 'La contraseña actual no es correcta.',
            'password_actual.required_with' => 'Escribe tu contraseña actual para cambiarla.',
        ]);

        $user = $request->user();
        $user->fill(['name' => $datos['name'], 'telefono' => $datos['telefono']]);
        if (! empty($datos['password'])) {
            $user->password = $datos['password'];
            // Cierra las demás sesiones; la actual sigue.
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        }
        $user->save();

        return response()->json(['user' => self::perfil($user)]);
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
