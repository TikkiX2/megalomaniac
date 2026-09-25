<?php

return [
    'retention_days' => (int) env('FEED_RETENTION_DAYS', 90),
    'max_items' => (int) env('FEED_MAX_ITEMS', 5000),
    'digest_hour' => (int) env('FEED_DIGEST_HOUR', 8),
    'window_days' => (int) env('FEED_WINDOW_DAYS', 7),
    'ingest_limit' => (int) env('FEED_INGEST_LIMIT', 50),
];
