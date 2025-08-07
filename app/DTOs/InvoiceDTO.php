<?php

namespace App\DTOs;

use App\DTOs\Client\ClientData;
use App\DTOs\Company\CompanyData;
use App\DTOs\Invoice\InvoiceData;
use DateMalformedStringException;

readonly class InvoiceDTO
{
    public function __construct(
        public ClientData $client,
        public CompanyData $company,
        public InvoiceData $invoice
    ) {}

    /**
     * @throws DateMalformedStringException
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            client: ClientData::from($data['client']),
            company: CompanyData::from($data['company']),
            invoice: InvoiceData::from($data['invoice'])
        );
    }

}
