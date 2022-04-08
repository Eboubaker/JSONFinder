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
     * @return bool|string|int|float|null|JSONArray|JSONObject
     */
    function value();

    /**
     * serialize the value into a json string, this is called when this entry is evaluated as string (.i.e strval())
     * @return string a valid json string which represents the entry
     */
    function __toString(): string;
}
