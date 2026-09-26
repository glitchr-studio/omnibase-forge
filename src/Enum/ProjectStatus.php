<?php

namespace Base\Forge\Enum;

enum ProjectStatus: string
{
    case OPEN = 'open';
    case PAUSED = 'paused';
    case DONE = 'done';
}
