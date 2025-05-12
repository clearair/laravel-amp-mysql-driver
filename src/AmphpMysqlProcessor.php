<?php

namespace Azhi\LaravelAmpMysqlDriver;

use Amp\Mysql\MysqlResult;
use Illuminate\Database\Query\Builder;

class AmphpMysqlProcessor extends \Illuminate\Database\Query\Processors\MySqlProcessor
{
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
	{
		/** @var MysqlResult $result */
		$result = $query->getConnection()->insertStatement($sql, $values);

		$id = $result->getLastInsertId();

		return is_numeric($id) ? (int) $id : $id;
	}
}
