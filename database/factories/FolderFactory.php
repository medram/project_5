<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => fn (array $attributes): int => User::query()->findOrFail($attributes['user_id'])->current_workspace_id,
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
        ];
    }
}
