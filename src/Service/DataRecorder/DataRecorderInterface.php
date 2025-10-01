<?php

namespace Cisse\Bundle\As400\Service\DataRecorder;

use Cisse\Bundle\As400\Enum\DataRecordType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DataRecorderInterface
{
    public function record(
        string $table,
        array $newData = [],
        array $oldData = [],
        array|null $where = null,
        DataRecordType|null $action = null
    ): void;

    public function insert(string $table, array $newData): void;

    public function update(string $table, array $newData, array $oldData, array $where): void;

    public function delete(string $table, array $oldData, array $where): void;
}
