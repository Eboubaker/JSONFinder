<?php declare(strict_types=1);

namespace Eboubaker\JSON\Contracts;

/**
 * contains json entries
 */
interface JSONContainer extends JSONEntry, JSONEnumerable
{
    /**
     * returns a list of entries contained in the container,<br>
     * if the container is an object it will return a php object containing all the key-value pairs of the json object .<br>
     * if the container is an array it will return an indexed array of values
     * @return object<JSONEntry>|array<int,JSONEntry>
     */
    function entries();

    /**
     * serialize the json with applied indentation
     * @param int $indent number of spaces to indent
     * @return string prettified json
     */
    function toReadableString(int $indent): string;
}
