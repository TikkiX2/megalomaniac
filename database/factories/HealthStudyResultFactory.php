<?php

namespace Database\Factories;

use App\Health\Enums\ResultFlag;
use App\Models\HealthStudy;
use App\Models\HealthStudyResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthStudyResult>
 */
class HealthStudyResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'study_id' => HealthStudy::factory(),
            'analyte' => $this->faker->word(),
            'value' => $this->faker->randomFloat(2, 0, 100),
            'unit' => $this->faker->randomElement(['mg/dL', 'mIU/L', 'g/dL']),
            'reference_range' => '10-20',
            'flag' => ResultFlag::Normal,
            'sort_order' => 0,
            'notes' => null,
        ];
    }
}
