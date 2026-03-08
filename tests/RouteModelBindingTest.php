<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey\Tests;

use Channor\HashedRouteKey\HashedRouteKeyCodec;
use Channor\HashedRouteKey\UsesHashedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class RouteModelBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('hashed_route_key_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('hashed_route_key_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->string('title');
            $table->timestamps();
        });

        Route::model('project', RouteFakeProject::class);
        Route::model('task', RouteFakeTask::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('hashed_route_key_tasks');
        Schema::dropIfExists('hashed_route_key_projects');

        parent::tearDown();
    }

    private function fakeRoute(string $method, string $uri, \Closure $action): \Illuminate\Routing\Route
    {
        return Route::$method($uri, $action)->middleware(SubstituteBindings::class);
    }

    private function routeUrl(string $path): string
    {
        return 'http://localhost'.$path;
    }

    public function test_single_model_route_resolves(): void
    {
        $project = RouteFakeProject::create(['name' => 'Acme']);

        $this->fakeRoute('get', '/hash-test/{project}', fn (RouteFakeProject $project) => response()->json([
            'id' => $project->getKey(),
            'name' => $project->name,
        ]));

        $this->get($this->routeUrl('/hash-test/'.$project->getRouteKey()))
            ->assertOk()
            ->assertJson([
                'id' => $project->getKey(),
                'name' => 'Acme',
            ]);
    }

    public function test_wrong_model_hash_returns_404(): void
    {
        $project = RouteFakeProject::create(['name' => 'Acme']);

        $this->fakeRoute('get', '/hash-test/{project}', fn (RouteFakeProject $project) => response()->json([
            'id' => $project->getKey(),
        ]));

        $wrongCodec = new HashedRouteKeyCodec(salt: config('hashed-route-key.salt').':route_fake_task');
        $wrongHash = $wrongCodec->encode((int) $project->getKey());

        $this->get($this->routeUrl('/hash-test/'.$wrongHash))->assertNotFound();
    }

    public function test_nested_route_url_generation_uses_hashed_keys(): void
    {
        $project = RouteFakeProject::create(['name' => 'Acme']);
        $task = RouteFakeTask::create(['project_id' => $project->getKey(), 'title' => 'Do stuff']);

        $this->fakeRoute('get', '/hash-test/{project}/tasks/{task}', fn (RouteFakeProject $project, RouteFakeTask $task) => '')
            ->name('hash.projects.tasks.show');

        app('router')->getRoutes()->refreshNameLookups();

        $url = route('hash.projects.tasks.show', ['project' => $project, 'task' => $task]);

        $this->assertStringContainsString($project->getRouteKey(), $url);
        $this->assertStringContainsString($task->getRouteKey(), $url);
    }
}

class RouteFakeProject extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'hashed_route_key_projects';

    protected $guarded = [];
}

class RouteFakeTask extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'hashed_route_key_tasks';

    protected $guarded = [];
}
