<?php

namespace App\Commands;

use App\Formatters\EmailFormatter;
use App\Formatters\LowerCaseFormatter;
use App\Formatters\StudlyCaseFormatter;
use App\Formatters\TitleFormatter;
use App\Formatters\UpperCaseFormatter;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\TextPrompt;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function Illuminate\Filesystem\join_paths;
use function Laravel\Prompts\spin;

class InitCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'init
                            { vendor : The vendor\'s name (vendor-name) }
                            { package : The package\'s name (package-name) }
                            { author :  The author\'s name }
                            { author-email : The author\'s email }
                            { description : The plugin description }
                            { --p|path= : Path to the plugin directory }
                            { --exclude=* : Paths to exclude }
                            { --d|dont-delete-cli : Prevent deleting the CLI }';

    protected $description = 'Initialize the plugin development package';

    protected array $excludedDirectories = [
        '.git',
        '.idea',
        'build',
        'vendor',
        'node_modules',
    ];

    protected array $excludedFiles = [
        'plugin',
        'phpunit.xml',
        'package.json',
        'testbench.yaml',
        '.github/**/*.yml',
    ];

    public function __construct()
    {
        parent::__construct();

        TextPrompt::fallbackUsing(fn (TextPrompt $prompt) => $this->ask($prompt->label, $prompt->default));

        ConfirmPrompt::fallbackUsing(fn (ConfirmPrompt $prompt) => $this->confirm($prompt->label, $prompt->default));
    }

    public function handle(): int
    {
        $files = $this->getFiles();

        foreach ($files as $file) {
            $this->info('USING CONFIGURATION:');

            $this->printConfiguration();

            spin(
                fn () => $this->initFile($file),
                "Replacing placeholders in file [{$file->getBasename()}]",
            );
        }

        if (! $this->hasOption('dont-delete-cli') &&
            $this->isInteractive() &&
            $this->confirm('Do you want to delete the CLI')) {
            $this->deleteCli();
        }

        return self::SUCCESS;
    }

    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        $this->printConfiguration();

        if (! $this->confirm('Do you want to use this configuration') && $this->isInteractive()) {
            $this->flushArguments();

            $this->promptForMissingArguments($input, $output);
        }
    }

    protected function flushArguments(): void
    {
        collect($this->promptForMissingArgumentsUsing())
            ->keys()
            ->each(fn (string $argument) => $this->input->setArgument($argument, null));
    }

    protected function printConfiguration(): void
    {
        $this->line(<<<CONFIG
        Author:        <fg=yellow>{$this->getAuthor()}</>
        Author E-mail: <fg=yellow>{$this->getAuthorEmail()}</>
        Vendor:        <fg=yellow>{$this->getVendor()}</>
        Package:       <fg=yellow>{$this->getPackage()}</>
        Description:   <fg=yellow>{$this->getPluginDescription()}</>
        CONFIG);
    }

    protected function getAuthor(): string
    {
        return Str::of($this->argument('author'))->title();
    }

    protected function getAuthorEmail(): string
    {
        return Str::of($this->argument('author-email'));
    }

    protected function getPluginDescription(): string
    {
        return $this->argument('description');
    }

    protected function getVendor(): string
    {
        return Str::slug($this->argument('vendor'));
    }

    protected function getPackage(): string
    {
        return Str::slug($this->argument('package'));
    }

    public function isInteractive(): bool
    {
        return $this->input->isInteractive();
    }

    public function promptForMissingArgumentsUsing(): array
    {
        return [
            'vendor' => ['What\'s the Vendor name', 'vendor'],
            'package' => ['What\'s the Package name', 'package'],
            'author' => ['What\'s the Author\'s name', 'John Doe'],
            'author-email' => ['What\'s the Author\'s e-mail', 'john@doe.com'],
            'description' => ['Describe your plugin', 'Lorem ipsum dolor it'],
        ];
    }

    protected function getFiles(): Finder
    {
        return (new Finder)
            ->sortByName()
            ->in($this->getPackageDirectories())
            ->files()
            ->notPath($this->getExcludedPaths())
            ->ignoreDotFiles(false)
            ->exclude($this->getExcludedDirectories());
    }

    protected function getPackageDirectories(): array
    {
        return [
            $this->option('path') ?: getcwd(),
        ];
    }

    protected function getExcludedDirectories(): array
    {
        return $this->excludedDirectories;
    }

    protected function getExcludedPaths(): array
    {
        $excludedPaths = Arr::wrap($this->option('exclude'));

        return array_merge($this->excludedFiles, $excludedPaths);
    }

    protected function initFile(SplFileInfo $file): void
    {
        $this
            ->replaceInFile($file)
            ->renameFile($file);

        Sleep::usleep(50_000_000);
    }

    protected function renameFile(SplFileInfo $file): static
    {
        $values = [$this->getAuthor(), $this->getVendor(), $this->getPackage(), $this->getNamespace()];
        $placeholders = ['author', 'vendor', 'package', 'namespace'];
        $filename = $file->getBasename('.stub');

        $this->replacePlaceholder(
            placeholder: $placeholders,
            value: $values,
            content: $filename,
            formatters: [
                UpperCaseFormatter::class,
                LowerCaseFormatter::class,
                StudlyCaseFormatter::class,
            ],
            shouldWrap: false,
        );

        File::move($file->getRealPath(), join_paths($file->getPath(), $filename));

        return $this;
    }

    protected function getNamespace(bool $scape = false): string
    {
        $vendor = Str::studly($this->getVendor());
        $package = Str::studly($this->getPackage());
        $separator = $scape ? '\\\\' : '\\';

        return "{$vendor}{$separator}{$package}";
    }

    protected function replacePlaceholder(array|string $placeholder, array|string $value, string &$content, array $formatters = [], bool $shouldWrap = true): void
    {
        $content = \App\replacePlaceholder(
            placeholder: $placeholder,
            value: $value,
            content: $content,
            formatters: $formatters,
            startWrapper: $shouldWrap ? '{{' : '',
            endWrapper: $shouldWrap ? '}}' : '',
        );
    }

    protected function replaceInFile(SplFileInfo $file): static
    {
        $content = $file->getContents();

        $this->replacePipe($content);

        File::put($file->getRealPath(), $content);

        return $this;
    }

    protected function replacePipe(string &$content): static
    {
        return $this
            ->replaceAuthor($content)
            ->replaceAuthorEmail($content)
            ->replaceDescription($content)
            ->replaceVendor($content)
            ->replacePackage($content)
            ->replaceNamespace($content)
            ->removeTags($content);
    }

    protected function removeTags(string &$content): static
    {
        $content = \App\removeTag('DELETE', $content);

        return $this;
    }

    protected function replaceNamespace(string &$content): static
    {
        $this->replacePlaceholder(
            placeholder: ['namespace'],
            value: $this->getNamespace(),
            content: $content,
            formatters: [
                UpperCaseFormatter::class,
                LowerCaseFormatter::class,
                StudlyCaseFormatter::class,
            ]
        );

        return $this;
    }

    protected function replacePackage(string &$content): static
    {
        $this->replacePlaceholder(
            placeholder: 'package',
            value: $this->getPackage(),
            content: $content,
            formatters: [
                UpperCaseFormatter::class,
                LowerCaseFormatter::class,
                StudlyCaseFormatter::class,
                TitleFormatter::class,
            ]
        );

        return $this;
    }

    protected function replaceVendor(string &$content): static
    {
        $this->replacePlaceholder(
            placeholder: 'vendor',
            value: $this->getVendor(),
            content: $content,
            formatters: [
                UpperCaseFormatter::class,
                LowerCaseFormatter::class,
                StudlyCaseFormatter::class,
                TitleFormatter::class,
            ]
        );

        return $this;
    }

    protected function replaceAuthorEmail(string &$content): static
    {
        $this->replacePlaceholder(
            placeholder: 'author-email',
            value: $this->getAuthorEmail(),
            content: $content,
            formatters: [
                EmailFormatter::class,
            ]
        );

        return $this;
    }

    protected function replaceAuthor(string &$content): static
    {
        $this->replacePlaceholder(
            placeholder: 'author',
            value: $this->getAuthor(),
            content: $content,
            formatters: [
                TitleFormatter::class,
            ]
        );

        return $this;
    }

    protected function replaceDescription(string &$content): static
    {
        $this->replacePlaceholder(
            placeholder: 'description',
            value: $this->getPluginDescription(),
            content: $content,
        );

        return $this;
    }

    protected function deleteCli(): bool
    {
        $status = spin(function () {
            $file = \Phar::running(false);

            throw_if(empty($file), new \PharException('The file is not a phar file, please compile the CLI first'));

            Sleep::sleep(1);

            return unlink($file);
        }, 'Deleting CLI');

        return $status;
    }
}
