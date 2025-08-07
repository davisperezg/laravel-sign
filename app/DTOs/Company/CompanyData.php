<?php

namespace App\DTOs\Company;

readonly class CompanyData
{
    public function __construct(
        public string $ruc,
        public string $razonSocial,
        public string $nombreComercial,
        public AddressData $address
    ) {}

    public static function from(array $data): self
    {
        return new self(
            ruc: $data['ruc'],
            razonSocial: $data['razon_social'],
            nombreComercial: $data['nombre_comercial'],
            address: AddressData::from($data['address'])
        );
    }
}
