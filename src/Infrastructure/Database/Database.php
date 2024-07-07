<?php

namespace FpDbTest\Infrastructure\Database;

use FpDbTest\Infrastructure\Database\Exception\InvalidIdentifierTypeException;
use FpDbTest\Infrastructure\Database\Exception\InvalidValueTypeException;
use mysqli;

readonly class Database implements DatabaseInterface
{
    protected const string FULL_CONDITIONAL_BLOCK_WITH_EMBEDDEDS_REGEX  = '/\{((?:[^{}]*+|\{(?:[^{}]*+|(?R))*\})*)\}/';
    protected const string EMBEDDED_CONDITIONAL_BLOCK_REGEX             = '/\{(?:[^{}]*+|(?R))*\}/';
    protected const string PLACEHOLDER_REGEX                            = '/(\?(d|f|a|#)?|\{((?:[^{}]++|(?R))*)\})/';
    protected const string QUALIFIER_REGEX                              = '/\?(d|f|a|#)?/';
    protected const string CONDITIONAL_BLOCK_REGEX                      = '/\{([^{}]+)\}/';
    protected const string SKIP                                         = '__SKIP__';
    protected const string NULL_STRING                                  = 'NULL';
    protected const string INT_QUALIFIER                                = 'd';
    protected const string FLOAT_QUALIFIER                              = 'f';
    protected const string ARRAY_QUALIFIER                              = 'a';
    protected const string IDENTIFIER_QUALIFIER                         = '#';
    protected const string BOOL_TRUE_STRING                             = '1';
    protected const string BOOL_FALSE_STRING                            = '0';

    public function __construct(private mysqli $mysqli)
    {}

    /**
     * @throws InvalidValueTypeException
     * @throws InvalidIdentifierTypeException
     */
    public function buildQuery(string $query, array $args = []): string
    {
        if (preg_match_all(self::PLACEHOLDER_REGEX, $query, $rawPlaceholders)) {
            $placeholders   = $rawPlaceholders[0] ?? [];
            $qualifierTypes = $rawPlaceholders[2] ?? [];

            foreach ($placeholders as $key => $placeholder) {
                if ($this->isConditionalBlock($placeholder)) {
                    if ($this->handleSkippedBlock($query, $placeholder, $args)) {
                        continue;
                    }

                    $placeholder = $this->cutEmbeddedConditionalBlocks($placeholder);
                    $this->replaceQualifiers($query, $placeholder, $args);
                } else {
                    $this->replaceQualifier($query, $qualifierTypes[$key], $args);
                }
            }
        }

        return $query;
    }

    private function replaceConditionalBlock(string &$query, string $conditionalBlock): void
    {
        $query = preg_replace(
            static::FULL_CONDITIONAL_BLOCK_WITH_EMBEDDEDS_REGEX,
            trim($conditionalBlock, '{}'),
            $query
        );
    }

    /**
     * @throws InvalidIdentifierTypeException
     * @throws InvalidValueTypeException
     */
    private function replaceQualifiers(string &$query, string $placeholder, array &$args): void
    {
        if (preg_match_all(static::QUALIFIER_REGEX, $placeholder, $qualifiers)) {
            foreach ($qualifiers[1] as $qualifierType) {
                $this->replaceQualifier($placeholder, $qualifierType, $args);
            }
        }

        $this->replaceConditionalBlock($query, $placeholder);
    }

    /**
     * @throws InvalidValueTypeException
     * @throws InvalidIdentifierTypeException
     */
    private function replaceQualifier(string &$target, string $qualifierType, array &$args): void
    {
        $value  = $this->processPlaceholder($qualifierType, array_shift($args));
        $target = preg_replace(static::QUALIFIER_REGEX, $value, $target, 1);
    }

    private function cutEmbeddedConditionalBlocks(string $placeholder): string
    {
        return preg_replace_callback(static::FULL_CONDITIONAL_BLOCK_WITH_EMBEDDEDS_REGEX,
            function ($matches) {
                return '{' . preg_replace(static::EMBEDDED_CONDITIONAL_BLOCK_REGEX, '', $matches[1]) . '}';
            },
            $placeholder
        );
    }

    private function isConditionalBlock(string $placeholder): bool
    {
        return preg_match(self::CONDITIONAL_BLOCK_REGEX, $placeholder);
    }

    /**
     * Returns a bool value indicating whether the block was skipped.
     */
    private function handleSkippedBlock(string &$query, string $placeholder, array &$args): bool
    {
        if ($num = preg_match_all(static::QUALIFIER_REGEX, $placeholder)) {
            for ($i = 0; $i < $num; $i++) {
                if ($args[$i] === static::SKIP) {
                    // Cut the args for number of placeholders.
                    $args = array_values(array_slice($args, $num - 1, count($args) - $num - 1));
                    // Replace skipped conditional block.
                    $query = preg_replace(static::FULL_CONDITIONAL_BLOCK_WITH_EMBEDDEDS_REGEX, '', $query);
                    // Go to next iteration of outer loop.
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @throws InvalidValueTypeException
     * @throws InvalidIdentifierTypeException
     */
    private function processPlaceholder(string|null $type, mixed $value): string
    {
        return match ($type) {
            static::INT_QUALIFIER => is_null($value) ? static::NULL_STRING : intval($value),
            static::FLOAT_QUALIFIER => is_null($value) ? static::NULL_STRING : floatval($value),
            static::ARRAY_QUALIFIER => $this->processArray($value),
            static::IDENTIFIER_QUALIFIER => $this->escapeIdentifier($value),
            default => $this->escapeValue($value),
        };
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
            $pairsMap = [];
            foreach ($array as $key => $value) {
                $pairsMap[] = $this->escapeIdentifier($key) . " = " . $this->escapeValue($value);
            }
            return implode(', ', $pairsMap);
        } else {
            return implode(', ', array_map([$this, 'escapeValue'], $array));
        }
    }

    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
