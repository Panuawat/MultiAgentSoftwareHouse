<?php

namespace App\Exceptions;

use RuntimeException;

class TokenBudgetExceededException extends RuntimeException
{
    public function __construct(int $used, int $budget)
    {
        parent::__construct("Token budget exceeded: used {$used} of {$budget}.");
    }
}
