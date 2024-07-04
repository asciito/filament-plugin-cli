<?php

namespace App\Commands;

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
                            { --p|path : Path to the plugin directory }
                            { --d|dont-delete-cli : Prevent deleting the CLI }';

    protected $description = 'Initialize the plugin development package';

    protected string $vendorReplacer = 'vendor';

    protected string $packageReplacer = 'package';

    protected string $includedFileExtension = '*.stub';

    protected array $includedFilenames = [
        'README.md',
        'LICENSE.md',
    ];

    protected array $excludedDirectories = [
        'build',
        'vendor',
        'node_modules',
    ];

    // Pattern from: https://regex101.com/library/tQ0bN5
    protected string $validationRuleForPromptValue = '/^(?!-)((?:[a-z0-9]+-?)+)(?<!-)$/';

    public function handle(): int
    {
        $files = $this->getFiles();

        foreach ($files as $file) {
            $this->replaceValuesInFile($file);

            $this->renameFile($file);
        }

        if (! $this->option('dont-delete-cli') && ! $this->deleteCli()) {
            $this->error('The CLI could not be deleted');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    public function getFiles(): Finder
    {
        $finder = (new Finder)
            ->in($this->getPackageDirectory())
            ->name($this->getFilenames())
            ->exclude($this->getExcludedDirectories())
            ->files();

        return $finder;
    }

    public function replaceValuesInFile(SplFileInfo $file): void
    {
        spin(
            function () use ($file) {
                $shouldReplace = [];

                foreach ($this->getReplacersAndValues() as $replacer => $value) {
                    $shouldReplace['{{'.str::studly($replacer).'}}'] = Str::studly($value);
                    $shouldReplace['{{'.str::upper($replacer).'}}'] = str::upper($value);
                    $shouldReplace['{{'.$replacer.'}}'] = $value;
                }

                Sleep::usleep(0.5);

                File::replaceInFile(
                    search: array_keys($shouldReplace),
                    replace: array_values($shouldReplace),
                    path: $file->getRealPath(),
                );
            },
            'Replacing values in file '.$file->getBasename(),
        );
    }

    public function renameFile(SplFileInfo $file): bool
    {
        $path = $file->getPath();
        $name = Str::replace(
            'package',
            Str::studly($this->getPackage()),
            $file->getBasename('.stub'),
            false
        );

        return File::move($file->getRealPath(), join_paths($path, $name));
    }

    protected function getExcludedDirectories(): array
    {
        return $this->excludedDirectories;
    }

    public function getPackageDirectory(): array
    {
        return [
            $this->option('path') ?: getcwd(),
        ];
    }

    public function getFilenames(): array
    {
        return array_merge([$this->includedFileExtension], $this->getIncludedFilenames());
    }

    public function getIncludedFilenames(): array
    {
        return $this->includedFilenames;
    }

    public function getVendor(): string
    {
        return Str::slug($this->argument('vendor'));
    }

    public function getPackage(): string
    {
        return Str::slug($this->argument('package'));
    }

    public function getVendorReplacer(): string
    {
        return $this->vendorReplacer;
    }

    public function getPackageReplacer(): string
    {
        return $this->packageReplacer;
    }

    public function getReplacersAndValues(): array
    {
        return [
            $this->getVendorReplacer() => $this->getVendor(),
            $this->getPackageReplacer() => $this->getPackage(),
        ];
    }

    protected function deleteCli(): bool
    {
        $status = spin(function () {
            $file = \Phar::running(false);

            if (empty($file)) {
                return false;
            }

            Sleep::sleep(1);

            return unlink($file);
        }, 'Deleting CLI');

        return $status;
    }

    protected function vendorPrompt(): string
    {
        return $this->askAndRepeat(
            'Vendor',
            fn (string $value) => ! $this->confirm("Do you want to use the vendor name [$value]"),
            function (string $value) {
                if (! Str::isMatch($this->getValidationRuleForPromptValue(), $value)) {
                    return 'please provide a valid name like [some-vendor-name]';
                }

                return true;
            }
        );
    }

    protected function packagePrompt(): string
    {
        return $this->askAndRepeat(
            'Package',
            fn (string $value) => ! $this->confirm("Do you want to use the package name [$value]"),
            function (string $value) {
                if (! Str::isMatch($this->getValidationRuleForPromptValue(), $value)) {
                    return 'please provide a valid name like [some-package-name]';
                }

                return true;
            }
        );
    }

    public function askAndRepeat(string $question, ?\Closure $shouldRepeat = null, ?\Closure $validate = null): string
    {
        do {
            $value = $this->ask($question);

            if ($value && $validate instanceof \Closure && $valid = $validate($value)) {
                if (! is_string($valid)) {
                    break;
                }

                $this->error($valid);
            }
        } while (true);

        if ($shouldRepeat instanceof \Closure && $shouldRepeat($value)) {
            return $this->askAndRepeat($question, $shouldRepeat, $validate);
        }

        return $value;
    }

    public function promptForMissingArgumentsUsing(): array
    {
        return [
            'vendor' => fn () => $this->vendorPrompt(),
            'package' => fn () => $this->packagePrompt(),
        ];
    }

    protected function getValidationRuleForPromptValue(): string
    {
        return $this->validationRuleForPromptValue;
    }
}
