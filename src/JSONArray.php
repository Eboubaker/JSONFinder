<?php declare(strict_types=1);

namespace Eboubaker\JSON;

use Eboubaker\JSON\Contracts\JSONContainer;
use Eboubaker\JSON\Contracts\JSONEntry;
use InvalidArgumentException;

/**
 * array which contains {@link JSONEntry}s
 * @author eboubaker bekkouche <eboubakkar@gmail.com>
 */
class JSONArray implements JSONContainer
{
    use ArrayOrObject;

    private static JSONFinder $valueFinder;

    /**
     * @param array<int,JSONEntry> $entries
     * @throws InvalidArgumentException if the array contains a value which is not a {@link JSONEntry},
     * or if the array contains a non integer key
     */
    public function __construct(array $entries)
    {
        $this->entries = [];
        foreach ($entries as $key => $entry) {
            if (!is_int($key)) {
                throw new InvalidArgumentException("array keys must be integers, " . gettype($key) . "($key) given");
            }
            if (!$entry instanceof JSONEntry) {
                /** @noinspection DuplicatedCode */
                if (is_array($entry)) {
                    if (Utils::is_associative($entry)) {
                        $this->entries[] = new JSONObject($entry);
                    } else {
                        $this->entries[] = new JSONArray($entry);
                    }
                } else if (is_object($entry)) {
                    $this->entries[] = new JSONObject($entry);
                } else {
                    $this->entries[] = new JSONValue($entry);
                }
            } else {
                $this->entries[$key] = $entry;
            }
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


    public function serialize(): string
    {
        return strval($this);
    }

    public function unserialize($data)
    {
        if(self::$valueFinder === null) {
            self::$valueFinder = new JSONFinder(JSONFinder::T_ARRAY);
        }
        return self::$valueFinder->findJsonEntries($data)[0] ?? null;
    }
}
