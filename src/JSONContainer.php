<?php

namespace Eboubaker\JSON;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Eboubaker\JSON\Contracts\JSONEntry;
use Eboubaker\JSON\Contracts\JSONStringable;
use Generator;
use IteratorAggregate;
use RecursiveArrayIterator;

/**
 * shared logic between {@link JSONArray} and {@link JSONObject}
 */
abstract class JSONContainer implements JSONEntry, ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @var array<int|string,JSONEntry>
     */
    protected array $entries;


    /**
     * wrap the value in a json entry.
     * @param $value mixed
     * @return JSONEntry the wrapped value as JSONEntry
     * @see JSONArray
     * @see JSONEntry
     */
    public static function toEntry($value): JSONEntry
    {
        if (!($value instanceof JSONEntry)) {
            if ((is_iterable($value) || is_object($value)) && !($value instanceof JSONStringable)) {
                return self::iterable_to_container($value);
            } else {
                return new JSONValue($value);
            }
        } else {
            return $value;
        }
    }

    /**
     * @param $value iterable|object|array
     * @return JSONContainer
     */
    private static function iterable_to_container($value): JSONContainer
    {
        $list = [];
        $has_string_key = false;
        foreach ($value as $key => $entry) {
            $list[$key] = $entry;
            if (!$has_string_key && is_string($key)) {
                $has_string_key = true;
                if ($value instanceof \ArrayAccess || is_array($value)) {
                    $list = $value;
                    break;
                } else if (is_object($value)) {
                    $list = get_object_vars($value);
                    break;
                }
                // it is an iterator... continue pulling values from the iterator
            }
        }
        if (empty($list)) {
            if (is_array($value)) {
                return new JSONArray();
            } else {
                return new JSONObject();
            }
        } else {
            if ($has_string_key) {
                return JSONObject::from($list);
            } else {
                return JSONArray::from($list);
            }
        }
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->entries[] = $value;
        } else {
            $this->entries[$offset] = $value;
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
     * returns the first entry or null if empty.
     */
    public function first(): ?JSONEntry
    {
        return reset($this->entries) ?: null;
    }

    /**
     * returns the last entry or null if empty.
     */
    public function last(): ?JSONEntry
    {
        return end($this->entries) ?: null;
    }

    /**
     * @return bool returns true if entries size is 0.
     */
    public function isEmpty(): bool
    {
        return count($this->entries) === 0;
    }

    public function matches(string $regex): bool
    {
        // a JSONContainer is not a JSONValue and cannot match a regex.
        return false;
    }

    /**
     * true if a value found that matches the path. accepts wildcards <code>*</code> and <code>**</code>.
     * @param string $path path to the value.
     * @return bool true if the path exists or false if the path was not found or the path has invalid syntax (contains ".." or <code>"**.*"</code>)
     */
    public function has(string $path): bool
    {
        // get() will return null if the path is not found.
        return null !== $this->get($path);
    }

    /**
     * find first JSONEntry that matches the dot notation path. accepts wildcards <code>*</code> and <code>**</code>.<br>
     * @param string $path path to the value.
     * @param $default mixed|callable default value to return if no result was found, can be a callback.
     * @return JSONArray|JSONObject|JSONValue|JSONEntry|null returns the found JSONEntry or default value if not found or if path has invalid syntax (contains ".." or <code>"**.*"</code>).
     */
    public function get(string $path, $default = null)
    {
        if (!$path
            || strpos($path, '..') !== false
            || strpos($path, '**.*') !== false) {
            return null;
        }
        $segments = explode('.', $path);
        if (empty($segments)) return null;
        return $this->internal_get(explode('.', $path))
            ?? ($default instanceof Closure ? $default() : $default);
    }

    /**
     * find entries that match the given path.
     * the path is a dot notation path which accepts wildcards of "<b><code>*</code></b>" and "<b><code>**</code></b>". path segments are separated by a dot "<b>.</b>"<br>
     * if the path ends with a "<b><code>**</code></b>" wildcard, the result will be all nested {@link JSONValue}s of the preceding path segments.<br>
     * if the path ends with a "<b><code>*</code></b>" wildcard, the result will be all entries of the preceding path segments.
     * @param string $path the path to search for
     * @param bool $paths_as_keys if true, the result will be an associative array of paths as keys and {@link JSONValue}s as values.
     * @param Closure|null $filter optional filter function to filter the results, it will be called with the found {@link JSONValue} as the first parameter and should return a boolean value to indicate if the entry should be included in the result or not
     * @return JSONEntry[]|false returns array of found entries or false if path syntax is invalid (contains ".." or <code>"**.*"</code>)
     */
    public function getAll(string $path, Closure $filter = null, bool $paths_as_keys = false)
    {
        if (!$path
            || strpos($path, '..') !== false
            || strpos($path, '**.*') !== false) {
            return false;
        }
        $segments = explode('.', $path);
        if (empty($segments)) return false;
        return $this->internal_getAll(explode('.', $path), $filter, $paths_as_keys);
    }

    /**
     * gets single result of a single path
     */
    private function internal_get(array $segments): ?JSONEntry
    {
        $segment = $segments[0];
        array_shift($segments);// next segments will be passed to children if needed
        $is_last_segment = empty($segments);
        if ($segment === '**') {
            if ($is_last_segment) {
                foreach ($this->values() as $entry) {
                    return $entry;
                }
            } else {
                foreach ($this->containersIterator() as $entry) {
                    $v = $entry->internal_get($segments);
                    if ($v !== null) {
                        return $v;
                    }
                }
                // add all values found by other deep containers that contains the next segments
                foreach ($this->containersIterator() as $entry) {
                    $v = $entry->internal_get(['**', ...$segments]);
                    if ($v !== null) {
                        return $v;
                    }
                }
            }
        } else if ($segment === '*') {
            if ($is_last_segment) {
                foreach ($this->entries as $entry) {
                    return $entry;
                }
            } else {
                foreach ($this->containersIterator() as $entryKey => $entry) {
                    $v = $entry->internal_get($segments);
                    if ($v !== null) {
                        return $v;
                    }
                }
            }
        } else {
            if (isset($this[$segment])) {
                $entry = $this[$segment];
                if ($is_last_segment) {
                    return $entry;
                } else {
                    if ($entry instanceof JSONContainer) {
                        return $entry->internal_get($segments);
                    }
                }
            }
        }
        return null;
    }

    /**
     * gets results of a single path
     */
    private function internal_getAll(array $segments, ?Closure $filter, bool $paths_as_keys): array
    {
        $result = [];
        $segment = $segments[0];
        array_shift($segments);// next segments will be passed to children if needed
        $is_last_segment = empty($segments);
        if ($segment === '**') {
            if ($is_last_segment) {
                // add all nested values
                foreach ($this->values() as $key => $value) {
                    if ($filter != null && !$filter($value)) continue;// value did not pass the filter, skip it...
                    if ($paths_as_keys) {
                        $result[$key] = $value;
                    } else {
                        $result[] = $value;
                    }
                }
            } else {
                // add all values found by containers that contains the next segments
                foreach ($this->containersIterator() as $entryKey => $entry) {
                    foreach ($entry->internal_getAll($segments, $filter, $paths_as_keys) as $key => $resultEntry) {
                        if ($paths_as_keys) {
                            $result[$entryKey . '.' . $key] = $resultEntry;
                        } else {
                            $result[] = $resultEntry;
                        }
                    }
                }
                // add all values found by other deep containers that contains the next segments
                foreach ($this->containersIterator() as $entryKey => $entry) {
                    foreach ($entry->internal_getAll(['**', ...$segments], $filter, $paths_as_keys) as $key => $resultEntry) {
                        if ($paths_as_keys) {
                            $result[$entryKey . '.' . $key] = $resultEntry;
                        } else {
                            $result[] = $resultEntry;
                        }
                    }
                }
            }
        } else if ($segment === '*') {
            if ($is_last_segment) {
                // last segment, add all entries
                foreach ($this->entries as $key => $value) {
                    if ($filter != null && !$filter($value)) continue;// value did not pass the filter, skip it...
                    if ($paths_as_keys) {
                        $result[$key] = $value;
                    } else {
                        $result[] = $value;
                    }
                }
            } else {
                // not last segment, add all values found by containers that contains the next segments
                foreach ($this->containersIterator() as $entryKey => $entry) {
                    foreach ($entry->internal_getAll($segments, $filter, $paths_as_keys) as $key => $resultEntry) {
                        if ($paths_as_keys) {
                            $result[$entryKey . '.' . $key] = $resultEntry;
                        } else {
                            $result[] = $resultEntry;
                        }
                    }
                }
            }
        } else {
            // check if the current segment exists in this container as an entry
            if (isset($this[$segment])) {
                $entry = $this[$segment];
                // entry was found, check if it is the last segment or not
                if ($is_last_segment) {
                    // last segment, add it if it passes the filter.
                    if ($filter == null || $filter($entry)) {
                        // filter was null or value did not pass the filter.
                        if ($paths_as_keys) {
                            $result[$segment] = $entry;
                        } else {
                            $result[] = $entry;
                        }
                    }
                } else {
                    // not the last segment, pass the next segments to the entry if it is container and add the results
                    if ($entry instanceof JSONContainer) {
                        foreach ($entry->internal_getAll($segments, $filter, $paths_as_keys) as $key => $resultEntry) {
                            if ($paths_as_keys) {
                                $result[$segment . '.' . $key] = $resultEntry;
                            } else {
                                $result[] = $resultEntry;
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * find a single {@link JSONArray} or {@link JSONObject} which contains all the provided paths.<br>
     * paths can be in dot notation and accepts wildcards <code>'*'<code> or <code>'**'<code><br>
     * @param $paths string|array<int,string>|array<string|callable> path or array of paths that must be found in the target.
     * if the path is represented as a key in the array then it's value must be a callback function and it will be
     * used to filter the path value. if the callback returns true then the path will be considered as found during search.
     *
     * @return JSONContainer|null|false return the first found array or object which contains all the
     * provided paths or null if not found, returns false if one of the paths is invalid.
     * @noinspection PhpDocDuplicateTypeInspection
     */
    public function search($paths)
    {
        $paths = (array)$paths;
        foreach ($paths as $k => $key) {
            if (is_integer($k)) {
                if (!is_string($key)) {
                    // path must be string
                    return false;
                }
                unset($paths[$k]);
                $paths[$key] = null;// set filter to null
            } else if (!is_callable($key)) {
                // expected a callback filter
                return false;
            }
        }
        foreach ($paths as $path => $__) {
            if (!$path
                || strpos($path, '..') !== false
                || strpos($path, '**.*') !== false) {
                return false;
            }
            $segments = explode('.', $path);
            if (empty($segments)) return false;
        }
        return $this->internal_search($paths);
    }

    /**
     * @param array $paths
     * @return JSONEntry|null
     * @internal
     */
    private function internal_search(array $paths): ?JSONContainer
    {
        // we must have all the paths in this entry
        $foundPathsCount = 0;
        foreach ($paths as $path => $filter) {
            if (empty($this->getAll($path, $filter))) {
                // path not found
                break;
            } else {
                $foundPathsCount++;
            }
        }
        if ($foundPathsCount === count($paths)) {
            // this is the target
            return $this;
        }
        // not all paths were found, check if my children have the target
        foreach ($this->containersIterator() as $entry) {
            $found = $entry->internal_search($paths);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    /**
     * check if this container contains the same entry keys and values as <code>$other</code>
     * @param $other JSONContainer|mixed the other array or object to compare with
     * @return bool returns true if all keys and values are the same with $other, otherwise false.
     */
    public function equals($other, bool $strict = true): bool
    {
        if (!($other instanceof JSONContainer)) return false;
        if (count($this) !== count($other)) return false;
        foreach ($this->entries as $key => $entry) {
            if (!$other->offsetExists($key)) return false;
            if ($entry instanceof JSONContainer) {
                if (!$entry->equals($other[$key])) {
                    return false;
                }
            } else {// should be and must be a JSONValue
                /** @var $entry JSONValue */
                if (!$entry->equals($other[$key], $strict)) {
                    return false;
                }
            }
        }
        return true;// empty or everything is equal
    }

    /**
     * serialize the json with applied indentation
     * @param int $indent number of spaces to indent
     * @return string returns the prettified json
     */
    abstract function toReadableString(int $indent): string;
}
