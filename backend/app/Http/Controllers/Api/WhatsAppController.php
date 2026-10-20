<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MensajeWhatsapp;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Avisos;
use App\Services\WhatsApp\WhatsAppCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppCliente $wa) {}

    /** Mi sesión: estado y, si está esperando, el QR. */
    public function estado(Request $request): JsonResponse
    {
        return response()->json($this->wa->estado($request->user()->id));
    }

    public function conectar(Request $request): JsonResponse
    {
        return response()->json($this->wa->iniciar($request->user()->id));
    }

    public function desconectar(Request $request): JsonResponse
    {
        return response()->json($this->wa->cerrar($request->user()->id));
    }

    /** Para el administrador: quién tiene su WhatsApp vinculado. */
    public function sesiones(): JsonResponse
    {
        $estados = $this->wa->todas();

        return response()->json(User::query()->where('activo', true)->with('roles:id,name')->orderBy('name')->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'rol' => $u->roles->first()?->name,
                'telefono' => $u->telefono,
                'estado' => $estados[$u->id]['status'] ?? 'desconectado',
                'numero' => $estados[$u->id]['numero'] ?? null,
            ]));
    }

    public function mensajes(Request $request): JsonResponse
    {
        $user = $request->user();
        $todos = $user->can('usuarios.ver') && $request->boolean('todos');

        $mensajes = MensajeWhatsapp::query()
            ->with(['remitente:id,name', 'destinatario:id,name', 'cliente:id,nombre', 'pedido:id,numero'])
            ->when(! $todos, fn ($q) => $q->where(fn ($q) => $q->where('remitente_id', $user->id)->orWhere('destinatario_id', $user->id)))
            ->when($request->query('estado'), fn ($q, $e) => $q->where('estado', $e))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($mensajes->through(fn (MensajeWhatsapp $m) => [
            'id' => $m->id,
            'fecha' => $m->created_at->toIso8601String(),
            'de' => $m->remitente?->name,
            'para' => $m->destinatario?->name ?? $m->cliente?->nombre,
            'telefono' => $m->telefono,
            'pedido' => $m->pedido?->only(['id', 'numero']),
            'tipo' => $m->tipo,
            'mensaje' => $m->mensaje,
            'estado' => $m->estado,
            'error' => $m->error,
            'puede_reintentar' => $m->estado === 'fallido' && $m->remitente_id === $user->id,
        ]));
    }

    public function reintentar(Request $request, MensajeWhatsapp $mensaje, Avisos $avisos): JsonResponse
    {
        abort_unless($mensaje->remitente_id === $request->user()->id, 403, 'Solo quien lo envía puede reintentarlo.');
        if ($mensaje->estado !== 'fallido') {
            throw ValidationException::withMessages(['mensaje' => 'Este mensaje no falló.']);
        }

        $avisos->reintentar($mensaje);

        return response()->json(['ok' => true]);
    }

    /** El vendedor le escribe al cliente del pedido desde su propio WhatsApp. */
    public function alCliente(Request $request, Pedido $pedido, Avisos $avisos): JsonResponse
    {
        abort_unless($pedido->visiblePara($request->user()) && $request->user()->can('pedidos.gestionar'), 404);
        $datos = $request->validate(['mensaje' => ['required', 'string', 'max:2000']]);

        if (! $pedido->cliente->telefono) {
            throw ValidationException::withMessages(['mensaje' => 'El cliente no tiene teléfono registrado.']);
        }
        if ($this->wa->estado($request->user()->id)['status'] !== 'conectado') {
            throw ValidationException::withMessages(['mensaje' => 'Tu WhatsApp no está vinculado. Vincúlalo en «Mi WhatsApp».']);
        }

        $m = $avisos->alCliente($pedido, $request->user(), $datos['mensaje']);

        return response()->json(['id' => $m->id], 202);
    }
}
