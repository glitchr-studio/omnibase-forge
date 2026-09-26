<?php

namespace Base\Forge\Enum;

enum SoftwareStatus: string
{
    case LIVE = 'live';
    case DEV = 'dev';
    case DRAFT = 'draft';
}
