<?php

namespace Pijler\Actions\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:workflow')]
class MakeWorkflowCommand extends GeneratorCommand
{
    /**
     * The console command name.
     */
    protected $name = 'make:workflow';

    /**
     * The console command description.
     */
    protected $description = 'Create a new workflow class and its context';

    /**
     * The type of class being generated.
     */
    protected $type = 'Workflow';

    /**
     * Generate the workflow and, if it does not exist yet, its context.
     */
    public function handle(): ?bool
    {
        if (parent::handle() === false) {
            return false;
        }

        $this->makeContext($this->qualifyClass($this->getNameInput()).'Context');

        return null;
    }

    /**
     * Generate the context, never overwriting one, not even with --force.
     */
    protected function makeContext(string $name): void
    {
        $path = $this->getPath($name);

        if ($this->files->exists($path)) {
            return;
        }

        $stub = $this->files->get(__DIR__.'/../../stubs/workflow-context.stub');

        $this->files->put($path, $this->replaceNamespace($stub, $name)->replaceClass($stub, $name));

        $this->components->info(sprintf('Context [%s] created successfully.', $path));
    }

    /**
     * Build the workflow class, typed by its context.
     */
    protected function buildClass($name): string
    {
        return str_replace('{{ context }}', class_basename($name).'Context', parent::buildClass($name));
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/workflow.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Workflows";
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the workflow already exists'],
        ];
    }
}
