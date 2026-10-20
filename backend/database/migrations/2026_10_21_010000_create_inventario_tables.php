<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido de inventario: lista compartida de repuestos que faltan en la tienda
 * (no son de un cliente). Va en el mismo pedido consolidado (lote) al proveedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_items', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->index();
            $table->string('descripcion', 200);
            $table->unsignedInteger('cantidad');
            $table->string('estado', 20)->default('pendiente')->index(); // pendiente | no_disponible | enviado | descontinuado
            $table->date('fecha_ingreso')->nullable();
            $table->string('nota_proveedor', 500)->nullable();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('atendido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('atendido_en')->nullable();
            $table->timestamps();
        });

        // Quién agregó, sumó o cambió cada línea.
        Schema::create('inventario_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_item_id')->constrained('inventario_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 30); // agregado | sumado | editado | enviado | no_disponible | descontinuado
            $table->integer('cantidad')->nullable();
            $table->string('nota', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Permisos nuevos, también para los roles que ya existen en la base.
        $permisos = ['inventario.ver', 'inventario.gestionar', 'inventario.atender'];
        $ahora = now();
        foreach ($permisos as $p) {
            DB::table('permissions')->insertOrIgnore(['name' => $p, 'guard_name' => 'web', 'created_at' => $ahora, 'updated_at' => $ahora]);
        }
        $asignar = [
            'Administrador' => $permisos,
            'Vendedor' => ['inventario.ver', 'inventario.gestionar'],
            'Proveedor' => ['inventario.ver', 'inventario.atender'],
            'Supervisor' => ['inventario.ver'],
        ];
        foreach ($asignar as $rol => $lista) {
            $rolId = DB::table('roles')->where('name', $rol)->value('id');
            if (! $rolId) {
                continue;
            }
            foreach (DB::table('permissions')->whereIn('name', $lista)->pluck('id') as $permisoId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permisoId, 'role_id' => $rolId]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_historial');
        Schema::dropIfExists('inventario_items');
        DB::table('permissions')->where('name', 'like', 'inventario.%')->delete();
    }
};
