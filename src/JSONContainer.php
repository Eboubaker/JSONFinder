<?php

namespace Eboubaker\JSON;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Eboubaker\JSON\Contracts\JSONEntry;
use Generator;
use IteratorAggregate;
use RecursiveArrayIterator;

/**
 * shared logic between {@link JSONArray} and {@link JSONObject}
 * @internal
 */
abstract class JSONContainer implements JSONEntry, ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @var array<int|string,JSONEntry>
     */
    protected array $entries;

    /**
     * json container always returns itself
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function value()
    {
        return $this;
    }

    /**
     * query the number of all stored/nested values
     */
    public function countAll(): int
    {
        $count = 0;
        foreach ($this->entries as $entry) {
            if ($entry instanceof JSONContainer) {
                $count += $entry->countAll();
            } else {
                $count++;
            }
        }
        return $count;
    }

    /**
     * returns an iterator of all nested primitive values
     */
    public function values(): Generator
    {
        foreach ($this->entries as $key => $entry) {
            if ($entry instanceof JSONContainer) {
                foreach ($entry->values() as $k => $value) {
                    yield $k => $value;
                }
            } else {// it must be JSONValue
                yield $key => $entry->value();
            }
        }
    }

    /**
     * returns count of entries
     */
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
     * returns the list of entries inside this container
     * @returns array<int|string,JSONEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * converts the container structure to a nested associative array of primitive types
     * @return array<string|int, bool|string|int|float|null|array> associative or indexed array of primitive types or other associative or indexed arrays
     */
    public function assoc(): array
    {
        $result = [];
        foreach ($this->entries as $key => $entry) {
            if ($entry instanceof JSONContainer) {
                $result[$key] = $entry->assoc();
            } else {// it must be JSONValue
                $result[$key] = $entry->value();
            }
        }
        return $result;
    }

    public function serialize(): string
    {
        return strval($this);
    }

    /**
     * serialize the json with applied indentation
     * @param int $indent number of spaces to indent
     * @return string returns the prettified json
     */
    abstract function toReadableString(int $indent): string;
}
