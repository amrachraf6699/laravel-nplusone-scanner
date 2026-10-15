<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Console;

use Amrachraf6699\LaravelNPlusOneScanner\Reporting\ConsoleReporter;
use Amrachraf6699\LaravelNPlusOneScanner\Reporting\JsonReporter;
use Amrachraf6699\LaravelNPlusOneScanner\Scanner\ProjectScanner;
use Amrachraf6699\LaravelNPlusOneScanner\Scanner\ScanOptions;
use Illuminate\Console\Command;
use Throwable;

final class ScanCommand extends Command
{
    protected $signature = 'nplusone:scan
        {--path=* : Directory or PHP file to scan}
        {--format=console : console or json}
        {--min-severity= : low, medium, or high}
        {--fail-on= : Return 1 when a finding reaches this severity}
        {--no-progress : Disable progress output}';

    protected $description = 'Statically scan PHP source for potential Laravel N+1 query patterns.';

    public function handle(ProjectScanner $scanner, ConsoleReporter $console, JsonReporter $json): int
    {
        try {
            $format = strtolower((string) $this->option('format'));
            if (!in_array($format, ['console', 'json'], true)) {
                throw new \InvalidArgumentException('format must be console or json.');
            }
            $config = (array) config('nplusone', []);
            $paths = $this->option('path');
            $options = ScanOptions::fromConfig($config, $paths ?: null, $this->option('min-severity') ?: null);
            $result = $scanner->scan($options, base_path());
            if ($format === 'json') {
                $this->output->write($json->render($result));
            } else {
                $console->render($result, $this->output, !$this->option('no-progress'));
            }
            if ($result->status !== 'complete') {
                return 2;
            }
            $failOn = $this->option('fail-on');
            if ($failOn && $this->hasThreshold($result, (string) $failOn)) {
                return 1;
            }
            return 0;
        } catch (Throwable $error) {
            if (strtolower((string) $this->option('format')) === 'json') {
                $this->output->write(json_encode(['schema_version' => '1', 'scanner_version' => \Amrachraf6699\LaravelNPlusOneScanner\Support\Version::VERSION, 'status' => 'failed', 'summary' => [], 'findings' => [], 'diagnostics' => [['code' => 'command_error', 'level' => 'error', 'message' => $error->getMessage(), 'file' => null, 'line' => null]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            } else {
                $this->error($error->getMessage());
            }
            return 2;
        }
    }

    private function hasThreshold($result, string $threshold): bool
    {
        $severity = \Amrachraf6699\LaravelNPlusOneScanner\Findings\Severity::tryFrom(strtolower($threshold));
        if (!$severity) {
            throw new \InvalidArgumentException('fail-on must be low, medium, or high.');
        }
        foreach ($result->findings as $finding) {
            if ($finding->severity->rank() >= $severity->rank()) {
                return true;
            }
        }
        return false;
    }
}
