<?php

namespace App\Commands;

use App\Formatters\EmailFormatter;
use App\Formatters\LowerCaseFormatter;
use App\Formatters\StudlyCaseFormatter;
use App\Formatters\TitleFormatter;
use App\Formatters\UpperCaseFormatter;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function Illuminate\Filesystem\join_paths;
use function Laravel\Prompts\spin;

class InitPluginCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'init
                            { vendor : The vendor\'s name (vendor-name) }
                            { package : The package\'s name (package-name) }
                            { author :  The author\'s name }
                            { author-email : The author\'s email }
                            { --p|path= : Path to the plugin directory }
                            { --d|dont-delete-cli : Prevent deleting the CLI }';

    protected $description = 'Initialize the plugin development package';

    protected string $vendorReplacer = 'vendor';

    protected string $packageReplacer = 'package';

    protected string $authorReplacer = 'author';

    protected string $authorEmailReplacer = 'author-email';

    protected array $excludedDirectories = [
        'build',
        'vendor',
        'node_modules',
    ];

    protected array $replacerFormatters = [
        StudlyCaseFormatter::class,
        UpperCaseFormatter::class,
        LowerCaseFormatter::class,
        EmailFormatter::class,
        TitleFormatter::class,
    ];

    public function handle(): int
    {
        $this->validateConfiguration();

        $files = $this->getFiles();

        foreach ($files as $file) {
            $this->initFile($file);
        }

        if (! $this->hasOption('dont-delete-cli') && $this->confirm('Do you want to delete the CLI')) {
            $this->deleteCli();
        }

        return self::SUCCESS;
    }

    protected function initFile(SplFileInfo $file): void
    {
        $this
            ->removeTags('DELETE', $file)
            ->replacePlaceholdersInFile($file)
            ->replacePlaceholdersInFileName($file);
    }

    protected function getExcludedDirectories(): array
    {
        return $this->excludedDirectories;
    }

    protected function getVendor(): string
    {
        return Str::slug($this->argument('vendor'));
    }

    protected function getPackage(): string
    {
        return Str::slug($this->argument('package'));
    }

    protected function getAuthor(): string
    {
        return Str::of($this->argument('author'))->title();
    }

    protected function getAuthorEmail(): string
    {
        return Str::of($this->argument('author-email'));
    }

    protected function getVendorReplacer(): string
    {
        return $this->vendorReplacer;
    }

    protected function getPackageReplacer(): string
    {
        return $this->packageReplacer;
    }

    protected function getAuthorReplacer(): string
    {
        return $this->authorReplacer;
    }

    protected function getAuthorEmailReplacer(): string
    {
        return $this->authorEmailReplacer;
    }

    protected function getReplacerFormatters(): array
    {
        return $this->replacerFormatters;
    }

    protected function getReplacersAndValues(): array
    {
        return [
            $this->getVendorReplacer() => $this->getVendor(),
            $this->getPackageReplacer() => $this->getPackage(),
            $this->getAuthorReplacer() => $this->getAuthor(),
            $this->getAuthorEmailReplacer() => $this->getAuthorEmail(),
        ];
    }

    protected function getFiles(): Finder
    {
        $finder = (new Finder)
            ->in($this->getPackageDirectory())
            ->files()
            ->exclude($this->getExcludedDirectories());

        return $finder;
    }

    protected function validateConfiguration(): void
    {
        $this->printConfiguration();

        if (! $this->confirm('Do you want to use this configuration')) {
            $this->promptAgain();

            $this->validateConfiguration();
        }
    }

    protected function promptAgain(): void
    {
        foreach ($this->promptForMissingArgumentsUsing() as $argument => $prompt) {
            $this->input->setArgument($argument, $prompt());
        }
    }

    protected function printConfiguration(): void
    {
        $this->line(<<<CONFIG
        Author:        {$this->getAuthor()}
        Author E-mail: {$this->getAuthorEmail()}
        Vendor:        {$this->getVendor()}
        Package:       {$this->getPackage()}
        CONFIG);
    }

    protected function replacePlaceholdersInFile(SplFileInfo $file): static
    {
        spin(
            function () use ($file) {
                $content = File::get($file->getRealPath());

                File::put($file->getRealPath(), $this->replacePlaceholders($content));

                Sleep::usleep(500_000);
            },
            'Replacing values in file '.$file->getBasename(),
        );

        return $this;
    }

    protected function replacePlaceholdersInFileName(SplFileInfo $file): static
    {
        $content = $this->replacePlaceholders($file->getBasename('.stub'), false);

        File::move($file->getRealPath(), join_paths($file->getPath(), $content));

        return $this;
    }

    protected function replacePlaceholders(string $content, bool $shouldWrap = true): string
    {
        return \App\replacePlaceholder(
            placeholder: array_keys($this->getReplacersAndValues()),
            value: array_values($this->getReplacersAndValues()),
            content: $content,
            formatters: $this->getReplacerFormatters(),
            startWrapper: $shouldWrap ? '{{' : '',
            endWrapper: $shouldWrap ? '}}' : '',
        );
    }

    protected function removeTags(array|string $tags, SplFileInfo $file): static
    {
        $content = collect($tags)
            ->reduce(
                fn (string $content, string $tag) => \App\removeTag($tag, $content),
                $file->getContents()
            );

        File::put($file->getRealPath(), $content);

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

    protected function vendorPrompt(): string
    {
        return $this->askAndRepeat('What\'s the Vendor name');
    }

    protected function packagePrompt(): string
    {
        return $this->askAndRepeat('What\'s the Package name');
    }

    protected function authorPrompt(): string
    {
        return $this->askAndRepeat('What\'s the Author\'s name');
    }

    protected function authorEmailPrompt(): string
    {
        return $this->askAndRepeat('What\'s the Author\'s e-mail');
    }

    public function askAndRepeat(string $question, ?\Closure $validate = null): string
    {
        $value = $this->ask($question);

        if ($validate instanceof \Closure) {
            if (is_string($error = $validate(value: $value))) {
                $this->error($error);

                return $this->askAndRepeat($question, $validate);
            }
        }

        return $value;
    }

    public function promptForMissingArgumentsUsing(): array
    {
        return [
            'vendor' => fn () => $this->vendorPrompt(),
            'package' => fn () => $this->packagePrompt(),
            'author' => fn () => $this->authorPrompt(),
            'author-email' => fn () => $this->authorEmailPrompt(),
        ];
    }

    protected function getPackageDirectory(): array
    {
        return [
            $this->option('path') ?: getcwd(),
        ];
    }
}
