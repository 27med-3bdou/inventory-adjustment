<?php

namespace Database\Factories;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
        'product_id' => $this->faker->unique()->numberBetween(1, 1_000_000),
        'warehouse_id' => $this->faker->numberBetween(1, 10),
        'quantity' => $this->faker->numberBetween(0, 100),
        ];
    }
}
