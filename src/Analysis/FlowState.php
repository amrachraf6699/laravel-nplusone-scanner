<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

final class FlowState
{
    /** @var array<string, ValueFact> */
    public array $vars = [];
    public string $iterationRule = 'N1';

    public function copy(): self
    {
        $copy = new self();
        $copy->vars = array_map(static fn (ValueFact $fact): ValueFact => $fact->copy(), $this->vars);
        $copy->iterationRule = $this->iterationRule;
        return $copy;
    }
}
