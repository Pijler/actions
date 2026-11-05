<?php

use App\Workflows\GeneratedWorkflow;
use App\Workflows\GeneratedWorkflowContext;
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

test('it should generate a workflow and its context', function () {
    $this->artisan('make:workflow', ['name' => 'Checkout'])->assertSuccessful();

    expect(File::get(app_path('Workflows/Checkout.php')))
        ->toContain('namespace App\Workflows;')
        ->toContain('class Checkout extends Workflow')
        ->toContain('protected CheckoutContext $context')
        ->not->toContain('{{');

    expect(File::get(app_path('Workflows/CheckoutContext.php')))
        ->toContain('namespace App\Workflows;')
        ->toContain('class CheckoutContext')
        ->not->toContain('{{');
});

test('it should keep an existing context, even with the force option', function () {
    $this->artisan('make:workflow', ['name' => 'Checkout']);

    File::put(app_path('Workflows/CheckoutContext.php'), 'changed');

    $this->artisan('make:workflow', ['name' => 'Checkout', '--force' => true])->assertSuccessful();

    expect(File::get(app_path('Workflows/CheckoutContext.php')))->toBe('changed');
});

test('it should generate a workflow that can be executed', function () {
    $this->artisan('make:workflow', ['name' => 'GeneratedWorkflow'])->assertSuccessful();

    require_once app_path('Workflows/GeneratedWorkflowContext.php');
    require_once app_path('Workflows/GeneratedWorkflow.php');

    $context = new GeneratedWorkflowContext;

    expect(GeneratedWorkflow::execute($context))
        ->cancelled->toBeFalse()
        ->context->toBe($context);
});
