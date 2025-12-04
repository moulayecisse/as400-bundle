<?php

namespace Cisse\Bundle\As400\Command;

use Cisse\Bundle\As400\Database\Connection\As400Connection;
use Cisse\Bundle\As400\Exception\As400Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'as400:generate:entity',
    description: 'Generate a custom attribute-based entity from an AS400 table using templates',
)]
class As400GenerateEntityCommand extends Command
{
    public function __construct(
        private readonly As400Connection $connection,
        private readonly string $projectDir,
        private readonly string $entityDir,
        private readonly string $repositoryDir,
        private readonly string $testDir,
        private readonly string $entityNamespace,
        private readonly string $repositoryNamespace,
        private readonly string $testNamespace,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('database', InputArgument::OPTIONAL, 'AS400 schema/database name')
            ->addArgument('table', InputArgument::OPTIONAL, 'AS400 table name')
            ->addArgument('output-namespace', InputArgument::OPTIONAL, 'Target namespace for generated entities (overrides config)')
            ->addOption('with-repository', null, InputOption::VALUE_NONE, 'Generate associated repository')
            ->addOption('with-test', null, InputOption::VALUE_NONE, 'Generate associated PHPUnit test');
    }

    /**
     * @throws As400Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $db = strtoupper($input->getArgument('database'));
        $table = strtoupper($input->getArgument('table'));
        $outputNamespace = $input->getArgument('output-namespace') ?? $this->entityNamespace;
        $withRepo = $input->getOption('with-repository');
        $withTest = $input->getOption('with-test');

        if (!$db || !$table) {
            $io->error('Please provide a database and table name.');
            return Command::FAILURE;
        }

        $filesystem = new Filesystem();

        $columns = $this->connection->fetchAll(
            "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM QSYS2.SYSCOLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
            [$db, $table]
        );

        $className = str_replace(' ', '', ucwords(strtolower(str_replace('_', ' ', $table))));
        $namespace = "$outputNamespace\\$db";

        // Create the path based on configured directory and project root
        $entityPath = $this->projectDir . '/' . $this->entityDir . '/' . $db . '/' . $className . '.php';

        $constants = array_reduce($columns, static fn($carry, $col) => $carry . "    const string " . strtoupper($col['COLUMN_NAME']) . " = '" . strtoupper($col['COLUMN_NAME']) . "';\n",
            ''
        );

        $properties = array_reduce($columns, static fn($carry, $col) => $carry . "    #[Column(name: self::" . strtoupper($col['COLUMN_NAME']) . ")] public string|null $" . strtolower($col['COLUMN_NAME']) . " = null;\n",
            ''
        );

        $entityContent = $this->renderTemplate(
            __DIR__ . '/../Resources/templates/as400/entity.tpl.php',
            [
                'namespace' => $namespace,
                'className' => $className,
                'database' => $db,
                'table' => $table,
                'identifier' => $columns[0]['COLUMN_NAME'] ?? '',
                'constants' => trim($constants),
                'properties' => trim($properties),
            ]
        );

        $filesystem->mkdir(dirname($entityPath));

        file_put_contents($entityPath, $entityContent);
        $entityRelativePath = str_replace($this->projectDir . '/', '', $entityPath);
        $io->success("Entity generated at: $entityRelativePath");

        if ($withRepo) {
            $repoNamespace = $this->repositoryNamespace . '\\' . $db;
            $repoPath = $this->projectDir . '/' . $this->repositoryDir . '/' . $db . '/' . $className . 'Repository.php';

            $repoContent = $this->renderTemplate(
                __DIR__ . '/../Resources/templates/as400/repository.tpl.php',
                [
                    'repositoryNamespace' => $repoNamespace,
                    'entityNamespace' => $namespace,
                    'className' => $className,
                ]
            );

            $filesystem->mkdir(dirname($repoPath));
            file_put_contents($repoPath, $repoContent);
            $repoRelativePath = str_replace($this->projectDir . '/', '', $repoPath);
            $io->success("Repository generated at: $repoRelativePath");
        }

        if ($withTest) {
            $testNs = $this->testNamespace . '\\' . $db;
            $testPath = $this->projectDir . '/' . $this->testDir . '/' . $db . '/' . $className . 'Test.php';

            $testContent = $this->renderTemplate(
                __DIR__ . '/../Resources/templates/as400/entity_test.tpl.php',
                [
                    'testNamespace' => $testNs,
                    'entityNamespace' => $namespace,
                    'className' => $className,
                    'database' => $db,
                    'table' => $table,
                    'identifier' => $columns[0]['COLUMN_NAME'] ?? '',
                    'columnConstantAssertions' => $this->generateColumnConstantAssertions($columns, $className),
                    'propertySetStatements' => $this->generatePropertySetStatements($columns),
                    'propertyAssertions' => $this->generatePropertyAssertions($columns),
                    'nullAssertions' => $this->generateNullAssertions($columns),
                ]
            );

            $filesystem->mkdir(dirname($testPath));
            file_put_contents($testPath, $testContent);
            $testRelativePath = str_replace($this->projectDir . '/', '', $testPath);
            $io->success("Test generated at: $testRelativePath");
        }

        return Command::SUCCESS;
    }

    private function renderTemplate(string $templatePath, array $variables): string
    {
        $template = file_get_contents($templatePath);
        foreach ($variables as $key => $value) {
            $template = str_replace('{{ ' . $key . ' }}', $value, $template);
        }
        return $template;
    }

    /**
     * Generate assertions for column constants (first 5 columns).
     */
    private function generateColumnConstantAssertions(array $columns, string $className): string
    {
        $assertions = [];
        $columnsToTest = array_slice($columns, 0, min(5, count($columns)));

        foreach ($columnsToTest as $col) {
            $colName = strtoupper($col['COLUMN_NAME']);
            $assertions[] = "        \$this->assertSame('$colName', $className::$colName);";
        }

        return implode("\n", $assertions);
    }

    /**
     * Generate property set statements for tests (first 5 columns).
     */
    private function generatePropertySetStatements(array $columns): string
    {
        $statements = [];
        $columnsToTest = array_slice($columns, 0, min(5, count($columns)));

        foreach ($columnsToTest as $col) {
            $propName = strtolower($col['COLUMN_NAME']);
            $testValue = $this->generateTestValue($col);
            $statements[] = "        \$entity->$propName = '$testValue';";
        }

        return implode("\n", $statements);
    }

    /**
     * Generate property assertions for tests (first 5 columns).
     */
    private function generatePropertyAssertions(array $columns): string
    {
        $assertions = [];
        $columnsToTest = array_slice($columns, 0, min(5, count($columns)));

        foreach ($columnsToTest as $col) {
            $propName = strtolower($col['COLUMN_NAME']);
            $testValue = $this->generateTestValue($col);
            $assertions[] = "        \$this->assertSame('$testValue', \$entity->$propName);";
        }

        return implode("\n", $assertions);
    }

    /**
     * Generate null assertions for tests (first 5 columns).
     */
    private function generateNullAssertions(array $columns): string
    {
        $assertions = [];
        $columnsToTest = array_slice($columns, 0, min(5, count($columns)));

        foreach ($columnsToTest as $col) {
            $propName = strtolower($col['COLUMN_NAME']);
            $assertions[] = "        \$this->assertNull(\$entity->$propName);";
        }

        return implode("\n", $assertions);
    }

    /**
     * Generate a test value based on column data type.
     */
    private function generateTestValue(array $column): string
    {
        $colName = strtoupper($column['COLUMN_NAME']);
        $dataType = strtoupper($column['DATA_TYPE']);

        // Use meaningful test values based on common column name patterns
        return match (true) {
            str_contains($colName, 'DATE') || str_starts_with($colName, 'DT') => '2024-01-15',
            str_contains($colName, 'EMAIL') => 'test@example.com',
            str_contains($colName, 'TEL') || str_contains($colName, 'PHONE') => '0123456789',
            str_contains($colName, 'CODE') => 'CODE01',
            str_contains($colName, 'ID') => '12345',
            str_contains($colName, 'NOM') || str_contains($colName, 'NAME') => 'TestName',
            str_contains($colName, 'LIBEL') => 'Test Label',
            in_array($dataType, ['INTEGER', 'DECIMAL', 'NUMERIC', 'SMALLINT', 'BIGINT']) => '123',
            default => 'test_value',
        };
    }
}
