<?php declare(strict_types=1);

namespace Eboubaker\JSON\Contracts;

use Eboubaker\JSON\JSONArray;
use Eboubaker\JSON\JSONObject;
use Eboubaker\JSON\JSONValue;
use Serializable;

/**
 * a single json entry it can be one of {@link JSONArray}, {@link JSONObject}, {@link JSONValue}.
 */
interface JSONEntry extends Serializable
{
    /**
     * returns value contained in the entry.<br>
     * if the entry is a {@link JSONArray} or {@link JSONObject} it will return itself.<br>
     * if the entry is a {@link JSONValue} it will return the value contained.
     * @return bool|string|int|float|null|JSONArray|JSONObject|JSONStringable
     */
    function value();

    /**
     * serialize the value into a json string, this is called when this entry is evaluated as string (.i.e strval())
     * @return string a valid json string which represents the entry
     */
    function __toString(): string;

    /**
     * @return bool returns true if this entry is JSONArray or JSONObject.
     */
    function isContainer(): bool;

    /**
     * check if the value of the entry matches a perl regular expression
     * @param string $regex a valid preg_regex pattern which starts and ends with a delimiter preferably <code>'/'<code>
     * @return bool returns true if the value() of this entry matches the given regex
     */
    function matches(string $regex): bool;

    /**
     * check if this JSONEntry's value is equal to the given value.<br>
     * note: this method is recursive, StackOverflow may occur if there are circular references
     * @param $other JSONEntry|string|int|float|bool|null
     * @param $strict bool if true, the value must be strictly equal to the given value with <code>===</code>
     * @return bool true if the value is equal to the given value
     */
    public function equals($other, bool $strict = false): bool;
}
