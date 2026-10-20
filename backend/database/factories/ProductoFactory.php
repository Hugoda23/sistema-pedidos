<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('??-#####'),
            'nombre' => fake()->randomElement(['Lodera trasera', 'Pastillas de freno', 'Cadena 428', 'Filtro de aire', 'Espejo retrovisor', 'Bujía']),
            'precio_venta' => fake()->randomFloat(2, 25, 900),
        ];
    }
}
