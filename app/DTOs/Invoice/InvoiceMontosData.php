<?php

namespace App\DTOs\Invoice;

readonly class InvoiceMontosData
{
    public function __construct(
        public float $operGravadas = 0.0,
        public float $operExoneradas = 0.0,
        public float $operInafectas = 0.0,
        public float $operGratuitas = 0.0,
        public float $operExportacion = 0.0,
        public float $igv = 0.0,
        public float $igvGratuitas = 0.0,
        public float $totalImpuestos = 0.0,
        public float $valorVenta = 0.0,
        public float $subTotal = 0.0,
        public float $impVenta = 0.0
    ) {}

    public static function from(array $data): self
    {
        return new self(
            operGravadas: $data['oper_gravadas'] ?? 0.0,
            operExoneradas: $data['oper_exoneradas'] ?? 0.0,
            operInafectas: $data['oper_inafectas'] ?? 0.0,
            operGratuitas: $data['oper_gratuitas'] ?? 0.0,
            operExportacion: $data['oper_exportacion'] ?? 0.0,
            igv: $data['igv'] ?? 0.0,
            igvGratuitas: $data['igv_gratuitas'] ?? 0.0,
            totalImpuestos: $data['total_impuestos'] ?? 0.0,
            valorVenta: $data['valor_venta'] ?? 0.0,
            subTotal: $data['sub_total'] ?? 0.0,
            impVenta: $data['imp_venta'] ?? 0.0
        );
    }

}
