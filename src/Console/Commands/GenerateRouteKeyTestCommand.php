<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey\Console\Commands;

use Channor\HashedRouteKey\HashedRouteKeyCodec;
use Channor\HashedRouteKey\UsesHashedRouteKey;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class GenerateRouteKeyTestCommand extends Command
{
    private const FIXED_SALT_BASE = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

    /**
     * @var list<int>
     */
    private const SAMPLE_IDS = [1, 2, 42];

    protected $signature = 'route-key:generate-test
        {--class= : Model class, basename, or common plural alias}
        {--all : Generate tests for all models using the trait in the target namespace}
        {--namespace=App\\Models : Model namespace used for --all discovery}
        {--model-path= : Model directory used for --all discovery}
        {--path=tests/Feature/RouteKeys : Directory to write the generated test into}
        {--force : Overwrite an existing generated test file}';

    protected $description = 'Generate a stable route-key contract test for a model using UsesHashedRouteKey.';

    public function handle(): int
    {
        $modelClasses = $this->resolveModelClasses();

        if ($modelClasses === null) {
            return self::FAILURE;
        }

        if ($modelClasses === []) {
            $this->warn('No models using UsesHashedRouteKey were found for the requested scope.');

            return self::FAILURE;
        }

        $generatedCount = 0;
        $skippedCount = 0;

        foreach ($modelClasses as $modelClass) {
            $targetPath = $this->targetPath($modelClass);

            if (File::exists($targetPath) && ! $this->option('force')) {
                if (count($modelClasses) === 1) {
                    $this->error(sprintf('Test file already exists at [%s]. Use --force to overwrite it.', $targetPath));

                    return self::FAILURE;
                }

                $this->line(sprintf('Skipped existing route-key contract test at [%s].', $targetPath));
                $skippedCount++;

                continue;
            }

            /** @var Model $model */
            $model = new $modelClass;

            File::ensureDirectoryExists(dirname($targetPath));
            File::put($targetPath, $this->renderTest($modelClass, $model));
            $this->info(sprintf('Generated route-key contract test at [%s].', $targetPath));
            $generatedCount++;
        }

        if (count($modelClasses) > 1) {
            $this->info(sprintf(
                'Route-key contract generation complete: %d generated, %d skipped.',
                $generatedCount,
                $skippedCount,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<class-string<Model>>|null
     */
    private function resolveModelClasses(): ?array
    {
        $all = (bool) $this->option('all');
        $classOption = $this->option('class');

        if ($all && is_string($classOption) && trim($classOption) !== '') {
            $this->error('Use either --class or --all, not both.');

            return null;
        }

        if (! $all && (! is_string($classOption) || trim($classOption) === '')) {
            $this->error('Either --class or --all is required.');

            return null;
        }

        if ($all) {
            return $this->discoverModelClasses();
        }

        /** @var string $classOption */
        $modelClass = $this->resolveModelClass($classOption);

        if ($modelClass === null) {
            return null;
        }

        if (! $this->usesHashedRouteKey($modelClass)) {
            $this->error(sprintf(
                'Model [%s] does not use %s.',
                $modelClass,
                UsesHashedRouteKey::class,
            ));

            return null;
        }

        return [$modelClass];
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveModelClass(string $option): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            $option,
            'App\\Models\\'.$option,
            'App\\Models\\'.Str::studly($option),
            'App\\Models\\'.Str::studly(Str::singular($option)),
        ], static fn (string $candidate): bool => $candidate !== '')));

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, Model::class)) {
                /** @var class-string<Model> $candidate */
                return $candidate;
            }
        }

        $this->error(sprintf('Unable to resolve model class from [%s].', $option));

        return null;
    }

    /**
     * @return list<class-string<Model>>|null
     */
    private function discoverModelClasses(): ?array
    {
        $namespaceOption = $this->option('namespace');
        $namespace = is_string($namespaceOption) ? trim($namespaceOption, '\\') : 'App\\Models';
        $modelPath = $this->resolveModelPath($namespace);

        if ($modelPath === null) {
            return null;
        }

        if (! File::isDirectory($modelPath)) {
            $this->error(sprintf('Model discovery path [%s] does not exist.', $modelPath));

            return null;
        }

        /** @var list<class-string<Model>> $models */
        $models = LazyCollection::make(File::allFiles($modelPath))
            ->filter(static fn (\SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->map(function (\SplFileInfo $file) use ($modelPath, $namespace): ?string {
                $relativePath = str_replace($modelPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                $class = $namespace.'\\'.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    str_replace(DIRECTORY_SEPARATOR, '/', $relativePath),
                );

                if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                    return null;
                }

                return $this->usesHashedRouteKey($class) ? $class : null;
            })
            ->filter()
            ->values()
            ->all();

        sort($models);

        return $models;
    }

    private function resolveModelPath(string $namespace): ?string
    {
        $modelPathOption = $this->option('model-path');

        if (is_string($modelPathOption) && trim($modelPathOption) !== '') {
            return $this->projectBasePath(trim($modelPathOption, '/'));
        }

        if ($namespace === 'App\\Models') {
            return $this->projectBasePath('app/Models');
        }

        if (str_starts_with($namespace, 'App\\')) {
            return $this->projectBasePath('app/'.str_replace('\\', '/', Str::after($namespace, 'App\\')));
        }

        $this->error('When --namespace is outside App\\..., you must also provide --model-path.');

        return null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function usesHashedRouteKey(string $modelClass): bool
    {
        return in_array(UsesHashedRouteKey::class, class_uses_recursive($modelClass), true);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function targetPath(string $modelClass): string
    {
        $pathOption = $this->option('path');
        $directory = is_string($pathOption) ? $pathOption : 'tests/Feature/RouteKeys';

        return $this->projectBasePath(trim($directory, '/').'/'.class_basename($modelClass).'RouteKeyContractTest.php');
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function renderTest(string $modelClass, Model $model): string
    {
        $targetPath = $this->targetPath($modelClass);

        $replacements = [
            '{{ namespace }}' => $this->namespaceFor($targetPath),
            '{{ class }}' => class_basename($modelClass).'RouteKeyContractTest',
            '{{ modelClass }}' => '\\'.$modelClass.'::class',
            '{{ fixedSaltBase }}' => var_export(self::FIXED_SALT_BASE, true),
            '{{ minPayloadLength }}' => (string) $this->callProtected($model, 'routeKeyMinPayloadLength'),
            '{{ checkLength }}' => (string) $this->callProtected($model, 'routeKeyCheckLength'),
            '{{ offsetMultiplier }}' => (string) $this->callProtected($model, 'routeKeyOffsetMultiplier'),
            '{{ saltSuffix }}' => var_export((string) $this->callProtected($model, 'routeKeySaltSuffix'), true),
            '{{ sampleAssertions }}' => $this->sampleAssertions($model),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            (string) File::get($this->stubPath()),
        );
    }

    private function namespaceFor(string $targetPath): string
    {
        $relativePath = str_replace($this->projectBasePath().DIRECTORY_SEPARATOR, '', $targetPath);
        $directory = str_replace(DIRECTORY_SEPARATOR, '/', dirname($relativePath));

        if ($directory === 'tests' || ! str_starts_with($directory, 'tests/')) {
            return 'Tests';
        }

        return str_replace('/', '\\', Str::studly($directory));
    }

    private function callProtected(Model $model, string $method): mixed
    {
        return (fn () => $this->{$method}())->call($model);
    }

    private function sampleAssertions(Model $model): string
    {
        $codec = new HashedRouteKeyCodec(
            salt: self::FIXED_SALT_BASE.':'.$this->callProtected($model, 'routeKeySaltSuffix'),
            minPayloadLength: (int) $this->callProtected($model, 'routeKeyMinPayloadLength'),
            checkLength: (int) $this->callProtected($model, 'routeKeyCheckLength'),
            offsetMultiplier: (int) $this->callProtected($model, 'routeKeyOffsetMultiplier'),
        );

        $keyName = $model->getKeyName();

        return collect(self::SAMPLE_IDS)
            ->map(function (int $id) use ($keyName, $codec): string {
                $hash = $codec->encode($id);
                $quotedKeyName = var_export($keyName, true);

                return <<<PHP
                        \$model = new \$modelClass;
                        \$model->forceFill([{$quotedKeyName} => {$id}]);
                        \$this->assertSame('{$hash}', \$model->getRouteKey());
                PHP;
            })
            ->implode("\n\n");
    }

    protected function projectBasePath(string $path = ''): string
    {
        $basePath = base_path();

        if ($path === '') {
            return $basePath;
        }

        return $basePath.DIRECTORY_SEPARATOR.trim($path, '/');
    }

    protected function stubPath(): string
    {
        return __DIR__.'/../../../stubs/route-key-contract.test.stub';
    }
}
