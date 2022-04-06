<?php declare(strict_types=1);

namespace Eboubaker\JSON\Contracts;

use ArrayAccess;
use Countable;
use Generator;
use IteratorAggregate;

/**
 * enumerates json values in an associative or indexed array,
 * also can be accessed or looped like an array with foreach loops
 */
interface JSONEnumerable extends ArrayAccess, IteratorAggregate, Countable
{
    /**
     * return a nested associative array of primitive values
     * @return array<string|int, bool|string|int|float|null|array> associative or indexed array of primitive types or other associative or indexed arrays
     */
    function assoc(): array;

    /**
     * returns number of nested elements (number of primitive values stored)
     */
    function countContainedEntries(): int;

    /**
     * returns count of entries
     */
    function count(): int;

    /**
     * returns an iterator of all nested primitive values
     */
    function values(): Generator;
}
