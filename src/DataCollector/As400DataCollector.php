<?php

namespace Cisse\Bundle\As400\DataCollector;

use DateTime;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class As400DataCollector extends DataCollector
{
    public function __construct(private readonly As400QueryLogger $queryLogger)
    {
    }

    public static function getTemplate(): string|null
    {
        return '@As400/data_collector/as400.html.twig';
    }

    public function collect(Request $request, Response $response, Throwable|null $exception = null): void
    {
        $queries = $this->queryLogger->queries;
        
        $this->data = [
            'queries' => $queries,
            'query_count' => count($queries),
            'collected_at' => new DateTime(),
            'request_uri' => $request->getRequestUri(),
        ];
    }

    public function getQueries(): array
    {
        return $this->data['queries'] ?? [];
    }

    public function getTotalTimes(): array
    {
        $totalTimes = [];
        $queries = $this->data['queries'] ?? [];
        foreach ($queries as $query) {
            $totalTimes[] = $query['time'];
        }
        return $totalTimes;
    }

    public function getQueryCount(): int
    {
        return $this->data['query_count'] ?? 0;
    }

    public function getCollectedAt(): DateTime|null
    {
        return $this->data['collected_at'] ?? null;
    }

    public function getName(): string
    {
        return 'as400';
    }

    public function getTotalExecutionTime(): float
    {
        $totalTime = 0;
        foreach ($this->getQueries() as $query) {
            if (!empty($query['execution_time'])) {
                $totalTime += $query['execution_time'];
            }
        }
        return round($totalTime, 3);
    }
}
