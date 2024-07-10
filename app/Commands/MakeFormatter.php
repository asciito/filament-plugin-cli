<?php

namespace App\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:formatter')]
class MakeFormatter extends GeneratorCommand
{
    protected $name = 'make:formatter';

    protected $description = 'Create new formatter';

    protected $type = 'Formatter';

    protected function getStub(): string
    {
        return $this->laravel->basePath('/stubs/formatter.stub');
    }

    protected function getPath($name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return app_path('Formatters').str_replace('\\', '/', $name).'.php';
    }

    protected function rootNamespace(): string
    {
        return parent::rootNamespace().'Formatters';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the formatter even if the already exists'],
        ];
    }
}
