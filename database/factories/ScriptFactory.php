<?php

namespace Database\Factories;

use App\Models\Script;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Script>
 */
class ScriptFactory extends Factory
{
    protected $model = Script::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'topic'             => $this->faker->sentence(5),
            'source_text'       => $this->faker->paragraph(3),
            'style_text'        => null,
            'generated_content' => null,
            'status'            => 'pending',
            'error_message'     => null,
        ];
    }

    /**
     * Estado com geração concluída.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'generated_content' => $this->faker->paragraph(5),
            'status'            => 'done',
        ]);
    }

    /**
     * Estado com falha na geração.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'failed',
            'error_message' => 'Erro ao gerar roteiro.',
        ]);
    }

    /**
     * Estado em processamento.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }
}
