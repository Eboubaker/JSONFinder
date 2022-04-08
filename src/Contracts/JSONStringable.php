<?php

namespace Eboubaker\JSON\Contracts;

interface JSONStringable
{
    /**
     * Convert this object to its syntactically VALID JSON representation.
     */
    public function toJSONString();
}
