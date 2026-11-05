<?php

use PHPUnit\Framework\AssertionFailedError;
use Pijler\Actions\Action;
use Workbench\App\Actions\Fail;
use Workbench\App\Actions\Greet;
use Workbench\App\Actions\Record;

beforeEach(function () {
    config(['queue.default' => 'sync']);

    Record::$runs = [];
});

test('it should fake an action with a value or a callback', function () {
    Action::fake([Greet::class => 'fixed']);

    expect(Greet::execute('Ana'))->toBe('fixed');

    Action::fake([Greet::class => function (array $parameters) {
        return strtoupper(data_get($parameters, 'name'));
    }]);

    expect(Greet::execute('Ana'))->toBe('ANA');
});

test('it should fake an anonymous action, whose class name has dots', function () {
    $action = new class extends Action
    {
        public function handle(): string
        {
            return 'real';
        }
    };

    $fake = Action::fake([$action::class => 'fake']);

    expect($action::execute())->toBe('fake');

    $fake->assertExecutedTimes($action::class, 1);
});

test('it should fake an action given only by its class', function () {
    Action::fake([Fail::class]);

    expect(Fail::execute())->toBeNull();
});

test('it should let a callback throw in place of the action', function () {
    Action::fake([Greet::class => function () {
        throw new LogicException('Faked.');
    }]);

    Greet::execute('Ana');
})->throws(LogicException::class, 'Faked.');

test('it should run for real the actions that are not faked', function () {
    Action::fake();

    expect(Greet::execute('Ana'))->toBe('Hello, Ana!');
});

test('it should fake only inside the given callback', function () {
    $result = Action::fakeFor(function () {
        return Greet::execute('Ana');
    }, [Greet::class => 'inside']);

    expect($result)->toBe('inside');

    expect(Action::isFake())->toBeFalse();

    expect(Greet::execute('Ana'))->toBe('Hello, Ana!');
});

test('it should put back the previous fake after the callback', function () {
    Action::fake([Greet::class => 'outer']);

    Action::fakeFor(function () {
        return Greet::execute('Ana');
    }, [Greet::class => 'inner']);

    expect(Greet::execute('Ana'))->toBe('outer');
});

test('it should stop faking after a restore', function () {
    Action::fake([Greet::class => 'fake']);

    expect(Action::isFake())->toBeTrue();

    Action::restore();

    expect(Action::isFake())->toBeFalse();

    expect(Greet::execute('Ana'))->toBe('Hello, Ana!');
});

test('it should not carry the fake over to the next application', function () {
    Action::fake([Greet::class => 'fake']);

    $this->refreshApplication();

    expect(Action::isFake())->toBeFalse();
});

test('it should assert on executed actions', function () {
    $fake = Action::fake([Greet::class => 'fake']);

    Greet::execute('Ana');

    Greet::execute('Bia');

    $fake->assertExecuted(Greet::class);

    $fake->assertExecuted(Greet::class, function (array $parameters) {
        return data_get($parameters, 'name') === 'Bia';
    });

    $fake->assertNotExecuted(Greet::class, function (array $parameters) {
        return data_get($parameters, 'name') === 'Cid';
    });

    $fake->assertNotExecuted(Fail::class);

    $fake->assertExecutedTimes(Greet::class, 2);

    $fake->assertExecutedTimes(Fail::class, 0);
});

test('it should record the actions that run for real', function () {
    $fake = Action::fake();

    Greet::execute('Ana');

    $fake->assertExecuted(Greet::class);
});

test('it should fail the assertions when the expectation is not met', function (Closure $assertion) {
    $fake = Action::fake([Greet::class => 'fake']);

    Greet::execute('Ana');

    expect(function () use ($assertion, $fake) {
        return $assertion($fake);
    })->toThrow(AssertionFailedError::class);
})->with([
    'assertExecuted' => [function ($fake) {
        return $fake->assertExecuted(Fail::class);
    }],
    'assertExecuted with callback' => [function ($fake) {
        return $fake->assertExecuted(Greet::class, function () {
            return false;
        });
    }],
    'assertNotExecuted' => [function ($fake) {
        return $fake->assertNotExecuted(Greet::class);
    }],
    'assertExecutedTimes' => [function ($fake) {
        return $fake->assertExecutedTimes(Greet::class, 2);
    }],
]);

test('it should fake an action run by the queue worker', function () {
    $fake = Action::fake([Record::class]);

    Record::dispatch('value');

    $fake->assertExecuted(Record::class, function (array $parameters) {
        return data_get($parameters, 'value') === 'value';
    });

    expect(Record::$runs)->toBe([]);
});

test('it should record an action run for real by the queue worker', function () {
    $fake = Action::fake();

    Record::dispatch('value');

    $fake->assertExecuted(Record::class);

    expect(Record::$runs)->toBe(['value']);
});
