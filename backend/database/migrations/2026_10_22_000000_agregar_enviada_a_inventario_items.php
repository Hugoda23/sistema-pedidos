<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Envíos parciales del inventario: de 10 pedidos el proveedor manda 5 y el resto
 * sigue en la lista (sin existencia, con fecha aprox. de ingreso) hasta que lo mande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_items', function (Blueprint $table) {
            $table->unsignedInteger('enviada')->default(0)->after('cantidad');
        });
        DB::table('inventario_items')->where('estado', 'enviado')->update(['enviada' => DB::raw('cantidad')]);
    }

    public function down(): void
    {
        Schema::table('inventario_items', function (Blueprint $table) {
            $table->dropColumn('enviada');
        });
    }
};
