<?php

namespace App\DTOs\Company;

readonly class AddressData
{
    public function __construct(
        public string $ubigeo,
        public string $departamento,
        public string $provincia,
        public string $distrito,
        public string $urbanizacion,
        public string $direccion,
        public string $codLocal
    ) {}

    public static function from(array $data): self
    {
        return new self(
            ubigeo: $data['ubigeo'],
            departamento: $data['departamento'],
            provincia: $data['provincia'],
            distrito: $data['distrito'],
            urbanizacion: $data['urbanizacion'],
            direccion: $data['direccion'],
            codLocal: $data['cod_local']
        );
    }
}
