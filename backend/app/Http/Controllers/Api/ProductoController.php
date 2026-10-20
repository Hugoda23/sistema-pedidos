<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        $productos = Producto::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w
                ->where('codigo', 'like', '%'.Producto::normalizarCodigo($q).'%')
                ->orWhereRaw('nombre ILIKE ?', ["%{$q}%"])))
            ->when($request->has('descontinuado') && $request->query('descontinuado') !== '',
                fn ($w) => $w->where('descontinuado', $request->boolean('descontinuado')))
            ->orderBy('codigo')
            ->paginate(min((int) $request->query('por_pagina', 15), 100));

        return response()->json($productos);
    }

    /** Autocompletado al escribir el código en un pedido: primero los que empiezan igual. */
    public function buscar(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if ($q === '') {
            return response()->json([]);
        }
        $codigo = Producto::normalizarCodigo($q);

        $productos = Producto::query()
            ->where(fn ($w) => $w->where('codigo', 'like', "%{$codigo}%")->orWhereRaw('nombre ILIKE ?', ["%{$q}%"]))
            ->orderByRaw('CASE WHEN codigo = ? THEN 0 WHEN codigo LIKE ? THEN 1 ELSE 2 END', [$codigo, "{$codigo}%"])
            ->orderBy('codigo')
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'precio_venta', 'descontinuado']);

        return response()->json($productos);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Producto::create($this->validar($request)), 201);
    }

    public function update(Request $request, Producto $producto): JsonResponse
    {
        $producto->update($this->validar($request, $producto));

        return response()->json($producto);
    }

    public function destroy(Producto $producto): JsonResponse
    {
        $producto->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request, ?Producto $producto = null): array
    {
        if ($request->filled('codigo')) {
            $request->merge(['codigo' => Producto::normalizarCodigo($request->input('codigo'))]);
        }

        return $request->validate([
            'codigo' => ['required', 'string', 'max:60', Rule::unique('productos', 'codigo')->ignore($producto)],
            'nombre' => ['required', 'string', 'max:200'],
            'precio_venta' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'descontinuado' => ['boolean'],
        ], ['codigo.unique' => 'Ya existe un producto con ese código.']);
    }
}
