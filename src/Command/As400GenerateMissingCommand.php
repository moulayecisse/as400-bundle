<?php

namespace Cisse\Bundle\As400\Command;

use Cisse\Bundle\As400\Entity\AbstractEntity;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'as400:generate:missing',
    description: 'Generate missing tests and/or repositories for existing AS400 entities',
)]
class As400GenerateMissingCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $entityDir,
        private readonly string $repositoryDir,
        private readonly string $testDir,
        private readonly string $entityNamespace,
        private readonly string $repositoryNamespace,
        private readonly string $testNamespace,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tests', 't', InputOption::VALUE_NONE, 'Generate missing tests only')
            ->addOption('repositories', 'r', InputOption::VALUE_NONE, 'Generate missing repositories only')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be generated without creating files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $testsOnly = $input->getOption('tests');
        $reposOnly = $input->getOption('repositories');
        $dryRun = $input->getOption('dry-run');

        // If neither specified, generate both
        $generateTests = !$reposOnly || $testsOnly;
        $generateRepos = !$testsOnly || $reposOnly;

        $entityPath = $this->projectDir . '/' . $this->entityDir;

        if (!is_dir($entityPath)) {
            $io->error("Entity directory not found: $entityPath");
            return Command::FAILURE;
        }

        $finder = new Finder();
        $finder->files()->in($entityPath)->name('*.php');

        $generatedTests = 0;
        $generatedRepos = 0;
        $skippedTests = 0;
        $skippedRepos = 0;

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePath();
            $className = $file->getBasename('.php');
            $database = $relativePath ?: 'Unknown';

            // Build the full class name
            $fullClassName = $this->entityNamespace . '\\' . ($relativePath ? $relativePath . '\\' : '') . $className;
            $fullClassName = str_replace('/', '\\', $fullClassName);

            // Skip if class doesn't exist or isn't an AS400 entity
            if (!class_exists($fullClassName)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($fullClassName);
                if (!$reflection->isSubclassOf(AbstractEntity::class)) {
                    continue;
                }
            } catch (\ReflectionException) {
                continue;
            }

            // Get entity metadata via reflection
            $entityInfo = $this->extractEntityInfo($reflection);
            if (!$entityInfo) {
                continue;
            }

            // Generate missing test
            if ($generateTests) {
                $testPath = $this->projectDir . '/' . $this->testDir . '/' . $relativePath . '/' . $className . 'Test.php';

                if (file_exists($testPath)) {
                    $skippedTests++;
                } else {
                    if ($dryRun) {
                        $io->text("Would generate test: " . str_replace($this->projectDir . '/', '', $testPath));
                    } else {
                        $this->generateTest($filesystem, $testPath, $className, $relativePath, $entityInfo);
                        $io->success("Generated test: " . str_replace($this->projectDir . '/', '', $testPath));
                    }
                    $generatedTests++;
                }
            }

            // Generate missing repository
            if ($generateRepos) {
                $repoPath = $this->projectDir . '/' . $this->repositoryDir . '/' . $relativePath . '/' . $className . 'Repository.php';

                if (file_exists($repoPath)) {
                    $skippedRepos++;
                } else {
                    if ($dryRun) {
                        $io->text("Would generate repository: " . str_replace($this->projectDir . '/', '', $repoPath));
                    } else {
                        $this->generateRepository($filesystem, $repoPath, $className, $relativePath);
                        $io->success("Generated repository: " . str_replace($this->projectDir . '/', '', $repoPath));
                    }
                    $generatedRepos++;
                }
            }
        }

        $io->newLine();
        $io->table(
            ['Type', 'Generated', 'Already Exist'],
            [
                ['Tests', $generatedTests, $skippedTests],
                ['Repositories', $generatedRepos, $skippedRepos],
            ]
        );

        if ($dryRun) {
            $io->note('Dry run - no files were created');
        }

        return Command::SUCCESS;
    }

    private function extractEntityInfo(ReflectionClass $reflection): ?array
    {
        $info = [
            'database' => null,
            'table' => null,
            'identifier' => null,
            'columns' => [],
            'properties' => [],
        ];

        // Get constants
        if ($reflection->hasConstant('DATABASE_NAME')) {
            $info['database'] = $reflection->getConstant('DATABASE_NAME');
        }
        if ($reflection->hasConstant('TABLE_NAME')) {
            $info['table'] = $reflection->getConstant('TABLE_NAME');
        }
        if ($reflection->hasConstant('IDENTIFIER_NAME')) {
            $info['identifier'] = $reflection->getConstant('IDENTIFIER_NAME');
        }

        if (!$info['database'] || !$info['table']) {
            return null;
        }

        // Get column constants (all string constants that are uppercase)
        foreach ($reflection->getConstants() as $name => $value) {
            if (is_string($value) && $name === strtoupper($name)
                && !in_array($name, ['DATABASE_NAME', 'TABLE_NAME', 'IDENTIFIER_NAME'])) {
                $info['columns'][$name] = $value;
            }
        }

        // Get properties
        foreach ($reflection->getProperties() as $property) {
            if ($property->isPublic() && !$property->isStatic()) {
                $info['properties'][] = $property->getName();
            }
        }

        return $info;
    }

    private function generateTest(Filesystem $filesystem, string $testPath, string $className, string $database, array $entityInfo): void
    {
        $testNamespace = $this->testNamespace . ($database ? '\\' . $database : '');
        $entityNamespace = $this->entityNamespace . ($database ? '\\' . $database : '');

        // Generate column constant assertions (first 5)
        $columnAssertions = [];
        $columns = array_slice($entityInfo['columns'], 0, 5);
        foreach ($columns as $constName => $value) {
            $columnAssertions[] = "        \$this->assertSame('$value', $className::$constName);";
        }

        // Generate property tests (first 5)
        $properties = array_slice($entityInfo['properties'], 0, 5);
        $propertySetStatements = [];
        $propertyAssertions = [];
        $nullAssertions = [];

        foreach ($properties as $prop) {
            $testValue = $this->generateTestValue($prop);
            $propertySetStatements[] = "        \$entity->$prop = '$testValue';";
            $propertyAssertions[] = "        \$this->assertSame('$testValue', \$entity->$prop);";
            $nullAssertions[] = "        \$this->assertNull(\$entity->$prop);";
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace $testNamespace;

use $entityNamespace\\$className;
use PHPUnit\Framework\TestCase;

class {$className}Test extends TestCase
{
    public function testConstantsAreDefined(): void
    {
        \$this->assertSame('{$entityInfo['database']}', $className::DATABASE_NAME);
        \$this->assertSame('{$entityInfo['table']}', $className::TABLE_NAME);
        \$this->assertSame('{$entityInfo['identifier']}', $className::IDENTIFIER_NAME);
    }

    public function testColumnConstantsAreDefined(): void
    {
%s
    }

    public function testPropertiesCanBeSet(): void
    {
        \$entity = new $className();

%s

%s
    }

    public function testAllPropertiesDefaultToNull(): void
    {
        \$entity = new $className();

%s
    }
}

PHP;

        $content = sprintf(
            $content,
            implode("\n", $columnAssertions),
            implode("\n", $propertySetStatements),
            implode("\n", $propertyAssertions),
            implode("\n", $nullAssertions)
        );

        $filesystem->mkdir(dirname($testPath));
        file_put_contents($testPath, $content);
    }

    private function generateRepository(Filesystem $filesystem, string $repoPath, string $className, string $database): void
    {
        $repoNamespace = $this->repositoryNamespace . ($database ? '\\' . $database : '');
        $entityNamespace = $this->entityNamespace . ($database ? '\\' . $database : '');

        $content = $this->renderTemplate(
            __DIR__ . '/../Resources/templates/as400/repository.tpl.php',
            [
                'repositoryNamespace' => $repoNamespace,
                'entityNamespace' => $entityNamespace,
                'className' => $className,
            ]
        );

        $filesystem->mkdir(dirname($repoPath));
        file_put_contents($repoPath, $content);
    }

    private function renderTemplate(string $templatePath, array $variables): string
    {
        $template = file_get_contents($templatePath);
        foreach ($variables as $key => $value) {
            $template = str_replace('{{ ' . $key . ' }}', $value, $template);
        }
        return $template;
    }

    private function generateTestValue(string $propertyName): string
    {
        $prop = strtoupper($propertyName);

        return match (true) {
            str_contains($prop, 'DATE') || str_starts_with($prop, 'DT') => '2024-01-15',
            str_contains($prop, 'EMAIL') => 'test@example.com',
            str_contains($prop, 'TEL') || str_contains($prop, 'PHONE') => '0123456789',
            str_contains($prop, 'CODE') => 'CODE01',
            str_contains($prop, 'ID') => '12345',
            str_contains($prop, 'NOM') || str_contains($prop, 'NAME') => 'TestName',
            str_contains($prop, 'LIBEL') => 'Test Label',
            default => 'test_value',
        };
    }
}
