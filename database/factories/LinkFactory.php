<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workspace_id' => fn (array $attributes): int => User::query()->findOrFail($attributes['user_id'])->current_workspace_id,
            'slug' => fake()->unique()->regexify('[a-z0-9]{7}'),
            'destination_url' => 'https://'.fake()->domainName().'/'.fake()->slug(),
            'title' => fake()->sentence(3),
            'is_active' => true,
        ];
    }
}
