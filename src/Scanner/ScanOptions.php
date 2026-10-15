<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Scanner;

use Amrachraf6699\LaravelNPlusOneScanner\Findings\Severity;
use InvalidArgumentException;

final readonly class ScanOptions
{
    /** @param list<string> $paths @param list<string> $metadataPaths @param list<string> $exclude @param array<string, bool> $rules @param array<string, string> $resourceModels @param list<class-string> $additionalRules */
    public function __construct(
        public array $paths,
        public array $metadataPaths,
        public array $exclude,
        public array $rules,
        public Severity $minimumSeverity,
        public array $resourceModels = [],
        public array $additionalRules = [],
    ) {
    }

    public static function fromConfig(array $config, ?array $pathOverride = null, ?string $minimumSeverity = null): self
    {
        $paths = $pathOverride ?? (array) ($config['paths'] ?? ['app']);
        $metadataPaths = (array) ($config['metadata_paths'] ?? []);
        $exclude = (array) ($config['exclude'] ?? []);
        $rules = (array) ($config['rules'] ?? []);
        $severity = $minimumSeverity ?? (string) ($config['minimum_severity'] ?? 'low');
        $minimum = Severity::tryFrom(strtolower($severity));
        if (!$minimum) {
            throw new InvalidArgumentException('minimum severity must be low, medium, or high.');
        }
        foreach (array_merge($paths, $metadataPaths) as $path) {
            if (!is_string($path) || $path === '') {
                throw new InvalidArgumentException('scan paths must be non-empty strings.');
            }
        }
        return new self($paths, $metadataPaths, array_values(array_filter($exclude, 'is_string')), $rules, $minimum, (array) ($config['resource_models'] ?? []), (array) ($config['additional_rules'] ?? []));
    }
}
