<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Los clientes son de la empresa: todos los vendedores los ven y los usan.
class ClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $digitos = preg_replace('/\D/', '', $q);

        $clientes = Cliente::query()
            ->with('creador:id,name')
            ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w
                ->whereRaw('nombre ILIKE ?', ["%{$q}%"])
                ->orWhereRaw('moto ILIKE ?', ["%{$q}%"])
                ->when(strlen($digitos) >= 3, fn ($w) => $w->orWhere('telefono', 'like', "%{$digitos}%"))))
            ->orderBy('nombre')
            ->paginate(min((int) $request->query('por_pagina', 15), 100));

        return response()->json($clientes->through(fn (Cliente $c) => self::datos($c)));
    }

    public function store(Request $request): JsonResponse
    {
        $cliente = Cliente::create($this->validar($request) + ['creado_por' => $request->user()->id]);

        return response()->json(self::datos($cliente->load('creador')), 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json(self::datos($cliente->load('creador')));
    }

    public function update(Request $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($this->validar($request));

        return response()->json(self::datos($cliente->load('creador')));
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        if (\App\Models\Pedido::where('cliente_id', $cliente->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cliente' => 'El cliente tiene pedidos; no se puede eliminar.']);
        }
        $cliente->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request): array
    {
        $request->merge([
            'telefono' => $request->filled('telefono') ? preg_replace('/[\s\-()]/', '', $request->input('telefono')) : null,
        ]);

        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'regex:/^\+?\d{8,15}$/'],
            'moto' => ['nullable', 'string', 'max:150'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ], ['telefono.regex' => 'El teléfono debe tener entre 8 y 15 dígitos.']);
    }

    public static function datos(Cliente $c): array
    {
        return [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'telefono' => $c->telefono,
            'moto' => $c->moto,
            'notas' => $c->notas,
            'creado_por' => $c->creador?->name,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
