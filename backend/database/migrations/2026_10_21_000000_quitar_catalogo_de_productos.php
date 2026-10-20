<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ya no hay catálogo de productos: cada línea del pedido guarda su propio
 * código y nombre. Los pedidos existentes no se tocan (ya tenían su copia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
        });
        Schema::dropIfExists('productos');

        // Permisos del módulo que desaparece.
        $ids = DB::table('permissions')->where('name', 'like', 'productos.%')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 200);
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->boolean('descontinuado')->default(false);
            $table->timestamps();
        });
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('pedido_id')->constrained('productos')->nullOnDelete();
        });
    }
};
