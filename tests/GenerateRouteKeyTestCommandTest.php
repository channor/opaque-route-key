<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey\Tests;

use Channor\OpaqueRouteKey\Console\Commands\GenerateRouteKeyTestCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateRouteKeyTestCommandTest extends TestCase
{
    private string $sandboxRoot;

    private string $fixtureModelClass;

    private string $plainFixtureClass;

    private string $fixtureNamespaceClass;

    private string $secondaryFixtureClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxRoot = sys_get_temp_dir().'/opaque-route-key-test-'.bin2hex(random_bytes(8));
        $suffix = Str::studly(bin2hex(random_bytes(4)));
        $this->fixtureModelClass = 'RouteKeyCommandFixtureModel'.$suffix;
        $this->plainFixtureClass = 'PlainRouteKeyCommandFixture'.$suffix;
        $this->fixtureNamespaceClass = 'FixtureModel'.$suffix;
        $this->secondaryFixtureClass = 'SecondaryRouteKeyFixture'.$suffix;

        File::ensureDirectoryExists($this->sandboxPath('app/Models'));
        File::ensureDirectoryExists($this->sandboxPath('tests/GeneratedRouteKeyContracts'));

        $this->writeFixtureModel(
            $this->sandboxPath('app/Models/'.$this->fixtureModelClass.'.php'),
            'App\\Models',
            $this->fixtureModelClass,
            true,
        );

        $this->writeFixtureModel(
            $this->sandboxPath('app/Models/'.$this->plainFixtureClass.'.php'),
            'App\\Models',
            $this->plainFixtureClass,
            false,
        );

        $this->writeFixtureModel(
            $this->sandboxPath('app/Models/'.$this->secondaryFixtureClass.'.php'),
            'App\\Models',
            $this->secondaryFixtureClass,
            true,
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandboxRoot);

        parent::tearDown();
    }

    public function test_it_generates_a_route_key_contract_test_for_a_model_using_the_trait(): void
    {
        $tester = $this->runCommand([
            '--class' => Str::plural($this->fixtureModelClass),
            '--path' => 'tests/GeneratedRouteKeyContracts',
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Generated route-key contract test', $tester->getDisplay());

        $path = $this->sandboxPath('tests/GeneratedRouteKeyContracts/'.$this->fixtureModelClass.'RouteKeyContractTest.php');

        $this->assertFileExists($path);

        $contents = (string) File::get($path);

        $this->assertStringContainsString('namespace Tests\\GeneratedRouteKeyContracts;', $contents);
        $this->assertStringContainsString('private const MODEL_CLASS = \\App\\Models\\'.$this->fixtureModelClass.'::class;', $contents);
        $this->assertStringContainsString('private const CHANGE_MESSAGE = \'Route-key contract changed.', $contents);
        $this->assertStringContainsString('$this->assertSame(3, $minPayloadLength, self::CHANGE_MESSAGE);', $contents);
        $this->assertStringContainsString('$this->assertSame(4, $checkLength, self::CHANGE_MESSAGE);', $contents);
        $this->assertStringContainsString(
            '$this->assertSame(\''.Str::snake($this->fixtureModelClass).'\', $saltSuffix, self::CHANGE_MESSAGE);',
            $contents,
        );
        $this->assertStringContainsString('$this->assertSame(\'', $contents);
        $this->assertStringContainsString('$model->getRouteKey(), self::CHANGE_MESSAGE);', $contents);
        $this->assertStringNotContainsString('test_reserved_route_key_config_is_stable', $contents);
    }

    public function test_it_generates_a_reserved_route_key_config_contract_test(): void
    {
        config([
            'opaque-route-key.reserved_words' => ['admin', 'account'],
            'opaque-route-key.reserved_words_case_sensitive' => true,
            'opaque-route-key.auto_reserve_model_names' => false,
            'opaque-route-key.reserved_word_max_attempts' => 10,
        ]);

        $tester = $this->runCommand([
            '--reserved' => true,
            '--path' => 'tests/GeneratedRouteKeyContracts',
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Generated reserved route-key config contract test', $tester->getDisplay());

        $path = $this->sandboxPath('tests/GeneratedRouteKeyContracts/ReservedOpaqueRouteKeyTest.php');

        $this->assertFileExists($path);

        $contents = (string) File::get($path);

        $this->assertStringContainsString('class ReservedOpaqueRouteKeyTest extends TestCase', $contents);
        $this->assertStringContainsString('namespace Tests\\GeneratedRouteKeyContracts;', $contents);
        $this->assertStringContainsString('private const ROUTE_KEY_CONFIG = \'opaque-route-key\';', $contents);
        $this->assertStringContainsString('private const CHANGE_MESSAGE = \'Reserved route-key config changed.', $contents);
        $this->assertStringContainsString('$this->assertSame(array (', $contents);
        $this->assertStringContainsString('0 => \'admin\'', $contents);
        $this->assertStringContainsString('1 => \'account\'', $contents);
        $this->assertStringContainsString('$this->assertSame(true, config(self::ROUTE_KEY_CONFIG.\'.reserved_words_case_sensitive\'), self::CHANGE_MESSAGE);', $contents);
        $this->assertStringContainsString('$this->assertSame(false, config(self::ROUTE_KEY_CONFIG.\'.auto_reserve_model_names\'), self::CHANGE_MESSAGE);', $contents);
        $this->assertStringContainsString('$this->assertSame(10, config(self::ROUTE_KEY_CONFIG.\'.reserved_word_max_attempts\'), self::CHANGE_MESSAGE);', $contents);
    }

    public function test_it_overwrites_existing_contract_test_with_force(): void
    {
        $path = $this->sandboxPath('tests/GeneratedRouteKeyContracts/'.$this->fixtureModelClass.'RouteKeyContractTest.php');

        File::put($path, 'stale');

        $tester = $this->runCommand([
            '--class' => $this->fixtureModelClass,
            '--path' => 'tests/GeneratedRouteKeyContracts',
            '--force' => true,
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringNotContainsString('stale', (string) File::get($path));
    }

    public function test_it_overwrites_existing_reserved_contract_test_with_force(): void
    {
        $path = $this->sandboxPath('tests/GeneratedRouteKeyContracts/ReservedOpaqueRouteKeyTest.php');

        File::put($path, 'stale');

        $tester = $this->runCommand([
            '--reserved' => true,
            '--path' => 'tests/GeneratedRouteKeyContracts',
            '--force' => true,
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringNotContainsString('stale', (string) File::get($path));
    }

    public function test_it_rejects_models_that_do_not_use_the_trait(): void
    {
        $tester = $this->runCommand([
            '--class' => $this->plainFixtureClass,
            '--path' => 'tests/GeneratedRouteKeyContracts',
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('does not use', $tester->getDisplay());
    }

    public function test_it_generates_contract_tests_for_all_models_using_the_trait(): void
    {
        $tester = $this->runCommand([
            '--all' => true,
            '--path' => 'tests/GeneratedRouteKeyContracts',
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Route-key contract generation complete:', $tester->getDisplay());
        $this->assertFileExists(
            $this->sandboxPath('tests/GeneratedRouteKeyContracts/'.$this->fixtureModelClass.'RouteKeyContractTest.php'),
        );
        $this->assertFileExists(
            $this->sandboxPath('tests/GeneratedRouteKeyContracts/'.$this->secondaryFixtureClass.'RouteKeyContractTest.php'),
        );
    }

    public function test_it_can_generate_all_model_contracts_and_reserved_contract_together(): void
    {
        $tester = $this->runCommand([
            '--all' => true,
            '--reserved' => true,
            '--path' => 'tests/GeneratedRouteKeyContracts',
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Route-key contract generation complete: 3 generated, 0 skipped.', $tester->getDisplay());
        $this->assertFileExists(
            $this->sandboxPath('tests/GeneratedRouteKeyContracts/'.$this->fixtureModelClass.'RouteKeyContractTest.php'),
        );
        $this->assertFileExists(
            $this->sandboxPath('tests/GeneratedRouteKeyContracts/'.$this->secondaryFixtureClass.'RouteKeyContractTest.php'),
        );
        $this->assertFileExists(
            $this->sandboxPath('tests/GeneratedRouteKeyContracts/ReservedOpaqueRouteKeyTest.php'),
        );
    }

    public function test_it_reports_skipped_files_in_all_mode_without_force(): void
    {
        $existingPath = $this->sandboxPath('tests/GeneratedRouteKeyContracts/'.$this->fixtureModelClass.'RouteKeyContractTest.php');
        File::put($existingPath, 'existing');

        $tester = $this->runCommand([
            '--all' => true,
            '--path' => 'tests/GeneratedRouteKeyContracts',
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Skipped existing route-key contract test', $tester->getDisplay());
        $this->assertStringContainsString('generated, 1 skipped', $tester->getDisplay());
        $this->assertSame('existing', (string) File::get($existingPath));
    }

    public function test_it_can_infer_model_path_from_an_app_namespace_for_all_generation(): void
    {
        $this->writeFixtureModel(
            $this->sandboxPath('app/Support/RouteKeyCommandFixtures/'.$this->fixtureNamespaceClass.'.php'),
            'App\\Support\\RouteKeyCommandFixtures',
            $this->fixtureNamespaceClass,
            true,
        );

        $tester = $this->runCommand([
            '--all' => true,
            '--namespace' => 'App\\Support\\RouteKeyCommandFixtures',
            '--path' => 'tests/GeneratedRouteKeyContracts',
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString($this->fixtureNamespaceClass.'RouteKeyContractTest.php', $tester->getDisplay());

        $path = $this->sandboxPath('tests/GeneratedRouteKeyContracts/'.$this->fixtureNamespaceClass.'RouteKeyContractTest.php');

        $this->assertFileExists($path);
        $this->assertStringContainsString(
            'private const MODEL_CLASS = \\App\\Support\\RouteKeyCommandFixtures\\'.$this->fixtureNamespaceClass.'::class;',
            (string) File::get($path),
        );
    }

    public function test_it_rejects_using_class_and_all_together(): void
    {
        $tester = $this->runCommand([
            '--class' => $this->fixtureModelClass,
            '--all' => true,
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Use either --class or --all, not both.', $tester->getDisplay());
    }

    public function test_it_requires_class_or_all(): void
    {
        $tester = $this->runCommand();

        $this->assertSame(GenerateRouteKeyTestCommand::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Either --class or --all is required.', $tester->getDisplay());
    }

    public function test_it_requires_model_path_for_non_app_namespaces(): void
    {
        $tester = $this->runCommand([
            '--all' => true,
            '--namespace' => 'Domain\\Models',
            '--path' => 'tests/GeneratedRouteKeyContracts',
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString(
            'When --namespace is outside App\\..., you must also provide --model-path.',
            $tester->getDisplay(),
        );
    }

    public function test_it_fails_when_model_directory_does_not_exist(): void
    {
        $tester = $this->runCommand([
            '--all' => true,
            '--model-path' => 'app/MissingModels',
            '--path' => 'tests/GeneratedRouteKeyContracts',
        ]);

        $this->assertSame(GenerateRouteKeyTestCommand::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('does not exist', $tester->getDisplay());
    }

    public function test_it_fails_when_all_discovery_finds_no_trait_models(): void
    {
        $emptyRoot = sys_get_temp_dir().'/opaque-route-key-empty-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($emptyRoot.'/app/Models');
        File::ensureDirectoryExists($emptyRoot.'/tests/GeneratedRouteKeyContracts');

        try {
            $tester = $this->runCommand([
                '--all' => true,
                '--path' => 'tests/GeneratedRouteKeyContracts',
            ], $emptyRoot);

            $this->assertSame(GenerateRouteKeyTestCommand::FAILURE, $tester->getStatusCode());
            $this->assertStringContainsString('No models using UsesOpaqueRouteKey or deprecated UsesHashedRouteKey were found', $tester->getDisplay());
        } finally {
            File::deleteDirectory($emptyRoot);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function runCommand(array $input = [], ?string $sandboxRoot = null): CommandTester
    {
        $command = new class($sandboxRoot ?? $this->sandboxRoot) extends GenerateRouteKeyTestCommand
        {
            public function __construct(private readonly string $sandboxRoot)
            {
                parent::__construct();
            }

            protected function projectBasePath(string $path = ''): string
            {
                if ($path === '') {
                    return $this->sandboxRoot;
                }

                return $this->sandboxRoot.DIRECTORY_SEPARATOR.trim($path, '/');
            }
        };

        $command->setLaravel($this->app);

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    private function sandboxPath(string $path = ''): string
    {
        if ($path === '') {
            return $this->sandboxRoot;
        }

        return $this->sandboxRoot.DIRECTORY_SEPARATOR.trim($path, '/');
    }

    private function writeFixtureModel(string $path, string $namespace, string $class, bool $usesTrait): void
    {
        File::ensureDirectoryExists(dirname($path));

        $traitImport = $usesTrait ? "use Channor\\OpaqueRouteKey\\UsesOpaqueRouteKey;\n" : '';
        $traitUsage = $usesTrait ? "    use UsesOpaqueRouteKey;\n\n" : '';

        File::put($path, <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$traitImport}use Illuminate\Database\Eloquent\Model;

class {$class} extends Model
{
{$traitUsage}    protected \$guarded = [];
}
PHP);

        require_once $path;
    }
}
