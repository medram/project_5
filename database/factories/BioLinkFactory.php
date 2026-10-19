<?php

namespace Database\Factories;

use App\Models\BioLink;
use App\Models\BioPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BioLink>
 */
class BioLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bio_page_id' => BioPage::factory(),
            'title' => fake()->words(2, true),
            'destination_url' => 'https://'.fake()->domainName().'/'.fake()->slug(),
            'position' => 0,
            'is_active' => true,
        ];
    }
}
