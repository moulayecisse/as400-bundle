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

#[AsCommand(
    name: 'as400:run-sql',
    description: 'Executes arbitrary SQL directly from the command line against AS400.',
)]
class As400RunSqlCommand extends Command
{
    public function __construct(
        private readonly As400Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('sql', InputArgument::REQUIRED, 'The SQL statement to execute.')
            ->addOption('force-fetch', null, InputOption::VALUE_NONE, 'Forces fetching the result even for non-SELECT queries.')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Output raw data without table formatting.')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command executes the given SQL query and outputs the results:

  <info>php %command.full_name% "SELECT * FROM SCHEMA.TABLE FETCH FIRST 10 ROWS ONLY"</info>

For INSERT, UPDATE, or DELETE statements, the command will display affected rows:

  <info>php %command.full_name% "UPDATE SCHEMA.TABLE SET FIELD = 'value' WHERE ID = 1"</info>

Use the --force-fetch option to force fetching results even for non-SELECT queries:

  <info>php %command.full_name% --force-fetch "SELECT * FROM SCHEMA.TABLE"</info>

Use the --raw option to output data without table formatting (useful for piping):

  <info>php %command.full_name% --raw "SELECT * FROM SCHEMA.TABLE FETCH FIRST 10 ROWS ONLY"</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sql = $input->getArgument('sql');
        $forceFetch = $input->getOption('force-fetch');
        $raw = $input->getOption('raw');

        if (empty(trim($sql))) {
            $io->error('SQL statement cannot be empty.');
            return Command::FAILURE;
        }

        $isSelectQuery = $this->isSelectQuery($sql);

        try {
            if ($isSelectQuery || $forceFetch) {
                $results = $this->connection->fetchAll($sql);

                if (empty($results)) {
                    $io->info('Query returned no results.');
                    return Command::SUCCESS;
                }

                if ($raw) {
                    $this->outputRaw($output, $results);
                } else {
                    $this->outputTable($io, $results);
                }

                $io->success(sprintf('Returned %d row(s).', count($results)));
            } else {
                $result = $this->connection->executeWithTransaction($sql);

                if ($result) {
                    $io->success('Query executed successfully.');
                } else {
                    $io->warning('Query executed but returned false (possibly no rows affected).');
                }
            }

            return Command::SUCCESS;
        } catch (As400Exception $e) {
            $io->error('SQL execution failed: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (\PDOException $e) {
            $io->error('Database error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function isSelectQuery(string $sql): bool
    {
        $trimmedSql = trim($sql);
        return preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\s/i', $trimmedSql) === 1;
    }

    private function outputTable(SymfonyStyle $io, array $results): void
    {
        $headers = array_keys($results[0]);
        $rows = array_map('array_values', $results);

        $io->table($headers, $rows);
    }

    private function outputRaw(OutputInterface $output, array $results): void
    {
        $headers = array_keys($results[0]);
        $output->writeln(implode("\t", $headers));

        foreach ($results as $row) {
            $output->writeln(implode("\t", array_values($row)));
        }
    }
}
