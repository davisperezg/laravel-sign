<?php

namespace App\DTOs\Client;

readonly class ClientData
{
    public function __construct(
        public string $tipo_doc,
        public string $num_doc,
        public string $razon_social,
        public string $direccion
    ) {
    }
}
