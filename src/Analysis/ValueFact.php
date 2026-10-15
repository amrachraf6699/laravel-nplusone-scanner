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
    public static function model(?string $model, bool $iterable = false, array $loaded = [], array $aggregates = []): self
    {
        return new self('model', $model, null, $loaded, $aggregates, $iterable);
    }
    public static function collection(?string $model, array $loaded = [], array $aggregates = []): self
    {
        return new self('collection', $model, null, $loaded, $aggregates, true);
    }
    public static function builder(?string $model, array $loaded = [], array $aggregates = []): self
    {
        return new self('builder', $model, null, $loaded, $aggregates);
    }
    public static function relationQuery(?string $model, ?string $relationship, bool $iterable = false): self
    {
        return new self('relation_query', $model, $relationship, [], [], $iterable);
    }
    public function copy(): self
    {
        return new self($this->kind, $this->model, $this->relationship, $this->loaded, $this->aggregates, $this->iterable, $this->autoloading);
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind
            && $this->model === $other->model
            && $this->relationship === $other->relationship
            && $this->loaded === $other->loaded
            && $this->aggregates === $other->aggregates
            && $this->iterable === $other->iterable
            && $this->autoloading === $other->autoloading;
    }
}
