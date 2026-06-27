<?php

namespace Database\Factories;

use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            'shipped_at' => null,
            'delivery_note_path' => null,
            'returned_at' => null,
            'return_reason' => null,
            'shipped_by' => User::factory(),
        ];
    }
}
