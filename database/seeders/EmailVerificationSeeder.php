<?php

namespace Database\Seeders;

use App\Models\EmailVerification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmailVerificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EmailVerification::factory()->count(100)->create();
    }
}
