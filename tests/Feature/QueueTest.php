<?php

use Illuminate\Queue\Middleware\Skip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Pijler\Actions\Events\ActionFailed;
use Pijler\Actions\Events\ActionFinished;
use Pijler\Actions\Events\ActionStarted;
use Workbench\App\Actions\Login;
use Workbench\App\Actions\Record;
use Workbench\App\Models\Customer;

beforeEach(function () {
    config(['queue.default' => 'sync']);

    Login::$runs = [];

    Record::$runs = [];
});

test('it should push the action itself onto the queue', function () {
    Queue::fake();

    Record::dispatch('value');

    Queue::assertPushed(Record::class, function (Record $action) {
        return $action->value === 'value';
    });
});

test('it should accept the options of a Laravel job', function () {
    Queue::fake();

    Record::dispatch('value')->onQueue('other')->delay(60);

    Queue::assertPushedOn('other', Record::class, function (Record $action) {
        return $action->delay === 60;
    });
});

test('it should run the action through the queue worker', function () {
    Record::dispatch('value');

    expect(Record::$runs)->toBe(['value']);
});

test('it should restore models in a queued action', function () {
    Schema::create('customers', function ($table) {
        $table->id();
        $table->string('name');
    });

    $customer = Customer::create(['name' => 'Ana']);

    Record::dispatch(customer: $customer);

    expect(Record::$runs[0])->toBeInstanceOf(Customer::class);

    expect(Record::$runs[0]->is($customer))->toBeTrue();

    expect(Record::$runs[0])->not->toBe($customer);
});

test('it should dispatch the events of a queued action, with sensitive values hidden', function () {
    Event::fake([ActionStarted::class, ActionFinished::class, ActionFailed::class]);

    Login::dispatch('ana@example.com', 'secret');

    $check = function ($event) {
        return $event->action === Login::class
            && $event->parameters === ['email' => 'ana@example.com', 'password' => '[sensitive]'];
    };

    Event::assertDispatched(ActionStarted::class, $check);

    Event::assertDispatched(ActionFinished::class, $check);

    Event::assertNotDispatched(ActionFailed::class);
});

describe('database queue', function () {
    beforeEach(function () {
        config(['queue.default' => 'database', 'queue.connections.database.table' => 'jobs']);

        Schema::create('jobs', function ($table) {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    });

    test('it should read the job settings from the action', function () {
        Record::dispatch('value');

        $payload = json_decode(DB::table('jobs')->value('payload'), true);

        expect(data_get($payload, 'maxTries'))->toBe(3);

        expect(data_get($payload, 'displayName'))->toBe(Record::class);
    });

    test('it should keep sensitive parameters out of an encrypted action', function () {
        Record::dispatch('visible');

        Login::dispatch('ana@example.com', 'secret');

        [$plain, $encrypted] = DB::table('jobs')->orderBy('id')->pluck('payload')->all();

        expect($plain)->toContain('visible');

        expect($encrypted)
            ->toContain(addslashes(Login::class))
            ->not->toContain('secret')
            ->not->toContain('ana@example.com');
    });

    test('it should decrypt and run the stored action in a worker', function () {
        Event::fake([ActionFinished::class]);

        Login::dispatch('ana@example.com', 'secret');

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        expect(Login::$runs)->toBe(['secret']);

        expect(DB::table('jobs')->count())->toBe(0);

        Event::assertDispatched(ActionFinished::class);
    });

    test('it should run the middleware of the action in a worker', function () {
        Record::dispatch('value')->through(Skip::when(true));

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        expect(Record::$runs)->toBe([]);
    });
});
