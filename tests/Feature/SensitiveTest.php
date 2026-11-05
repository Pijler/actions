<?php

use Monolog\Formatter\NormalizerFormatter;
use Pijler\Actions\Action;
use Pijler\Actions\Support\Redactor;
use Workbench\App\Actions\Login;

test('it should hide sensitive properties when the action is logged', function () {
    $normalized = (new NormalizerFormatter)->normalizeValue(new Login('ana@example.com', 'secret'));

    expect(json_encode($normalized))
        ->not->toContain('secret')
        ->toContain('ana@example.com');
});

test('it should hide sensitive properties when the action is dumped', function () {
    $action = new Login('ana@example.com', 'secret');

    expect(print_r($action, true))->not->toContain('secret');

    expect($action->__debugInfo())->toBe(['email' => 'ana@example.com', 'password' => '[sensitive]']);
});

test('it should hide a sensitive parameter that is not a promoted property', function () {
    $action = new class('secret') extends Action
    {
        private string $token;

        public function __construct(#[SensitiveParameter] string $token)
        {
            $this->token = $token;
        }

        protected function handle(): string
        {
            return $this->token;
        }
    };

    expect($action->jsonSerialize())->toBe(['token' => '[sensitive]']);

    expect($action::execute('secret'))->toBe('secret');
});

test('it should leave the queue settings out of what is logged', function () {
    $action = (new Login('ana@example.com', 'secret'))->onQueue('other');

    expect(collect($action->jsonSerialize())->keys()->all())->toBe(['email', 'password']);
});

test('it should read the constructor values of an inherited private property', function () {
    $child = new ChildOfPrivateConstructor('value');

    expect(Redactor::constructorValues($child))->toBe(['name' => 'value']);
});

class ParentWithPrivateConstructor extends Action
{
    public function __construct(private string $name) {}
}

class ChildOfPrivateConstructor extends ParentWithPrivateConstructor {}
