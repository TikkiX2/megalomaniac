<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<int, array{key: string, label: string, color: string, is_done: bool}> */
    private array $personalColumns = [
        ['key' => 'Pending', 'label' => 'Pendiente', 'color' => 'amber', 'is_done' => false],
        ['key' => 'In Progress', 'label' => 'En Progreso', 'color' => 'primary', 'is_done' => false],
        ['key' => 'Done', 'label' => 'Hecho', 'color' => 'emerald', 'is_done' => true],
    ];

    /** @var array<int, array{key: string, label: string, color: string, is_done: bool}> */
    private array $freelanceColumns = [
        ['key' => 'To Do', 'label' => 'Pendiente', 'color' => 'amber', 'is_done' => false],
        ['key' => 'In Progress', 'label' => 'En Progreso', 'color' => 'primary', 'is_done' => false],
        ['key' => 'Done', 'label' => 'Completada', 'color' => 'emerald', 'is_done' => true],
    ];

    public function up(): void
    {
        $now = now();

        $projects = DB::table('projects')->select('id', 'user_id', 'type')->get();

        foreach ($projects as $project) {
            $columns = $project->type === 'personal' ? $this->personalColumns : $this->freelanceColumns;

            $this->insertColumns($project->user_id, $project->id, $columns, $now);
        }

        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            if (DB::table('task_board_columns')->where('user_id', $userId)->whereNull('project_id')->exists()) {
                continue;
            }

            $this->insertColumns($userId, null, $this->personalColumns, $now);
        }

        $statusMap = $this->statusMap();

        DB::table('project_tasks')
            ->select('id', 'project_id', 'user_id', 'status')
            ->orderBy('id')
            ->chunk(200, function ($tasks) use ($statusMap, $projects) {
                foreach ($tasks as $task) {
                    $project = $task->project_id ? $projects->firstWhere('id', $task->project_id) : null;

                    $columns = $project && $project->type !== 'personal'
                        ? $this->freelanceColumns
                        : $this->personalColumns;

                    $index = $statusMap[$task->status] ?? 0;

                    DB::table('project_tasks')->where('id', $task->id)->update([
                        'status' => $columns[$index]['key'],
                        'is_done' => $columns[$index]['is_done'],
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('task_board_columns')->delete();
        DB::table('project_tasks')->update(['is_done' => false]);
    }

    /**
     * @param  array<int, array{key: string, label: string, color: string, is_done: bool}>  $columns
     */
    private function insertColumns(int $userId, ?int $projectId, array $columns, mixed $now): void
    {
        foreach ($columns as $index => $column) {
            DB::table('task_board_columns')->insert([
                'user_id' => $userId,
                'project_id' => $projectId,
                'key' => $column['key'],
                'label' => $column['label'],
                'color' => $column['color'],
                'sort_order' => $index,
                'is_done' => $column['is_done'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function statusMap(): array
    {
        return [
            'Pending' => 0,
            'To Do' => 0,
            'Todo' => 0,
            'pendiente' => 0,
            'Pendiente' => 0,
            'In Progress' => 1,
            'in_progress' => 1,
            'En Progreso' => 1,
            'en_progreso' => 1,
            'Review' => 1,
            'Done' => 2,
            'done' => 2,
            'Completed' => 2,
            'completed' => 2,
            'Completada' => 2,
            'completada' => 2,
        ];
    }
};
