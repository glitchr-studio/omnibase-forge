<?php

namespace Base\Forge\Enum;

/**
 * requested  a visitor asked for one (the quote form); nothing priced yet
 * draft      the studio is writing the lines
 * sent       the client can read and accept it
 * accepted   the client said yes: an order waits for payment
 * paid       the order was paid; its hours are credited
 * declined   the client said no, or it lapsed
 */
enum QuoteStatus: string
{
    case REQUESTED = 'requested';
    case DRAFT = 'draft';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case PAID = 'paid';
    case DECLINED = 'declined';

    public function isOpenToClient(): bool
    {
        return self::SENT === $this;
    }
}
