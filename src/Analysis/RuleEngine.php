<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

use Amrachraf6699\LaravelNPlusOneScanner\Contracts\AnalysisRule;
use Amrachraf6699\LaravelNPlusOneScanner\Findings\FindingCollector;

final class RuleEngine
{
    /** @param list<AnalysisRule> $rules */
    public function __construct(private readonly array $rules = [])
    {
    }

    public static function default(): self
    {
        return new self([
            new Rules\RelationshipInLoopRule(),
            new Rules\RelationshipQueryInLoopRule(),
            new Rules\CollectionCallbackRule(),
            new Rules\ResourceRelationshipRule(),
        ]);
    }

    /** @param list<string> $enabled */
    public function emit(AnalysisEvent $event, SourceIndex $index, array $enabled, FindingCollector $collector): void
    {
        $context = new AnalysisContext($index, $event->file, $event);
        foreach ($this->rules as $rule) {
            if (in_array($rule->id(), $enabled, true)) {
                foreach ($rule->analyze($event->node, $context) as $finding) {
                    $collector->add($finding);
                }
            }
        }
    }
}
