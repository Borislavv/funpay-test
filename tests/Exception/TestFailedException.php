<?php

namespace Tests\FpDbTest\Exception;

class TestFailedException extends \RuntimeException
{
    public function __construct(string $message = 'Tests failed.', int $code = 0, \Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}