<?php

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Pijler\Actions\Action;
use Pijler\Actions\Events\ActionFailed;
use Pijler\Actions\Events\ActionFinished;
use Pijler\Actions\Events\ActionStarted;
use Workbench\App\Actions\Fail;
use Workbench\App\Actions\Greet;
use Workbench\App\Actions\Login;

beforeEach(function () {
    Event::fake([ActionStarted::class, ActionFinished::class, ActionFailed::class]);
});

test('it should dispatch the events of an action that finishes', function () {
    Greet::execute('Ana');

    Event::assertDispatched(ActionStarted::class, function (ActionStarted $event) {
        return $event->action === Greet::class
            && $event->parameters === ['name' => 'Ana'];
    });

    Event::assertDispatched(ActionFinished::class, function (ActionFinished $event) {
        return $event->action === Greet::class
            && $event->parameters === ['name' => 'Ana'];
    });

    Event::assertNotDispatched(ActionFailed::class);
});

test('it should dispatch the events of an action that fails', function () {
    expect(function () {
        return Fail::execute();
    })->toThrow(RuntimeException::class);

    Event::assertDispatched(ActionStarted::class);

    Event::assertDispatched(ActionFailed::class, function (ActionFailed $event) {
        return $event->action === Fail::class
            && $event->exception->getMessage() === 'It failed.';
    });

    Event::assertNotDispatched(ActionFinished::class);
});

test('it should not dispatch events for a faked action', function () {
    Action::fake([Greet::class => 'fake']);

    Greet::execute('Ana');

    Event::assertNotDispatched(ActionStarted::class);
});

test('it should hide sensitive parameters from the events', function () {
    Login::execute('ana@example.com', 'secret');

    $check = function ($event) {
        return $event->parameters === ['email' => 'ana@example.com', 'password' => '[sensitive]'];
    };

    Event::assertDispatched(ActionStarted::class, $check);

    Event::assertDispatched(ActionFinished::class, $check);
});

test('it should not report an action as failed when a finished listener throws', function () {
    Event::swap($dispatcher = new Dispatcher(app()));

    $failed = [];

    $dispatcher->listen(ActionFailed::class, function (ActionFailed $event) use (&$failed) {
        $failed[] = $event->action;
    });

    $dispatcher->listen(ActionFinished::class, function () {
        throw new LogicException('Listener.');
    });

    expect(function () {
        return Greet::execute('Ana');
    })->toThrow(LogicException::class, 'Listener.');

    expect($failed)->toBe([]);
});
