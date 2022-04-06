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
 * object which contains associative array of {@link JSONEntry}s
 * @author eboubaker bekkouche <eboubakkar@gmail.com>
 */
class JSONObject implements JSONContainer
{
    /**
     * @var array<string,JSONEntry>
     */
    private array $entries;

    /**
     * @param array<string,JSONEntry> $entries
     * @throws InvalidArgumentException if the associative array contains a value which is not a {@link JSONEntry}
     * or if the array contains a non string key
     */
    public function __construct(array $entries)
    {
        foreach ($entries as $key => $entry) {
            if (!is_string($key)) {
                if (!is_integer($key)) {
                    throw new InvalidArgumentException("object keys must be strings or integers, " . gettype($key) . "($key) given");
                } else {
                    unset($entries[$key]);
                    $entries[strval($key)] = $entry;
                }
            }
            if (!($entry instanceof JSONEntry)) {
                throw new InvalidArgumentException("object values must be of type JSONEntry, " . gettype($entry) . " given for value of key " . $key);
            }
        }
        $this->entries = $entries;
    }

    #region JSONEntry

    /**
     * returns self (this object)
     * @return $this
     */
    public function value(): JSONObject
    {
        return $this;
    }

    /**
     * @inheritDoc
     * @return string a valid json string which represents the object
     */
    public function __toString(): string
    {
        $keyValuePairs = array_map(fn($key, $value) => "\"$key\":$value", array_keys($this->entries), $this->entries);
        return "{" . implode(",", $keyValuePairs) . "}";
    }
    #endregion JSONEntry

    #region JSONEnumerable
    /**
     * @inheritDoc
     * @return array<string, bool|string|int|float|null|array> associative array of (primitive types or other associative or indexed arrays)
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

    /**
     * @inheritDoc
     */
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
     * @return object
     */
    public function entries(): object
    {
        $result = [];
        foreach ($this->entries as $key => $entry) {
            if ($entry instanceof JSONContainer) {
                $result[$key] = $entry->entries();
            } else {// it must be JSONValue
                $result[$key] = $entry->value();
            }
        }
        return (object)$result;
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
        $str = '{';
        if ($indent > 0 && count($this->entries) > 0) {
            $str .= "\n";
        }
        $count = 0;
        foreach ($this->entries as $key => $entry) {
            $str .= str_repeat(" ", $indent);
            $str .= "\"$key\":";
            if ($indent > 0) {
                $str .= " ";
            }
            if ($entry instanceof JSONArray || $entry instanceof JSONObject) {
                $str .= $entry->__toReadableString($indent + $indentIncrease, $indentIncrease);
            } else {// it must be JSONValue
                $str .= $entry;
            }
            // add commas if not at end
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
        $str .= '}';
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
    #endregion PHP
}
