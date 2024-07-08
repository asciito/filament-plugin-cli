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
        return Str::of($value)->title()->toString();
    }
}
