<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurringStatus: string
{
    case Paid = 'paid';
    case Waiting = 'waiting';
    case Missing = 'missing';
    case NotDue = 'not_due';

    public function label(): string
    {
        return match ($this) {
            self::Paid => __('Paid'),
            self::Waiting => __('Waiting'),
            self::Missing => __('Missing'),
            self::NotDue => __('Not due this month'),
        };
    }

    public function isSettled(): bool
    {
        return $this === self::Paid;
    }
}
