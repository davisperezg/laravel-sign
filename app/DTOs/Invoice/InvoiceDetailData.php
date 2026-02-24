<?php

namespace App\DTOs\Invoice;

readonly class InvoiceDetailData
{
    public function __construct(
        public ?string $cod_producto,
        public string $unidad,
        public string $descripcion,
        public float $cantidad,
        public float $mto_valor_unitario,
        public float $mto_valor_venta,
        public float $mto_base_igv,
        public float $porcentaje_igv,
        public float $igv,
        public string $tip_afe_igv,
        public float $total_impuestos,
        public float $mto_precio_unitario,
        public ?float $mto_valor_gratuito,
        //adicionales
        public ?float $icbper,  //(cantidad)*(factor ICBPER)
        public ?float $factor_icbper,   //factor ICBPER segun anio
    ) {
    }

}
