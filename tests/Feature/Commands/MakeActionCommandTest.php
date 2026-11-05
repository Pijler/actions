<?php

use App\Actions\GeneratedAction;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/'.uniqid('pijler-actions-');

    File::ensureDirectoryExists("{$this->path}/app");

    File::put("{$this->path}/composer.json", json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));

    app()->setBasePath($this->path);
});

afterEach(function () {
    File::deleteDirectory($this->path);
});

test('it should generate an action', function () {
    $this->artisan('make:action', ['name' => 'CreateUser'])->assertSuccessful();

    $content = File::get(app_path('Actions/CreateUser.php'));

    expect($content)
        ->toContain('namespace App\Actions;')
        ->toContain('class CreateUser extends Action')
        ->toContain('public function handle()')
        ->not->toContain('{{');
});

test('it should not overwrite an action without the force option', function () {
    $this->artisan('make:action', ['name' => 'CreateUser']);

    File::put(app_path('Actions/CreateUser.php'), 'changed');

    $this->artisan('make:action', ['name' => 'CreateUser']);

    expect(File::get(app_path('Actions/CreateUser.php')))->toBe('changed');

    $this->artisan('make:action', ['name' => 'CreateUser', '--force' => true]);

    expect(File::get(app_path('Actions/CreateUser.php')))->not->toBe('changed');
});

test('it should generate an action that can be executed', function () {
    $this->artisan('make:action', ['name' => 'GeneratedAction'])->assertSuccessful();

    require_once app_path('Actions/GeneratedAction.php');

    expect(GeneratedAction::execute())->toBeNull();
});
