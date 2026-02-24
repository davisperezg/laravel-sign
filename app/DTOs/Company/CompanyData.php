<?php

namespace App\DTOs\Company;

readonly class CompanyData
{
    public function __construct(
        public string $ruc,
        public string $razon_social,
        public string $nombre_comercial,
        public AddressData $address
    ) {
    }
}
