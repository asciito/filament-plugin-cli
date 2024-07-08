<?php

namespace App\Commands;

use App\Formatters\StudlyCaseFormatter;
use Illuminate\Console\Prohibitable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:formatter')]
class MakeFormatter extends Command implements \Illuminate\Contracts\Console\PromptsForMissingInput
{
    use Prohibitable;

    protected $signature = 'make:formatter
                            { name : The name of the Formatter }
                            { --f|force : Create the Formatter class even if the class already exists }';

    protected $description = 'Create new formatter';

    protected array $reservedNames = [
        '__halt_compiler',
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'enum',
        'eval',
        'exit',
        'extends',
        'false',
        'final',
        'finally',
        'fn',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'match',
        'namespace',
        'new',
        'or',
        'parent',
        'print',
        'private',
        'protected',
        'public',
        'readonly',
        'require',
        'require_once',
        'return',
        'self',
        'static',
        'switch',
        'throw',
        'trait',
        'true',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield',
        '__CLASS__',
        '__DIR__',
        '__FILE__',
        '__FUNCTION__',
        '__LINE__',
        '__METHOD__',
        '__NAMESPACE__',
        '__TRAIT__',
    ];

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * @throws FileNotFoundException
     */
    public function handle(): int
    {
        if ($this->isReservedName($this->getNameInput())) {
            $this->error("The name {$this->getNameInput()} is reserved by PHP");

            return self::FAILURE;
        }

        if ((! $this->hasOption('force') ||
                ! $this->option('force')) &&
            $this->alreadyExists($this->getNameInput())) {
            $this->error("{$this->getNameInput()} already exists.");

            return false;
        }

        $name = $this->qualifyClass($this->getNameInput());
        $path = $this->getPath($name);

        $this->files->put($path, $this->buildClass($name));

        $this->info("Formatter [$path] created successfully.");

        return self::SUCCESS;
    }

    protected function getStubPath(): string
    {
        return $this->laravel->basePath('stubs/formatter.stub');
    }

    protected function getNameInput(): string
    {
        return $this->argument('name');
    }

    protected function qualifyClass(string $name): string
    {
        $name = ltrim($name, '\\/');

        $name = str_replace('/', '\\', $name);

        $rootNamespace = $this->rootNamespace().'\\Formatters';

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        return $this->qualifyClass(trim($rootNamespace, '\\').'\\'.$name);
    }

    protected function alreadyExists(string $rawName): bool
    {
        return $this->files->exists($this->getPath($this->qualifyClass($rawName)));
    }

    protected function getPath(string $name): string
    {
        $name = trim(Str::replaceFirst($this->rootNamespace(), '', $name), '\\');

        return $this->laravel['path'].'/'.str_replace('\\', '/', $name).'.php';
    }

    protected function getNamespace(string $name): string
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    protected function replaceClass(string &$content, string $name): static
    {
        $placeholders = ['Class'];
        $class = str_replace($this->getNamespace($name).'\\', '', $name);

        $content = $this->replace($placeholders, $class, $content);

        return $this;
    }

    protected function replaceNamespace(string &$content, string $namespace): static
    {
        $placeholders = ['Namespace', 'RootNamespace'];

        $content = $this->replace($placeholders, [$this->getNamespace($namespace), $this->rootNamespace()], $content);

        return $this;
    }

    protected function replace(array|string $placeholders, array|string $value, string $content): string
    {
        return \App\replacePlaceholder(
            placeholder: $placeholders,
            value: $value,
            content: $content,
            formatters: [
                StudlyCaseFormatter::class,
            ],
            startWrapper: '{{',
            endWrapper: '}}',
        );
    }

    /**
     * @throws FileNotFoundException
     */
    protected function buildClass(string $name): string
    {
        $content = $this->files->get($this->getStubPath());

        $this->replaceNamespace($content, $name)->replaceClass($content, $name);

        return $content;
    }

    protected function rootNamespace(): string
    {
        return trim($this->laravel->getNamespace(), '\\');
    }

    protected function isReservedName($name): bool
    {
        return in_array(
            strtolower($name),
            collect($this->reservedNames)
                ->transform(fn ($name) => strtolower($name))
                ->all()
        );
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => 'What should the Formatter be named?',
        ];
    }
}
