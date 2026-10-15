<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

final class ValueFact
{
    public function __construct(
        public string $kind,
        public ?string $model = null,
        public ?string $relationship = null,
        public array $loaded = [],
        public array $aggregates = [],
        public bool $iterable = false,
        public bool $autoloading = false,
    ) {
    }

    public static function unknown(): self
    {
        return new self('unknown');
    }
    public static function model(?string $model, bool $iterable = false, array $loaded = []): self
    {
        return new self('model', $model, null, $loaded, [], $iterable);
    }
    public static function collection(?string $model, array $loaded = [], array $aggregates = []): self
    {
        return new self('collection', $model, null, $loaded, $aggregates, true);
    }
    public static function builder(?string $model, array $loaded = [], array $aggregates = []): self
    {
        return new self('builder', $model, null, $loaded, $aggregates);
    }
    public static function relationQuery(?string $model, ?string $relationship): self
    {
        return new self('relation_query', $model, $relationship);
    }
    public function copy(): self
    {
        return new self($this->kind, $this->model, $this->relationship, $this->loaded, $this->aggregates, $this->iterable, $this->autoloading);
    }
}
