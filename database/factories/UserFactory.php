<?php

namespace Database\Factories;

use App\Constants\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'usertype' => UserType::CLIENT,
            'active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['usertype' => UserType::ADMIN]);
    }

    public function client(): static
    {
        return $this->state(fn () => ['usertype' => UserType::CLIENT]);
    }
}
