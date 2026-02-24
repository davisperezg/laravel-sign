<?php

namespace App\Enums;

enum PorcentajeIgvEnum: string
{
    // Vigentes actualmente
    case EXONERADO = '0.0';   // Exportación, operaciones exoneradas o inafectas
    case MYPE = '10.0';       // Restaurantes, hoteles y alojamientos turísticos (hasta 31/12/2026)
    case GENERAL = '18.0';    // Régimen general

    // Futuros (parametrizables según normativa)
    case GENERAL_2026 = '16.0';  // Posible reducción general (propuesta para 2026)
    case MYPE_2027 = '14.0';     // Para MYPE turismo/restaurantes a partir de 2027

    /**
     * Obtiene la etiqueta descriptiva del porcentaje de IGV
     */
    public function label(): string
    {
        return match ($this) {
            self::EXONERADO => '0% - Exonerado / Exportación / Inafecto',
            self::MYPE => '10% - MYPE Restaurantes y Hoteles (vigente hasta 2026)',
            self::GENERAL => '18% - Tasa General',
            self::GENERAL_2026 => '16% - General (propuesta desde 2026)',
            self::MYPE_2027 => '14% - MYPE Restaurantes y Hoteles (desde 2027)',
        };
    }

    /**
     * Obtiene el valor como float para cálculos
     */
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /**
     * Obtiene el valor decimal para cálculos (ej: 18% = 0.18)
     */
    public function decimal(): float
    {
        return $this->toFloat() / 100;
    }


    /**
     * Calcula el IGV sobre un monto base
     */
    public function calcularIgv(float $montoBase): float
    {
        return round($montoBase * $this->decimal(), 2);
    }

    /**
     * Calcula el precio con IGV incluido
     */
    public function calcularPrecioConIgv(float $montoBase): float
    {
        return round($montoBase * (1 + $this->decimal()), 2);
    }
}
