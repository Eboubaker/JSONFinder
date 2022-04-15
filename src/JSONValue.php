<?php declare(strict_types=1);

namespace Eboubaker\JSON;

use Eboubaker\JSON\Contracts\JSONEntry;
use Eboubaker\JSON\Contracts\JSONStringable;
use InvalidArgumentException;

/**
 * a json primitive value it's value can be one of string, float, int, bool, null, {@link JSONStringable}
 * @author eboubaker bekkouche <eboubakkar@gmail.com>
 */
class JSONValue implements JSONEntry
{
    /**
     * the value that this entry holds
     * @var bool|float|int|string|null|JSONStringable
     */
    public $value;

    private static JSONFinder $valueFinder;

    /**
     * @param $value bool|float|int|string|null|JSONStringable
     * @throws InvalidArgumentException if the value is not one of the allowed types
     */
    public function __construct($value)
    {
        if (self::allowedValue($value)) {
            $this->value = $value;
        } else {
            throw new InvalidArgumentException("value must be a primitive type or implement JSONStringable, \"" . Utils::typeof($value) . "\" given");
        }
    }

    /**
     * @param $value
     * @return bool returns true if JSONValue constructor can be called with this value
     */
    public static function allowedValue($value): bool
    {
        return $value === null || is_int($value) || is_float($value) || is_string($value) || is_bool($value) || $value instanceof JSONStringable;
    }

    public function isContainer(): bool
    {
        return false;
    }

    /**
     * @return bool|float|int|string|null
     */
    public function value()
    {
        return $this->value;
    }

    public function __toString(): string
    {
        if (is_float($this->value)) {
            $string = strval($this->value);
            if (strpos($string, '.') === false && stripos($string, 'E') === false) {
                return $string . ".0";// force float
            } else {
                return $string;
            }
        } else if (is_int($this->value)) {
            return strval($this->value);
        } else if (is_bool($this->value)) {
            return $this->value ? 'true' : 'false';
        } else if ($this->value === null) {
            return 'null';
        } else if (is_string($this->value)) {
            $string = "\"";
            $len = strlen($this->value);
            for ($i = 0; $i < $len; $i++) {
                $char = $this->value[$i];
                if ($char === '\\') $string .= '\\\\';
                else if ($char === '"') $string .= '\\"';
                else if ($char === '/') $string .= '\\/';
                else if ($char === "\n") $string .= '\\n';
                else if ($char === "\r") $string .= '\\r';
                else if ($char === "\t") $string .= '\\t';
                else if (ord($char) === 8) $string .= '\\b';
                else if ($char === "\f") $string .= '\\f';
                else $string .= $char;
            }
            $string .= "\"";
            return $string;
        } else if ($this->value instanceof JSONStringable) {
            return $this->value->toJSONString();
        } else {
            throw new InvalidArgumentException("json serialization error: unexpected value type: \"" . Utils::typeof($this->value) . "\"");
        }
    }

    public function serialize(): string
    {
        return strval($this);
    }

    /**
     * @throws InvalidArgumentException if data is not string or if no value found inside data
     */
    public function unserialize($data)
    {
        if (!isset(self::$valueFinder)) {
            self::$valueFinder = JSONFinder::make(
                JSONFinder::T_STRING
                | JSONFinder::T_BOOL
                | JSONFinder::T_NULL
                | JSONFinder::T_NUMBER
            );
        }
        $this->value = self::$valueFinder->findEntries($data)[0]->value;
    }

    /**
     * check if this JSONValue's value is equal to the given value.
     * if <code>$value<code> is {@link JSONValue} then it's values will be compared
     *
     * @param $value JSONValue|string|int|float|bool|null
     * @param $strict bool if true, the value must be strictly equal to the given value
     * @return bool true if the value is equal to the given value
     */
    public function equals($value, bool $strict = false): bool
    {
        if ($value instanceof JSONValue) {
            $value = $value->value;
        }
        if ($strict) {
            return $this->value === $value;
        } else {
            return $this->value == $value;
        }
    }

    public function matches(string $regex): bool
    {
        return preg_match($regex, strval($this->value)) === 1;
    }
}
