<?php

namespace Eboubaker\JSON;

use ArrayIterator;
use Eboubaker\JSON\Contracts\JSONEntry;
use Eboubaker\JSON\Contracts\JSONEnumerable;
use Generator;
use RecursiveArrayIterator;

trait ArrayOrObject
{
    /**
     * @var array<int,JSONEntry>
     */
    private array $entries;

    /**
     * json container always returns itself
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function value()
    {
        return $this;
    }


    public function countContainedEntries(): int
    {
        $count = 0;
        foreach ($this->entries as $entry) {
            if ($entry instanceof JSONEnumerable) {
                $count += $entry->countContainedEntries();
            } else {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @inheritDoc
     */
    public function values(): Generator
    {
        foreach ($this->entries as $key => $entry) {
            if ($entry instanceof JSONEnumerable) {
                foreach ($entry->values() as $k => $value) {
                    yield $k => $value;
                }
            } else {// it must be JSONValue
                yield $key => $entry->value();
            }
        }
    }


    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @internal this method is not part of the public API
     */
    public function offsetExists($offset): bool
    {
        return isset($this->entries[$offset]);
    }

    /**
     * @internal this method is not part of the public API
     */
    public function offsetGet($offset)
    {
        return $this->entries[$offset];
    }

    /**
     * @internal this method is not part of the public API
     */
    public function offsetSet($offset, $value)
    {
        if (!($value instanceof JSONEntry)) {
            $this->entries[$offset] = $value;
        } else {
            $this->entries[$offset] = new JSONValue($value);
        }
        $this->entries[$offset] = $value;
    }

    /**
     * @internal this method is not part of the public API
     */
    public function offsetUnset($offset)
    {
        unset($this->entries[$offset]);
    }

    /**
     * @internal this method is not part of the public API
     */
    public function getIterator(): ArrayIterator
    {
        return new RecursiveArrayIterator($this->entries);
    }

    /**
     * @inheritDoc
     * @return array<JSONEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @inheritDoc
     * @return array<int|string, bool|string|int|float|null|array> convert the container structure to a nested associative array of primitive types
     */
    public function assoc(): array
    {
        $result = [];
        foreach ($this->entries as $key => $entry) {
            if ($entry instanceof JSONEnumerable) {
                $result[$key] = $entry->assoc();
            } else {// it must be JSONValue
                $result[$key] = $entry->value();
            }
        }
        return $result;
    }

}
