<?php

namespace Eboubaker\JSON\Contracts;

use Eboubaker\JSON\JSONValue;

/**
 * The JSONStringable interface allows a {@link toJSONString}
 * method so that a class can change the behavior of json serialization.
 * if a class implements JSONStringable it will be allowed as a value for
 * {@link JSONValue} and when serialization is called this method will be
 * used as a json representation of the class that implements JSONStringable.
 */
interface JSONStringable
{
    /**
     * Convert this object to its syntactically VALID JSON value.<br>
     * There is no validation for the string that the method will return.
     */
    public function toJSONString(): string;
}
