<?php

namespace App\DTOs\Invoice;

use DateMalformedStringException;
use DateTime;

readonly class InvoiceData
{
    public function __construct(
        public string $ublVersion,
        public ?DateTime $fechaVencimiento,
        public string $tipoOperacion,
        public string $tipoDoc,
        public string $serie,
        public string $correlativo,
        public DateTime $fechaEmision,
        public string $formaPago,
        public string $tipoMoneda,
        public InvoiceMontosData $montos,
        /** @var InvoiceDetailData[] */
        public array $details,
        /** @var ObservationData[] */
        public array $observations,
        /** @var LegendData[] */
        public array $legends
    ) {}

    /**
     * @throws DateMalformedStringException
     */
    public static function from(array $data): self
    {
        return new self(
            ublVersion: $data['ubl_version'],
            fechaVencimiento: $data['fecha_vencimiento'] ? new DateTime($data['fecha_vencimiento']) : null,
            tipoOperacion: $data['tipo_operacion'],
            tipoDoc: $data['tipo_doc'],
            serie: $data['serie'],
            correlativo: $data['correlativo'],
            fechaEmision: new DateTime($data["fecha_emision"]),
            formaPago: $data['forma_pago'],
            tipoMoneda: $data['tipo_moneda'],
            montos: InvoiceMontosData::from($data['montos']),
            details: array_map(
                fn(array $detail): InvoiceDetailData => InvoiceDetailData::from($detail),
                $data['details'] ?? []
            ),
            observations: array_map(
                fn(string $observation): ObservationData => ObservationData::from($observation),
                $data['observations'] ?? []
            ),
            legends: array_map(
                fn(array $legend): LegendData => LegendData::from($legend),
                $data['legends'] ?? []
            )
        );
    }
}
