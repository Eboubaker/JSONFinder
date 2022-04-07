<?php

namespace Eboubaker\JSON;

use Eboubaker\JSON\Contracts\JSONEntry;
use InvalidArgumentException;

/**
 * can find all valid json values in a string of mixed text
 * @author eboubaker bekkouche <eboubakker@gmail.com>
 */
class JSONFinder
{
    public const T_ARRAY = 1;
    /**
     * array with zero elements inside
     */
    public const T_EMPTY_ARRAY = 1;
    public const T_OBJECT = 2;
    /**
     * object with zero elements inside
     */
    public const T_EMPTY_OBJECT = 2;
    public const T_STRING = 3;
    public const T_NUMBER = 4;
    public const T_BOOLEAN = 5;
    public const T_NULL = 6;
    public const T_ALL = [self::T_ARRAY, self::T_OBJECT, self::T_STRING, self::T_NUMBER, self::T_BOOLEAN, self::T_NULL];

    private bool $T_OBJECT_ALLOWED = false;
    private bool $T_EMPTY_OBJECT_ALLOWED = false;
    private bool $T_ARRAY_ALLOWED = false;
    private bool $T_EMPTY_ARRAY_ALLOWED = false;
    private bool $T_STRING_ALLOWED = false;
    private bool $T_NUMBER_ALLOWED = false;
    private bool $T_BOOLEAN_ALLOWED = false;
    private bool $T_NULL_ALLOWED = false;

    /**
     * @param int[]|int $allowed_types array of allowed types that the parser should not add to the resulting array of found tokens, does not affect the tokens that are nested in the array
     * @throws InvalidArgumentException if the array of allowed types contains an invalid type
     */
    public function __construct($allowed_types = [JSONFinder::T_ARRAY, JSONFinder::T_OBJECT])
    {
        if (!is_array($allowed_types)) {
            if ($allowed_types === JSONFinder::T_ALL) {
                allow_all_types:
                $this->T_OBJECT_ALLOWED = true;
                $this->T_EMPTY_OBJECT_ALLOWED = true;
                $this->T_ARRAY_ALLOWED = true;
                $this->T_EMPTY_ARRAY_ALLOWED = true;
                $this->T_STRING_ALLOWED = true;
                $this->T_NUMBER_ALLOWED = true;
                $this->T_BOOLEAN_ALLOWED = true;
                $this->T_NULL_ALLOWED = true;
            } else {
                throw new InvalidArgumentException("Invalid type provided for the allowed types parameter");
            }
        } else {
            foreach ($allowed_types as $t) {
                if ($t === JSONFinder::T_OBJECT) $this->T_OBJECT_ALLOWED = true;
                else if ($t === JSONFinder::T_EMPTY_OBJECT) $this->T_EMPTY_OBJECT_ALLOWED = true;
                else if ($t === JSONFinder::T_ARRAY) $this->T_ARRAY_ALLOWED = true;
                else if ($t === JSONFinder::T_EMPTY_ARRAY) $this->T_EMPTY_ARRAY_ALLOWED = true;
                else if ($t === JSONFinder::T_STRING) $this->T_STRING_ALLOWED = true;
                else if ($t === JSONFinder::T_NUMBER) $this->T_NUMBER_ALLOWED = true;
                else if ($t === JSONFinder::T_BOOLEAN) $this->T_BOOLEAN_ALLOWED = true;
                else if ($t === JSONFinder::T_NULL) $this->T_NULL_ALLOWED = true;
                else if ($t === JSONFinder::T_ALL) goto allow_all_types;
                else throw new InvalidArgumentException("Invalid type $t");
            }
        }
    }

    /**
     * find all possible valid json tokens in the given string
     * @return JSONArray all found {@link JSONEntries} in the string inside a {@link JSONArray}
     */
    public function findJsonEntries(string $text): JSONArray
    {
        $values = [];
        $offset = 0;
        $len = strlen($text);
        while ($offset < $len) {
            $value = $this->parse($text, $len, $offset);
            if ($value === null) {
                // invalid json, move the offset and try again
                $offset++;
            } else {
                if ($this->isAllowedEntry($value->entry)) {
                    // only add the entry if it is allowed by the configuration
                    $values[] = $value->entry;
                }
                $offset += $value->length;
            }
        }
        return new JSONArray($values);
    }

    #region private-logic

    /**
     * @return bool true if the entry is allowed by the parser configuration
     */
    private function isAllowedEntry(JSONEntry $entry): bool
    {
        if ($entry instanceof JSONValue) {
            return is_string($entry->value) && $this->T_STRING_ALLOWED
                || is_numeric($entry->value) && $this->T_NUMBER_ALLOWED
                || is_bool($entry->value) && $this->T_BOOLEAN_ALLOWED
                || is_null($entry->value) && $this->T_NULL_ALLOWED;
        } else {
            if($entry instanceof JSONObject && $this->T_OBJECT_ALLOWED){
                return $entry->count() > 0 || $this->T_EMPTY_OBJECT_ALLOWED;
            }else if($entry instanceof JSONArray && $this->T_ARRAY_ALLOWED){
                return $entry->count() > 0 || $this->T_EMPTY_ARRAY_ALLOWED;
            }else{
                return false;
            }
        }
    }

    /**
     * returns the first found json entry in the given string or null if no valid entry was found
     * @param string $raw
     * @param int $len
     * @param int $from
     * @return JSONValue|JSONArray|JSONObject
     */
    private function parse(string $raw, int $len, int $from): ?JTokenStruct
    {
        //@formatter:off
        return $this->parseString($raw, $len, $from)
            ?: $this->parseObject($raw, $len, $from)
            ?: $this->parseArray($raw, $len, $from)
            ?: $this->parseNumber($raw, $len, $from)
            ?: $this->parseBoolean($raw, $from)
            ?: $this->parseNull($raw, $from)
            ?: null;
        //@formatter:on
    }

    private function parseNull(string $raw, int $from): ?JTokenStruct
    {
        if (substr($raw, $from, 4) === 'null') {
            return new JTokenStruct(new JSONValue(null), 4);
        } else {
            return null;
        }
    }

    private function parseBoolean(string $raw, int $from): ?JTokenStruct
    {
        if (substr($raw, $from, 4) === 'true') {
            return new JTokenStruct(new JSONValue(true), 4);
        } else if (substr($raw, $from, 5) === 'false') {
            return new JTokenStruct(new JSONValue(false), 5);
        }
        return null;
    }

    /**
     * read through the string characters until an unclosing '"' is found, return string that is between the first non escaped '"' and the last non escaped '"'
     * @param string $raw
     * @param int $len
     * @param int $from
     * @return JTokenStruct|null
     */
    private function parseString(string $raw, int $len, int $from): ?JTokenStruct
    {
        if ($raw[$from] !== '"') {
            return null;
        }
        $i = 1 + $from;
        $chars = '';
        while ($i < $len) {
            if ($raw[$i] === '"') {
                return new JTokenStruct(new JSONValue($chars), ($i - $from) + 1);
            } else if ($raw[$i] === '\\' && $i + 1 < $len) {
                // parse codepoint chars(\u...., \t, \n, \r, \f, \b, \/, \\, \")
                $i++;
                $code = $raw[$i];
                if ($code === 'u') {
                    $hex = substr($raw, $i + 1, 4);
                    if (!ctype_xdigit($hex) || strpos($hex, '.0') !== false) {
                        // invalid hex char-code
                        return null;
                    }
                    $chars .= chr(hexdec($hex));
                    $i += 5;
                } //@formatter:off
                else if($code === '"') { $chars .= '"' ;$i++; }
                else if($code === '\\'){ $chars .= "\\";$i++; }
                else if($code === '/') { $chars .= "/" ;$i++; }
                else if($code === 'n') { $chars .= "\n";$i++; }
                else if($code === 'r') { $chars .= "\r";$i++; }
                else if($code === 't') { $chars .= "\t";$i++; }
                else if($code === 'b') { $chars .= "\b";$i++; }
                else if($code === 'f') { $chars .= "\f";$i++; }
                //@formatter:on
                else {
                    // invalid escaped char
                    return null;
                }
            } else {
                $chars .= $raw[$i];
                $i++;
            }
        }
        return null;
    }

    /**
     * try parse a json number
     */
    private function parseNumber(string $raw, int $len, int $from): ?JTokenStruct
    {
        if ($raw[$from] !== "+" && $raw[$from] !== "-" && !is_numeric($raw[$from])) {
            return null;
        }
        $eSign = '';
        $intSign = '';
        $foundDot = false;
        $foundE = false;
        $number = '';
        $i = $from;
        while ($i < $len) {
            $char = $raw[$i];
            if ($char === '.') {
                if ($foundDot) {
                    return null;
                }
                $foundDot = true;
                $number .= $char;
            } else if ($char === 'e' || $char === 'E') {
                $number .= $char;
                if ($foundE) {
                    // invalid number (two e's found)
                    return null;
                }
                $foundE = true;
            } else if ($char === '+' || $char === '-') {
                $number .= $char;
                if ($foundE) {
                    if ($eSign !== '') {
                        // invalid number (two e's signs found)
                        return null;
                    } else {
                        $eSign = $char;
                    }
                } else {
                    if ($intSign !== '') {
                        // invalid number (two signs found)
                        return null;
                    }
                    $intSign = $char;
                }
            } else if (is_numeric($char)) {
                $number .= $char;
            } else {// end of number
                break;
            }
            $i++;
        }
        if ($number === '') {// should not happen, but just in case something went wrong above
            return null;
        }
        $lastChar = $number[strlen($number) - 1];
        if ($lastChar === 'e' || $lastChar === 'E' || $lastChar === '.' || $lastChar === '+' || $lastChar === '-') {
            // unexpected end of number
            return null;
        }
        return new JTokenStruct(new JSONValue($foundDot ? floatval($number) : intval($number)), ($i - $from) + 1);
    }

    private function parseArray(string $raw, int $len, int $from): ?JTokenStruct
    {
        if ($raw[$from] !== "[") {
            // not start of array
            return null;
        }
        if ($from+1 >= $len) {
            // array not closed
            return null;
        }
        $values = [];
        $i = 1 + $from;
        $lastWasComma = false;
        while ($i < $len) {
            $i = $this->skipWhitespaces($raw, $i, $len);
            if ($i >= $len) {
                // reached end of string and array was not closed
                return null;
            }
            if ($raw[$i] === ']') {
                if ($lastWasComma) {
                    // comma without a value after is incorrect
                    return null;
                }
                return new JTokenStruct(new JSONArray($values), ($i - $from) + 1);
            } else if ($raw[$i] === ',') {
                if ($lastWasComma) {
                    // there are two consecutive commas, which is invalid
                    return null;
                }
                $lastWasComma = true;
                $i++;
            } else {
                $lastWasComma = false;
                $token = $this->parse($raw, $len, $i);
                if ($token === null) {
                    // invalid value
                    return null;
                }
                $values[] = $token->entry;
                $i += $token->length;
            }
        }
        // we reached end of string and the array was not closed with ']'
        return null;
    }

    private function parseObject(string $raw, int $len, int $from): ?JTokenStruct
    {
        if ($raw[$from] !== "{") {
            // not start of object
            return null;
        }
        if ($from+1 >= $len) {
            // object not closed
            return null;
        }
        $values = [];
        $i = 1 + $from;
        $lastWasComma = false;
        while ($i < $len) {
            $i = $this->skipWhitespaces($raw, $i, $len);
            if ($i >= $len) {
                // reached end of string and object was not closed
                return null;
            }
            if ($raw[$i] === '}') {
                if ($lastWasComma) {
                    // comma without a key-value pair after is incorrect
                    return null;
                }
                return new JTokenStruct(new JSONObject($values), ($i - $from) + 1);
            } else if ($raw[$i] === ',') {
                if ($lastWasComma) {
                    // there are two consecutive commas, which is invalid
                    return null;
                }
                $lastWasComma = true;
                $i++;
            } else {// parse key-value pair
                $lastWasComma = false;
                if ($raw[$i] !== '"') {
                    // expected start of json key
                    return null;
                }
                $keyToken = $this->parseString($raw, $len, $i);
                if ($keyToken === null || (!$keyToken->entry instanceof JSONValue) || (!is_string($keyToken->entry->value) && !is_string($keyToken->entry->value))) {
                    // invalid json key, must be string
                    return null;
                }
                $i = $this->skipWhitespaces($raw, $i + $keyToken->length, $len);
                if ($i >= $len) {
                    // reached end of string and object was not closed
                    return null;
                }
                if ($raw[$i] !== ':') {
                    // expected colon (key-value pair)
                    return null;
                }
                $i++;
                $i = $this->skipWhitespaces($raw, $i, $len);
                if ($i >= $len) {
                    // reached end of string and object was not closed
                    return null;
                }
                $valueToken = $this->parse($raw, $len, $i);
                if ($valueToken === null) {
                    // invalid value
                    return null;
                }
                $values[strval($keyToken->entry->value)] = $valueToken->entry;
                $i += $valueToken->length;
            }
        }
        // we reached end of string and the object was not closed with '}'
        return null;
    }

    /**
     * \#[Pure]
     * @param string $raw raw json string
     * @param $i int start from this index
     * @param $len int length of raw json string
     * @return int new index where character is not whitespace
     */
    private static function skipWhitespaces(string $raw, int $i, int $len): int
    {
        while ($i < $len) {
            $char = $raw[$i];
            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $i++;
            } else {
                // char is not whitespace, per: https://www.json.org/img/whitespace.png
                break;
            }
        }
        // $raw[$i] is not whitespace, or reached end of string
        return $i;
    }
    #endregion
}
