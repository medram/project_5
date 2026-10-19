<?php

namespace Database\Factories;

use App\Models\BioPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BioPage>
 */
class BioPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'handle' => fake()->unique()->regexify('[a-z0-9]{8}'),
            'display_name' => fake()->name(),
            'bio' => fake()->optional()->sentence(),
            'is_published' => false,
        ];
    }
}
