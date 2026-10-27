<?php

namespace Database\Factories;

use App\Models\LinkOverlay;
use App\Models\User;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LinkOverlay> */
class LinkOverlayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'heading' => fake()->sentence(4),
            'body' => fake()->optional()->sentence(),
            'cta_label' => 'Continue',
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (LinkOverlay $overlay): void {
            $overlay->workspace_id ??= app(WorkspaceContext::class)->currentFor($overlay->user)->id;
        });
    }
}
