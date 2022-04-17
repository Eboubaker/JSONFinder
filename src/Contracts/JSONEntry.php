<?php declare(strict_types=1);

namespace Eboubaker\JSON\Contracts;

use Eboubaker\JSON\JSONArray;
use Eboubaker\JSON\JSONObject;
use Serializable;

/**
 * a single json entry it's value() can be one of {@link JSONArray}, {@link JSONObject}, string, number, bool, null.  <br>
 * an entry will return itself as a value if it is a {@link JSONObject} or {@link JSONArray}
 */
interface JSONEntry extends Serializable
{
    /**
     * returns value contained in the entry
     * @return bool|string|int|float|null|JSONArray|JSONObject|JSONStringable
     */
    function value();

    /**
     * serialize the value into a json string, this is called when this entry is evaluated as string (.i.e strval())
     * @return string a valid json string which represents the entry
     */
    function __toString(): string;

    /**
     * @return bool returns true if this entry contains other entries inside.
     */
    function isContainer(): bool;

    /**
     * check if the value of the entry matches a perl regular expression
     * @param string $regex a valid preg_regex pattern which starts and ends with a delimiter preferably '/'
     * @return bool returns true if the value() of this entry matches the given regex
     */
    function matches(string $regex): bool;

    /**
     * check if this JSONEntry's value is equal to the given value.
     * @param $other JSONEntry|string|int|float|bool|null
     * @param $strict bool if true, the value must be strictly equal to the given value with <code>===</code>
     * @return bool true if the value is equal to the given value
     */
    public function equals($other, bool $strict = false): bool;
}
