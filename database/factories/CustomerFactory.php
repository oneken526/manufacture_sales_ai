<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'address' => fake()->address(),
            'phone' => fake()->numerify('0##-####-####'),
            'email' => fake()->unique()->safeEmail(),
            'credit_limit' => fake()->numberBetween(0, 10_000_000),
        ];
    }
}
