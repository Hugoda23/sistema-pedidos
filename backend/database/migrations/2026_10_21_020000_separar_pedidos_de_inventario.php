<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El pedido de inventario se cierra aparte del de clientes: cada lote es de un tipo
 * (clientes | inventario) con su propia numeración. Los lotes que mezclaban ambos
 * se parten: las líneas de clientes se quedan y el inventario pasa a un lote nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->string('tipo', 20)->default('clientes')->after('id');
            $table->unsignedInteger('numero')->nullable()->after('tipo');
        });
        DB::statement('DROP INDEX IF EXISTS lotes_un_solo_abierto');

        foreach (DB::table('lotes')->whereExists(fn ($q) => $q->from('inventario_items')->whereColumn('inventario_items.lote_id', 'lotes.id'))->get() as $lote) {
            $tieneClientes = DB::table('pedido_items')->where('lote_id', $lote->id)->exists();
            if (! $tieneClientes) {
                DB::table('lotes')->where('id', $lote->id)->update(['tipo' => 'inventario']);

                continue;
            }
            $nuevo = DB::table('lotes')->insertGetId([
                'tipo' => 'inventario', 'estado' => $lote->estado, 'cerrado_por' => $lote->cerrado_por,
                'cerrado_en' => $lote->cerrado_en, 'created_at' => $lote->created_at, 'updated_at' => now(),
            ]);
            DB::table('inventario_items')->where('lote_id', $lote->id)->update(['lote_id' => $nuevo]);
        }

        // Numeración propia por tipo, en orden de apertura.
        DB::statement(<<<'SQL'
            UPDATE lotes SET numero = n.fila
            FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY tipo ORDER BY created_at, id) AS fila FROM lotes) n
            WHERE lotes.id = n.id
        SQL);

        Schema::table('lotes', function (Blueprint $table) {
            $table->unsignedInteger('numero')->nullable(false)->change();
            $table->unique(['tipo', 'numero']);
        });
        // Un solo pedido abierto por tipo.
        DB::statement("CREATE UNIQUE INDEX lotes_un_abierto_por_tipo ON lotes (tipo) WHERE estado = 'abierto'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS lotes_un_abierto_por_tipo');
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropUnique(['tipo', 'numero']);
            $table->dropColumn(['tipo', 'numero']);
        });
    }
};
