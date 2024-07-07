<?php

namespace FpDbTest\Infrastructure\Database;

use FpDbTest\Infrastructure\Database\Exception\InvalidTypeExceptionInterface;

interface DatabaseInterface
{
    /**
     * @throws InvalidTypeExceptionInterface
     */
    public function buildQuery(string $query, array $args = []): string;

    public function skip(): string;
}
