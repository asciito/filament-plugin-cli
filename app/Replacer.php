<?php

declare(strict_types=1);

namespace App;

use App\Formatters\Contracts\Formatter;

class Replacer
{
    public function __construct(
        protected string $placeholder,
        protected string $value,
        protected array|string $formatters = [],
        protected string $startWrapper = '',
        protected string $endWrapper = '',
    ) {
        //
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getFormatters(): array
    {
        return is_string($this->formatters) ? [$this->formatters] : $this->formatters;
    }

    public function replaceOn(string $content): string
    {
        if (empty($this->getFormatters())) {
            return str_replace($this->wrapPlaceholder($this->getPlaceholder()), $this->getValue(), $content);
        }

        foreach ($this->getFormatters() as $formatter) {
            /** @var Formatter $formatter */
            $formatter = new $formatter;
            $placeholder = $this->getPlaceholder();
            $value = $this->getValue();

            $content = str_replace(
                search: $this->wrapPlaceholder($formatter->formatPlaceholder($placeholder)),
                replace: $formatter->formatValue($value),
                subject: $content,
            );
        }

        return $content;
    }

    protected function wrapPlaceholder(string $placeholder): string
    {
        return $this->startWrapper.$placeholder.$this->endWrapper;
    }
}
