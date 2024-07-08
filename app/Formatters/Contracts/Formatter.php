<?php

declare(strict_types=1);

namespace App\Formatters\Contracts;

interface Formatter
{
    public function formatPlaceholder(string $placeholder): string;

    public function formatValue(string $value): string;
}
