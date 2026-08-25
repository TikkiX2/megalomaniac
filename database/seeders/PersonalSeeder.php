<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskMilestone;
use App\Models\TaskProperty;
use App\Models\TaskSavedView;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PersonalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first()
            ?? User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $personalClient = Client::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Personal'],
            ['email' => null, 'phone' => null, 'company' => null, 'address' => null, 'tax_id' => null, 'notes' => null, 'is_active' => true]
        );

        $currency = Currency::firstOrCreate(
            ['code' => 'ARS'],
            ['name' => 'Peso Argentino', 'symbol' => '$', 'is_active' => true]
        );

        $now = Carbon::now();

        // ── Project 1: Renovar Departamento ──
        $p1 = Project::create([
            'user_id' => $user->id,
            'client_id' => $personalClient->id,
            'currency_id' => $currency->id,
            'name' => 'Renovar Departamento',
            'description' => [
                'id' => 'desc-p1',
                'value' => [
                    [
                        'id' => 'block-p1-1',
                        'type' => 'paragraph',
                        'children' => [['text' => 'Remodelación completa del departamento: cocina, baño y sala.']],
                    ],
                ],
            ],
            'status' => 'in_progress',
            'type' => 'personal',
            'color' => '#EF4444',
            'icon' => 'home',
            'priority' => 'High',
            'start_date' => $now->copy()->startOfMonth(),
            'end_date' => $now->copy()->addMonth()->endOfMonth(),
            'tags' => ['casa', 'remodelación', 'urgente'],
            'budget' => 500000,
            'is_archived' => false,
        ]);

        $p1Tasks = [
            ['title' => 'Buscar presupuestos de plomería', 'status' => 'Done', 'priority' => 'High', 'due_date' => $now->copy()->subDays(5)],
            ['title' => 'Comprar materiales de cocina', 'status' => 'Done', 'priority' => 'Normal', 'due_date' => $now->copy()->subDays(2)],
            ['title' => 'Desmontar gabinetes viejos', 'status' => 'Done', 'priority' => 'High', 'due_date' => $now->copy()->subDay()],
            ['title' => 'Instalar nuevas tuberías', 'status' => 'In Progress', 'priority' => 'High', 'due_date' => $now->copy()->addDays(3)],
            ['title' => 'Pintar sala y pasillo', 'status' => 'In Progress', 'priority' => 'Normal', 'due_date' => $now->copy()->addDays(5)],
            ['title' => 'Colocar piso cerámico', 'status' => 'Pending', 'priority' => 'High', 'due_date' => $now->copy()->addDays(8)],
            ['title' => 'Instalar muebles de cocina', 'status' => 'Pending', 'priority' => 'Normal', 'due_date' => $now->copy()->addDays(10)],
            ['title' => 'Cambiar luces del techo', 'status' => 'Pending', 'priority' => 'Low', 'due_date' => $now->copy()->addDays(12)],
            ['title' => 'Arreglar bañera', 'status' => 'Pending', 'priority' => 'Normal', 'due_date' => $now->copy()->addDays(14)],
            ['title' => 'Limpieza final', 'status' => 'Pending', 'priority' => 'Low', 'due_date' => $now->copy()->addDays(18)],
        ];

        foreach ($p1Tasks as $i => $t) {
            $task = ProjectTask::create([
                'project_id' => $p1->id,
                'user_id' => $user->id,
                'title' => $t['title'],
                'status' => $t['status'],
                'priority' => $t['priority'],
                'due_date' => $t['due_date'],
                'start_date' => $t['due_date']->copy()->subDays(2),
                'sort_order' => $i,
                'tags' => [],
            ]);

            if ($i < 3) {
                TaskProperty::create([
                    'project_task_id' => $task->id,
                    'key' => 'Proveedor',
                    'type' => 'text',
                    'value_text' => fake()->company(),
                    'sort_order' => 0,
                ]);
            }
        }

        TaskMilestone::create([
            'project_id' => $p1->id,
            'name' => 'Cocina lista',
            'description' => 'Cocina completamente renovada y funcional.',
            'due_date' => $now->copy()->addDays(10),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        TaskMilestone::create([
            'project_id' => $p1->id,
            'name' => 'Remodelación completa',
            'description' => 'Departamento terminado y habitable.',
            'due_date' => $now->copy()->addMonth()->endOfMonth(),
            'status' => 'pending',
            'sort_order' => 1,
        ]);

        // ── Project 2: Entrenamiento 5K ──
        $p2 = Project::create([
            'user_id' => $user->id,
            'client_id' => $personalClient->id,
            'currency_id' => $currency->id,
            'name' => 'Entrenamiento 5K',
            'description' => [
                'id' => 'desc-p2',
                'value' => [
                    [
                        'id' => 'block-p2-1',
                        'type' => 'paragraph',
                        'children' => [['text' => 'Preparación para correr mi primera carrera de 5 kilómetros.']],
                    ],
                ],
            ],
            'status' => 'in_progress',
            'type' => 'personal',
            'color' => '#3B82F6',
            'icon' => 'running',
            'priority' => 'Normal',
            'start_date' => $now->copy()->startOfMonth(),
            'end_date' => $now->copy()->addMonths(2),
            'tags' => ['fitness', 'correr', 'salud'],
            'budget' => 0,
            'is_archived' => false,
        ]);

        $p2Tasks = [
            ['title' => 'Comprar zapatillas de running', 'status' => 'Done', 'priority' => 'High', 'due_date' => $now->copy()->subDays(10)],
            ['title' => 'Planificar ruta de entrenamiento', 'status' => 'Done', 'priority' => 'Normal', 'due_date' => $now->copy()->subDays(8)],
            ['title' => 'Correr 2km sin parar', 'status' => 'Done', 'priority' => 'Normal', 'due_date' => $now->copy()->subDays(3)],
            ['title' => 'Correr 3km sin parar', 'status' => 'In Progress', 'priority' => 'Normal', 'due_date' => $now->copy()->addDays(4)],
            ['title' => 'Entrenar intervalos 1min/1min', 'status' => 'In Progress', 'priority' => 'Normal', 'due_date' => $now->copy()->addDays(7)],
            ['title' => 'Correr 4km sin parar', 'status' => 'Pending', 'priority' => 'High', 'due_date' => $now->copy()->addDays(10)],
            ['title' => 'Día de descanso activo', 'status' => 'Pending', 'priority' => 'Low', 'due_date' => $now->copy()->addDays(11)],
            ['title' => 'Correr 5km completos', 'status' => 'Pending', 'priority' => 'High', 'due_date' => $now->copy()->addDays(14)],
            ['title' => 'Inscribirme a la carrera', 'status' => 'Pending', 'priority' => 'High', 'due_date' => $now->copy()->addDays(16)],
        ];

        foreach ($p2Tasks as $i => $t) {
            $task = ProjectTask::create([
                'project_id' => $p2->id,
                'user_id' => $user->id,
                'title' => $t['title'],
                'status' => $t['status'],
                'priority' => $t['priority'],
                'due_date' => $t['due_date'],
                'start_date' => $t['due_date']->copy()->subDay(),
                'sort_order' => $i,
                'tags' => [],
            ]);

            if ($i === 0) {
                TaskProperty::create([
                    'project_task_id' => $task->id,
                    'key' => 'Marca',
                    'type' => 'text',
                    'value_text' => 'Nike',
                    'sort_order' => 0,
                ]);
                TaskProperty::create([
                    'project_task_id' => $task->id,
                    'key' => 'Presupuesto',
                    'type' => 'number',
                    'value_number' => 25000,
                    'sort_order' => 1,
                ]);
            }
        }

        TaskMilestone::create([
            'project_id' => $p2->id,
            'name' => 'Primera vez 3km',
            'description' => 'Lograr correr 3 kilómetros sin detenerme.',
            'due_date' => $now->copy()->addDays(7),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        // ── Project 3: Aprender Guitarra ──
        $p3 = Project::create([
            'user_id' => $user->id,
            'client_id' => $personalClient->id,
            'currency_id' => $currency->id,
            'name' => 'Aprender Guitarra',
            'description' => [
                'id' => 'desc-p3',
                'value' => [
                    [
                        'id' => 'block-p3-1',
                        'type' => 'paragraph',
                        'children' => [['text' => 'Aprender a tocar guitarra acústica desde cero.']],
                    ],
                ],
            ],
            'status' => 'pending',
            'type' => 'personal',
            'color' => '#10B981',
            'icon' => 'music',
            'priority' => 'Low',
            'start_date' => $now->copy()->addWeek(),
            'end_date' => $now->copy()->addMonths(3),
            'tags' => ['música', 'hobby', 'aprendizaje'],
            'budget' => 50000,
            'is_archived' => false,
        ]);

        $p3Tasks = [
            ['title' => 'Comprar guitarra acústica', 'status' => 'Pending', 'priority' => 'High', 'due_date' => $now->copy()->addWeek()],
            ['title' => 'Aprender acordes básicos (Am, C, G, D)', 'status' => 'Pending', 'priority' => 'High', 'due_date' => $now->copy()->addWeeks(2)],
            ['title' => 'Practicar 15 minutos diarios', 'status' => 'Pending', 'priority' => 'Normal', 'due_date' => $now->copy()->addWeeks(3)],
            ['title' => 'Aprender primera canción completa', 'status' => 'Pending', 'priority' => 'Normal', 'due_date' => $now->copy()->addWeeks(4)],
            ['title' => 'Aprender acordes menores (Em, Am, Dm)', 'status' => 'Pending', 'priority' => 'Normal', 'due_date' => $now->copy()->addWeeks(5)],
            ['title' => 'Practicar transiciones de acordes', 'status' => 'Pending', 'priority' => 'Normal', 'due_date' => $now->copy()->addWeeks(6)],
            ['title' => 'Aprender rasgueo básico', 'status' => 'Pending', 'priority' => 'Normal', 'due_date' => $now->copy()->addWeeks(7)],
            ['title' => 'Tocar 3 canciones de memoria', 'status' => 'Pending', 'priority' => 'Low', 'due_date' => $now->copy()->addWeeks(8)],
            ['title' => 'Grabar video tocando una canción', 'status' => 'Pending', 'priority' => 'Low', 'due_date' => $now->copy()->addWeeks(10)],
        ];

        foreach ($p3Tasks as $i => $t) {
            ProjectTask::create([
                'project_id' => $p3->id,
                'user_id' => $user->id,
                'title' => $t['title'],
                'status' => $t['status'],
                'priority' => $t['priority'],
                'due_date' => $t['due_date'],
                'start_date' => $t['due_date']->copy()->subDays(3),
                'sort_order' => $i,
                'tags' => [],
            ]);
        }

        TaskMilestone::create([
            'project_id' => $p3->id,
            'name' => 'Primera canción',
            'description' => 'Tocar una canción completa de principio a fin.',
            'due_date' => $now->copy()->addWeeks(4),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        // ── Saved View ──
        TaskSavedView::create([
            'user_id' => $user->id,
            'name' => 'Tareas Pendientes',
            'view_type' => 'table',
            'filters' => ['status' => 'Pending'],
            'sort' => ['field' => 'due_date', 'direction' => 'asc'],
            'group_by' => 'project',
            'is_default' => false,
        ]);
    }
}
