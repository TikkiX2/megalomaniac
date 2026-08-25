<?php

namespace Database\Factories;

use App\Models\ProjectTask;
use App\Models\TaskProperty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskProperty>
 */
class TaskPropertyFactory extends Factory
{
    public function definition(): array
    {
        $type = $this->faker->randomElement(['text', 'number', 'date', 'select', 'multi_select', 'checkbox', 'url', 'person']);

        $data = [
            'project_task_id' => ProjectTask::factory(),
            'key' => $this->faker->unique()->word().'_'.$this->faker->randomNumber(3),
            'type' => $type,
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];

        switch ($type) {
            case 'text':
                $data['value_text'] = $this->faker->sentence();
                break;
            case 'number':
                $data['value_number'] = $this->faker->randomFloat(2, 0, 1000);
                break;
            case 'date':
                $data['value_date'] = $this->faker->date();
                break;
            case 'select':
                $data['value_json'] = ['options' => ['Option A', 'Option B', 'Option C'], 'value' => 'Option A'];
                break;
            case 'multi_select':
                $data['value_json'] = ['options' => ['Tag1', 'Tag2', 'Tag3'], 'value' => ['Tag1', 'Tag2']];
                break;
            case 'checkbox':
                $data['value_json'] = ['value' => $this->faker->boolean()];
                break;
            case 'url':
                $data['value_text'] = $this->faker->url();
                break;
            case 'person':
                $data['value_text'] = $this->faker->name();
                $data['value_json'] = ['user_id' => 1, 'name' => $this->faker->name()];
                break;
        }

        return $data;
    }
}
