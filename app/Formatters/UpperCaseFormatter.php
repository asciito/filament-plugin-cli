<?php

declare(strict_types=1);

namespace App\Formatters;

use App\Formatters;
use Illuminate\Support\Str;

class UpperCaseFormatter implements Formatters\Contracts\Formatter
{
    public function formatPlaceholder(string $placeholder): string
    {
        return Str::of($placeholder)->slug('')->upper()->toString();
    }

    public function formatValue(string $value): string
    {
        return Str::of($value)->slug('')->upper()->toString();
    }
}
