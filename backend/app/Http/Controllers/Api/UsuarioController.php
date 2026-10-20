<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        $usuarios = User::query()
            ->with('roles:id,name')
            ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w
                ->whereRaw('name ILIKE ?', ["%{$q}%"])
                ->orWhereRaw('email ILIKE ?', ["%{$q}%"])
                ->orWhere('telefono', 'like', "%{$q}%")))
            ->when($request->query('rol'), fn ($w, $rol) => $w->role($rol))
            ->when($request->has('activo') && $request->query('activo') !== '',
                fn ($w) => $w->where('activo', $request->boolean('activo')))
            ->orderBy('name')
            ->paginate(min((int) $request->query('por_pagina', 15), 100));

        return response()->json($usuarios->through(fn (User $u) => self::datos($u)));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $user = User::create([...$datos, 'email' => mb_strtolower($datos['email'])]);
        $user->syncRoles([$datos['rol']]);

        return response()->json(self::datos($user->load('roles')), 201);
    }

    public function show(User $usuario): JsonResponse
    {
        return response()->json(self::datos($usuario->load('roles')));
    }

    public function update(Request $request, User $usuario): JsonResponse
    {
        $datos = $this->validar($request, $usuario);

        if ($usuario->is($request->user()) && ! $datos['activo']) {
            throw ValidationException::withMessages(['activo' => 'No puedes desactivarte a ti mismo.']);
        }

        $quitaAdmin = $usuario->hasRole('Administrador') && ($datos['rol'] !== 'Administrador' || ! $datos['activo']);
        if ($quitaAdmin && $this->adminsActivos() <= 1) {
            throw ValidationException::withMessages(['rol' => 'Debe quedar al menos un administrador activo.']);
        }

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        $usuario->update([...$datos, 'email' => mb_strtolower($datos['email'])]);
        $usuario->syncRoles([$datos['rol']]);

        // Al desactivar o cambiar la contraseña se cierran sus sesiones abiertas.
        if (! $usuario->activo || isset($datos['password'])) {
            $usuario->tokens()->delete();
        }

        return response()->json(self::datos($usuario->load('roles')));
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        $request->merge([
            'telefono' => $request->filled('telefono') ? preg_replace('/[\s\-()]/', '', $request->input('telefono')) : null,
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuario)],
            'telefono' => ['nullable', 'regex:/^\+?\d{8,15}$/'],
            'rol' => ['required', Rule::exists(Role::class, 'name')],
            'activo' => ['boolean'],
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
        ], [
            'telefono.regex' => 'El teléfono debe tener entre 8 y 15 dígitos.',
        ]) + ['activo' => true];
    }

    private function adminsActivos(): int
    {
        return User::role('Administrador')->where('activo', true)->count();
    }

    public static function datos(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'telefono' => $u->telefono,
            'activo' => $u->activo,
            'rol' => $u->roles->first()?->name,
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }
}
