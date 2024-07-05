<?php

use App\Replacer;

it('can create replacer', function () {
    $replacer = new Replacer(placeholder: '{{placeholder}}', value: 'banana');

    $content = 'This is a {{placeholder}}, a really, really yellow {{placeholder}}.';

    expect($replacer->replaceOn(content: $content))
        ->toBe('This is a banana, a really, really yellow banana.')
        ->toBeString();
});

it('cannot replace anything', function () {
    $replacer = new Replacer(placeholder: '{{placeholder}}', value: 'nothing');

    $content = 'The {{Placeholder}} will not be replace.';

    expect($replacer->replaceOn($content))
        ->toBe('The {{Placeholder}} will not be replace.')
        ->toBeString();
});

it('can use formatter', function () {
    $content = <<<'TEXT'
    Lorem ipsum dolor sit amet, {{placeholder}} adipiscing elit, sed do eiusmod tempo
    incididunt ut labore et dolore magna aliqua. {{PLACEHOLDER}} enim ad minim veniam, quis
    nostrud exercitation ullamco laboris nisi ut {{placeholder}} ex ea commodo consequat.
    TEXT;

    $lowercaseFormatter = new class implements \App\Formatters\Contracts\Formatter
    {
        public function format(string $value): string
        {
            return mb_convert_case($value, MB_CASE_LOWER, 'UTF-8');
        }
    };

    $uppercaseFormatter = new class implements \App\Formatters\Contracts\Formatter
    {
        public function format(string $value): string
        {
            return mb_convert_case($value, MB_CASE_UPPER, 'UTF-8');
        }
    };

    $replacer = new Replacer(
        placeholder: 'placeholder',
        value: 'placeholder',
        formatters: [$lowercaseFormatter::class, $uppercaseFormatter::class],
        startWrapper: '{{',
        endWrapper: '}}',
    );

    expect($replacer->replaceOn($content))
        ->toBe(<<<'TEXT'
        Lorem ipsum dolor sit amet, placeholder adipiscing elit, sed do eiusmod tempo
        incididunt ut labore et dolore magna aliqua. PLACEHOLDER enim ad minim veniam, quis
        nostrud exercitation ullamco laboris nisi ut placeholder ex ea commodo consequat.
        TEXT);
});
