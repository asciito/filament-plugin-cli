<?php

use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->disk = Storage::fake('stubs'));
beforeEach(fn () => $this->commandConfig = [
    'vendor' => 'asciito',
    'package' => 'package',
    'author' => 'John Doe',
    'author-email' => 'john@doe.com',
    '--path' => $this->disk->path(''),
    '--dont-delete-cli' => true,
]);

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
          "\\{{Vendor}}\\{{Package}}\\": "src/"
        }
      },
      "extra": {
        "laravel": [
          "{{Vendor}}\\{{Package}}\\SomeClass"
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

    $this->artisan('init', ['--path' => $this->disk->path(''), '-d'])
        ->expectsQuestion('What\'s the Vendor name', 'asciito')
        ->expectsQuestion('What\'s the Package name', 'package')
        ->expectsQuestion('What\'s the Author\'s name', 'John Doe')
        ->expectsQuestion('What\'s the Author\'s e-mail', 'john@doe.com')
        ->expectsOutput(<<<'OUTPUT'
        Author:        John Doe
        Author E-mail: john@doe.com
        Vendor:        asciito
        Package:       package
        OUTPUT)
        ->expectsConfirmation('Do you want to use this configuration', 'yes')
        ->expectsConfirmation('Do you want to delete the CLI')
        ->assertSuccessful();

    \Illuminate\Support\Sleep::assertSleptTimes(3);

    expect($this->disk->get('SomeClass.php'))
        ->toContain('namespace Asciito\\Package')
        ->and($this->disk->get('composer.json'))
        ->toBe(<<<'JSON'
        {
          "name": "asciito/package",
          "description": "Lorem ipsum dolor it Package",
          "autoload": {
            "psr-4": {
              "\\Asciito\\Package\\": "src/"
            }
          },
          "extra": {
            "laravel": [
              "Asciito\\Package\\SomeClass"
            ]
          }
        }
        JSON)
        ->and($this->disk->get('replacers.txt'))
        ->toBe(<<<'TEXT'
        Asciito and PACKAGE
        asciito and Package

        This is the ASCIITO and this is the package

        This does nothing {{VenDOr}} and {{pACkage}}, this {{also}} does nothing
        TEXT);
});

it('replace file name', function () {
    \Illuminate\Support\Sleep::fake();

    $this->disk->put('PackageClass.php.stub', '');
    $this->disk->put('VendorClass.php.stub', '');

    $this->artisan('init', $this->commandConfig)
        ->expectsConfirmation('Do you want to use this configuration')
        ->assertSuccessful();

    \Illuminate\Support\Sleep::assertSleptTimes(2);

    expect($this->disk->exists('PackageClass.php'))->toBeTrue()
        ->and($this->disk->exists('AsciitoClass.php'))->toBeTrue();
});

it('remove tags', function () {
    \Illuminate\Support\Sleep::fake();

    $this->disk->put('fake.txt.stub', <<<'TEXT'
    <!--DELETE-->This will not be here soon<!--/DELETE-->
    This will be in the fake file,<!--DELETE--> but not this <!--/DELETE--> and
    will not remove the text that is not surrounded by the `DELETE` tag.
    The next tag <!--DELETE-->is has a typo so will not be removed<!--DELETE-->
    from the text.
    TEXT);

    $this->artisan('init', $this->commandConfig)
        ->expectsConfirmation('Do you want to use this configuration');

    expect($this->disk->get('fake.txt'))
        ->toBe(<<<'TEXT'
        This will be in the fake file, and
        will not remove the text that is not surrounded by the `DELETE` tag.
        The next tag <!--DELETE-->is has a typo so will not be removed<!--DELETE-->
        from the text.
        TEXT);
});
