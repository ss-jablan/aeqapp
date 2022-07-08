<?php

namespace App\Exceptions;

use Throwable;

class RetryableAutomationException extends AutomationException
{
    /**
     * @var int
     */
    public $retryDelay;

    /**
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     * @param int            $retryDelay
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null, int $retryDelay = 0)
    {
        parent::__construct($message, $code, $previous);
        $this->retryDelay = $retryDelay;
    }
}
