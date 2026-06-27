<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'SO-'.fake()->unique()->numerify('########'),
            'quotation_id' => null,
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now(),
            'cancelled_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
