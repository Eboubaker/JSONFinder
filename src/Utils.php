<?php

namespace Eboubaker\JSON;

/**
 * @author Eboubaker Bekkouche <eboubakkar@gmail.com>
 * @internal this class is not part of the public API
 */
final class Utils
{
    static function typeof($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }
        return gettype($value);
    }
}
