<?php

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quotation_number' => 'QUO-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'customer_id' => Customer::factory(),
            'status' => QuotationStatus::DRAFT,
            'remarks' => null,
            'expires_at' => now()->addDays(30)->toDateString(),
            'created_by' => User::factory(),
        ];
    }
}
