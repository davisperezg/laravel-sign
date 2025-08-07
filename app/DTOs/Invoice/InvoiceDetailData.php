<?php

namespace App\DTOs\Invoice;

readonly class InvoiceDetailData
{
    public function __construct(
        public string $codProducto,
        public string $unidad,
        public string $descripcion,
        public int $cantidad,
        public float $mtoValorUnitario,
        public float $mtoValorVenta,
        public float $mtoBaseIgv,
        public float $porcentajeIgv,
        public float $igv,
        public string $tipAfeIgv,
        public float $totalImpuestos,
        public float $mtoPrecioUnitario,
        public ?float $mtoValorGratuito = null,
        public ?string $codProductoSunat = null
    ) {}

    public static function from(array $data): self
    {

        return new self(
            codProducto: $data['cod_producto'],
            unidad: $data['unidad'],
            descripcion: $data['descripcion'],
            cantidad: $data['cantidad'],
            mtoValorUnitario: $data['mto_valor_unitario'],
            mtoValorVenta: $data['mto_valor_venta'],
            mtoBaseIgv: $data['mto_base_igv'],
            porcentajeIgv: $data['porcentaje_igv'],
            igv: $data['igv'],
            tipAfeIgv: $data['tip_afe_igv'],
            totalImpuestos: $data['total_impuestos'],
            mtoPrecioUnitario: $data['mto_precio_unitario'],
            mtoValorGratuito: $data['mto_valor_gratuito'] ?? null,
            codProductoSunat: $data['cod_producto_sunat'] ?? null
        );
    }

}
