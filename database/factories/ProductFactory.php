<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_code' => 'P-'.fake()->unique()->numerify('######'),
            'product_name' => fake()->words(3, true),
            'unit_price' => fake()->numberBetween(100, 100_000),
            'unit' => '個',
            'stock_quantity' => fake()->numberBetween(0, 1000),
            'reserved_quantity' => 0,
            'alert_threshold' => fake()->numberBetween(0, 50),
        ];
    }
}
