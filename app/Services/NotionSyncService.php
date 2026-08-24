<?php

namespace App\Services;

use App\Models\ProjectTask;
use FiveamCode\Component\Notion\Notion;
use Illuminate\Support\Facades\Log;

class NotionSyncService
{
    protected $client;

    protected $databaseId;

    public function __construct()
    {
        $token = config('services.notion.token');
        $this->databaseId = config('services.notion.database_id');

        if ($token) {
            $this->client = new Notion($token);
        }
    }

    public function syncToNotion(ProjectTask $task)
    {
        if (! $this->client || ! $this->databaseId) {
            Log::warning('Notion credentials not configured.');

            return;
        }

        try {
            $properties = [
                'Name' => ['title' => [['text' => ['content' => $task->title]]]],
                'Status' => ['select' => ['name' => $task->status]],
                'Priority' => ['select' => ['name' => $task->priority ?? 'Normal']],
                // Add more mappings
            ];

            if ($task->notion_page_id) {
                // Update existing page
                $this->client->pages()->find($task->notion_page_id)->update($properties);
            } else {
                // Create new page
                $page = $this->client->pages()->createInDatabase($this->databaseId, $properties);
                $task->update([
                    'notion_page_id' => $page->getId(),
                    'notion_last_sync' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Notion Sync Error: '.$e->getMessage());
        }
    }

    public function syncFromNotion(ProjectTask $task)
    {
        // Logic to fetch from Notion and update local task
    }
}
