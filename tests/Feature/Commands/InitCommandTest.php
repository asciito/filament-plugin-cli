<?php

beforeEach(fn () => $this->commandConfig = [
    'vendor' => 'asciito',
    'package' => 'sample',
    'author' => 'Ayax Córdova',
    'author-email' => 'example@mail.com',
    'description' => 'Lorem ipsum dolor it sa, des quan tu mit lamp',
    '--path' => $this->disk->path(''),
    '--no-interaction',
]);

it('replace placeholders', function () {
    \Illuminate\Support\Sleep::fake();

    $this->getTestingDisk()->put('SampleClass.php', <<<'PHP'
    <?php

    declare(strict_types=1);

    namespace {{Namespace}};

    class SampleClass extends \{{Namespace}}\Core\AbstractTool implements \{{Namespace}}\Contracts\InterfaceTool {
        use \{{Namespace}}\Concerns\TraitTool;

        const {{PACKAGE}}_CONSTANT = '{{VENDOR}}_VALUE';

        public function get{{Package}}Directory(): string
        {
            return '/home/directory/{{vendor}}/{{package}}';
        }
    }
    PHP);

    $this->getTestingDisk()->put('composer.json', <<<'JSON'
    {
      "name": "{{vendor}}/{{package}}",
      "description": "{{description}}",
      "type": "project",
      "authors": [
        {
          "name": "{{author:title}}",
          "email": "{{author-email:email}}"
        }
      ],
      "autoload": {
        "psr-4": {
          "\\{{Vendor}}\\{{Package}}\\{{Package}}ProviderClass\\": "src/"
        }
      },
      "autoload-dev": {
        "psr-4": {
          "\\{{Vendor}}\\{{Package}}\\SampleClassTest\\": "tests/"
        }
      },
      "extra": {
        "laravel": [
          "{{Vendor}}\\{{Package}}\\SampleClass"
        ]
      }
    }
    JSON);

    $this->artisan('init', ['--path' => $this->disk->path(''), '-d'])
        ->expectsQuestion('What\'s the Vendor name', 'asciito')
        ->expectsQuestion('What\'s the Package name', 'example')
        ->expectsQuestion('What\'s the Author\'s name', 'Ayax Córdova')
        ->expectsQuestion('What\'s the Author\'s e-mail', 'example@mail.com')
        ->expectsQuestion('Describe your plugin', 'Lorem ipsum dolor it sa, des quan tu mit lamp')
        ->expectsOutputToContain(<<<'CONFIG'
        Author:        Ayax Córdova
        Author E-mail: example@mail.com
        Vendor:        asciito
        Package:       example
        Description:   Lorem ipsum dolor it sa, des quan tu mit lamp
        CONFIG)
        ->expectsConfirmation('Do you want to use this configuration', 'yes')
        ->expectsOutputToContain('Replacing placeholders in file [SampleClass.php]')
        ->expectsOutputToContain('Replacing placeholders in file [composer.json]')
        ->assertSuccessful();

    \Illuminate\Support\Sleep::assertSleptTimes(2);

    expect($this->getTestingDisk()->get('SampleClass.php'))
        ->toBe(<<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Asciito\Example;

        class SampleClass extends \Asciito\Example\Core\AbstractTool implements \Asciito\Example\Contracts\InterfaceTool {
            use \Asciito\Example\Concerns\TraitTool;

            const EXAMPLE_CONSTANT = 'ASCIITO_VALUE';

            public function getExampleDirectory(): string
            {
                return '/home/directory/asciito/example';
            }
        }
        PHP)
        ->and($this->getTestingDisk()->get('composer.json'))
        ->toBe(<<<'JSON'
        {
          "name": "asciito/example",
          "description": "Lorem ipsum dolor it sa, des quan tu mit lamp",
          "type": "project",
          "authors": [
            {
              "name": "Ayax Córdova",
              "email": "example@mail.com"
            }
          ],
          "autoload": {
            "psr-4": {
              "\\Asciito\\Example\\ExampleProviderClass\\": "src/"
            }
          },
          "autoload-dev": {
            "psr-4": {
              "\\Asciito\\Example\\SampleClassTest\\": "tests/"
            }
          },
          "extra": {
            "laravel": [
              "Asciito\\Example\\SampleClass"
            ]
          }
        }
        JSON);
});

it('replace file name', function () {
    \Illuminate\Support\Sleep::fake();

    $this->getTestingDisk()->put('PackageClass.php', '');
    $this->getTestingDisk()->put('VendorFile.txt', '');
    $this->getTestingDisk()->put('PackageAuthorFile.txt', '');

    $this->artisan('init', $this->commandConfig)->assertSuccessful();

    \Illuminate\Support\Sleep::assertSleptTimes(3);

    expect($this->getTestingDisk())
        ->exists('SampleClass.php')
        ->toBeTrue()
        ->and($this->getTestingDisk())
        ->exists('AsciitoFile.txt')
        ->toBeTrue()
        ->and($this->getTestingDisk())
        ->exists('SampleAyaxCórdovaFile.txt')
        ->toBeTrue();
});

it('replace nothing', function () {
    \Illuminate\Support\Sleep::fake();

    $this->getTestingDisk()->put('phpunit.xml', '');
    $this->getTestingDisk()->put('package.json', '');
    $this->getTestingDisk()->put('testbench.yaml', '');
    $this->getTestingDisk()->put('ExampleClass.php', '');

    $this->artisan('init', [...$this->commandConfig, '--exclude' => 'ExampleClass.php'])
        ->doesntExpectOutputToContain('Replacing placeholders in file [phpunit.xml]')
        ->doesntExpectOutputToContain('Replacing placeholders in file [package.json]')
        ->doesntExpectOutputToContain('Replacing placeholders in file [testbench.yaml]')
        ->assertSuccessful();

    \Illuminate\Support\Sleep::assertSleptTimes(0);

    expect($this->getTestingDisk()->files())->toHaveCount(4);
});

it('remove tags', function () {
    // Arrange
    \Illuminate\Support\Sleep::fake();

    $this->getTestingDisk()->put('README.md', <<<'MD'
    # Lorem ipsum dolor sit amet

    <!--DELETE-->
    This will not be in the file fusce scelerisque luctus facilisis.
    Mauris ac consequat libero.
    <!--/DELETE-->

    Consectetur adipiscing elit. Vivamus interdum
    orci sem, sit amet blandit metus lacinia a.

    1. Rhoncus magna vitae.
    2. bibendum nisi.

    ---

    ## Nam a ex cursus, suscipit mauris

    Sed, ultricies eros. Integer tincidunt, justo et vestibulum pretium, orci ex
    facilisis dolor.

    <!--DELETE-->
    This malformed tag will cause a problem and will delete all the content
    until the next closing tag, due to the missing `/` in the closing
    `DELETE` tag.
    <!--DELETE-->

    * Quis pretium felis diam et tellus.
    * Aliquam in odio hendreri.

    ---

    ### Aliquam eget tristique

    Cras vitae nibh *venenatis* magna cursus vestibulum. Ut tristique et lorem quis venenatis.

    > Magna
    >
    > Quis maximus nunc. Aliquam erat volutpat. Praesent feugiat, enim sed
    > varius mattis, purus magna scelerisque nibh, at maximus odio urna in ex.

    <!--DELETE-->
    The content will be deleted until here due to the malformed, due to the missing
    closing tag.
    <!--/DELETE-->

    <!--NOTHING-->
    This will not be deleted
    <!--/NOTING-->

    **Cras condimentum** velit nisl, eget semper

    ## Ut enim ad minim veniam, quis nostrud

    Exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis
    aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu
    fugiat nulla pariatur.

    <!--DELETE-->
    Excepteur sint occaecat cupidatat non [proident](https://example.com/lorem-ipsum), sunt in culpa qui officia
    deserunt mollit anim id est laborum.
    <!--/DELETE-->
    MD);

    // Act
    $this->artisan('init', $this->commandConfig)
        ->expectsOutputToContain('Replacing placeholders in file [README.md]');

    \Illuminate\Support\Sleep::assertSleptTimes(1);

    expect($this->getTestingDisk()->get('README.md'))
        ->toBe(<<<'MD'
        # Lorem ipsum dolor sit amet

        Consectetur adipiscing elit. Vivamus interdum
        orci sem, sit amet blandit metus lacinia a.

        1. Rhoncus magna vitae.
        2. bibendum nisi.

        ---

        ## Nam a ex cursus, suscipit mauris

        Sed, ultricies eros. Integer tincidunt, justo et vestibulum pretium, orci ex
        facilisis dolor.

        <!--NOTHING-->
        This will not be deleted
        <!--/NOTING-->

        **Cras condimentum** velit nisl, eget semper

        ## Ut enim ad minim veniam, quis nostrud

        Exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis
        aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu
        fugiat nulla pariatur.
        MD);
});
