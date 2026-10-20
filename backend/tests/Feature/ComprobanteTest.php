<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprobanteTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private array $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->vendedor = User::factory()->create(['name' => 'Ana'])->assignRole('Vendedor');
        $this->pedido = $this->actingAs($this->vendedor)->postJson('/api/pedidos', [
            'cliente_id' => Cliente::factory()->create(['nombre' => 'Mario Pérez', 'moto' => 'Yamaha FZ'])->id,
            'anticipo' => 100,
            'items' => [
                ['codigo' => 'LOD-1', 'descripcion' => 'Lodera trasera', 'cantidad' => 2, 'precio' => 150],
                ['codigo' => 'BUJ', 'descripcion' => 'Bujía NGK', 'cantidad' => 1, 'precio' => 35],
            ],
        ])->json();
    }

    public function test_vista_previa_html_con_lineas_totales_y_pagos(): void
    {
        $this->postJson("/api/pedido-items/{$this->pedido['items'][1]['id']}/estado", ['estado' => 'anulado']);

        $html = $this->get("/api/pedidos/{$this->pedido['id']}/comprobante")
            ->assertOk()->assertHeader('content-type', 'text/html; charset=utf-8')->getContent();

        foreach ([$this->pedido['numero'], 'Mario Pérez', 'Yamaha FZ', 'Lodera trasera', 'Q 300.00', 'Q 100.00', 'Q 200.00', 'Anticipo', 'no se cobran', 'no es una factura'] as $texto) {
            $this->assertStringContainsString($texto, $html, "Falta «{$texto}»");
        }
    }

    public function test_descarga_pdf(): void
    {
        $res = $this->get("/api/pedidos/{$this->pedido['id']}/comprobante?formato=pdf")->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString("pedido-{$this->pedido['numero']}.pdf", $res->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_solo_quien_ve_el_pedido(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Vendedor'))
            ->get("/api/pedidos/{$this->pedido['id']}/comprobante")->assertNotFound();
        $this->actingAs(User::factory()->create()->assignRole('Proveedor'))
            ->getJson("/api/pedidos/{$this->pedido['id']}/comprobante")->assertForbidden();
    }

    public function test_pedido_anulado_lleva_la_marca(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador');
        $this->actingAs($admin)->postJson("/api/pedidos/{$this->pedido['id']}/anular", ['motivo' => 'Canceló'])->assertOk();

        $html = $this->get("/api/pedidos/{$this->pedido['id']}/comprobante")->getContent();
        $this->assertStringContainsString('ANULADO', $html);
        $this->assertStringContainsString('Canceló', $html);
    }
}
