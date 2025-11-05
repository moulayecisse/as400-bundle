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
        private readonly string $entityNamespace,
        private readonly string $repositoryNamespace,
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
            ->addOption('with-repository', null, InputOption::VALUE_NONE, 'Generate associated repository');
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
}
