<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Reporting;

use Amrachraf6699\LaravelNPlusOneScanner\Findings\Finding;
use Amrachraf6699\LaravelNPlusOneScanner\Scanner\ScanResult;
use Amrachraf6699\LaravelNPlusOneScanner\Support\Version;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleReporter
{
    public function render(ScanResult $result, OutputInterface $output, bool $progress = true): void
    {
        $output->writeln('<info>Laravel N+1 Query Scanner v' . Version::VERSION . '</info>');
        $output->writeln(str_repeat('─', 60));
        $output->writeln('');
        $output->writeln('Scanning application source files...');
        if ($progress) {
            $output->writeln('');
        }
        $output->writeln(sprintf('✓ %d PHP files analyzed', $result->filesAnalyzed));
        $output->writeln(sprintf('%s %d potential N+1 issues detected', count($result->findings) ? '⚠' : '✓', count($result->findings)));
        $output->writeln('');
        foreach ($result->findings as $finding) {
            $this->finding($finding, $output);
        }
        foreach ($result->diagnostics as $diagnostic) {
            $output->writeln('<error>' . strtoupper($diagnostic->level) . ': ' . $diagnostic->message . '</error>');
        }
        $output->writeln(str_repeat('─', 60));
        $output->writeln('');
        $output->writeln(sprintf('Files scanned: %d', $result->filesAnalyzed));
        $output->writeln(sprintf('Potential issues: %d', count($result->findings)));
    }

    private function finding(Finding $finding, OutputInterface $output): void
    {
        $output->writeln(sprintf('<%s>%s</%s> %s:%d', $finding->severity->value === 'high' ? 'error' : 'comment', strtoupper($finding->severity->value), $finding->severity->value === 'high' ? 'error' : 'comment', $finding->file, $finding->line));
        $output->writeln('      ' . $finding->message);
        if ($finding->model) {
            $output->writeln('      Model: ' . $finding->model);
        }
        if ($finding->relationship) {
            $output->writeln('      Relationship: ' . $finding->relationship);
        }
        if ($finding->suggestion) {
            $output->writeln('      Suggestion: ' . $finding->suggestion);
        }
        $output->writeln('');
    }
}
