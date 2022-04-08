<?php

namespace Eboubaker\JSON;

/**
 * @author Eboubaker Bekkouche <eboubakkar@gmail.com>
 * @internal
 */
final class Utils
{
    /**
     * returns true if array contains a string key. and false if array keys are all integers
     */
    static function is_associative(array $array): bool
    {
        for ($i = 0, $len = count($array); $i < $len; $i++) {
            if (!is_int($array[$i])) {
                return true;
            }
        }
        return false;
    }
}
