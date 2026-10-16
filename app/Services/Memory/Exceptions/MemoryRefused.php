<?php

namespace App\Services\Memory\Exceptions;

class MemoryRefused extends MemoryException
{
    /** @param list<string> $messages */
    public function __construct(array $messages)
    {
        parent::__construct('Dot.Memory refused the record: '.implode(' ', $messages));
    }
}
