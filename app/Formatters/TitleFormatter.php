<?php

declare(strict_types=1);

namespace App\Formatters;

use Illuminate\Support\Str;

class TitleFormatter implements \App\Formatters\Contracts\Formatter
{
    public function formatPlaceholder(string $placeholder): string
    {
        return Str::of($placeholder)->lower()->slug().':title';
    }

    public function formatValue(string $value): string
    {
        /**
         * `[:punct:]` Matches characters that are not whitespace, letters or
         * numbers. The double square brackets is not a typo, POSIX notation
         * demands it.
         */
        return Str::of($value)->replaceMatches('/[[:punct:]\s]\s?/', ' ')->title()->toString();
    }
}
