<?php

namespace App\Http\Requests;

use App\DTOs\Invoice\InvoiceDetailData;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *  descripcion -> regex:/^(?!\s*$)[^\s].{2,}$/
     * @return array{
         * cod_producto: string,
         * unidad: string,
         * descripcion: string,
         * cantidad: string,
         * mto_valor_unitario: string,
         * mto_valor_venta: string,
         * mto_base_igv: string,
         * porcentaje_igv: string,
         * igv: string,
         * tip_afe_igv: string,
         * total_impuestos: string,
         * mto_precio_unitario: string,
         * mto_valor_gratuito: string
     * }
     */
    public function rules(): array
    {
        return [
            'cod_producto' => 'sometimes|nullable|string|max:55',
            'unidad' => 'required|string',
            'descripcion' => 'required|string|min:3|max:500|regex:/^(?!\s*$)[\s\S]{0,}$/',
            'cantidad' => 'required|numeric|gt:0|regex:/^\d{1,12}(\.\d{1,10})?$/',
            'mto_valor_unitario' => 'required|numeric|gt:0|regex:/^\d{1,12}(\.\d{1,10})?$/',
            'mto_valor_venta' => 'required|numeric|gt:0|regex:/^\d{1,12}(\.\d{1,10})?$/',
            'mto_base_igv' => 'required|numeric|gt:0|regex:/^\d{1,12}(\.\d{1,10})?$/',
            'porcentaje_igv' => 'required|numeric|regex:/^(?!(0)[0-9]+$)[0-9]{1,3}(\.[0-9]{1,5})?$/',
            'igv' => 'required|numeric|gt:0|regex:/^\d{1,12}(\.\d{1,10})?$/',
            'tip_afe_igv' => 'required|string|regex:/^(10|11|12|13|14|15|16|17|20|21|30|31|32|33|34|35|36|37|40)$/',
            'total_impuestos' => 'required|numeric|gt:0|regex:/^\d{1,12}(\.\d{1,10})?$/',
            'mto_precio_unitario' => 'required|numeric|gt:0|regex:/^\d{1,12}(\.\d{1,10})?$/',
            'mto_valor_gratuito' => 'sometimes|nullable|numeric|gt:0|regex:/^\d{1,12}(\.\d{1,10})?$/'
        ];
    }

    public function messages(): array
    {
        return [
            'cod_producto.string' => 'El cod_producto debe ser texto',
            'cod_producto.max' => 'El cod_producto no puede tener más de 55 caracteres',
            'unidad.required' => 'La unidad es obligatorio',
            'unidad.string' => 'La unidad debe ser texto',
            'descripcion.required' => 'La descripcion es obligatoria',
            'descripcion.string' => 'La descripcion debe ser texto',
            'descripcion.min' => 'La descripcion debe tener al menos 3 caracteres',
            'descripcion.max' => 'La descripcion debe tener al menos 500 caracteres',
            'descripcion.regex' => 'La descripción no puede estar vacía ni contener solo espacios en blanco',
            'cantidad.required' => 'La cantidad es obligatoria',
            'cantidad.numeric' => 'La cantidad debe ser numerico',
            'cantidad.gt' => 'La cantidad debe ser mayor a 0',
            'cantidad.regex' => 'La cantidad debe tener hasta 12 enteros y un máximo de 10 decimales',
            'mto_valor_unitario.required' => 'Es necesario ingresar mto_valor_unitario',
            'mto_valor_unitario.numeric' => 'El mto_valor_unitario debe ser numerico',
            'mto_valor_unitario.gt' => 'El mto_valor_unitario debe ser mayor a 0',
            'mto_valor_unitario.regex' => 'El mto_valor_unitario debe tener hasta 12 enteros y un máximo de 10 decimales',
            'mto_valor_venta.required' => 'Es necesario ingresar mto_valor_venta',
            'mto_valor_venta.numeric' => 'El mto_valor_venta debe ser numerico',
            'mto_valor_venta.gt' => 'El mto_valor_venta debe ser mayor a 0',
            'mto_valor_venta.regex' => 'El mto_valor_venta debe tener hasta 12 enteros y un máximo de 10 decimales',
            'mto_base_igv.required' => 'Es necesario ingresar mto_base_igv',
            'mto_base_igv.numeric' => 'El mto_base_igv debe ser numerico',
            'mto_base_igv.gt' => 'El mto_base_igv debe ser mayor a 0',
            'mto_base_igv.regex' => 'El mto_base_igv debe tener hasta 12 enteros y un máximo de 10 decimales',
            'porcentaje_igv.required' => 'Es necesario ingresar porcentaje_igv',
            'porcentaje_igv.numeric' => 'El porcentaje_igv debe ser numerico',
            'porcentaje_igv.regex' => 'El porcentaje debe ser un número entre 0 y 999 con hasta 5 decimales, sin ceros a la izquierda',
            'igv.required' => 'Es necesario ingresar igv',
            'igv.numeric' => 'El igv debe ser numerico',
            'igv.gt' => 'El igv debe ser mayor a 0',
            'igv.regex' => 'El igv debe tener hasta 12 enteros y un máximo de 10 decimales',
            'tip_afe_igv.required' => 'Es necesario ingresar tip_afe_igv',
            'tip_afe_igv.string' => 'El tip_afe_igv debe ser texto',
            'tip_afe_igv.regex' => 'El tip_afe_igv debe ser un código válido según SUNAT (10, 11, 12, 13, 14, 15, 16, 17, 20, 21, 30, 31, 32, 33, 34, 35, 36, 37 o 40)',
            'total_impuestos.required' => 'Es necesario ingresar total_impuestos',
            'total_impuestos.numeric' => 'El total_impuestos debe ser numerico',
            'total_impuestos.gt' => 'El total_impuestos debe ser mayor a 0',
            'total_impuestos.regex' => 'El total_impuestos debe tener hasta 12 enteros y un máximo de 10 decimales',
            'mto_precio_unitario.required' => 'Es necesario ingresar mto_precio_unitario',
            'mto_precio_unitario.numeric' => 'El mto_precio_unitario debe ser numerico',
            'mto_precio_unitario.gt' => 'El mto_precio_unitario debe ser mayor a 0',
            'mto_precio_unitario.regex' => 'El mto_precio_unitario debe tener hasta 12 enteros y un máximo de 10 decimales',
            'mto_valor_gratuito.numeric' => 'El mto_precio_unitario debe ser numerico',
            'mto_valor_gratuito.gt' => 'El mto_precio_unitario debe ser mayor a 0',
            'mto_valor_gratuito.regex' => 'El mto_precio_unitario debe tener hasta 12 enteros y un máximo de 10 decimales',
        ];
    }

    public function toDTO(): InvoiceDetailData
    {
        return new InvoiceDetailData(
            cod_producto: $this->input('cod_producto'),
            unidad: $this->input('unidad'),
            descripcion: $this->input('descripcion'),
            cantidad: $this->input('cantidad'),
            mto_valor_unitario: $this->input('mto_valor_unitario'),
            mto_valor_venta: $this->input('mto_valor_venta'),
            mto_base_igv: $this->input('mto_base_igv'),
            porcentaje_igv: $this->input('porcentaje_igv'),
            igv: $this->input('igv'),
            tip_afe_igv: $this->input('tip_afe_igv'),
            total_impuestos: $this->input('total_impuestos'),
            mto_precio_unitario: $this->input('mto_precio_unitario'),
            mto_valor_gratuito: $this->input('mto_valor_gratuito'),
        );
    }
}
