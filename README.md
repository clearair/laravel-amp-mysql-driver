# Laravel AMP MySQL Driver

[English | 中文](#中文说明)

---

## English

An asynchronous MySQL driver for Laravel based on [amphp/mysql](https://github.com/amphp/mysql).

> **This project is inspired by and borrows from [xpader/amphp-eloquent-mysql](https://github.com/xpader/amphp-eloquent-mysql).**

### Features
- Supports Laravel 11+
- Built on amphp/mysql, with async MySQL connection pool
- Compatible with Eloquent / Query Builder

### Installation

```bash
composer require azhi/laravel-amp-mysql-driver
```

### Configuration

#### 1. Register ServiceProvider

Make sure the provider is registered in `config/app.php`:

```php
'providers' => [
    // ...
    Azhi\LaravelAmpMysqlDriver\AmphpMySqlServiceProvider::class,
],
```

> Laravel package auto-discovery will usually register it automatically.

#### 2. Add connection to database.php

In `config/database.php`:

```php
'amphp-mysql' => [
    'driver'    => 'amphp-mysql',
    'host'      => env('DB_HOST', '127.0.0.1'),
    'database'  => env('DB_DATABASE', 'forge'),
    'username'  => env('DB_USERNAME', 'forge'),
    'password'  => env('DB_PASSWORD', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
    'pool'      => [
        'max_connections' => 10,
        'max_idle_time' => 60,
    ],
],
```

#### 3. Set .env

```
DB_CONNECTION=amphp-mysql
```

### Usage

- Use like native Laravel DB:

```php
DB::connection('amphp-mysql')->select('SELECT 1');
```

- For Eloquent models:

```php
class User extends Model {
    protected $connection = 'amphp-mysql';
}
```

### Test

```bash
./vendor/bin/phpunit
```

### Contributing
Pull requests and issues are welcome.

### License
MIT

---

## 中文说明

一个基于 [amphp/mysql](https://github.com/amphp/mysql) 的 Laravel 异步 MySQL 数据库驱动。

> **本项目借鉴自 [xpader/amphp-eloquent-mysql](https://github.com/xpader/amphp-eloquent-mysql)。**

### 特性
- 支持 Laravel 11+
- 基于 amphp/mysql，支持异步 MySQL 连接池
- 兼容 Eloquent/Query Builder

### 安装

```bash
composer require azhi/laravel-amp-mysql-driver
```

### 配置

#### 1. 注册 ServiceProvider

确保在 `config/app.php` 的 `providers` 数组中注册：

```php
'providers' => [
    // ...
    Azhi\LaravelAmpMysqlDriver\AmphpMySqlServiceProvider::class,
],
```

> Laravel 包自动发现通常会自动注册，无需手动操作。

#### 2. 配置 database.php

在 `config/database.php` 的 `connections` 数组中添加：

```php
'amphp-mysql' => [
    'driver'    => 'amphp-mysql',
    'host'      => env('DB_HOST', '127.0.0.1'),
    'database'  => env('DB_DATABASE', 'forge'),
    'username'  => env('DB_USERNAME', 'forge'),
    'password'  => env('DB_PASSWORD', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
    'pool'      => [
        'max_connections' => 10,
        'max_idle_time' => 60,
    ],
],
```

#### 3. 配置 .env

```
DB_CONNECTION=amphp-mysql
```

### 使用

- 数据库操作与原生 Laravel 一致：

```php
DB::connection('amphp-mysql')->select('SELECT 1');
```

- Eloquent 模型可指定 `$connection = 'amphp-mysql';`

```php
class User extends Model {
    protected $connection = 'amphp-mysql';
}
```

### 测试

```bash
./vendor/bin/phpunit
```

### 贡献
欢迎 PR 或 issue。

### License
MIT
