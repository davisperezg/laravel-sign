<?php

namespace App\DTOs\Invoice;

readonly class ObservationData
{
    public function __construct(
        public string $observation
    ) {}

    public static function from(string $observation): self
    {
        return new self(
            observation: $observation
        );
    }
}
