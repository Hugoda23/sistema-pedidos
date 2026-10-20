<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique(); // código del proveedor, en mayúsculas
            $table->string('nombre', 200);
            $table->decimal('precio_venta', 12, 2)->nullable(); // último precio cobrado, solo referencia
            $table->boolean('descontinuado')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
