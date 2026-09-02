<?php

namespace Database\Factories;

use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location' => fake()->randomElement(['campus', 'centro']),
            'type' => Video::TYPE_LINK,
            'filename' => null,
            'url' => 'https://www.youtube.com/watch?v=' . fake()->lexify('???????????'),
        ];
    }
}
