<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PedidoItem;
use App\Models\User;
use App\Notifications\Aviso;
use App\Support\WhatsApp;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvisosTest extends TestCase
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

    private function texto(string $url): string
    {
        return rawurldecode(explode('?text=', $url)[1]);
    }

    public function test_numero_para_whatsapp(): void
    {
        $this->assertSame('50255551234', WhatsApp::numero('5555-1234'));
        $this->assertSame('50255551234', WhatsApp::numero('+502 5555 1234'));
        $this->assertSame('13055550142', WhatsApp::numero('+1 305 555 0142'));
        $this->assertNull(WhatsApp::numero('1234'));
        $this->assertNull(WhatsApp::enlace(null, 'hola'));
    }

    public function test_no_disponible_devuelve_el_enlace_para_avisar_al_vendedor(): void
    {
        $fecha = today()->addDays(5);

        $res = $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', [
            'ids' => $this->ids(), 'estado' => 'no_disponible', 'fecha_ingreso' => $fecha->toDateString(), 'nota' => 'Contenedor',
        ])->assertOk()->assertJsonCount(1, 'whatsapp')->assertJsonPath('whatsapp.0.para', 'Ana');

        $url = $res->json('whatsapp.0.url');
        $this->assertStringStartsWith('https://wa.me/50255550001?text=', $url);
        $texto = $this->texto($url);
        $this->assertStringContainsString('CAD-428', $texto);
        $this->assertStringContainsString($fecha->format('d/m/Y'), $texto);
        $this->assertStringContainsString('Nota: Contenedor', $texto);
        $this->assertStringNotContainsString('Cliente Secreto', $texto);
        $this->assertStringNotContainsString('260', $texto);

        $this->assertSame("/pedidos/{$this->pedido['id']}", $this->vendedor->notifications()->first()->data['url']);
    }

    public function test_vendedor_sin_telefono_no_tiene_enlace_pero_si_campanita(): void
    {
        $this->vendedor->update(['telefono' => null]);

        $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', ['ids' => $this->ids(), 'estado' => 'enviado'])
            ->assertOk()->assertJsonPath('whatsapp.0.url', null);
        $this->assertSame(1, $this->vendedor->notifications()->count());
    }

    public function test_cerrar_lote_da_un_enlace_por_vendedor(): void
    {
        $otro = User::factory()->create(['name' => 'Beto', 'telefono' => '55550003'])->assignRole('Vendedor');
        $this->actingAs($otro)->postJson('/api/pedidos', [
            'cliente_id' => Cliente::factory()->create()->id,
            'items' => [['codigo' => 'X', 'descripcion' => 'x', 'cantidad' => 1, 'precio' => 1]],
        ])->assertCreated();

        $res = $this->actingAs($this->proveedor)->postJson('/api/proveedor/lotes/cerrar')->assertOk()->assertJsonCount(2, 'whatsapp');
        $this->assertEqualsCanonicalizing(['Ana', 'Beto'], array_column($res->json('whatsapp'), 'para'));
        $this->assertSame(1, $otro->notifications()->count());
    }

    public function test_fecha_cumplida_avisa_una_vez_con_boton_para_preguntar_al_proveedor(): void
    {
        PedidoItem::whereKey($this->ids()[0])->update(['estado' => 'no_disponible', 'fecha_ingreso' => today()]);
        PedidoItem::whereKey($this->ids()[1])->update(['estado' => 'no_disponible', 'fecha_ingreso' => today()->addDay()]);

        $this->artisan('avisos:ingresos')->expectsOutput('Líneas avisadas: 1')->assertSuccessful();

        $aviso = $this->vendedor->notifications()->sole()->data;
        $this->assertSame('Preguntar a Prov', $aviso['whatsapp'][0]['texto']);
        $this->assertStringStartsWith('https://wa.me/50255550002?text=', $aviso['whatsapp'][0]['url']);
        $this->assertStringContainsString('CAD-428', $this->texto($aviso['whatsapp'][0]['url']));
        $this->assertSame('/proveedor?estado=revisar', $this->proveedor->notifications()->sole()->data['url']);

        $this->artisan('avisos:ingresos')->expectsOutput('Líneas avisadas: 0');

        $this->travel(1)->days();
        $this->artisan('avisos:ingresos')->expectsOutput('Líneas avisadas: 1');

        // Fecha nueva: cuando llegue, se vuelve a avisar.
        $this->travel(3)->days();
        $this->actingAs($this->proveedor)->postJson('/api/proveedor/items/estado', [
            'ids' => [$this->ids()[0]], 'estado' => 'no_disponible', 'fecha_ingreso' => today()->toDateString(),
        ])->assertOk();
        $this->artisan('avisos:ingresos')->expectsOutput('Líneas avisadas: 1');
    }

    public function test_detalle_trae_enlace_para_preguntar_por_lineas_sin_existencia(): void
    {
        PedidoItem::whereKey($this->ids()[0])->update(['estado' => 'no_disponible', 'fecha_ingreso' => today()->addDays(2)]);

        $res = $this->actingAs($this->vendedor)->getJson("/api/pedidos/{$this->pedido['id']}")->assertOk();
        $this->assertStringStartsWith('https://wa.me/50255550002?text=', $res->json('items.0.preguntar_url'));
        $this->assertNull($res->json('items.1.preguntar_url'));
    }

    public function test_campanita_lista_y_marca_leidas(): void
    {
        $this->vendedor->notify(new Aviso('Hola', 'Texto', '/pedidos'));
        $this->travel(1)->seconds();
        $this->vendedor->notify(new Aviso('Otra', 'Texto', null, [['texto' => 'WA', 'url' => 'https://wa.me/1']]));

        $this->actingAs($this->vendedor)->getJson('/api/notificaciones')
            ->assertJsonPath('sin_leer', 2)->assertJsonPath('items.0.titulo', 'Otra')->assertJsonPath('items.0.whatsapp.0.texto', 'WA');
        $this->postJson('/api/notificaciones/leer')->assertOk();
        $this->getJson('/api/notificaciones')->assertJsonPath('sin_leer', 0);
    }
}
