<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Azhi\LaravelAmpMysqlDriver\AmphpMySqlConnection;

class AmphpMySqlTransactionTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = [
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'database' => 'test',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'pool' => [
                'max_connections' => 1,
                'max_idle_time' => 60,
            ],
        ];
    }

    public function testTransactionCommitAndRollback()
    {
        $conn = new AmphpMySqlConnection($this->config);
        $conn->unprepared('CREATE TABLE IF NOT EXISTS t1 (id INT PRIMARY KEY AUTO_INCREMENT, val INT)');
        $conn->unprepared('TRUNCATE TABLE t1');

        // Commit
        $conn->transaction(function ($c) {
            $c->statement('INSERT INTO t1 (val) VALUES (?)', [100]);
        });
        $result = $conn->select('SELECT COUNT(*) as cnt FROM t1 WHERE val = ?', [100]);
        $this->assertSame(1, (int)$result[0]['cnt']);

        // Rollback
        try {
            $conn->transaction(function ($c) {
                $c->statement('INSERT INTO t1 (val) VALUES (?)', [200]);
                throw new \Exception('fail');
            });
        } catch (\Exception $e) {}
        $result = $conn->select('SELECT COUNT(*) as cnt FROM t1 WHERE val = ?', [200]);
        $this->assertSame(0, (int)$result[0]['cnt']);
    }

    public function testNestedTransactionRollback()
    {
        $conn = new AmphpMySqlConnection($this->config);
        $conn->unprepared('CREATE TABLE IF NOT EXISTS t2 (id INT PRIMARY KEY AUTO_INCREMENT, val INT)');
        $conn->unprepared('TRUNCATE TABLE t2');

        try {
            $conn->transaction(function ($c) {
                $c->statement('INSERT INTO t2 (val) VALUES (?)', [1]);
                $c->transaction(function ($c2) {
                    $c2->statement('INSERT INTO t2 (val) VALUES (?)', [2]);
                    throw new \Exception('fail inner');
                });
            });
        } catch (\Exception $e) {}
        $result = $conn->select('SELECT COUNT(*) as cnt FROM t2');
        $this->assertSame(0, (int)$result[0]['cnt']);
    }
}
