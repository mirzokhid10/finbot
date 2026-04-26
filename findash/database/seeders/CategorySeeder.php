<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (!$user) {
            $this->command->info('No user found, skipping CategorySeeder. Please seed users first.');
            return;
        }

        $categories = [
            // Incomes
            ['name' => 'Salary', 'type' => 'income'],
            ['name' => 'Sales', 'type' => 'income'],

            // Expenses
            ['name' => 'Rent', 'type' => 'expense'],
            ['name' => 'Logistics', 'type' => 'expense'],
            ['name' => 'Salaries', 'type' => 'expense'],
            ['name' => 'Utilities', 'type' => 'expense'],
            ['name' => 'Marketing', 'type' => 'expense'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                [
                    'name' => $category['name'],
                    'user_id' => $user->id
                ],
                [
                    'type' => $category['type'],
                    'is_custom' => false,
                ]
            );
        }
    }
}
