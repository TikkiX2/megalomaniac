<?php

namespace App\Services\Media;

use App\Models\QueueItem;
use Illuminate\Support\Collection;

class DailyPickService
{
    /**
     * Elección determinística del día: mismo usuario + fecha + tipo + mismos
     * items => siempre el mismo item. Sin aleatoriedad real (no slot-machine).
     *
     * @param  Collection<int, QueueItem>  $items  items ya filtrados por tipo
     */
    public function pick(int $userId, string $date, string $type, Collection $items): ?QueueItem
    {
        $items = $items->values();

        if ($items->isEmpty()) {
            return null;
        }

        $ids = implode(',', $items->pluck('id')->sort()->values()->all());
        $index = hexdec(substr(sha1("{$userId}|{$date}|{$type}|{$ids}"), 0, 8)) % $items->count();

        return $items[$index];
    }
}
