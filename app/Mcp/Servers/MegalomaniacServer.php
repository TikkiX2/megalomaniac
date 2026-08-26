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
use App\Mcp\Tools\NutritionReadTool;
use App\Mcp\Tools\NutritionWriteTool;
use App\Mcp\Tools\PersonalProjectReadTool;
use App\Mcp\Tools\PersonalProjectWriteTool;
use App\Mcp\Tools\PersonalTaskReadTool;
use App\Mcp\Tools\PersonalTaskWriteTool;
use App\Mcp\Tools\WorkoutReadTool;
use App\Mcp\Tools\WorkoutWriteTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Megalomaniac')]
#[Version('1.0.0')]
#[Instructions('Megalomaniac fitness, finance, freelance, and personal task management MCP server. Provides read and write access to workouts, nutrition, grocery inventory, finances, freelance projects, and personal projects/tasks. All operations are scoped to the authenticated user.')]
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
    ];

    protected array $resources = [
        UserProfileResource::class,
        WorkoutHistoryResource::class,
    ];
}
