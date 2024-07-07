<?php

namespace FpDbTest\Infrastructure\Database\Exception;

class InvalidValueTypeException extends \Exception implements InvalidTypeExceptionInterface
{
    public function __construct(
        string          $message    = 'Unable to escape value due to invalid value type passed.',
        int             $code       = 0,
        \Throwable|null $previous   = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}