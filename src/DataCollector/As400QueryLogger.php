<?php

namespace Cisse\Bundle\As400\DataCollector;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class As400QueryLogger
{
    public array $queries = [];

    public function __construct(
        private readonly LoggerInterface                        $logger,
        #[Autowire('%env(APP_ENV)%')] protected readonly string $appEnv,
    )
    {
    }

    public function logQuery(string $query, array|null $params = [], float|null $executionTime = null): void
    {
        if ($this->appEnv !== 'prod') {
            $this->logger->info('AS400 Query: ' . $query, $params ?? []);
        }

        $this->queries[] = [
            'query' => $query,
            'params' => $params,
            'start_time' => microtime(true),
            'execution_time' => $executionTime, // in milliseconds
        ];
    }

    public function startQuery(string $query, array|null $params = []): int
    {
        if ($this->appEnv !== 'prod') {
            $this->logger->info('AS400 Query: ' . $query, $params ?? []);
        }
        $queryIndex = count($this->queries);

        $this->queries[] = [
            'query' => $query,
            'params' => $params,
            'start_time' => microtime(true),
            'execution_time' => null,
        ];
        return $queryIndex;
    }

    public function stopQuery(int $queryIndex): void
    {
        if (isset($this->queries[$queryIndex])) {
            $endTime = microtime(true);
            $startTime = $this->queries[$queryIndex]['start_time'];
            $this->queries[$queryIndex]['execution_time'] = round(($endTime - $startTime) * 1000, 3); // Convert to milliseconds
        }
    }

    public function reset(): void
    {
        $this->queries = [];
    }
}
