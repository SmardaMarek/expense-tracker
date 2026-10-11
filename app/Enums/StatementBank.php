<?php

declare(strict_types=1);

namespace App\Enums;

enum StatementBank: string
{
    case Csob = 'csob';
    case AirBank = 'air_bank';

    public function label(): string
    {
        return match ($this) {
            self::Csob => 'ČSOB',
            self::AirBank => 'Air Bank',
        };
    }
}
