<?php

namespace Workbench\App\Actions;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Pijler\Actions\Action;
use SensitiveParameter;

class Login extends Action implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * The passwords received by this action in the current process.
     */
    public static array $runs = [];

    /**
     * Create a new action instance.
     */
    public function __construct(
        public string $email,
        #[SensitiveParameter] public string $password,
    ) {}

    /**
     * Execute the action.
     */
    public function handle(): string
    {
        return static::$runs[] = $this->password;
    }
}
