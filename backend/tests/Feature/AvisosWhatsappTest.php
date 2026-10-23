<?php

namespace Tests\Feature;

use App\Jobs\EnviarWhatsapp;
use App\Models\Cliente;
use App\Models\MensajeWhatsapp;
use App\Models\PedidoItem;
use App\Models\User;
use App\Notifications\Aviso;
use App\Services\WhatsApp\WhatsAppCliente;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvisosWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private User $proveedor;

    private array $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->vendedor = User::factory()->create(['name' => 'Ana', 'telefono' => '55550001'])->assignRole('Vendedor');
        $this->proveedor = User::factory()->create(['name' => 'Prov', 'telefono' => '55550002'])->assignRole('Proveedor');

        $cliente = Cliente::factory()->create(['nombre' => 'Cliente Secreto', 'telefono' => '41112222']);
        $this->pedido = $this->actingAs($this->vendedor)->postJson('/api/pedidos', [
            'cliente_id' => $cliente->id,
            'anticipo' => 50,
            'items' => [
                ['codigo' => 'CAD-428', 'descripcion' => 'Cadena', 'cantidad' => 1, 'precio' => 260],
                ['codigo' => 'BUJ', 'descripcion' => 'Bujía', 'cantidad' => 4, 'precio' => 35],
            ],
        ])->json();
    }

    private function ids(): array
    {
        return array_column($this->pedido['items'], 'id');
    }

    private function sesion(string $estado): void
    {
        Http::fake(['*/sessions/*' => Http::response(['status' => $estado, 'qr' => null, 'numero' => null, 'error' => null])]);
    }

    public function test_no_disponible_se_envia_solo_desde_el_whatsapp_del_proveedor_al_vendedor(): void
    {
        Queue::fake();
        $this->sesion('conectado');
        $fecha = today()->addDays(5);

        $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', [
            'ids' => $this->ids(), 'estado' => 'no_disponible', 'fecha_ingreso' => $fecha->toDateString(), 'nota' => 'Contenedor',
        ])->assertOk()->assertJsonPath('whatsapp.enviados', ['Ana'])->assertJsonPath('whatsapp.vinculado', true);

        $m = MensajeWhatsapp::sole();
        $this->assertSame($this->proveedor->id, $m->remitente_id);
        $this->assertSame($this->vendedor->id, $m->destinatario_id);
        $this->assertSame('55550001', $m->telefono);
        $this->assertStringContainsString('CAD-428', $m->mensaje);
        $this->assertStringContainsString($fecha->format('d/m/Y'), $m->mensaje);
        $this->assertStringContainsString('Nota: Contenedor', $m->mensaje);
        $this->assertStringNotContainsString('Cliente Secreto', $m->mensaje);
        $this->assertStringNotContainsString('260', $m->mensaje);
        Queue::assertPushed(EnviarWhatsapp::class, 1);

        $this->assertSame("/pedidos/{$this->pedido['id']}", $this->vendedor->notifications()->first()->data['url']);
    }

    public function test_proveedor_sin_vincular_se_entera_en_la_respuesta(): void
    {
        Queue::fake();
        $this->sesion('desconectado');

        $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(), 'estado' => 'enviado'])
            ->assertOk()->assertJsonPath('whatsapp.vinculado', false);
    }

    public function test_vendedor_sin_telefono_solo_recibe_la_campanita(): void
    {
        Queue::fake();
        $this->vendedor->update(['telefono' => null]);

        $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(), 'estado' => 'enviado'])
            ->assertOk()->assertJsonPath('whatsapp.sin_telefono', ['Ana']);

        $this->assertSame(0, MensajeWhatsapp::count());
        $this->assertSame(1, $this->vendedor->notifications()->count());
    }

    public function test_cerrar_lote_avisa_a_cada_vendedor(): void
    {
        Queue::fake();
        $otro = User::factory()->create(['telefono' => '55550003'])->assignRole('Vendedor');
        $this->actingAs($otro)->postJson('/api/pedidos', [
            'cliente_id' => Cliente::factory()->create()->id,
            'items' => [['codigo' => 'X', 'descripcion' => 'x', 'cantidad' => 1, 'precio' => 1]],
        ])->assertCreated();

        $this->actingAs($this->proveedor)->postJson('/api/proveedor/lotes/cerrar')->assertOk();

        $this->assertEqualsCanonicalizing(['55550001', '55550003'], MensajeWhatsapp::where('tipo', 'lote_cerrado')->pluck('telefono')->all());
        $this->assertSame([$this->proveedor->id], MensajeWhatsapp::distinct()->pluck('remitente_id')->all());
    }

    public function test_fecha_cumplida_el_vendedor_pregunta_al_proveedor_una_sola_vez(): void
    {
        Queue::fake();
        PedidoItem::whereKey($this->ids()[0])->update(['estado' => 'no_disponible', 'fecha_ingreso' => today()]);
        PedidoItem::whereKey($this->ids()[1])->update(['estado' => 'no_disponible', 'fecha_ingreso' => today()->addDay()]);

        $this->artisan('avisos:ingresos')->expectsOutput('Repuestos avisados: 1')->assertSuccessful();

        $m = MensajeWhatsapp::sole();
        $this->assertSame($this->vendedor->id, $m->remitente_id);
        $this->assertSame('55550002', $m->telefono);
        $this->assertStringContainsString('Buen día, ¿ya ingresaron', $m->mensaje);
        $this->assertSame('/proveedor?estado=revisar', $this->proveedor->notifications()->sole()->data['url']);
        $this->assertSame(1, $this->vendedor->notifications()->count());

        $this->artisan('avisos:ingresos')->expectsOutput('Repuestos avisados: 0');

        $this->travel(1)->days();
        $this->artisan('avisos:ingresos')->expectsOutput('Repuestos avisados: 1');

        $this->travel(3)->days();
        $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', [
            'ids' => [$this->ids()[0]], 'estado' => 'no_disponible', 'fecha_ingreso' => today()->toDateString(),
        ])->assertOk();
        $this->artisan('avisos:ingresos')->expectsOutput('Repuestos avisados: 1');
    }

    public function test_preguntar_al_proveedor_desde_el_pedido(): void
    {
        Queue::fake();
        $this->sesion('conectado');
        $id = $this->ids()[0];

        $this->postJson("/api/pedido-items/{$id}/preguntar")->assertStatus(422); // aún está pendiente

        PedidoItem::whereKey($id)->update(['estado' => 'no_disponible', 'fecha_ingreso' => today()->addDays(2)]);
        $this->actingAs($this->vendedor)->postJson("/api/pedido-items/{$id}/preguntar")
            ->assertOk()->assertJsonPath('enviados', ['Prov'])->assertJsonPath('vinculado', true);

        $m = MensajeWhatsapp::sole();
        $this->assertSame([$this->vendedor->id, '55550002', 'pregunta_ingreso'], [$m->remitente_id, $m->telefono, $m->tipo]);
    }

    public function test_el_job_envia_desde_la_sesion_del_remitente(): void
    {
        Http::fake(['*/sessions/*/send' => Http::response(['id' => 'abc', 'para' => '50255550001'])]);
        $m = MensajeWhatsapp::create(['remitente_id' => $this->proveedor->id, 'telefono' => '55550001', 'tipo' => 'x', 'mensaje' => 'hola']);

        (new EnviarWhatsapp($m->id))->handle(app(WhatsAppCliente::class));

        $this->assertSame('enviado', $m->fresh()->estado);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), "/sessions/{$this->proveedor->id}/send") && $r['to'] === '55550001' && ! isset($r['media']));
    }

    public function test_si_el_remitente_no_vinculo_su_whatsapp_falla_y_se_le_avisa(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Tu WhatsApp no está vinculado.', 'permanente' => false], 409)]);
        $m = MensajeWhatsapp::create(['remitente_id' => $this->proveedor->id, 'telefono' => '55550001', 'tipo' => 'x', 'mensaje' => 'hola']);

        (new EnviarWhatsapp($m->id))->handle(app(WhatsAppCliente::class));

        $m->refresh();
        $this->assertSame('fallido', $m->estado);
        $this->assertSame('/whatsapp', $this->proveedor->notifications()->first()->data['url']);
    }

    public function test_reintentar_solo_el_remitente_y_solo_fallidos(): void
    {
        Queue::fake();
        $m = MensajeWhatsapp::create(['remitente_id' => $this->proveedor->id, 'telefono' => '1', 'tipo' => 'x', 'mensaje' => 'x', 'estado' => 'fallido']);

        $this->actingAs($this->vendedor)->postJson("/api/whatsapp/mensajes/{$m->id}/reintentar")->assertForbidden();
        $this->actingAs($this->proveedor)->postJson("/api/whatsapp/mensajes/{$m->id}/reintentar")->assertOk();
        $this->assertSame('pendiente', $m->fresh()->estado);
        $this->postJson("/api/whatsapp/mensajes/{$m->id}/reintentar")->assertStatus(422);
        Queue::assertPushed(EnviarWhatsapp::class, 1);
    }

    public function test_mensaje_al_cliente_con_el_comprobante_pdf_adjunto(): void
    {
        Storage::fake('local');
        Queue::fake();
        $ruta = "/api/pedidos/{$this->pedido['id']}/whatsapp";

        Http::fake(['*/sessions/*' => Http::sequence()
            ->push(['status' => 'desconectado', 'qr' => null, 'numero' => null, 'error' => null])
            ->push(['status' => 'conectado', 'qr' => null, 'numero' => '50255550001', 'error' => null])
            ->push(['id' => 'x'])]);
        $this->actingAs($this->vendedor)->postJson($ruta, ['mensaje' => 'Hola'])->assertStatus(422);
        $this->postJson($ruta, ['mensaje' => 'Buen día, le compartimos su comprobante.', 'adjuntar' => true])->assertStatus(202);

        $m = MensajeWhatsapp::sole();
        $this->assertSame(['41112222', $this->vendedor->id, 'cliente'], [$m->telefono, $m->remitente_id, $m->tipo]);
        $this->assertSame("Pedido {$this->pedido['numero']}.pdf", $m->adjunto_nombre);
        Storage::disk('local')->assertExists($m->adjunto);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($m->adjunto));

        // Y el job lo manda como archivo.
        (new EnviarWhatsapp($m->id))->handle(app(WhatsAppCliente::class));
        Http::assertSent(fn ($r) => isset($r['media']) && $r['media']['filename'] === "Pedido {$this->pedido['numero']}.pdf"
            && str_starts_with(base64_decode($r['media']['data']), '%PDF'));
        $this->assertSame('enviado', $m->fresh()->estado);
    }

    public function test_mi_whatsapp_usa_la_sesion_del_usuario(): void
    {
        Http::fake(['*' => Http::response(['status' => 'qr', 'qr' => 'data:image/png;base64,xx', 'numero' => null, 'error' => null])]);

        $this->actingAs($this->vendedor)->postJson('/api/whatsapp/conectar')->assertOk()->assertJsonPath('status', 'qr');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), "/sessions/{$this->vendedor->id}/start"));

        $this->getJson('/api/whatsapp/sesiones')->assertForbidden();
    }

    public function test_campanita_lista_y_marca_leidas(): void
    {
        $this->vendedor->notify(new Aviso('Hola', 'Texto', '/pedidos'));
        $this->travel(1)->seconds();
        $this->vendedor->notify(new Aviso('Otra', 'Texto', null));

        $this->actingAs($this->vendedor)->getJson('/api/notificaciones')->assertJsonPath('sin_leer', 2)->assertJsonPath('items.0.titulo', 'Otra');
        $this->postJson('/api/notificaciones/leer')->assertOk();
        $this->getJson('/api/notificaciones')->assertJsonPath('sin_leer', 0);
    }
}
