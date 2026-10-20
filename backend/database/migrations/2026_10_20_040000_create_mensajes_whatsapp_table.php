<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitácora de todo lo que el sistema intenta mandar por WhatsApp.
        Schema::create('mensajes_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remitente_id')->constrained('users')->cascadeOnDelete(); // desde el WhatsApp de quién
            $table->foreignId('destinatario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->string('telefono', 20);
            $table->string('tipo', 40); // estado_proveedor, lote_cerrado, ingreso_vencido, cliente
            $table->text('mensaje');
            $table->string('estado', 20)->default('pendiente')->index(); // pendiente | enviado | fallido
            $table->string('error', 500)->nullable();
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->timestamp('enviado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_whatsapp');
    }
};
