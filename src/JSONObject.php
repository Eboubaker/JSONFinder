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
            if (!is_string($key) && !is_integer($key)) {// just in case
                throw new InvalidArgumentException("object keys must be strings or integers, " . gettype($key) . "given");
            }
            $this->addEntry($entry, $key);
        }
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
            throw new InvalidArgumentException("object keys must be strings or integers, " . gettype($offset) . "given");
        }
        /** @noinspection DuplicatedCode */
        if ($value instanceof JSONEntry) {
            parent::offsetSet($offset, $value);
        } else if (is_iterable($value) || is_object($value)) {
            parent::offsetSet($offset, $this->iterable_to_container($value));
        } else {
            parent::offsetSet($offset, new JSONValue($value));
        }
    }

    public function unserialize($data)
    {
        if (!isset(self::$valueFinder)) {
            self::$valueFinder = JSONFinder::make(JSONFinder::T_OBJECT | JSONFinder::T_EMPTY_OBJECT);
        }
        $this->entries = self::$valueFinder->findEntries($data)[0]->entries;
    }
}
