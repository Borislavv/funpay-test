<?php

namespace Tests\FpDbTest\Infrastructure\Database;

use FpDbTest\Infrastructure\Database\DatabaseInterface;
use FpDbTest\Infrastructure\Database\Exception\InvalidTypeExceptionInterface;
use Tests\FpDbTest\Exception\TestFailedException;

readonly class DatabaseTest
{
    private const array CORRECT_TEST_ANSWERS = [
        'SELECT name FROM users WHERE user_id = 1',
        'SELECT * FROM users WHERE name = \'Jack\' AND block = 0',
        'SELECT `name`, `email` FROM users WHERE user_id = 2 AND block = 1',
        'UPDATE users SET `name` = \'Jack\', `email` = NULL WHERE user_id = -1',
        'SELECT name FROM users WHERE `user_id` IN (1, 2, 3)',
        'SELECT name FROM users WHERE `user_id` IN (1, 2, 3) AND block = 1',
    ];

    public function __construct(private DatabaseInterface $db)
    {}

    /**
     * @throws InvalidTypeExceptionInterface
     */
    public function testBuildQuery(): void
    {
        $results = [];

        $results[] = $this->db->buildQuery('SELECT name FROM users WHERE user_id = 1');

        $results[] = $this->db->buildQuery(
            'SELECT * FROM users WHERE name = ? AND block = 0',
            ['Jack']
        );

        $results[] = $this->db->buildQuery(
            'SELECT ?# FROM users WHERE user_id = ?d AND block = ?d',
            [['name', 'email'], 2, true]
        );

        $results[] = $this->db->buildQuery(
            'UPDATE users SET ?a WHERE user_id = -1',
            [['name' => 'Jack', 'email' => null]]
        );

        foreach ([null, true] as $block) {
            $results[] = $this->db->buildQuery(
                'SELECT name FROM users WHERE ?# IN (?a){ AND block = ?d}',
                ['user_id', [1, 2, 3], $block ?? $this->db->skip()]
            );
        }

        var_dump($results, self::CORRECT_TEST_ANSWERS);

        if ($results !== self::CORRECT_TEST_ANSWERS) {
            throw new TestFailedException();
        }
    }
}
