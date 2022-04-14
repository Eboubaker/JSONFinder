<?php declare(strict_types=1);

namespace Eboubaker\JSON;

use Eboubaker\JSON\Contracts\JSONEntry;
use Eboubaker\JSON\Contracts\JSONStringable;
use InvalidArgumentException;

/**
 * array which contains {@link JSONEntry}s
 * @author eboubaker bekkouche <eboubakkar@gmail.com>
 */
class JSONArray extends JSONContainer
{
    private static JSONFinder $valueFinder;

    /**
     * @param array<JSONEntry|JSONStringable>|iterable $entries
     * @throws InvalidArgumentException if the array contains a non integer key, or the tail values of the array are not primitive types
     */
    public function __construct($entries = [])
    {
        if (!is_iterable($entries)) {
            throw new InvalidArgumentException('The entries must be iterable');
        }
        $this->entries = [];
        foreach ($entries as $key => $entry) {
            if (!is_int($key)) {
                throw new InvalidArgumentException("array keys must be integers, " . gettype($key) . " given");
            }
            $this->addEntry($entry, $key);
        }
    }

    /**
     * @inheritDoc
     * @return string a valid json string which represents the array
     */
    public function __toString(): string
    {
        return "[" . implode(",", $this->entries) . "]";
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
        $str .= ']';
        return $str;
    }

    public function offsetSet($offset, $value)
    {
        if (!is_integer($offset) && !is_null($offset)) {
            throw new InvalidArgumentException("array keys must be integers, " . gettype($offset) . " given");
        }
        /** @noinspection DuplicatedCode */
        if ($value instanceof JSONEntry) {
            parent::offsetSet($offset, $value);
        } else if (is_iterable($value)) {
            $list = [];
            $has_string_key = false;
            foreach ($value as $key => $entry) {
                if (!$has_string_key && !is_string($key)) {
                    $has_string_key = true;
                    if ($value instanceof \ArrayAccess || is_array($value)) {
                        $list = $value;
                        break;
                    }
                }
                $list[$key] = $entry;
            }
            if ($has_string_key) {
                parent::offsetSet($offset, new JSONObject($list));
            } else {
                parent::offsetSet($offset, new JSONArray($list));
            }
        } else {
            parent::offsetSet($offset, new JSONValue($value));
        }
    }

    public function unserialize($data)
    {
        if (!isset(self::$valueFinder)) {
            self::$valueFinder = JSONFinder::make(JSONFinder::T_ARRAY | JSONFinder::T_EMPTY_ARRAY);
        }
        $this->entries = self::$valueFinder->findEntries($data)[0]->entries;
    }
}
