<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Tenant;
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
        return [
            'tenant_id' => Tenant::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'mobile_number' => fake()->numerify('03#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'profile_picture' => null,
            'role' => UserRole::Customer,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function platformSuperAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'role' => UserRole::PlatformSuperAdmin,
        ]);
    }

    public function tenantOwner(?Tenant $tenant = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant?->id ?? Tenant::factory(),
            'role' => UserRole::TenantOwner,
        ]);
    }
}
