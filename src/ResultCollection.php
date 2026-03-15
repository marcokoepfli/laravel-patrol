<?php

namespace MarcoKoepfli\LaravelPatrol;

use Countable;
use IteratorAggregate;
use MarcoKoepfli\LaravelPatrol\Enums\Severity;
use Traversable;

/**
 * @implements IteratorAggregate<int, Result>
 */
class ResultCollection implements Countable, IteratorAggregate
{
    /** @var Result[] */
    private array $results = [];

    public function add(Result $result): void
    {
        $this->results[] = $result;
    }

    public function merge(array $results): void
    {
        foreach ($results as $result) {
            $this->add($result);
        }
    }

    public function hasErrors(): bool
    {
        foreach ($this->results as $result) {
            if ($result->severity === Severity::Error) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->results as $result) {
            if ($result->severity === Severity::Warning) {
                return true;
            }
        }

        return false;
    }

    public function bySeverity(Severity $severity): self
    {
        $collection = new self;
        foreach ($this->results as $result) {
            if ($result->severity === $severity) {
                $collection->add($result);
            }
        }

        return $collection;
    }

    /**
     * @return array<string, Result[]>
     */
    public function groupByFile(): array
    {
        $grouped = [];
        foreach ($this->results as $result) {
            $key = $result->file ?? '(no file)';
            $grouped[$key][] = $result;
        }

        return $grouped;
    }

    /**
     * @return array<string, Result[]>
     */
    public function groupByRule(): array
    {
        $grouped = [];
        foreach ($this->results as $result) {
            $grouped[$result->ruleId][] = $result;
        }

        return $grouped;
    }

    public function count(): int
    {
        return count($this->results);
    }

    /**
     * @return Result[]
     */
    public function all(): array
    {
        return $this->results;
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->results);
    }

    public function toArray(): array
    {
        return array_map(fn (Result $r) => $r->toArray(), $this->results);
    }
}
