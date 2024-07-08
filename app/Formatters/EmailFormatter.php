<?php

declare(strict_types=1);

namespace App\Formatters;

use Illuminate\Support\Str;

class EmailFormatter implements \App\Formatters\Contracts\Formatter
{
    public function formatPlaceholder(string $placeholder): string
    {
        return Str::of($placeholder)->slug().':email';
    }

    public function formatValue(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_EMAIL);
    }
}
