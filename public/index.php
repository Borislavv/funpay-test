<?php

use FpDbTest\Infrastructure\Database\Database;
use Tests\FpDbTest\Infrastructure\Database\DatabaseTest;

require_once __DIR__ . '/../vendor/autoload.php';

/** App status codes */
const SUCCESS_STATUS_CODE = 0;
const FAILED_STATUS_CODE = 1;

/** MySQL connect */
const MYSQL_HOST        = 'host.docker.internal';
const MYSQL_PORT        = 3306;
const MYSQL_USERNAME    = 'user';
const MYSQL_PASSWORD    = 'password';
const MYSQL_DATABASE    = 'database';

/** MySQL: no errors on connect */
const MYSQL_SUCCESS_CONNECT = 0;

try {
//    sleep(3);
    $mysqli = new mysqli(
        hostname: MYSQL_HOST,
        username: MYSQL_USERNAME,
        password: MYSQL_PASSWORD,
        database: MYSQL_DATABASE,
        port:     MYSQL_PORT
    );
    if ($mysqli->connect_errno !== MYSQL_SUCCESS_CONNECT) {
        throw new RuntimeException($mysqli->connect_error);
    }

    $db   = new Database($mysqli);
    $test = new DatabaseTest($db);

    $test->testBuildQuery();
} catch (\Throwable $e) {;
} finally {
    if (!isset($e)) {
        exit(SUCCESS_STATUS_CODE);
    } else {
        echo $e->getMessage();
        exit(FAILED_STATUS_CODE);
    }
}

