<?php

namespace App\DTOs\Invoice;

use App\DTOs\Client\ClientData;
use App\DTOs\Company\CompanyData;
use App\Enums\FormaPagoType;
use DateTime;

readonly class InvoiceData
{
    /**
     * @param InvoiceDetailData[] $details
     * @param string[]|null $observations
     * @param LegendData[] $legends
     */
    public function __construct(
        public ClientData $client,
        public CompanyData $company,
        public string $ubl_version,
        public ?DateTime $fecha_vencimiento,
        public string $tipo_operacion,
        public string $tipo_doc,
        public string $serie,
        public string $correlativo,
        public DateTime $fecha_emision,
        public string $forma_pago,
        public string $tipo_moneda,
        public InvoiceMontosData $montos,
        public array $details,
        /** @var string[] */
        public ?array $observations,
        public array $legends
    ) {
    }
}
