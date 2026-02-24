<?php

namespace App\DTOs\Invoice;

readonly class InvoiceMontosData
{
    public function __construct(
        public ?float $oper_gravadas,
        public ?float $oper_exoneradas,
        public ?float $oper_inafectas,
        public ?float $oper_gratuitas,
        public ?float $oper_exportacion,
        public ?float $igv,
        public ?float $igv_gratuitas,
        public float $total_impuestos,
        public float $valor_venta,
        public float $sub_total,
        public float $imp_venta,
    ) {
    }

//    public static function messages(): array
//    {
//        return [
//            '*.required' => 'El campo :attribute es obligatorio.',
//            '*.numeric' => 'El campo :attribute debe ser un número.',
//            '*.min' => 'El campo :attribute no puede ser negativo.',
//            '*.regex' => 'El campo :attribute debe tener máximo 2 decimales.',
//
//            'oper_gravadas.regex' => 'Las operaciones gravadas deben tener máximo 2 decimales.',
//            'oper_exoneradas.regex' => 'Las operaciones exoneradas deben tener máximo 2 decimales.',
//            'oper_inafectas.regex' => 'Las operaciones inafectas deben tener máximo 2 decimales.',
//            'oper_gratuitas.regex' => 'Las operaciones gratuitas deben tener máximo 2 decimales.',
//            'oper_exportacion.regex' => 'Las operaciones de exportación deben tener máximo 2 decimales.',
//
//            'igv.regex' => 'El IGV debe tener máximo 2 decimales.',
//            'igv_gratuitas.regex' => 'El IGV de gratuitas debe tener máximo 2 decimales.',
//            'total_impuestos.regex' => 'El total de impuestos debe tener máximo 2 decimales.',
//
//            'valor_venta.regex' => 'El valor de venta debe tener máximo 2 decimales.',
//            'sub_total.regex' => 'El subtotal debe tener máximo 2 decimales.',
//            'imp_venta.regex' => 'El importe de venta debe tener máximo 2 decimales.',
//            'monto_total.regex' => 'El monto total debe tener máximo 2 decimales.',
//
//            'tasa_igv.in' => 'La tasa de IGV debe ser 0%, 10% o 18%.',
//        ];
//    }

    /**
     * Método helper para calcular automáticamente los totales
     */
//    public function calcularTotales(): self
//    {
//        $valorVenta = $this->oper_gravadas + $this->oper_exoneradas +
//            $this->oper_inafectas + $this->oper_exportacion;
//
//        $igv = round(($this->oper_gravadas * $this->tasa_igv) / 100, 2);
//
//        $totalImpuestos = $igv + $this->igv_gratuitas + $this->isc;
//
//        $subTotal = $valorVenta + $totalImpuestos + $this->otros_cargos - $this->descuentos;
//
//        $impVenta = $subTotal + $this->redondeo;
//
//        return new self(
//            oper_gravadas: $this->oper_gravadas,
//            oper_exoneradas: $this->oper_exoneradas,
//            oper_inafectas: $this->oper_inafectas,
//            oper_gratuitas: $this->oper_gratuitas,
//            oper_exportacion: $this->oper_exportacion,
//            igv: $igv,
//            igv_gratuitas: $this->igv_gratuitas,
//            total_impuestos: $totalImpuestos,
//            valor_venta: $valorVenta,
//            sub_total: $subTotal,
//            imp_venta: $impVenta,
//            isc: $this->isc,
//            otros_cargos: $this->otros_cargos,
//            descuentos: $this->descuentos,
//            redondeo: $this->redondeo,
//            monto_total: $impVenta,
//            tasa_igv: $this->tasa_igv
//        );
//    }
//
//    /**
//     * Verificar si los cálculos son consistentes
//     */
//    public function esConsistente(): bool
//    {
//        $calculado = $this->calcularTotales();
//
//        return abs($this->valor_venta - $calculado->valor_venta) < 0.01 &&
//            abs($this->igv - $calculado->igv) < 0.01 &&
//            abs($this->total_impuestos - $calculado->total_impuestos) < 0.01 &&
//            abs($this->sub_total - $calculado->sub_total) < 0.01 &&
//            abs($this->imp_venta - $calculado->imp_venta) < 0.01;
//    }
}
