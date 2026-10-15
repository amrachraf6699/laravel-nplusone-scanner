<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Scanner;

use Amrachraf6699\LaravelNPlusOneScanner\Analysis\FlowAnalyzer;
use Amrachraf6699\LaravelNPlusOneScanner\Analysis\ModelMetadataResolver;
use Amrachraf6699\LaravelNPlusOneScanner\Analysis\SourceIndex;
use Amrachraf6699\LaravelNPlusOneScanner\Findings\FindingCollector;

final class ProjectScanner
{
    public function __construct(
        private readonly FileDiscovery $discovery = new FileDiscovery(),
        private readonly PhpFileParser $parser = new PhpFileParser(),
        private readonly ModelMetadataResolver $metadata = new ModelMetadataResolver(),
        private readonly FlowAnalyzer $analyzer = new FlowAnalyzer(),
    ) {
    }

    public function scan(ScanOptions $options, string $basePath): ScanResult
    {
        $analysisFiles = $this->discovery->discover($options->paths, $options->exclude, $basePath);
        $metadataFiles = $this->discovery->discover(array_values(array_unique(array_merge($options->paths, $options->metadataPaths))), $options->exclude, $basePath);
        $index = new SourceIndex();
        $diagnostics = [];
        $failed = 0;
        foreach ($metadataFiles as $file) {
            [$ast, $diagnostic] = $this->parser->parse($file);
            if ($diagnostic) {
                $diagnostics[] = $diagnostic;
                $failed++;
                continue;
            }
            $this->metadata->index($ast, $index, $options->resourceModels);
        }
        $collector = new FindingCollector();
        $enabled = array_keys(array_filter($options->rules, static fn ($value): bool => (bool) $value));
        $analyzed = 0;
        foreach ($analysisFiles as $file) {
            [$ast, $diagnostic] = $this->parser->parse($file);
            if ($diagnostic) {
                $diagnostics[] = $diagnostic;
                $failed++;
                continue;
            }
            $analyzed++;
            $this->analyzer->analyze($ast, $file, $index, $collector, $enabled);
        }
        $findings = array_values(array_filter($collector->all(), static fn ($finding): bool => $finding->severity->rank() >= $options->minimumSeverity->rank()));
        return new ScanResult($failed > 0 ? 'partial' : 'complete', count($analysisFiles), $analyzed, $failed, $findings, $diagnostics);
    }
}
