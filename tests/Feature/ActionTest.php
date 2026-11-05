<?php

use Illuminate\Contracts\Config\Repository;
use Pijler\Actions\Action;
use Workbench\App\Actions\Fail;
use Workbench\App\Actions\Greet;

test('it should execute the action and return the value of handle', function () {
    expect(Greet::execute('Ana'))->toBe('Hello, Ana!');
});

test('it should accept named, positional and mixed arguments', function () {
    expect(Greet::execute(name: 'Ana', greeting: 'Hi'))->toBe('Hi, Ana!');

    expect(Greet::execute('Ana', 'Hi'))->toBe('Hi, Ana!');

    expect(Greet::execute('Ana', greeting: 'Hi'))->toBe('Hi, Ana!');
});

test('it should let the container resolve the dependencies not given', function () {
    $action = new class(app(Repository::class)) extends Action
    {
        public function __construct(public Repository $config) {}

        protected function handle(): Repository
        {
            return $this->config;
        }
    };

    expect($action::execute())->toBe(app(Repository::class));
});

test('it should inject dependencies into handle', function () {
    $action = new class extends Action
    {
        protected function handle(Repository $config): Repository
        {
            return $config;
        }
    };

    expect($action::execute())->toBe(app(Repository::class));
});

test('it should execute conditionally', function () {
    expect(Greet::executeIf(true, 'Ana'))->toBe('Hello, Ana!');

    expect(Greet::executeIf(false, 'Ana'))->toBeNull();

    expect(Greet::executeUnless(false, 'Ana'))->toBe('Hello, Ana!');

    expect(Greet::executeUnless(true, 'Ana'))->toBeNull();

    expect(Fail::executeIf(false))->toBeNull();

    expect(Fail::executeUnless(true))->toBeNull();
});

test('it should let the exceptions of the action reach the caller', function () {
    Fail::execute();
})->throws(RuntimeException::class, 'It failed.');
