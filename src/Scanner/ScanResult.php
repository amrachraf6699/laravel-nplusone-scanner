<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Scanner;

use Amrachraf6699\LaravelNPlusOneScanner\Findings\Finding;

final readonly class ScanResult
{
    /** @param list<Finding> $findings @param list<ScanDiagnostic> $diagnostics */
    public function __construct(
        public string $status,
        public int $filesDiscovered,
        public int $filesAnalyzed,
        public int $filesFailed,
        public array $findings,
        public array $diagnostics,
        public int $filesParsed = 0,
        public int $cacheHits = 0,
    ) {
    }

    public function toArray(): array
    {
        $bySeverity = ['low' => 0, 'medium' => 0, 'high' => 0];
        foreach ($this->findings as $finding) {
            $bySeverity[$finding->severity->value]++;
        }
        return [
            'schema_version' => '1',
            'scanner_version' => \Amrachraf6699\LaravelNPlusOneScanner\Support\Version::VERSION,
            'status' => $this->status,
            'summary' => [
                'files_discovered' => $this->filesDiscovered,
                'files_analyzed' => $this->filesAnalyzed,
                'files_failed' => $this->filesFailed,
                'files_parsed' => $this->filesParsed,
                'cache_hits' => $this->cacheHits,
                'findings_total' => count($this->findings),
                'findings_reported' => count($this->findings),
                'findings_by_severity' => $bySeverity,
            ],
            'findings' => array_map(static fn (Finding $finding): array => $finding->toArray(), $this->findings),
            'diagnostics' => array_map(static fn (ScanDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
        ];
    }
}
