<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'customer_name' => strtoupper(fake()->company()),
            'source_type' => 'pdf',
            'status' => 'waiting',
        ];
    }
}
