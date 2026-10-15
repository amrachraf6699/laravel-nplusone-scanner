<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

final class FlowState
{
    /** @var array<string, ValueFact> */
    public array $vars = [];
    public string $iterationContext = 'none';
    public string $iterationConfidence = 'high';

    public function copy(): self
    {
        $copy = new self();
        $copy->vars = array_map(static fn (ValueFact $fact): ValueFact => $fact->copy(), $this->vars);
        $copy->iterationContext = $this->iterationContext;
        $copy->iterationConfidence = $this->iterationConfidence;
        return $copy;
    }

    /** @param list<self> $states */
    public static function join(array $states): self
    {
        $joined = new self();
        if ($states === []) {
            return $joined;
        }
        $keys = array_values(array_unique(array_merge(...array_map(static fn (self $state): array => array_keys($state->vars), $states))));
        foreach ($keys as $key) {
            $facts = array_map(static fn (self $state): ValueFact => $state->vars[$key] ?? ValueFact::unknown(), $states);
            $first = $facts[0];
            $joined->vars[$key] = array_reduce($facts, static fn (bool $same, ValueFact $fact): bool => $same && $first->equals($fact), true)
                ? $first->copy() : ValueFact::unknown();
        }
        $contexts = array_map(static fn (self $state): string => $state->iterationContext, $states);
        $confidences = array_map(static fn (self $state): string => $state->iterationConfidence, $states);
        $joined->iterationContext = count(array_unique($contexts)) === 1 ? $contexts[0] : 'none';
        $joined->iterationConfidence = count(array_unique($confidences)) === 1 ? $confidences[0] : 'medium';
        return $joined;
    }
}
