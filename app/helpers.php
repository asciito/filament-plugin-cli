<?php

declare(strict_types=1);

namespace App;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Remove tags from content
 *
 * e.g.
 * `<!--<TAG_NAME>--> ... <!--/<TAG_NAME>-->`
 * Replace the <TAG_NAME> with the name of the tag you want to be removed from
 * the content.
 *
 * Be sure to close correctly the tag in your content, because if no matching closing
 * tag is found, the tag will remove the content until the next close tag occurrence.
 *
 * @param  string  $tag  The tag to remove
 * @param  string  $content  The content which tags will be removed
 * @return string The content without the tag
 */
function removeTag(string $tag, string $content): string
{
    return Str::of($content)
        ->replaceMatches('/(?(?=\s*#)\s*#\s*|\s*)<!--'.$tag.'-->.*?<!--\/'.$tag.'-->/s', '')
        ->toString();
}

/**
 * Replace the placeholders with the given value
 *
 * @param  array|string  $placeholder  The searchable placeholder
 * @param  array|string  $value  The value to replace in the placeholder
 * @param  string  $content  The content where the placeholder will be replaced
 * @param  array  $formatters  The formatters to use with the placeholder
 * @param  string  $startWrapper  The starting wrapper characters
 * @param  string  $endWrapper  The ending wrapper characters
 */
function replacePlaceholder(
    array|string $placeholder,
    array|string $value,
    string $content,
    array $formatters = [],
    string $startWrapper = '',
    string $endWrapper = ''
): string {
    $zippedPlaceholdersAndValues = zip(Arr::wrap($placeholder), Arr::wrap($value));

    return collect($zippedPlaceholdersAndValues)
        ->filter(fn (array $arr) => ! is_null($arr[1]))
        ->map(fn (array $arr) => ['placeholder' => $arr[0], 'value' => $arr[1]])
        ->reduce(function (string $prev, array $container) use ($formatters, $startWrapper, $endWrapper) {
            $replacer = new Replacer($container['placeholder'], $container['value'], $formatters, $startWrapper, $endWrapper);

            return $replacer->replaceOn($prev);
        }, $content);
}

/**
 * Aggregates elements from each of the arrays
 *
 * zip() should only be used with unequal length inputs when you don’t care
 * about trailing, unmatched values from the longer iterables.
 */
function zip(array ...$arrays): array
{
    return collect(array_map(null, ...$arrays))
        ->filter(fn (array $arr) => ! in_array(null, $arr, true))
        ->toArray();
}
