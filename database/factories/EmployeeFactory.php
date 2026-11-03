<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\PaymentMethod;
use App\Enums\SalaryTaxMode;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'code' => fake()->unique()->numerify('EMP-####'),
            'name' => fake()->name(),
            'designation' => 'Accounts officer',
            'department' => 'Accounts',
            'employment_type' => EmploymentType::Permanent,
            'joining_date' => '2025-01-01',
            'basic_salary' => '100000.00',
            'payment_method' => PaymentMethod::BankTransfer,
            'bank_name' => 'Meezan Bank',
            'bank_account_number' => 'PK36MEZN0001234567890123',
            'tax_mode' => SalaryTaxMode::Auto,
            'eobi' => false,
        ];
    }
}
