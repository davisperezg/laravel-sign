<?php

namespace App\DTOs\Company;

readonly class AddressData
{
    public function __construct(
        public string $ubigeo,
        public string $departamento,
        public string $provincia,
        public string $distrito,
        public ?string $urbanizacion,
        public string $direccion,
        public string $cod_local
    ) {
    }
}
