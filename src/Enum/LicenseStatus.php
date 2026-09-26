<?php

namespace Base\Forge\Enum;

enum LicenseStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
}
