<?php declare(strict_types=1);

namespace Eboubaker\JSON;

use Eboubaker\JSON\Contracts\JSONEntry;
use InvalidArgumentException;
use LogicException;

/**
 * a json primitive value it's value can be one of string, float, int, bool, null
 * @author eboubaker bekkouche <eboubakkar@gmail.com>
 */
class JSONValue implements JSONEntry
{
    /**
     * the value that this entry holds
     * @var bool|float|int|string|null
     */
    public $value;

    /**
     * @param $value bool|float|int|string|null
     * @throws InvalidArgumentException if the value is not one of the allowed types
     */
    public function __construct($value)
    {
        if ($value === null || is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
            $this->value = $value;
        } else {
            throw new InvalidArgumentException("value must be a primitive type, \"" . gettype($value) . "\" given");
        }
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
            if (!strpos($string, '.') && !strpos($string, 'e') && !strpos($string, 'E')) {
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
                else if ($char === "\n") $string .= '\\n';
                else if ($char === "\r") $string .= '\\r';
                else if ($char === "\t") $string .= '\\t';
                else if (ord($char) === 8) $string .= '\\b';
                else if ($char === "\f") $string .= '\\f';
                else $string .= $char;
            }
            $string .= "\"";
            return $string;
        } else {
            throw new LogicException("json serialization error: unexpected primitive value type: \"" . gettype($this->value) . "\"");
        }
    }
    #endregion JSONEntry
}
