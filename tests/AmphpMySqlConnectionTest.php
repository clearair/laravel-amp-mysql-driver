<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Azhi\LaravelAmpMysqlDriver\AmphpMySqlConnection;
use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnectionPool;
use Amp\Sql\Common\SqlCommonConnectionPool;

class AmphpMySqlConnectionTest extends TestCase
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

    public function testGetDriverTitle()
    {
        $conn = new AmphpMySqlConnection($this->config);
        $this->assertSame('AmphpMySQL', $conn->getDriverTitle());
    }

    public function testGetPoolReturnsPoolInstance()
    {
        $conn = new AmphpMySqlConnection($this->config);
        $pool = $conn->getPool();
        $this->assertInstanceOf(MysqlConnectionPool::class, $pool);
    }

    public function testInsertStatementDelegatesToRawStatement()
    {
        $conn = $this->getMockBuilder(AmphpMySqlConnection::class)
            ->setConstructorArgs([$this->config])
            ->onlyMethods(['rawStatement'])
            ->getMock();
        $conn->expects($this->once())
            ->method('rawStatement')
            ->with('INSERT INTO foo VALUES (?)', [1])
            ->willReturn(true);
        $result = $conn->insertStatement('INSERT INTO foo VALUES (?)', [1]);
        $this->assertTrue($result);
    }
}
