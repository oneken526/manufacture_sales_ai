<?php

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'reason' => StockMovementReason::MANUAL_ADJUSTMENT,
            'quantity_change' => fake()->numberBetween(-50, 50),
            'related_order_id' => null,
            'operated_by' => User::factory(),
            'memo' => fake()->optional()->sentence(),
            'created_at' => now(),
        ];
    }
}
