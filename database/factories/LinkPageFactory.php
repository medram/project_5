<?php

namespace Database\Factories;

use App\Models\LinkPage;
use App\Models\User;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LinkPage> */
class LinkPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->regexify('[a-z0-9]{10}'),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'is_published' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (LinkPage $page): void {
            $page->workspace_id ??= app(WorkspaceContext::class)->currentFor($page->user)->id;
        })->afterCreating(function (LinkPage $page): void {
            $page->variants()->create([
                'name' => 'Control',
                'weight' => 100,
                'is_control' => true,
                'is_active' => true,
            ]);
        });
    }
}
