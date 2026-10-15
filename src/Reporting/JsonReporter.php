<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Reporting;

use Amrachraf6699\LaravelNPlusOneScanner\Scanner\ScanResult;

final class JsonReporter
{
    public function render(ScanResult $result): string
    {
        return json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    }
}
