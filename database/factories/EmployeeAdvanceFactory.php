<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAdvance>
 */
class EmployeeAdvanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            // The employee's company (the employee is created first).
            'team_id' => fn (array $attributes) => Employee::query()->withoutGlobalScopes()->whereKey($attributes['employee_id'])->value('team_id'),
            'date' => now()->toDateString(),
            'amount' => '30000.00',
            'installment' => '10000.00',
        ];
    }
}
