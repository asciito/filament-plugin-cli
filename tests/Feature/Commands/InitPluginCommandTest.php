<?php

use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->disk = Storage::fake('stubs'));

it('replace vendor and package in stub file', function () {
    \Illuminate\Support\Sleep::fake();

    $this->disk->put('SomeClass.php.stub', <<<PHP
    <?php

    namespace {{Vendor}}\{{Package}};

    class SomeClass {
        //
    }
    PHP);

    $this->disk->put('composer.json.stub', <<<'JSON'
    {
      "name": "{{vendor}}/{{package}}",
      "description": "Lorem ipsum dolor it {{Package}}",
      "autoload": {
        "psr-4": {
          "\{{Vendor}}\{{Package}}\": "src/"
        }
      },
      "extra": {
        "laravel": [
          "{{Vendor}}\{{Package}}\SomeClass"
        ]
      }
    }
    JSON
    );

    $this->disk->put('replacers.txt.stub', <<<'TEXT'
    {{Vendor}} and {{PACKAGE}}
    {{vendor}} and {{Package}}

    This is the {{VENDOR}} and this is the {{package}}

    This does nothing {{VenDOr}} and {{pACkage}}, this {{also}} does nothing
    TEXT);

    expect($this->disk->exists('SomeClass.php.stub'))->toBeTrue()
        ->and($this->disk->exists('composer.json.stub'))->toBeTrue()
        ->and($this->disk->exists('replacers.txt.stub'))->tobeTrue();

    $this->artisan('init', ['--path' => $this->disk->path(''), '--dont-delete-cli' => true])
        ->expectsQuestion('Vendor', 'vendor')
        ->expectsConfirmation('Do you want to use the vendor name [vendor]', 'yes')
        ->doesntExpectOutput('Please provide a valid name like [some-vendor-name]')
        ->expectsQuestion('Package', 'package')
        ->expectsConfirmation('Do you want to use the package name [package]', 'yes')
        ->doesntExpectOutput('Please provide a valid name like [some-package-name]')
        ->assertSuccessful();

    \Illuminate\Support\Sleep::assertSleptTimes(3);

    expect($this->disk->get('SomeClass.php'))
        ->toContain('namespace Vendor\\Package')
        ->and($this->disk->get('composer.json'))
        ->toBe(<<<'JSON'
        {
          "name": "vendor/package",
          "description": "Lorem ipsum dolor it Package",
          "autoload": {
            "psr-4": {
              "\Vendor\Package\": "src/"
            }
          },
          "extra": {
            "laravel": [
              "Vendor\Package\SomeClass"
            ]
          }
        }
        JSON)
        ->and($this->disk->get('replacers.txt'))
        ->toBe(<<<'TEXT'
        Vendor and PACKAGE
        vendor and Package

        This is the VENDOR and this is the package

        This does nothing {{VenDOr}} and {{pACkage}}, this {{also}} does nothing
        TEXT);
});

it('replace file name', function () {
    \Illuminate\Support\Sleep::fake();

    $this->disk->put('PackageClass.php.stub', '');
    $this->disk->put('VENDORClass.php.stub', '');

    $this->artisan('init', ['--path' => $this->disk->path(''), '--dont-delete-cli' => true])
        ->expectsQuestion('Vendor', 'vendor')
        ->expectsConfirmation('Do you want to use the vendor name [vendor]', 'yes')
        ->expectsQuestion('Package', 'some-package')
        ->expectsConfirmation('Do you want to use the package name [some-package]', 'yes')
        ->assertSuccessful();

    \Illuminate\Support\Sleep::assertSleptTimes(2);

    expect($this->disk->exists('SomePackageClass.php'))->toBeTrue()
        ->and($this->disk->exists('VENDORClass.php'))->toBeTrue();
});
