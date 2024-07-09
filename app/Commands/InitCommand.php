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
                            { --p|path= : Path to the plugin directory }
                            { --d|dont-delete-cli : Prevent deleting the CLI }';

    protected $description = 'Initialize the plugin development package';

    protected array $excludedDirectories = [
        'build',
        'vendor',
        'node_modules',
    ];

    public function handle(): int
    {
        $files = $this->getFiles();

        foreach ($files as $file) {
            $this->initFile($file);
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
        Author:        {$this->getAuthor()}
        Author E-mail: {$this->getAuthorEmail()}
        Vendor:        {$this->getVendor()}
        Package:       {$this->getPackage()}
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
            'vendor' => 'What\'s the Vendor name',
            'package' => 'What\'s the Package name',
            'author' => 'What\'s the Author\'s name',
            'author-email' => 'What\'s the Author\'s e-mail',
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

    protected function getPackageDirectory(): array
    {
        return [
            $this->option('path') ?: getcwd(),
        ];
    }

    protected function getExcludedDirectories(): array
    {
        return $this->excludedDirectories;
    }

    protected function initFile(SplFileInfo $file): void
    {
        $this
            ->replaceInFile($file)
            ->renameFile($file);

        Sleep::usleep(500_000);
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

    protected function replacePlaceholder(array|string $placeholder, array|string $value, string &$content, array $formatters, bool $shouldWrap = true): void
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
}
