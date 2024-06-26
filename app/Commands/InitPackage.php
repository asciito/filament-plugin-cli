<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function Illuminate\Filesystem\join_paths;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class InitPackage extends Command
{
    protected $signature = 'init:plugin
                            { vendor : The vendor\'s name (vendor-name) }
                            { package : The package\'s name (package-name) }';

    protected $description = 'Initialize the plugin development package';

    protected string $fileStubName = '*.stub';

    protected string $vendorReplacer = '{{vendor}}';

    protected string $packageReplacer = '{{package}}';

    protected array $excludedDirectories = [
        'build',
        'vendor',
        'node_modules',
    ];

    public function handle(): int
    {
        $files = $this->getFiles();

        foreach ($files as $file) {
            $this->replaceValuesInFile($file);
        }

        if (! $this->deleteCli()) {
            $this->error('The CLI could not be deleted');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    public function getFiles(): Finder
    {
        $finder = (new Finder)
            ->in($this->getPackageDirectory())
            ->name($this->getFileStubName())
            ->exclude($this->getExcludedDirectories())
            ->files();

        return $finder;
    }

    public function replaceValuesInFile(SplFileInfo $file): void
    {
        spin(
            function () use ($file) {
                usleep(500_000);

                File::replaceInFile($this->getReplacers(), $this->getReplacersValues(), $file);
            },
            'Replacing values in file '.$file->getBasename(),
        );

        $this->renameFile($file);
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
            getcwd(),
        ];
    }

    public function getFileStubName(): array
    {
        return [
            $this->fileStubName,
        ];
    }

    public function getVendor(): string
    {
        return $this->argument('vendor');
    }

    public function getPackage(): string
    {
        return $this->argument('package');
    }

    public function getVendorReplacer(): string
    {
        return $this->vendorReplacer;
    }

    public function getPackageReplacer(): string
    {
        return $this->packageReplacer;
    }

    public function getReplacers(): array
    {
        return [
            ...$this->getVendorReplacers(),
            ...$this->getPackageReplacers(),
        ];
    }

    public function getVendorReplacers(): array
    {
        return [
            Str::lower($this->getVendorReplacer()),
            Str::upper($this->getVendorReplacer()),
        ];
    }

    public function getPackageReplacers(): array
    {
        return [
            Str::lower($this->getPackageReplacer()),
            Str::upper($this->getPackageReplacer()),
        ];
    }

    public function getReplacersValues(): array
    {
        return [
            ...$this->getVendorReplacersValues(),
            ...$this->getPackageReplacersValues(),
        ];
    }


    public function getVendorReplacersValues(): array
    {
        $vendor = Str::of($this->getVendor())->slug();

        return [
            $vendor->toString(),
            $vendor->studly()->toString(),
        ];
    }

    public function getPackageReplacersValues(): array
    {
        $package = Str::of($this->getPackage())->slug();

        return [
            $package->toString(),
            $package->studly()->toString(),
        ];
    }

    protected function deleteCli(): bool
    {
        $status = spin(function () {
            $file = \Phar::running(false);

            if (empty($file)) {
                return false;
            }

            usleep(1_000_000);

            return unlink($file);
        }, 'Deleting CLI');

        return $status;
    }
}
