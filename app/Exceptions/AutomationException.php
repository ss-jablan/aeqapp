<?php

namespace App\Exceptions;

use RuntimeException;

class AutomationException extends RuntimeException
{
    /**
     * @var int
     */
    const INVALID_LIST = 100;
    /**
     * @var int
     */
    const LEAD_OWNER_MISMATCH = 200;
}
