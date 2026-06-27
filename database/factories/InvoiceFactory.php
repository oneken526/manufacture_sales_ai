<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-' . fake()->year() . '-' . fake()->unique()->numerify('####'),
            'sales_order_id' => SalesOrder::factory(),
            'total_amount' => fake()->numberBetween(10000, 1000000),
            'payment_status' => PaymentStatus::UNPAID,
            'invoice_pdf_path' => null,
            'issued_at' => now(),
            'issued_by' => User::factory(),
        ];
    }
}
