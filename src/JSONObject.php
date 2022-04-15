<?php declare(strict_types=1);

namespace Eboubaker\JSON;

use Eboubaker\JSON\Contracts\JSONEntry;
use InvalidArgumentException;

/**
 * object which contains associative array of {@link JSONEntry}s
 * @author eboubaker bekkouche <eboubakkar@gmail.com>
 */
class JSONObject extends JSONContainer
{
    private static JSONFinder $valueFinder;

    /**
     * @param object|iterable|array<string,JSONEntry|mixed> $entries
     * @throws InvalidArgumentException if the array or the object contains a tail value which is not a primitive type
     */
    public function __construct($entries = [])
    {
        if (!is_iterable($entries) && !is_object($entries)) {
            throw new InvalidArgumentException('The entries must be iterable');
        }
        $this->entries = [];
        foreach ($entries as $key => $entry) {
            if (!($entry instanceof JSONEntry)) {
                throw new InvalidArgumentException("JSONObject constructor only allows JSONEntries, " . Utils::typeof($entry) . " given, use JSONObject::from() instead.");
            }
            $this->offsetSet($key, $entry);
        }
    }

    /**
     * make a JSONObject from $iterable values, will wrap values that are not JSONEntries.
     * @param $iterable array|iterable|object
     * @return static
     * @see JSONObject
     * @see JSONEntry
     */
    public static function from($iterable): self
    {
        if (!is_iterable($iterable) && !is_object($iterable)) {
            throw new InvalidArgumentException('JSONObject::from(): expected parameter 1 to be iterable but got ' . Utils::typeof($iterable));
        }
        $instance = new static();
        foreach ($iterable as $key => $value) {
            $instance->offsetSet($key, JSONContainer::toEntry($value));
        }
        return $instance;
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

    /**
     * @inheritDoc
     */
    public function toReadableString(int $indent = 2): string
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
            /** @noinspection DuplicatedCode */
            if ($entry instanceof JSONArray || $entry instanceof JSONObject) {
                $str .= $entry->__toReadableString($indent + $indentIncrease, $indentIncrease);
            } else {// it must be JSONValue
                /** @var JSONValue $entry */
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
        $str .= '}';
        return $str;
    }

    public function offsetSet($offset, $value)
    {
        if (!is_string($offset) && !is_integer($offset) && !is_null($offset)) {
            throw new InvalidArgumentException("JSONObject keys must be strings or integers, " . Utils::typeof($offset) . "given");
        }
        if (!($value instanceof JSONEntry)) {
            if (!JSONValue::allowedValue($value)) {
                throw new InvalidArgumentException("JSONObject only accepts primitive types and JSONEntry or JSONStringable objects, " . Utils::typeof($value) . " given");
            }
            $value = new JSONValue($value);
        }
        parent::offsetSet($offset, $value);
    }

    public function unserialize($data)
    {
        if (!isset(self::$valueFinder)) {
            self::$valueFinder = JSONFinder::make(JSONFinder::T_OBJECT | JSONFinder::T_EMPTY_OBJECT);
        }
        $this->entries = self::$valueFinder->findEntries($data)[0]->entries;
    }
}
