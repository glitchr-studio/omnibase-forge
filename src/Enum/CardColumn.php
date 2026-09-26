<?php

namespace Base\Forge\Enum;

/** The four columns of a project's board, in the order they are shown. */
enum CardColumn: string
{
    case BACKLOG = 'backlog';
    case PROGRESS = 'progress';
    case REVIEW = 'review';
    case DONE = 'done';
}
