<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repuestos que se le pidieron al proveedor por teléfono cuando el pedido ya estaba cerrado:
 * quedan en ese pedido cerrado, marcados, en vez de irse al siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pedido_items', 'inventario_items'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->boolean('tras_cierre')->default(false)->after('lote_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['pedido_items', 'inventario_items'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('tras_cierre');
            });
        }
    }
};
