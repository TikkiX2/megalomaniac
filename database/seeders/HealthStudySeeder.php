<?php

namespace Database\Seeders;

use App\Health\Enums\ResultFlag;
use App\Health\Enums\StudyType;
use App\Models\HealthStudy;
use App\Models\HealthStudyResult;
use App\Models\User;
use Illuminate\Database\Seeder;

class HealthStudySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();
        if (! $user) {
            return;
        }

        $study = HealthStudy::create([
            'user_id' => $user->id,
            'type' => StudyType::Lab,
            'title' => 'Laboratorio TSH/CK',
            'performed_at' => now(),
        ]);

        HealthStudyResult::create([
            'study_id' => $study->id,
            'analyte' => 'TSH',
            'value' => '2.5',
            'unit' => 'mIU/L',
            'flag' => ResultFlag::Normal,
            'sort_order' => 1,
        ]);

        HealthStudyResult::create([
            'study_id' => $study->id,
            'analyte' => 'CK',
            'value' => '150',
            'unit' => 'U/L',
            'flag' => ResultFlag::High,
            'sort_order' => 2,
        ]);
    }
}
