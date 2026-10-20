<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ExportarTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesYPermisosSeeder::class);
        $this->vendedor = User::factory()->create(['name' => 'Ana'])->assignRole('Vendedor');

        foreach ([['Cliente Secreto', 'LOD-1', 2, 150], ['Otro Cliente', 'LOD-1', 3, 140]] as [$nombre, $codigo, $cant, $precio]) {
            $this->actingAs($this->vendedor)->postJson('/api/pedidos', [
                'cliente_id' => Cliente::factory()->create(['nombre' => $nombre])->id,
                'anticipo' => 50,
                'items' => [['codigo' => $codigo, 'descripcion' => 'Lodera', 'cantidad' => $cant, 'precio' => $precio]],
            ])->assertCreated();
        }
    }

    private function libro(TestResponse $res): Spreadsheet
    {
        $res->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $res->streamedContent());

        return IOFactory::load($archivo);
    }

    public function test_excel_del_proveedor_sin_clientes_ni_precios(): void
    {
        $prov = User::factory()->create()->assignRole('Proveedor');
        $libro = $this->libro($this->actingAs($prov)->get('/api/proveedor/exportar'));

        $detalle = $libro->getSheetByName('Repuestos')->toArray();
        $this->assertSame('Código', $detalle[0][0]);
        $this->assertCount(3, $detalle); // encabezado + 2 líneas
        $this->assertSame('Ana', $detalle[1][3]);

        $todo = json_encode([$detalle, $libro->getSheetByName('Sumado por código')->toArray()]);
        $this->assertStringNotContainsString('Cliente Secreto', $todo);
        $this->assertStringNotContainsString('150', $todo);

        $suma = $libro->getSheetByName('Sumado por código')->toArray();
        $this->assertEquals(['LOD-1', 'Lodera', 5, 2], $suma[1]);
    }

    public function test_excel_de_pedidos_respeta_filtros_y_visibilidad(): void
    {
        $libro = $this->libro($this->actingAs($this->vendedor)->get('/api/pedidos/exportar?q=secreto'));

        $pedidos = $libro->getSheetByName('Pedidos')->toArray(null, true, false);
        $this->assertCount(2, $pedidos);
        $this->assertSame('Cliente Secreto', $pedidos[1][2]);
        $this->assertEquals(300, $pedidos[1][6]);
        $this->assertEquals(250, $pedidos[1][8]);
        $this->assertCount(2, $libro->getSheetByName('Líneas')->toArray());

        // Otro vendedor no exporta pedidos ajenos.
        $otro = User::factory()->create()->assignRole('Vendedor');
        $this->assertCount(1, $this->libro($this->actingAs($otro)->get('/api/pedidos/exportar'))->getSheetByName('Pedidos')->toArray());
    }

    public function test_vendedor_no_exporta_lo_del_proveedor(): void
    {
        $this->actingAs($this->vendedor)->getJson('/api/proveedor/exportar')->assertForbidden();
    }
}
