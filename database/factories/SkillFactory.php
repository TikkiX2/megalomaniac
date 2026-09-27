<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    protected $model = Skill::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'id' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'key' => Str::slug($name),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'instructions' => fake()->paragraphs(3, true),
            'enabled' => true,
            'source' => 'manual',
            'metadata' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }
}
