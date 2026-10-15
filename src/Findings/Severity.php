<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Findings;

enum Severity: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    public function rank(): int
    {
        return match ($this) {
            self::LOW => 1, self::MEDIUM => 2, self::HIGH => 3
        };
    }
}
