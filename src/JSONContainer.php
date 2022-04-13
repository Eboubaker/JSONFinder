<?php

namespace Eboubaker\JSON;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Eboubaker\JSON\Contracts\JSONEntry;
use Eboubaker\JSON\Contracts\JSONStringable;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use RecursiveArrayIterator;

/**
 * shared logic between {@link JSONArray} and {@link JSONObject}
 * @internal
 */
abstract class JSONContainer implements JSONEntry, ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @var array<int|string,JSONEntry>
     */
    protected array $entries;


    /**
     * @param $entry mixed|JSONEntry
     * @param $key int|string
     * @throws InvalidArgumentException if entry is not allowed.
     */
    protected function addEntry($entry, $key)
    {
        if (!$entry instanceof JSONEntry) {
            if ($entry instanceof JSONStringable) {
                $this->entries[$key] = new JSONValue($entry);
            } else if (is_array($entry)) {
                if (Utils::is_associative($entry)) {
                    $this->entries[$key] = new JSONObject($entry);
                } else {
                    $this->entries[$key] = new JSONArray($entry);
                }
            } else if (is_object($entry)) {
                $this->entries[$key] = new JSONObject($entry);
            } else {
                $this->entries[$key] = new JSONValue($entry);
            }
        } else {
            $this->entries[$key] = $entry;
        }
    }

    public function isContainer(): bool
    {
        return true;
    }

    /**
     * json container always returns itself
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function value()
    {
        return $this;
    }

    /**
     * query the number of all stored/nested values
     */
    public function countAll(): int
    {
        $count = 0;
        foreach ($this->entries as $entry) {
            if ($entry instanceof JSONContainer) {
                $count += $entry->countAll();
            } else {
                $count++;
            }
        }
        return $count;
    }

    /**
     * returns an iterator of all nested {@link JSONValue}s<br>
     * keys returned by the iterator are the path of the nested values separated by dots
     * @return Generator<JSONValue>
     */
    public function values(): Generator
    {
        foreach ($this->entries as $key => $entry) {
            if ($entry instanceof JSONContainer) {
                foreach ($entry->values() as $k => $value) {
                    yield $key . '.' . $k => $value;
                }
            } else {// it must be JSONValue
                yield $key => $entry;
            }
        }
    }

    /**
     * returns count of entries
     */
    public function count(): int
    {
        return count($this->entries);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->entries[$offset]);
    }

    public function offsetGet($offset)
    {
        // if you are not sure if the container has the key use isset($obj[$offset]) before accessing to
        // avoid Undefined index exception
        return $this->entries[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if ($value instanceof JSONEntry) {
            $this->entries[$offset] = $value;
        } else {
            $this->entries[$offset] = new JSONValue($value);
        }
        $this->entries[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->entries[$offset]);
    }

    public function getIterator(): ArrayIterator
    {
        return new RecursiveArrayIterator($this->entries);
    }

    /**
     * returns the list of entries inside this container
     * @returns array<int|string,JSONEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * converts the container structure to a nested associative array of primitive types
     * @return array<string|int, bool|string|int|float|null|array> associative or indexed array of primitive types or other associative or indexed arrays
     */
    public function assoc(): array
    {
        $result = [];
        foreach ($this->entries as $key => $entry) {
            if ($entry instanceof JSONContainer) {
                $result[$key] = $entry->assoc();
            } else {// it must be JSONValue
                $result[$key] = $entry->value();
            }
        }
        return $result;
    }

    public function serialize(): string
    {
        return strval($this);
    }

    /**
     * find entries that match the given path.
     * the path is a dot notation path which accepts wildcards of "<b><code>*</code></b>" and "<b><code>**</code></b>". path segments are separated by a dot "<b>.</b>"<br>
     * if the path ends with a "<b><code>**</code></b>" wildcard, the result will be all nested {@link JSONValue}s of the preceding path segments.<br>
     * if the path ends with a "<b><code>*</code></b>" wildcard, the result will be all entries of the preceding path segments.
     * @param string $path the path to search for
     * @param Closure|null $matcher optional matcher function to filter the results, it will be called with the found {@link JSONValue} as the first parameter and should return a boolean value to indicate if the entry should be included in the result or not
     * @return array<string,JSONEntry>|false returns array of found entries or false if arguments are invalid
     */
    public function get(string $path, Closure $matcher = null)
    {
        if (!$this->validatePathString($path)) return false;
        return $this->internal_get_path(explode('.', $path), $matcher);
    }

    private function validatePathString(?string $path): bool
    {
        if (!$path
            || strpos($path, '..') !== false
            || strpos($path, '**.*') !== false) {
            return false;
        }
        $segments = explode('.', $path);
        foreach ($segments as $segment) {
            if (!is_string($segment)) {
                return false;
            }
        }
        if (empty($segments)) return false;
        return true;
    }

    /**
     * gets results of a single path
     */
    private function internal_get_path(array $segments, ?Closure $matcher): array
    {
        $result = [];
        $segment = $segments[0];
        array_shift($segments);// next segments will be passed to children if needed
        $is_last_segment = empty($segments);
        if ($segment === '**') {
            if ($is_last_segment) {
                // add all nested values
                foreach ($this->values() as $key => $value) {
                    if ($matcher != null && !$matcher($value)) continue;// matcher did not accept it, skip it.
                    $result[$key] = $value;
                }
            } else {
                // add all values found by containers that contains the next segments
                foreach ($this->entries as $entryKey => $entry) {
                    if ($entry instanceof JSONContainer) {
                        foreach ($entry->internal_get_path($segments, $matcher) as $key => $resultEntry) {
                            $result[$entryKey . '.' . $key] = $resultEntry;
                        }
                    }
                }
                // add all values found by other deep containers that contains the next segments
                foreach ($this->entries as $entryKey => $entry) {
                    if ($entry instanceof JSONContainer) {
                        foreach ($entry->internal_get_path(['**', ...$segments], $matcher) as $key => $resultEntry) {
                            $result[$entryKey . '.' . $key] = $resultEntry;
                        }
                    }
                }
            }
        } else if ($segment === '*') {
            if ($is_last_segment) {
                // last segment, add all entries
                foreach ($this->entries as $key => $value) {
                    if ($matcher != null && !$matcher($value)) continue;// matcher did not accept it, skip it
                    $result[$key] = $value;
                }
            } else {
                // not last segment, add all values found by containers that contains the next segments
                foreach ($this->entries as $entryKey => $entry) {
                    if ($entry instanceof JSONContainer) {
                        foreach ($entry->internal_get_path($segments, $matcher) as $key => $resultEntry) {
                            $result[$entryKey . '.' . $key] = $resultEntry;
                        }
                    }
                }
            }
        } else {
            // check if the current segment exists in this container as an entry
            foreach ($this->entries as $entryKey => $entry) {
                if ($segment === $entryKey) {
                    // entry was found, check if it is the last segment or not
                    if ($is_last_segment) {
                        // last segment, add it if the matcher accepted it.
                        if ($matcher != null && !$matcher($entry)) {
                            break;// was last segment, but matcher rejected it, so break the loop
                        }
                        $result[$entryKey] = $entry;
                    } else {
                        // not the last segment, pass the next segments to the entry and add the results
                        if ($entry instanceof JSONContainer) {
                            foreach ($entry->internal_get_path($segments, $matcher) as $key => $resultEntry) {
                                $result[$entryKey . '.' . $key] = $resultEntry;
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * returns list of entries that are {@link JSONObject} or {@link JSONArray}
     * @return JSONContainer[]
     */
    public function containers(): array
    {
        $results = [];
        foreach ($this->entries as $key => $entry) {
            if ($entry instanceof JSONContainer) {
                $results[$key] = $entry;
            }
        }
        return $results;
    }

    /**
     * returns iterator of entries that are {@link JSONObject} or {@link JSONArray}
     * @return Generator<JSONArray|JSONObject>
     */
    public function containersIterator(): Generator
    {
        foreach ($this->entries as $key => $entry) {
            if ($entry instanceof JSONContainer) {
                yield $key => $entry;
            }
        }
    }

    /**
     * serialize the json with applied indentation
     * @param int $indent number of spaces to indent
     * @return string returns the prettified json
     */
    abstract function toReadableString(int $indent): string;
}
