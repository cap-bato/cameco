<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FullPayrollTestDataSeeder extends Seeder
{

public function run(): void
{
    $this->call([
        EmployeePayrollInfoSeeder::class,
        FebruaryFirstHalfPayrollSeeder::class,
        FebruarySecondHalfPayrollSeeder::class,
    ]);
}

}