<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Resources\UserProfileResource;
use App\Mcp\Resources\WorkoutHistoryResource;
use App\Mcp\Tools\FinanceReadTool;
use App\Mcp\Tools\FinanceWriteTool;
use App\Mcp\Tools\FreelanceReadTool;
use App\Mcp\Tools\FreelanceWriteTool;
use App\Mcp\Tools\GroceryReadTool;
use App\Mcp\Tools\GroceryWriteTool;
use App\Mcp\Tools\HealthLogTool;
use App\Mcp\Tools\HealthReadTool;
use App\Mcp\Tools\HealthWriteTool;
use App\Mcp\Tools\NutritionReadTool;
use App\Mcp\Tools\NutritionWriteTool;
use App\Mcp\Tools\PeopleLogTool;
use App\Mcp\Tools\PeopleReadTool;
use App\Mcp\Tools\PeopleWriteTool;
use App\Mcp\Tools\PersonalProjectReadTool;
use App\Mcp\Tools\PersonalProjectWriteTool;
use App\Mcp\Tools\PersonalTaskReadTool;
use App\Mcp\Tools\PersonalTaskWriteTool;
use App\Mcp\Tools\SupplementReadTool;
use App\Mcp\Tools\SupplementWriteTool;
use App\Mcp\Tools\WorkoutReadTool;
use App\Mcp\Tools\WorkoutWriteTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Megalomaniac')]
#[Version('1.0.0')]
#[Instructions('Megalomaniac fitness, nutrition, supplements, grocery, finance, freelance, personal task management, personal contacts (people, interactions, key dates and socials), and health records: conditions, medications and intakes, measurements, symptoms and professionals MCP server. Offers read and write tools per module (workouts, nutrition and supplements, grocery inventory, finances including debt payments and withdrawals, freelance clients/quotes/projects, personal projects/tasks, personal contacts, and health records) plus health quick-logging and module moves for projects. All operations are scoped to the authenticated user.')]
class MegalomaniacServer extends Server
{
    protected array $tools = [
        WorkoutReadTool::class,
        WorkoutWriteTool::class,
        FinanceReadTool::class,
        FinanceWriteTool::class,
        NutritionReadTool::class,
        NutritionWriteTool::class,
        GroceryReadTool::class,
        GroceryWriteTool::class,
        FreelanceReadTool::class,
        FreelanceWriteTool::class,
        PersonalProjectReadTool::class,
        PersonalProjectWriteTool::class,
        PersonalTaskReadTool::class,
        PersonalTaskWriteTool::class,
        PeopleReadTool::class,
        PeopleWriteTool::class,
        PeopleLogTool::class,
        SupplementReadTool::class,
        SupplementWriteTool::class,
        HealthReadTool::class,
        HealthWriteTool::class,
        HealthLogTool::class,
    ];

    protected array $resources = [
        UserProfileResource::class,
        WorkoutHistoryResource::class,
    ];
}
