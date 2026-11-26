<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Restaurant';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'contact_number' => fake()->numerify('03#########'),
            'other_contact_number' => fake()->optional()->numerify('03#########'),
            'email' => fake()->unique()->companyEmail(),
            'is_active' => true,
        ];
    }
}
