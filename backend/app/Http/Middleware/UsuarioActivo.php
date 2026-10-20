<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Si desactivan a alguien con la sesión abierta, su token deja de servir.
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->activo) {
            $request->user()->currentAccessToken()?->delete();

            return response()->json(['message' => 'Usuario desactivado.'], 401);
        }

        return $next($request);
    }
}
