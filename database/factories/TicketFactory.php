<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 1;

        return [
            'key' => 'N-' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
            'location' => fake()->randomElement(['campus', 'centro']),
            'service_type' => fake()->randomElement(['Atendimento Normal', 'Atendimento Preferencial']),
            'completed' => false,
            'guiche' => null,
            'attended_by_user_id' => null,
            'called_at' => null,
            'completed_at' => null,
            'completion_type' => null,
        ];
    }
}
