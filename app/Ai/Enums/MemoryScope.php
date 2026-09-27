<?php

namespace App\Ai\Enums;

enum MemoryScope: string
{
    case Global = 'global';
    case Thread = 'thread';
}
