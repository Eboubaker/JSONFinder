<?php declare(strict_types=1);

namespace Eboubaker\JSON;

use ArrayIterator;
use Eboubaker\JSON\Contracts\JSONContainer;
use Eboubaker\JSON\Contracts\JSONEntry;
use Eboubaker\JSON\Contracts\JSONEnumerable;
use Generator;
use InvalidArgumentException;
use RecursiveArrayIterator;

/**
 * array which contains {@link JSONEntry}s
 * @author eboubaker bekkouche <eboubakkar@gmail.com>
 */
class JSONArray implements JSONContainer
{
    /**
     * @var array<int,JSONEntry>
     */
    private array $entries;

    /**
     * @param array<int,JSONEntry> $entries
     * @throws InvalidArgumentException if the array contains a value which is not a {@link JSONEntry},
     * or if the array contains a non integer key
     */
    public function __construct(array $entries)
    {
        foreach ($entries as $key => $entry) {
            if (!is_int($key)) {
                throw new InvalidArgumentException("array keys must be integers, " . gettype($key) . "($key) given");
            }
            if (!($entry instanceof JSONEntry)) {
                throw new InvalidArgumentException("array entries must be of type JSONEntry, " . gettype($entry) . " given at index " . $key);
            }
        }
        $this->entries = $entries;
    }

    #region JSONEntry

    /**
     * returns self (this array)
     * @return $this
     */
    public function value(): JSONArray
    {
        return $this;
    }

    /**
     * @inheritDoc
     * @return string a valid json string which represents the array
     */
    public function __toString(): string
    {
        return "[" . implode(",", $this->entries) . "]";
    }
    #endregion JSONEntry

    #region JSONEnumerable
    /**
     * @inheritDoc
     * @return array<int, bool|string|int|float|null|array> indexed array of (primitive types or other associative or indexed arrays)
     */
    public function assoc(): array
    {
        $result = [];
        foreach ($this->entries as $entry) {
            if ($entry instanceof JSONEnumerable) {
                $result[] = $entry->assoc();
            } else {// it must be JSONValue
                $result[] = $entry->value();
            }
        }
        return $result;
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
    #endregion JSONEnumerable

    #region JSONContainer
    /**
     * @inheritDoc
     * @return array<int,JSONEntry>
     */
    public function entries(): array
    {
        $result = [];
        foreach ($this->entries as $entry) {
            if ($entry instanceof JSONContainer) {
                $result[] = $entry->entries();
            } else {// it must be JSONValue
                $result[] = $entry->value();
            }
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function toReadableString(int $indent): string
    {
        return $this->__toReadableString($indent, $indent);
    }

    /**
     * @internal this method is not part of the public API
     */
    function __toReadableString(int $indent, $indentIncrease): string
    {
        $str = '[';
        if ($indent > 0 && count($this->entries) > 0) {
            $str .= "\n";
        }
        $count = 0;
        foreach ($this->entries as $entry) {
            $str .= str_repeat(" ", $indent);
            if ($entry instanceof JSONArray || $entry instanceof JSONObject) {
                $str .= $entry->__toReadableString($indent + $indentIncrease, $indentIncrease);
            } else {// it must be JSONValue
                $str .= $entry;
            }
            if ($count < count($this->entries) - 1) {
                $str .= ",";
            }
            if ($indent > 0) {
                $str .= "\n";
            }
            $count++;
        }
        if ($indent > $indentIncrease && count($this->entries) > 0) {
            $str .= str_repeat(" ", $indent - $indentIncrease);
        }
        $str .= ']';
        return $str;
    }

    public function count(): int
    {
        return count($this->entries);
    }
    #endregion JSONContainer

    #region PHP
    #region ArrayAccess
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
    #endregion ArrayAccess

    #region IteratorAggregate
    /**
     * @internal this method is not part of the public API
     */
    public function getIterator(): ArrayIterator
    {
        return new RecursiveArrayIterator($this->entries);
    }
    #endregion IteratorAggregate
    #endregion
}
