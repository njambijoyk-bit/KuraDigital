<?php

namespace App\Exceptions;

use RuntimeException;

class UnbalancedEntryException extends RuntimeException
{
    public function __construct(float $debitTotal, float $creditTotal)
    {
        parent::__construct(
            "Journal entry is unbalanced: debits ({$debitTotal}) != credits ({$creditTotal})"
        );
    }
}
