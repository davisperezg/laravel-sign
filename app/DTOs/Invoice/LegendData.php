<?php

namespace App\DTOs\Invoice;

readonly class LegendData
{
    public function __construct(
        public string $code,
        public string $value
    ) {}

    public static function from(array $data): self
    {
        return new self(
            code: $data['code'],
            value: $data['value']
        );
    }

}
