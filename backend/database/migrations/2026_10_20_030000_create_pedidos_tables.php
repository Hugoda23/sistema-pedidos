<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pedido consolidado al proveedor: junta las líneas hasta que el proveedor lo cierra.
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->string('estado', 20)->default('abierto'); // abierto | cerrado
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
        });
        // Solo puede haber un lote abierto a la vez.
        DB::statement("CREATE UNIQUE INDEX lotes_un_solo_abierto ON lotes ((estado)) WHERE estado = 'abierto'");

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->nullable()->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('vendedor_id')->constrained('users')->restrictOnDelete();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('pagado', 12, 2)->default(0);
            $table->text('notas')->nullable();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('pedido_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->string('codigo', 60);      // copia: el pedido no cambia si editan el producto
            $table->string('descripcion', 200);
            $table->unsignedInteger('cantidad');
            $table->decimal('precio', 12, 2);  // precio de venta unitario (el proveedor no lo ve)
            $table->string('estado', 20)->default('pendiente')->index();
            $table->date('fecha_ingreso')->nullable(); // aproximada, cuando no hay existencia
            $table->date('ingreso_notificado')->nullable(); // para no avisar dos veces la misma fecha
            $table->string('nota_proveedor', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('pedido_item_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_item_id')->constrained('pedido_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->date('fecha_ingreso')->nullable();
            $table->string('nota', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('tipo', 20); // anticipo | abono
            $table->string('nota', 300)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('pedido_item_historial');
        Schema::dropIfExists('pedido_items');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('lotes');
    }
};
