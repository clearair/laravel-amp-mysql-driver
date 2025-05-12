<?php

namespace Azhi\LaravelAmpMysqlDriver;

use Illuminate\Database\Connection;
use Amp\Mysql\MysqlConnectionPool;
use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlTransaction;
use Illuminate\Database\MySqlConnection;
use Amp\Sql\Common\SqlCommonConnectionPool;
use Revolt\EventLoop\FiberLocal;
use Closure;
use Illuminate\Database\QueryException;

class AmphpMySqlConnection extends MySqlConnection
{

    private FiberLocal $executor;

    public function __construct($config)
    {
        $conn = MysqlConfig::fromString(
            "host={$config['host']} user={$config['username']} password={$config['password']} db={$config['database']}"
        );
        if (isset($config['charset'])) {
            $conn = $conn->withCharset($config['charset'], $config['collation']);
        }
    
        $maxConnection = $config['pool']['max_connections'] ?? SqlCommonConnectionPool::DEFAULT_MAX_CONNECTIONS;
        $maxIdleTime = $config['pool']['max_idle_time'] ?? SqlCommonConnectionPool::DEFAULT_IDLE_TIMEOUT;

        $pool = new MysqlConnectionPool($conn, $maxConnection, $maxIdleTime);

        parent::__construct(function () {}, $config['database'], $config['prefix'] ?? '', $config);
        $this->executor ??= new FiberLocal(fn() => $pool);
    }

    public function getDriverTitle()
    {
        return 'AmphpMySQL';
    }

    public function getPool()
	{
		return $this->executor->get();
	}

    private function getConn()
	{
		return $this->executor->get();
	}

    public function insertStatement($query, $bindings = [])
	{
		return $this->rawStatement($query, $bindings);
	}

	/**
	 * Get the default post processor instance.
	 *
	 * @return \Illuminate\Database\Query\Processors\MySqlProcessor
	 */
	protected function getDefaultPostProcessor()
	{
		return new AmphpMysqlProcessor();
	}

	protected function run($query, $bindings, Closure $callback)
	{
		foreach ($this->beforeExecutingCallbacks as $beforeExecutingCallback) {
			$beforeExecutingCallback($query, $bindings, $this);
		}

		$start = microtime(true);

		// Here we will run this query. If an exception occurs we'll determine if it was
		// caused by a connection that has been lost. If that is the cause, we'll try
		// to re-establish connection and re-run the query with a fresh connection.
		try {
			$result = $this->runQueryCallback($query, $bindings, $callback);
		} catch (QueryException $e) {
			$result = $this->handleQueryException(
				$e, $query, $bindings, $callback
			);
		}

		// Once we have run the query we will calculate the time that it took to run and
		// then log the query, bindings, and execution time so we will report them on
		// the event that the developer needs them. We'll log time in milliseconds.
		$this->logQuery(
			$query, $bindings, $this->getElapsedTime($start)
		);

		return $result;
	}

	public function select($query, $bindings = [], $useReadPdo = true)
	{
		return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
			if ($this->pretending()) {
				return [];
			}

			$statement = $this->getConn()->prepare($query);
			$result = $statement->execute($this->prepareBindings($bindings));

			$rows = [];

			foreach ($result as $row) {
				$rows[] = $row;
			}

			return $rows;
		});
	}

	/**
	 * @inheritDoc
	 */
	public function selectResultSets($query, $bindings = [], $useReadPdo = true)
	{
		return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
			if ($this->pretending()) {
				return [];
			}

			$statement = $this->getConn()->prepare($query);
			$result = $statement->execute($this->prepareBindings($bindings));

			$sets = [];

			do {
				$rows = [];
				foreach ($result as $row) {
					$rows[] = $row;
				}
				$sets[] = $rows;
			} while ($result = $result->getNextResult());

			return $sets;
		});
	}

	public function cursor($query, $bindings = [], $useReadPdo = true)
	{
		$statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
			if ($this->pretending()) {
				return [];
			}

			$statement = $this->getConn()->prepare($query);
			$statement->execute($this->prepareBindings($bindings));

			return $statement;
		});


		foreach ($statement as $row) {
			yield $row;
		}
	}

	/**
	 * Execute an SQL statement and return the raw result.
	 *
	 * @param  string  $query
	 * @param  array  $bindings
	 * @return \Amp\Mysql\MysqlResult
	 */
	public function rawStatement($query, $bindings = [])
	{
		return $this->run($query, $bindings, function ($query, $bindings) {
			if ($this->pretending()) {
				return true;
			}

			$statement = $this->getConn()->prepare($query);

			$this->recordsHaveBeenModified();

			return $statement->execute($this->prepareBindings($bindings));
		});
	}

	public function statement($query, $bindings = [])
	{
		return $this->rawStatement($query, $bindings)->getRowCount() > 0;
	}

	public function affectingStatement($query, $bindings = [])
	{
		return $this->run($query, $bindings, function ($query, $bindings) {
			if ($this->pretending()) {
				return 0;
			}

			$statement = $this->getConn()->prepare($query);
			$result = $statement->execute($this->prepareBindings($bindings));

			$this->recordsHaveBeenModified(
				($count = $result->getRowCount()) > 0
			);

			return $count;
		});
	}

	public function unprepared($query)
	{
		return $this->run($query, [], function ($query) {
			if ($this->pretending()) {
				return true;
			}

			try {
				$this->getConn()->execute($query);
				$change = true;
			} catch (\Throwable $e) {
				$change = false;
			}

			$this->recordsHaveBeenModified($change);

			return $change;
		});


	}

	public function transaction(Closure $callback, $attempts = 1)
	{
		for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
			$this->beginTransaction();

			// We'll simply execute the given callback within a try / catch block and if we
			// catch any exception we can rollback this transaction so that none of this
			// gets actually persisted to a database or stored in a permanent fashion.
			try {
				$callbackResult = $callback($this);
			}

				// If we catch an exception we'll rollback this transaction and try again if we
				// are not out of attempts. If we are out of attempts we will just throw the
				// exception back out, and let the developer handle an uncaught exception.
			catch (\Throwable $e) {
				$this->handleTransactionException(
					$e, $currentAttempt, $attempts
				);

				continue;
			}

			try {
				if ($this->transactions == 1) {
					$this->fireConnectionEvent('committing');
					$this->getConn()->commit();
				}

				[$levelBeingCommitted, $this->transactions] = [
					$this->transactions,
					max(0, $this->transactions - 1),
				];

				$this->transactionsManager?->commit(
					$this->getName(),
					$levelBeingCommitted,
					$this->transactions
				);
			} catch (\Throwable $e) {
				$this->handleCommitTransactionException(
					$e, $currentAttempt, $attempts
				);

				continue;
			}

			$this->fireConnectionEvent('committed');

			return $callbackResult;
		}
	}


	public function commit()
	{
		if ($this->transactionLevel() == 1) {
			$this->fireConnectionEvent('committing');
			$this->getConn()->commit();
			$this->executor->unset();
		}

		$this->transactions = max(0, $this->transactions - 1);

		[$levelBeingCommitted, $this->transactions] = [
			$this->transactions,
			max(0, $this->transactions - 1),
		];

		$this->transactionsManager?->commit(
			$this->getName(), $levelBeingCommitted, $this->transactions
		);

		$this->fireConnectionEvent('committed');
	}

	protected function performRollBack($toLevel)
	{
		if ($toLevel == 0) {
			$conn = $this->getConn();

			if ($conn instanceof MysqlTransaction) {
				$conn->rollBack();
				$this->executor->unset();
			}
		} elseif ($this->queryGrammar->supportsSavepoints()) {
			$this->getConn()->execute(
				$this->queryGrammar->compileSavepointRollBack('trans'.($toLevel + 1))
			);
		}
	}

	/**
	 * Create a transaction within the database.
	 *
	 * @return void
	 *
	 * @throws \Throwable
	 */
	protected function createTransaction()
	{
		if ($this->transactions == 0) {
			$transaction = $this->getConn()->beginTransaction();
			$this->executor->set($transaction);
		} elseif ($this->transactions >= 1 && $this->queryGrammar->supportsSavepoints()) {
			$this->createSavepoint();
		}
	}

	/**
	 * Create a save point within the database.
	 *
	 * @return void
	 *
	 * @throws \Throwable
	 */
	protected function createSavepoint()
	{
		$this->getConn()->execute(
			$this->queryGrammar->compileSavepoint('trans'.($this->transactions + 1))
		);
	}


    public function bindValues($statement, $bindings)
    {

    }


    public function getPdo()
    {
        throw new \RuntimeException('PDO is not available in amphp/mysql driver.');
    }
}