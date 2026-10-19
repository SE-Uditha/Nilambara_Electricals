<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'name' => fake()->name(),
            'email' => $email,
            'username' => fake()->unique()->userName(),
            'email_verified_at' => null,
            'password' => static::$password ??= Hash::make('test-password-123'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }
}
