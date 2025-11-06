<?php

namespace Cisse\Bundle\As400\Repository;

use Cisse\Bundle\As400\Database\Connection\As400Connection;
use Cisse\Bundle\As400\DataCollector\As400QueryLogger;
use Cisse\Bundle\As400\Utility\Hydrator\EntityHydrator;
use Cisse\Bundle\As400\Utility\Resolver\Entity\DatabaseResolver;
use Cisse\Bundle\As400\Utility\Resolver\Entity\IdentifierResolver;
use Cisse\Bundle\As400\Utility\Resolver\Entity\TableResolver;
use DateMalformedStringException;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Repository
{
    protected const string ENTITY_CLASS = '';

    public function __construct(
        protected LoggerInterface                               $logger,
        protected As400Connection                               $connection,
        protected As400QueryLogger                              $queryLogger,
        #[Autowire('%env(APP_ENV)%')] protected readonly string $appEnv,
    )
    {
    }

    public function insert(array $data): bool|int
    {
        try {
            $inserted = $this->connection->insert(
                $this->getTableName(),
                $data
            );

            if ($inserted) {
                try {
                    $lastId = (int)$this->connection->connection->lastInsertId();
                    $lastId = $lastId > 0 ? $lastId : $this->connection->fetchColumn("SELECT MAX({$this->getIdentifier()}) FROM {$this->getTableName()}");

                    if ($lastId > 0) {
                        if ($this->appEnv === 'dev') {
                            $this->logger->info("Inserted new record into {$this->getTableName()} with ID: $lastId");
                        }
                        return $lastId;
                    }

                    $this->logger->warning("Insert operation did not return a valid ID for table {$this->getTableName()}");
                } catch (Exception $e) {
                    $this->logger->warning("Failed to retrieve last insert ID for table {$this->getTableName()}: " . $e->getMessage());
                }
            }

            return $inserted;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function update(array $data, array|string $conditions): bool
    {
        try {
            return $this->connection->update(
                $this->getTableName(),
                $data,
                $conditions
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function delete(array|string $conditions): bool
    {
        try {
            return $this->connection->delete(
                $this->getTableName(),
                $conditions
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function find(int|string $id, array|string|null $fields = null, bool $hydrate = true): object|array|null
    {
        try {
            return $this->findOneBy([$this->getIdentifier() => $id], fields: $fields, hydrate: $hydrate);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    public function findAll(array|string|null $fields = null, bool $hydrate = true): array
    {
        try {
            return $this->findBy(fields: $fields, hydrate: $hydrate);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return [];
        }
    }

    public function findBy(array|string|null $criteria = null, array|string|null $orderBy = null, int|null $limit = null, int|null $offset = null, array|string|null $fields = null, bool $hydrate = true): array
    {
        try {
            $results = $this->connection->select($this->getTableName(), $fields, $criteria, $orderBy, $limit, $offset);

            if ($hydrate) {
                return array_map(/**
                 * @throws DateMalformedStringException
                 * @throws ReflectionException
                 */ static fn($data) => EntityHydrator::hydrate($data, static::ENTITY_CLASS), $results);
            }
            return $results;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return [];
        }
    }

    public function count(array|string $criteria = []): int
    {
        try {
            return $this->connection->count($this->getTableName(), $criteria);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return 0;
        }
    }

    public function getMaxId(): int
    {
        try {
            return (int)$this->connection->fetchColumn("SELECT MAX({$this->getIdentifier()}) FROM {$this->getTableName()}");
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return 0;
        }
    }

    public function getNextId(): int
    {
        try {
            return (int)$this->connection->fetchColumn("SELECT MAX({$this->getIdentifier()}) + 1 FROM {$this->getTableName()}");
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return 0;
        }
    }

    public function findOneBy(array|string $criteria, array|string|null $orderBy = null, array|string|null $fields = null, bool $hydrate = true): object|array|null
    {
        try {
            return $this->findBy($criteria, $orderBy, 1, fields: $fields, hydrate: $hydrate)[0] ?? null;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    /**
     * @throws ReflectionException
     */
    private function getIdentifier(): string
    {
        return IdentifierResolver::resolve(static::ENTITY_CLASS);
    }

    /**
     * @throws ReflectionException
     */
    public function getTableName(): string
    {
        $database = DatabaseResolver::resolve(static::ENTITY_CLASS);
        $table = TableResolver::resolve(static::ENTITY_CLASS);
        if ($database) {
            return "$database.$table";
        }
        return $table;
    }

    /**
     * @throws ReflectionException
     */
    private function getInstance(): object
    {
        return $this->getReflectionClass()->newInstance();
    }

    private function getReflectionClass(): ReflectionClass
    {
        return new ReflectionClass(static::ENTITY_CLASS);
    }
}
