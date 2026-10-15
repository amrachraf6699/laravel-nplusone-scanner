<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Findings;

enum Confidence: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
