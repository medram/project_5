<?php

namespace Database\Factories;

use App\Models\PlatformRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformRole>
 */
class PlatformRoleFactory extends Factory
{
    protected $model = PlatformRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'custom_'.Str::lower(Str::random(10));

        return [
            'code' => $code,
            'name' => Str::headline($code),
        ];
    }
}