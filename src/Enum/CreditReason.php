<?php

namespace Base\Forge\Enum;

/** Why a client's support-hour balance moved. */
enum CreditReason: string
{
    case PURCHASE = 'purchase';     // an hour pack or a quote was paid
    case TIME = 'time';             // time logged on one of their projects
    case ADJUSTMENT = 'adjustment'; // a gesture, a correction, by hand
    case REFUND = 'refund';         // unused hours paid back (HourRefunds)
}
