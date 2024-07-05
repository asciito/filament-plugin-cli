<?php

declare(strict_types=1);

namespace App\Formatters\Contracts;

interface Formatter
{
    public function format(string $value): string;
}
