<?php

namespace FpDbTest\Infrastructure\Database\Exception;

class InvalidIdentifierTypeException extends \Exception implements InvalidTypeExceptionInterface
{
    public function __construct(
        string          $message = 'Unable to escape identifier due to invalid type was passed.',
        int             $code = 0,
        \Throwable|null $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}