<?php

namespace Pijler\Actions;

use Illuminate\Queue\Queue;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Pijler\Actions\Commands\MakeActionCommand;
use Pijler\Actions\Commands\MakeWorkflowCommand;
use Pijler\Actions\Queue\RunThroughLifecycle;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootCommands();

        $this->bootQueuePayloadUsing();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Boot the console commands.
     */
    private function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeActionCommand::class,
                MakeWorkflowCommand::class,
            ]);
        }
    }

    /**
     * Boot the queue payload using callback.
     */
    private function bootQueuePayloadUsing(): void
    {
        Queue::createPayloadUsing(function (?string $connection, ?string $queue, array $payload) {
            RunThroughLifecycle::register(data_get($payload, 'data.command'));

            return [];
        });
    }
}
