<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\TaskBoardColumnService;

class ProjectObserver
{
    public function created(Project $project): void
    {
        TaskBoardColumnService::seedFor($project);
    }
}
