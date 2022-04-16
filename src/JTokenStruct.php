<?php

namespace Eboubaker\JSON;

use Eboubaker\JSON\Contracts\JSONEntry;

/**
 * @internal this class is not part of the public API
 * used by the parser to pass values between parser methods
 */
final class JTokenStruct
{
    public JSONEntry $entry;
    /**
     * @var int length of this token inside the raw json string
     */
    public int $length;

    /**
     * @internal this class is not part of the public API
     */
    public function __construct(JSONEntry $entry, int $length)
    {
        $this->entry = $entry;
        $this->length = $length;
    }
}
