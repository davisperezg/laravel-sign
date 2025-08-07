<?php

namespace App\DTOs\Client;

readonly class ClientData
{
    public function __construct(
        public string $tipoDoc,
        public string $numDoc,
        public string $razonSocial,
        public string $direccion
    ) {}

    public static function from(array $data): self
    {
        return new self(
            tipoDoc: $data['tipo_doc'],
            numDoc: $data['num_doc'],
            razonSocial: $data['razon_social'],
            direccion: $data['direccion']
        );
    }
}
