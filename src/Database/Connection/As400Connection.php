<?php

namespace Cisse\Bundle\As400\Database\Connection;

use Cisse\Bundle\As400\DataCollector\As400QueryLogger;
use Cisse\Bundle\As400\Exception\As400Exception;
use Cisse\Bundle\As400\Service\DataRecorder\DataRecorder;
use Cisse\Bundle\As400\Utility\As400Utility;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

readonly class As400Connection implements ConnectionInterface
{
    private const PDO_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_AUTOCOMMIT => false,
        PDO::ATTR_TIMEOUT => 30,
    ];

    public PDO $connection;

    public function __construct(
        protected LoggerInterface            $as400Logger,
        private As400QueryLogger             $queryLogger,
        private DataRecorder                 $dataRecorder,
        private string                       $driver,
        private string                       $commitMode,
        private string                       $extendedDynamic,
        private string                       $packageLibrary,
        private string                       $translateHex,
        private string                       $system,
        private string                       $database,
        private string                       $defaultLibraries,
        private string                       $user,
        #[SensitiveParameter] private string $password,
    )
    {
        $this->connect();
    }

    /**
     * @throws As400Exception
     */
    public function insert(string $table, array $data): bool
    {
        if (empty($data)) {
            throw new As400Exception('No data provided for insert operation');
        }

        [$placeholders, $values] = $this->buildDataParams($data);

        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', $placeholders);
        $query = "INSERT INTO $table ($fields) VALUES ($placeholders)";

        $result = $this->executeWithTransaction($query, $values);
        if ($result && $this->hasRecorders()) {
            $this->dataRecorder->insert($table, $data);
        }

        return $result;
    }

    /**
     * @throws As400Exception
     */
    public function update(string $table, array $data, array|string $conditions): bool
    {
        if (empty($data)) {
            throw new As400Exception('No data provided for update operation');
        }

        [$whereClause, $conditionParams] = $this->buildWhereClause($conditions);

        $setStatements = [];
        $values = [];

        foreach ($data as $field => $value) {
            if ($this->isSubquery($value)) {
                $setStatements[] = "$field = $value";
            } else {
                $setStatements[] = "$field = ?";
                $values[] = $value;
            }
        }

        $setStatements = implode(', ', $setStatements);

        $query = "UPDATE $table SET $setStatements$whereClause";
        $params = array_merge($values, $conditionParams);

        $oldData = [];
        if ($this->hasRecorders()) {
            $oldData = $this->select($table, array_keys($data), $conditions);
            if (count($oldData) === 1) {
                $oldData = $oldData[0];
            } else {
                $oldData = [];
            }
        }
        $result = $this->executeWithTransaction($query, $params);

        if ($result && $this->hasRecorders()) {
            $this->dataRecorder->update($table, $data, $oldData, $conditions);
        }

        return $result;
    }

    /**
     * @throws As400Exception
     */
    public function delete(string $table, array|string $conditions): bool
    {
        [$whereClause, $params] = $this->buildWhereClause($conditions);

        $query = "DELETE FROM $table$whereClause";

        $queryIndex = $this->queryLogger->startQuery($query, $params);
        $oldData = [];

        if ($this->hasRecorders()) {
            $oldData = $this->select($table, null, $conditions);
        }

        try {
            $this->connection->beginTransaction();

            $stmt = $this->connection->prepare($query);
            $result = $stmt->execute($params);
            $affectedRows = $stmt->rowCount();

            if ($result && $affectedRows > 0) {
                $this->connection->commit();
                $this->queryLogger->stopQuery($queryIndex);

                if ($this->hasRecorders()) {
                    $this->dataRecorder->delete($table, $oldData, $conditions);
                }

                return true;
            }

            $this->connection->rollBack();
            $this->queryLogger->stopQuery($queryIndex);
            return false;
        } catch (PDOException $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            $this->queryLogger->stopQuery($queryIndex);
            throw new As400Exception('Delete operation failed: ' . $e->getMessage());
        }
    }

    /**
     * @throws As400Exception
     */
    public function select(
        string            $table,
        array|string|null $fields = null,
        array|string|null $conditions = null,
        array|string|null $orders = null,
        ?int              $limit = null,
        ?int              $offset = null,
    ): array
    {
        [$query, $params] = $this->buildSelectQuery($table, $fields, $conditions, $orders, $limit, $offset);

        return $this->fetchAll($query, $params);
    }

    /**
     * @throws As400Exception
     */
    public function fetchAll(string $query, array|null $params = null): array
    {
        $queryIndex = $this->queryLogger->startQuery($query, $params ?? []);

        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            $result = As400Utility::cleanAS400Values($stmt->fetchAll(PDO::FETCH_ASSOC));
            $this->queryLogger->stopQuery($queryIndex);
            return $result;
        } catch (PDOException $e) {
            $this->queryLogger->stopQuery($queryIndex);
            throw new As400Exception('Fetch operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generator-based fetch for memory-efficient processing of large datasets.
     * Yields one row at a time instead of loading all into memory.
     *
     * @param string $query SQL query to execute
     * @param array|null $params Query parameters
     * @return \Generator<array<string, mixed>>
     * @throws As400Exception
     */
    public function fetchIterator(string $query, array|null $params = null): \Generator
    {
        $queryIndex = $this->queryLogger->startQuery($query, $params ?? []);

        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                yield As400Utility::cleanAS400Row($row);
            }

            $this->queryLogger->stopQuery($queryIndex);
        } catch (PDOException $e) {
            $this->queryLogger->stopQuery($queryIndex);
            throw new As400Exception('Fetch iterator operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Select with generator-based streaming for large datasets.
     *
     * @return \Generator<array<string, mixed>>
     * @throws As400Exception
     */
    public function selectIterator(
        string            $table,
        array|string|null $fields = null,
        array|string|null $conditions = null,
        array|string|null $orders = null,
        ?int              $limit = null,
        ?int              $offset = null,
    ): \Generator
    {
        [$query, $params] = $this->buildSelectQuery($table, $fields, $conditions, $orders, $limit, $offset);

        yield from $this->fetchIterator($query, $params);
    }

    /**
     * @throws As400Exception
     */
    public function fetchColumn(string $query, array|null $params = null): string|int|null
    {
        $queryIndex = $this->queryLogger->startQuery($query, $params ?? []);

        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            $result = As400Utility::cleanAS400Values($stmt->fetchColumn());
            $this->queryLogger->stopQuery($queryIndex);
            return $result;
        } catch (PDOException $e) {
            $this->queryLogger->stopQuery($queryIndex);
            throw new As400Exception('Fetch column operation failed: ' . $e->getMessage());
        }
    }

    /**
     * @throws As400Exception
     */
    public function count(string $table, array|string|null $conditions = null): int
    {
        [$whereClause, $params] = $this->buildWhereClause($conditions);

        $query = "SELECT COUNT(*) FROM $table$whereClause";
        $queryIndex = $this->queryLogger->startQuery($query, $params);

        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            $result = (int)$stmt->fetchColumn();
            $this->queryLogger->stopQuery($queryIndex);
            return $result;
        } catch (PDOException $e) {
            $this->queryLogger->stopQuery($queryIndex);
            throw new As400Exception('Count operation failed: ' . $e->getMessage());
        }
    }

    public function isConnected(): bool
    {
        try {
            $this->connection->query('SELECT 1 FROM SYSIBM.SYSDUMMY1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @throws As400Exception
     */
    public function reconnect(): void
    {
        $this->connect();
    }

    private function hasRecorders(): bool
    {
        return count($this->dataRecorder->getRecorders()) > 0;
    }

    private function isSubquery(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\s*\(\s*SELECT\s/i', $value);
    }

    private function buildSelectQuery(
        string            $table,
        array|string|null $fields,
        array|string|null $conditions,
        array|string|null $orders,
        ?int              $limit,
        ?int              $offset,
    ): array
    {
        [$whereClause, $params] = $this->buildWhereClause($conditions);

        $fieldsStr = match (true) {
            is_array($fields) => implode(', ', $fields),
            is_string($fields) => $fields,
            default => '*'
        };

        $orderQuery = $this->buildOrderClause($orders);
        $offsetQuery = $offset ? " OFFSET $offset ROWS" : '';
        $limitQuery = $limit ? " FETCH FIRST $limit ROWS ONLY" : '';

        $query = "SELECT $fieldsStr FROM $table$whereClause$orderQuery$offsetQuery$limitQuery";

        return [$query, $params];
    }

    /**
     * @throws As400Exception
     */
    private function connect(): void
    {
        $dsnParts = [
            "DRIVER=$this->driver",
            "SYSTEM=$this->system",
            "CommitMode=$this->commitMode",
            "ExtendedDynamic=$this->extendedDynamic",
            "PackageLibrary=$this->packageLibrary",
            "TranslateHex=$this->translateHex",
        ];

        if ($this->database) {
            $dsnParts[] = "DATABASE=$this->database";
        }
        if ($this->defaultLibraries) {
            $dsnParts[] = "DefaultLibraries=$this->defaultLibraries";
        }

        $dsn = 'odbc:' . implode(';', $dsnParts);

        try {
            $this->connection = new PDO($dsn, $this->user, $this->password, self::PDO_OPTIONS);
        } catch (PDOException $e) {
            throw new As400Exception('Database connection error: ' . $e->getMessage());
        }
    }

    /**
     * @throws As400Exception
     */
    public function executeWithTransaction(string $query, array $params = []): bool
    {
        $queryIndex = $this->queryLogger->startQuery($query, $params);

        try {
            $this->connection->beginTransaction();

            $stmt = $this->connection->prepare($query);
            $result = $stmt->execute($params);

            if ($result) {
                $this->connection->commit();
                $this->queryLogger->stopQuery($queryIndex);
                return true;
            }

            $this->connection->rollBack();
            $this->queryLogger->stopQuery($queryIndex);
            return false;
        } catch (PDOException $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            $this->queryLogger->stopQuery($queryIndex);
            throw new As400Exception("Database operation failed: {$e->getMessage()}");
        }
    }

    private function buildWhereClause(array|string|null $conditions): array
    {
        if (empty($conditions)) {
            return ['', []];
        }

        if (is_string($conditions)) {
            return [" WHERE $conditions", []];
        }

        $whereParts = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_int($key)) {
                $whereParts[] = $value;
            } else if (is_array($value)) {
                $placeholders = str_repeat('?,', count($value) - 1) . '?';
                $whereParts[] = "$key IN ($placeholders)";
                $params = array_merge($params, $value);
            } else {
                $whereParts[] = "$key = ?";
                $params[] = $value;
            }
        }

        $whereClause = ' WHERE ' . implode(' AND ', $whereParts);
        return [$whereClause, $params];
    }

    private function buildOrderClause(array|string|null $orders): string
    {
        if (empty($orders)) {
            return '';
        }

        if (is_string($orders)) {
            return " ORDER BY $orders";
        }

        $orderParts = [];

        foreach ($orders as $key => $value) {
            if (is_string($key)) {
                $orderParts[] = "$key " . strtoupper($value);
            } elseif (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $orderParts[] = "$subKey " . strtoupper($subValue);
                }
            } elseif (is_string($value)) {
                // Try to extract field and direction from string
                $parts = preg_split('/\s+/', trim($value));
                $field = $parts[0] ?? '';
                $direction = strtoupper($parts[1] ?? 'ASC');
                $orderParts[] = "$field $direction";
            }
        }

        return ' ORDER BY ' . implode(', ', $orderParts);
    }

    private function buildDataParams(array $data): array
    {
        $placeholders = [];
        $values = [];

        foreach ($data as $value) {
            if ($this->isSubquery($value)) {
                $placeholders[] = $value;
            } else {
                $placeholders[] = '?';
                $values[] = $value;
            }
        }

        return [$placeholders, $values];
    }
}
