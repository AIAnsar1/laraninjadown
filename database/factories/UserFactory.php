<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'username' => $this->faker->userName(),
            'date' => $this->faker->date(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => $this->faker->date(),
            'phone' => $this->faker->unique()->phoneNumber(),
            'phone_verified_at' => $this->faker->date(),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'address' => json_encode([
                'country' => $this->faker->country(),
                'state' => $this->faker->city(),
                'city' => $this->faker->city(),
                'street' => $this->faker->streetAddress(),
                'zip' => $this->faker->postcode(),
            ]),
            'password' => Hash::make('password'),
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
