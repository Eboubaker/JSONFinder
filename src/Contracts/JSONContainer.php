<?php declare(strict_types=1);

namespace Eboubaker\JSON\Contracts;

/**
 * contains json entries
 */
interface JSONContainer extends JSONEntry, JSONEnumerable
{
    /**
     * returns the list of entries inside this container
     * @returns array<int|string,JSONEntry>
     */
    function entries(): array;

    /**
     * serialize the json with applied indentation
     * @param int $indent number of spaces to indent
     * @return string prettified json
     */
    function toReadableString(int $indent): string;
}
