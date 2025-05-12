<?php

namespace Azhi\LaravelAmpMysqlDriver;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\DatabaseManager;

class AmphpMySqlServiceProvider extends ServiceProvider
{
    public function boot()
    {
        /** @var DatabaseManager $db */
        $db = $this->app['db'];
        $db->extend('amphp-mysql', function ($config, $name) {
            $connector = new AmphpMySqlConnector();
            // 保证返回 AmphpMySqlConnection 实例
            return $connector->connect($config);
        });
    }

    public function register()
    {
        //
    }
}
