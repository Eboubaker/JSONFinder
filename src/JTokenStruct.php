<?php

namespace Eboubaker\JSON;

use Eboubaker\JSON\Contracts\JSONEntry;

/**
 * @internal used by the parser to pass values between it's functions.
 */
final class JTokenStruct
{
    public JSONEntry $entry;
    /**
     * @var int length of this token inside the raw json string
     */
    public int $length;

    /**
     * @internal
     */
    public function __construct(JSONEntry $entry, int $length)
    {
        $this->entry = $entry;
        $this->length = $length;
    }
}
