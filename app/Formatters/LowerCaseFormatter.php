<?php

declare(strict_types=1);

namespace App\Formatters;

use App\Formatters;
use Illuminate\Support\Str;

class LowerCaseFormatter implements Formatters\Contracts\Formatter
{
    public function format(string $value): string
    {
        return Str::of($value)->slug()->lower()->toString();
    }
}
