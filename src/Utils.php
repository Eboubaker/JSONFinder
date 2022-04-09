<?php

namespace Eboubaker\JSON;

/**
 * @author Eboubaker Bekkouche <eboubakkar@gmail.com>
 * @internal this class is not part of the public API
 */
final class Utils
{
    /**
     * returns true if $enumerable contains a string key. and false if $enumerable keys are all integers
     */
    static function is_associative($enumerable): bool
    {
        foreach ($enumerable as $key => $v) {
            if (!is_int($key)) {
                return true;
            }
        }
        return false;
    }
}
