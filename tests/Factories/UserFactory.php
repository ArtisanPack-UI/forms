<?php

declare( strict_types=1 );

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Tests\Models\User;

/**
 * User Factory
 *
 * Factory for creating test User instances.
 *
 * @extends Factory<User>
 *
 * @since 1.0.0
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'     => fake()->name(),
            'email'    => fake()->unique()->safeEmail(),
            'password' => Hash::make( 'password' ),
            'is_admin' => false,
        ];
    }
}
