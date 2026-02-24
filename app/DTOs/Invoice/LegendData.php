<?php

namespace App\DTOs\Invoice;

readonly class LegendData
{
    public function __construct(
        public int $code,
        public string $value
    ) {}
}
