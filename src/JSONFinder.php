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
    /**
     * a non empty json array
     */
    public const T_ARRAY = 0x2;
    /**
     * json array with exactly zero elements inside: "[]"
     */
    public const T_EMPTY_ARRAY = 0x4;
    /**
     * a non empty object .i.e. {"age":20}
     */
    public const T_OBJECT = 0x8;
    /**
     * this is a flag that extends other flags, if set to true the finder will also match javascript objects.<br>
     * a javascript object is were value keys are not wrapped in quotes '"' (.i.e. {age:20}).
     */
    public const T_JS = 0x10;
    /**
     * object with exactly zero elements inside: "{}"
     */
    public const T_EMPTY_OBJECT = 0x20;
    /**
     * json string starts and ends with '"'
     */
    public const T_STRING = 0x40;
    /**
     * json number : 1,2,1e12,-1,-1.1,3e-12 ...
     */
    public const T_NUMBER = 0x80;
    /**
     * "true" or "false"
     */
    public const T_BOOL = 0x100;
    /**
     * "null"
     */
    public const T_NULL = 0x200;
    /**
     * all json types, will not include T_JS flag
     */
    public const T_ALL_JSON = 0x3EE;

    /**
     * switch all flags to true
     */
    private const T_ALL = 0x3FE;

    /**
     * @var int contains the flags of allowed types
     */
    private int $allowedTypes;

    /**
     * @param int $allowed_types allowed types that the parser should add to the resulting array of found tokens, does not affect the tokens that are nested in the array
     * @throws InvalidArgumentException if the array of allowed types contains an invalid type
     */
    public function __construct(int $allowed_types = JSONFinder::T_ARRAY | JSONFinder::T_OBJECT)
    {
        if ($allowed_types & ~JSONFinder::T_ALL || $allowed_types === 0) {
            throw new InvalidArgumentException("invalid type: $allowed_types");
        }
        $this->allowedTypes = $allowed_types;
    }

    /**
     * find all possible valid json tokens in the given string
     * @return JSONArray {@link JSONArray} of all found {@link JSONEntry}s in the string
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
                // move the offset to the end of the parsed value
                $offset += $value->length;
            }
        }
        return new JSONArray($values);
    }

    /**
     * @return bool true if the entry is allowed by the parser configuration
     */
    private function isAllowedEntry(JSONEntry $entry): bool
    {
        if ($entry instanceof JSONValue) {
            return is_string($entry->value) && $this->allowedTypes & JSONFinder::T_STRING
                || (is_numeric($entry->value) && !is_string($entry->value)) && $this->allowedTypes & JSONFinder::T_NUMBER
                || is_bool($entry->value) && $this->allowedTypes & JSONFinder::T_BOOL
                || is_null($entry->value) && $this->allowedTypes & JSONFinder::T_NULL;
        } else {
            if ($entry instanceof JSONObject) {
                if ($this->allowedTypes & JSONFinder::T_OBJECT && $entry->count() > 0) {
                    return true;
                } else {
                    return $this->allowedTypes & JSONFinder::T_EMPTY_OBJECT && $entry->count() === 0;
                }
            } else if ($entry instanceof JSONArray) {
                if ($this->allowedTypes & JSONFinder::T_ARRAY && $entry->count() > 0) {
                    return true;
                } else {
                    return $this->allowedTypes & JSONFinder::T_EMPTY_ARRAY && $entry->count() === 0;
                }
            } else {
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
                    $chars .= html_entity_decode("&#x$hex;", ENT_COMPAT, 'UTF-8');
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
     * read through the string characters until an unclosing '"' is found, return string that is between the first non escaped '"' and the last non escaped '"'
     * @param string $raw
     * @param int $len
     * @param int $from
     * @return JTokenStruct|null
     */
    private function parseJSObjectKey(string $raw, int $len, int $from): ?JTokenStruct
    {
        $chars = '';
        $i = $from;
        while ($i < $len) {
            // check if character is allowed in javascript variable declaration and add it to $chars if it is valid
            if (ctype_alpha($raw[$i]) || (ctype_digit($raw[$i]) && $chars !== '') || $raw[$i] === '_' || $raw[$i] === '$') {
                $chars .= $raw[$i];
                $i++;
            } else {
                // check for a sneaky case where there is a space in the key name
                // for example this key pair: 'abd c: "value"'
                $j = $i;
                while ($j < $len && $raw[$j] === ' ') {
                    $j++;
                }
                if ($j < $len && $raw[$j] !== ':') {
                    return null;
                }
                // found a valid key name
                break;
            }
        }
        if ($chars !== '' && !$this->isJavaScriptKeyword($chars, true)) {
            return new JTokenStruct(new JSONValue($chars), $i - $from);
        } else {
            return null;
        }
    }

    /**
     * returns true if str is a reserved javascript keyword
     * the logic is from ECMAScript 5.1 spec: http://es5.github.io/x7.html#x7.6.1
     */
    private function isJavaScriptKeyword(string $str, bool $strict = false): bool
    {
        if (in_array($str, array(
            // KEYWORDS
            'break', 'case', 'catch', 'class', 'continue', 'debugger', 'default', 'delete', 'do', 'else', 'finally', 'for', 'function', 'if', 'in', 'instanceof', 'new', 'return', 'switch', 'this', 'throw', 'try', 'typeof', 'var', 'void', 'while', 'with', 'class', 'enum', 'export', 'extends', 'import', 'super',
            // BOOLEAN
            'true', 'false',
            // NULL
            'null'))) {
            return true;
        }
        // strict mode reserved keywords
        return $strict && in_array($str, array('implements', 'interface', 'let', 'package', 'private', 'protected', 'public', 'static', 'yield'));
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
        if ($number === '') {// should not happen, but just in case something went wrong above, or if for some reason $i was at the end of the string
            return null;
        }
        $lastChar = $number[strlen($number) - 1];
        if ($lastChar === 'e' || $lastChar === 'E' || $lastChar === '.' || $lastChar === '+' || $lastChar === '-') {
            // unexpected end of number
            return null;
        }
        return new JTokenStruct(new JSONValue($foundDot ? floatval($number) : intval($number)), $i - $from);
    }

    private function parseArray(string $raw, int $len, int $from): ?JTokenStruct
    {
        if ($raw[$from] !== "[") {
            // not start of array
            return null;
        }
        if ($from + 1 >= $len) {
            // array not closed
            return null;
        }
        $values = [];
        $i = 1 + $from;
        $lastWasEntry = false;
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
                    // there are two consecutive commas which is invalid
                    return null;
                }
                $lastWasComma = true;
                $lastWasEntry = false;
                $i++;
            } else {
                $lastWasComma = false;
                if ($lastWasEntry) {
                    // comma expected between entries
                    return null;
                }
                $token = $this->parse($raw, $len, $i);
                if ($token === null) {
                    // invalid value
                    return null;
                }
                $values[] = $token->entry;
                $i += $token->length;
                $lastWasEntry = true;
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
        if ($from + 1 >= $len) {
            // object not closed
            return null;
        }
        $values = [];
        $i = 1 + $from;
        $lastWasComma = false;
        $lastWasEntry = false;
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
                    // there are two consecutive commas which is invalid
                    return null;
                }
                $lastWasComma = true;
                $lastWasEntry = false;
                $i++;
            } else {// parse key-value pair
                $lastWasComma = false;
                if ($lastWasEntry) {
                    // comma expected between entries
                    return null;
                }
                $keyToken = null;
                if ($raw[$i] === '"') {
                    // start of json key
                    $jsonKey = $this->parseString($raw, $len, $i);
                    if ($jsonKey !== null && $jsonKey->entry instanceof JSONValue && is_string($jsonKey->entry->value)) {
                        // valid json key
                        $keyToken = $jsonKey;
                    }
                } else if ($this->allowedTypes & JSONFinder::T_JS) {
                    $jsKey = $this->parseJSObjectKey($raw, $len, $i);
                    if ($jsKey !== null && $jsKey->entry instanceof JSONValue && is_string($jsKey->entry->value)) {
                        // valid js key
                        $keyToken = $jsKey;
                    }
                }
                if ($keyToken === null) {
                    // invalid key
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
                $values[strval($keyToken->entry->value())] = $valueToken->entry;
                $i += $valueToken->length;
                $lastWasEntry = true;
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
     * @return int new index where character is not a json whitespace
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
}
