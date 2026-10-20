<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'sin_leer' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(30)->get()->map(fn ($n) => [
                'id' => $n->id,
                'fecha' => $n->created_at->toIso8601String(),
                'leida' => $n->read_at !== null,
                ...$n->data,
            ]),
        ]);
    }

    public function leer(Request $request): JsonResponse
    {
        $q = $request->user()->unreadNotifications();
        if ($request->filled('id')) {
            $q->where('id', $request->input('id'));
        }
        $q->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
