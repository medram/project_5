<?php

namespace Database\Factories;

use App\Models\TrackingPixel;
use App\Models\User;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrackingPixel> */
class TrackingPixelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'provider' => 'meta_pixel',
            'provider_id' => (string) fake()->numerify('###############'),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (TrackingPixel $pixel): void {
            $pixel->workspace_id ??= app(WorkspaceContext::class)->currentFor($pixel->user)->id;
        });
    }
}
