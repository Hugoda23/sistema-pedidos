<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'telefono' => fake()->numerify('5#######'),
            'moto' => fake()->randomElement(['Honda XR150', 'Yamaha FZ 2.0', 'Suzuki GN125', 'Bajaj Pulsar 200', 'Italika FT150']),
            'notas' => null,
        ];
    }
}
