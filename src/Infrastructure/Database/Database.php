<?php

namespace FpDbTest\Infrastructure\Database;

use FpDbTest\Infrastructure\Database\Exception\InvalidIdentifierTypeException;
use FpDbTest\Infrastructure\Database\Exception\InvalidValueTypeException;
use mysqli;

readonly class Database implements DatabaseInterface
{
    protected const string QUALIFIER_REGEX          = '/\?(d|f|a|#)?/';
    protected const string CONDITIONAL_QUALIFIER    = '/\{([^{}]+)\}/';
    protected const string SKIP                     = '__SKIP__';
    protected const string INT_QUALIFIER            = 'd';
    protected const string FLOAT_QUALIFIER          = 'f';
    protected const string ARRAY_QUALIFIER          = 'a';
    protected const string IDENTIFIER_QUALIFIER     = '#';
    protected const string NULL_STRING              = 'NULL';
    protected const string BOOL_TRUE_STRING         = '1';
    protected const string BOOL_FALSE_STRING        = '0';

    public function __construct(private mysqli $mysqli)
    {}

    /**
     * @throws InvalidValueTypeException
     * @throws InvalidIdentifierTypeException
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $query = $this->processConditionalBlocks($query, $args);

        $i = 0;
        return preg_replace_callback( static::QUALIFIER_REGEX, function ($matches) use (&$i, $args) {
            $type  = $matches[1] ?? null;
            $value = $args[$i++] ?? null;

            return match ($type) {
                static::INT_QUALIFIER           => is_null($value) ? static::NULL_STRING : intval($value),
                static::FLOAT_QUALIFIER         => is_null($value) ? static::NULL_STRING : floatval($value),
                static::ARRAY_QUALIFIER         => $this->processArray($value),
                static::IDENTIFIER_QUALIFIER    => $this->escapeIdentifier($value),
                default                         => $this->escapeValue($value),
            };
        }, $query);
    }

    public function skip(): string
    {
        return self::SKIP;
    }

    /**
     * @throws InvalidValueTypeException
     */
    private function escapeValue(string|int|float|bool|null $value): float|int|string
    {
        if (is_null($value)) {
            return static::NULL_STRING;
        } elseif (is_int($value) || is_float($value)) {
            return $value;
        } elseif (is_bool($value)) {
            return $value ? static::BOOL_TRUE_STRING : static::BOOL_FALSE_STRING;
        } elseif (is_string($value)) {
            return "'" . $this->mysqli->real_escape_string($value) . "'";
        } else {
            throw new InvalidValueTypeException();
        }
    }

    /**
     * @throws InvalidIdentifierTypeException
     */
    private function escapeIdentifier($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'escapeIdentifier'], $value));
        } elseif (is_string($value)) {
            return "`" . $this->mysqli->real_escape_string($value) . "`";
        } else {
            throw new InvalidIdentifierTypeException();
        }
    }

    /**
     * @throws InvalidValueTypeException
     * @throws InvalidIdentifierTypeException
     */
    private function processArray($array): string
    {
        if (!is_array($array)) {
            throw new InvalidValueTypeException('Unable to escape value due to expected array.');
        }

        if ($this->isAssoc($array)) {
            $parisMap = [];
            foreach ($array as $key => $value) {
                $parisMap[] = $this->escapeIdentifier($key) . " = " . $this->escapeValue($value);
            }
            return implode(', ', $parisMap);
        } else {
            return implode(', ', array_map([$this, 'escapeValue'], $array));
        }
    }

    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @throws InvalidValueTypeException
     * @throws InvalidIdentifierTypeException
     */
    private function processConditionalBlocks(string $query, array &$args): array|string|null
    {
        return preg_replace_callback(
            static::CONDITIONAL_QUALIFIER,
            function ($matches) use (&$args) {
                $block    = $matches[1] ?? [];
                $tempArgs = $args;

                $block = preg_replace_callback(
                 static::QUALIFIER_REGEX,
                    function ($matches) use (&$tempArgs) {
                        $type   = $matches[1] ?? null;
                        $value  = array_shift($tempArgs);

                        if ($value === self::SKIP) {
                            return self::SKIP;
                        }

                        return match ($type) {
                            static::INT_QUALIFIER           => is_null($value) ? static::NULL_STRING : intval($value),
                            static::FLOAT_QUALIFIER         => is_null($value) ? static::NULL_STRING : floatval($value),
                            static::ARRAY_QUALIFIER         => $this->processArray($value),
                            static::IDENTIFIER_QUALIFIER    => $this->escapeIdentifier($value),
                            default                         => $this->escapeValue($value),
                        };
                    },
                    $block
                );

                if (str_contains($block, self::SKIP)) {
                    $args = $tempArgs;
                    return '';
                }

                return $block;
            },
            $query
        );
    }
}