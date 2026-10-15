<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Findings;

final class FindingCollector
{
    /** @var array<string, Finding> */
    private array $findings = [];

    public function add(Finding $finding): void
    {
        $this->findings[$finding->key()] = $finding;
    }

    /** @return list<Finding> */
    public function all(): array
    {
        $items = array_values($this->findings);
        usort($items, static fn (Finding $a, Finding $b): int => [$a->file, $a->line, $a->column, $a->rule, $a->relationship]
            <=> [$b->file, $b->line, $b->column, $b->rule, $b->relationship]);
        return $items;
    }
}
