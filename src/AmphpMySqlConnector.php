<?php

namespace Azhi\LaravelAmpMysqlDriver;

use Illuminate\Database\Connectors\ConnectorInterface;

class AmphpMySqlConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        return new AmphpMySqlConnection($config);
    }
}
