<?php

namespace Cisse\Bundle\As400\Service\DataRecorder;

use Cisse\Bundle\As400\Enum\DataRecordType;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class DataRecorder
{
    private array $recorders = [];

    public function __construct(
        #[AutowireIterator(DataRecorderInterface::class)]
        readonly private iterable $recorderServices,
    )
    {
        foreach ($recorderServices as $recorder) {
            $this->recorders[] = $recorder;
        }
    }

    public function record(string $table, array $newData = [], array $oldData = [], array|null $where = null, DataRecordType|null $action = null): void
    {
        foreach ($this->recorders as $recorder) {
            $recorder->record($table, $newData, $oldData, $where, $action);
        }
    }

    public function insert(string $table, array $newData): void
    {
        foreach ($this->recorders as $recorder) {
            $recorder->insert($table, $newData);
        }
    }

    public function update(string $table, array $newData, array $oldData, array $where): void
    {
        foreach ($this->recorders as $recorder) {
            $recorder->update($table, $newData, $oldData, $where);
        }
    }

    public function delete(string $table, array $oldData, array $where): void
    {
        foreach ($this->recorders as $recorder) {
            $recorder->delete($table, $oldData, $where);
        }
    }

    public function getRecorders(): array
    {
        return $this->recorders;
    }
}
