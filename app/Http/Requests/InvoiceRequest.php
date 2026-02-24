<?php

namespace App\Http\Requests;

use App\DTOs\Client\ClientData;
use App\DTOs\Company\AddressData;
use App\DTOs\Company\CompanyData;
use App\DTOs\Invoice\InvoiceData;
use App\DTOs\Invoice\InvoiceDetailData;
use App\DTOs\Invoice\InvoiceMontosData;
use App\DTOs\Invoice\LegendData;
use DateTime;
use Exception;
use Greenter\Model\Sale\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
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

    public function rules(): array
    {
        return [
            // Tipo de documento SUNAT (Catálogo 06)
            'client.tipo_doc' => [
                'required',
                Rule::in(['0', '1', '4', '6', '7', 'A', 'B']),
            ],

            // Número de documento con validación condicional
            'client.num_doc' => [
                'required',
                function ($attribute, $value, $fail) {
                    $tipo = request('client.tipo_doc');
                    $comprobante = request('tipo_doc'); // 01 = factura, 03 = boleta

                    // --- Validaciones por tipo de documento ---
                    switch ($tipo) {
                        case '0': // No domiciliado
                            if (empty($value)) {
                                $fail('El documento para tipo 0 (No domiciliado) no puede estar vacío.');
                            }
                            break;

                        case '1': // DNI
                            if (!preg_match('/^\d{8}$/', $value)) {
                                $fail('El DNI debe tener exactamente 8 dígitos.');
                            }
                            // Restricción: No puede usarse DNI en facturas
                            if ($comprobante === '01') {
                                $fail('No se puede emitir facturas a clientes con DNI.');
                            }
                            break;

                        case '4': // Carné de extranjería
                            if (strlen($value) > 12) {
                                $fail('El Carné de extranjería no puede exceder 12 caracteres.');
                            }
                            // Restricción: No puede usarse en facturas
                            if ($comprobante === '01') {
                                $fail('No se puede emitir facturas a clientes con Carné de extranjería.');
                            }
                            break;

                        case '6': // RUC
                            if (!preg_match('/^\d{11}$/', $value)) {
                                $fail('El RUC debe tener exactamente 11 dígitos.');
                            }
                            break;

                        case '7': // Pasaporte
                            if (strlen($value) > 12) {
                                $fail('El Pasaporte no puede exceder 12 caracteres.');
                            }
                            // Restricción: No puede usarse en facturas
                            if ($comprobante === '01') {
                                $fail('No se puede emitir facturas a clientes con Pasaporte.');
                            }
                            break;

                        case 'A': // Cédula diplomática
                            if (strlen($value) > 15) {
                                $fail('La Cédula diplomática no puede exceder 15 caracteres.');
                            }
                            // Restricción: No puede usarse en facturas
                            if ($comprobante === '01') {
                                $fail('No se puede emitir facturas a clientes con Cédula diplomática.');
                            }
                            break;

                        case 'B': // Documento de país residencia (tratados internacionales)
                            if (empty($value)) {
                                $fail('El documento para tipo B no puede estar vacío.');
                            }
                            // Restricción: Generalmente no se usa en facturas locales
                            if ($comprobante === '01') {
                                $fail('No se puede emitir facturas a clientes con Documento tipo B.');
                            }
                            break;

                        default:
                            $fail("El tipo de documento $tipo no es válido según SUNAT.");
                    }
                },
            ],

            // Razon social o nombre completo
            'client.razon_social' => 'required|string|max:150',

            // Dirección (opcional, pero recomendada en facturas)
            'client.direccion' => [
                Rule::requiredIf(fn () => request('tipo_doc') === '01'), // obligatorio en facturas
                'string',
                'max:200',
            ],

            // ---- EMPRESA ----
            'company.ruc' => 'required|string|digits:11',
            'company.razon_social' => 'required|string|max:255',
            'company.nombre_comercial' => 'nullable|string|max:255',
            'company.address.ubigeo' => 'required|string|size:6',
            'company.address.departamento' => 'required|string|max:100',
            'company.address.provincia' => 'required|string|max:100',
            'company.address.distrito' => 'required|string|max:100',
            'company.address.urbanizacion' => 'nullable|string|max:100',
            'company.address.direccion' => 'required|string|max:255',
            'company.address.cod_local' => 'required|string|max:25',

            // ---- CABECERA ----
            'ubl_version' => 'required|string|in:2.1',
            'fecha_vencimiento' => 'nullable|date_format:Y-m-d\TH:i:s.v\Z',
            'tipo_operacion' => 'required|string|max:4',
            'tipo_doc' => [
                'required',
                'string',
                'in:01,03,07,08'    //01-Factura,03-Boleta,07-Nota de Crédito,08-Nota de Débito
            ],
            'serie' => [
                'required',
                'string',
                'max:4',
                function ($attribute, $value, $fail) {
                    $tipo = request('tipo_doc');

                    if ($tipo === '03' && !str_starts_with($value, 'B')) {
                        $fail('La serie debe empezar con B por el tipo de documento(03).');
                    }

                    if ($tipo === '01' && !str_starts_with($value, 'F')) {
                        $fail('La serie debe empezar con F por el tipo de documento (01).');
                    }

                    if (in_array($tipo, ['07', '08']) && !preg_match('/^[BF]/', $value)) {
                        $fail('La serie para Notas (07 o 08) debe empezar con B o F.');
                    }
                },
            ],
            'correlativo' => 'required|string|max:8',
            'fecha_emision' => 'required|date_format:Y-m-d\TH:i:s.v\Z',
            'forma_pago' => 'required|string|in:Contado,Credito',
            'tipo_moneda' => 'required|string|size:3',

            // ---- MONTOS ---- (12 enteros, hasta 2 decimales)
            // Verificar si oper_gravadas es requerido
            'montos.oper_gravadas' => [
                'nullable',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;

                    $details = request('details') ?? [];
                    $tiposGravados = ['10'];

                    $sumaTipos = '0';
                    foreach ($details as $detail) {
                        $tipAfe = $detail['tip_afe_igv'] ?? null;
                        if (in_array($tipAfe, $tiposGravados)) {
                            $valorVenta = (string)($detail['mto_valor_venta'] ?? 0);
                            $sumaTipos = bcadd($sumaTipos, $valorVenta, 2);
                        }
                    }

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $sumaTipos, 2) !== 0) {
                        $fail("El oper_gravadas debe ser {$sumaTipos} (suma de valores de venta con tip_afe_igv = 10).");
                    }
                },
            ],

            'montos.oper_exoneradas' => [
                'nullable',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;

                    $details = request('details') ?? [];
                    $tiposExonerados = ['20'];

                    $sumaTipos = '0';
                    foreach ($details as $detail) {
                        $tipAfe = $detail['tip_afe_igv'] ?? null;
                        if (in_array($tipAfe, $tiposExonerados)) {
                            $valorVenta = (string)($detail['mto_valor_venta'] ?? 0);
                            $sumaTipos = bcadd($sumaTipos, $valorVenta, 2);
                        }
                    }

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $sumaTipos, 2) !== 0) {
                        $fail("El oper_exoneradas debe ser {$sumaTipos} (suma de valores de venta con tip_afe_igv = 20).");
                    }
                },
            ],

            'montos.oper_inafectas' => [
                'nullable',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;

                    $details = request('details') ?? [];
                    $tiposInafectos = ['30'];

                    $sumaTipos = '0';
                    foreach ($details as $detail) {
                        $tipAfe = $detail['tip_afe_igv'] ?? null;
                        if (in_array($tipAfe, $tiposInafectos)) {
                            $valorVenta = (string)($detail['mto_valor_venta'] ?? 0);
                            $sumaTipos = bcadd($sumaTipos, $valorVenta, 2);
                        }
                    }

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $sumaTipos, 2) !== 0) {
                        $fail("El oper_inafectas debe ser {$sumaTipos} (suma de valores de venta con tip_afe_igv = 30).");
                    }
                },
            ],

            'montos.oper_gratuitas' => [
                'nullable',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;

                    $details = request('details') ?? [];
                    $tiposGratuitos = ['11','12','13','14','15','16','17','21','31','32','33','34','35','36','37'];

                    $sumaTipos = '0';
                    foreach ($details as $detail) {
                        $tipAfe = $detail['tip_afe_igv'] ?? null;
                        if (in_array($tipAfe, $tiposGratuitos)) {
                            $valorVenta = (string)($detail['mto_valor_venta'] ?? 0);
                            $sumaTipos = bcadd($sumaTipos, $valorVenta, 2);
                        }
                    }

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $sumaTipos, 2) !== 0) {
                        $fail("El oper_gratuitas debe ser {$sumaTipos} (suma de valores de venta gratuitas).");
                    }
                },
            ],

            'montos.oper_exportacion' => [
                'nullable',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;

                    $details = request('details') ?? [];
                    $tiposExportacion = ['40'];

                    $sumaTipos = '0';
                    foreach ($details as $detail) {
                        $tipAfe = $detail['tip_afe_igv'] ?? null;
                        if (in_array($tipAfe, $tiposExportacion)) {
                            $valorVenta = (string)($detail['mto_valor_venta'] ?? 0);
                            $sumaTipos = bcadd($sumaTipos, $valorVenta, 2);
                        }
                    }

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $sumaTipos, 2) !== 0) {
                        $fail("El oper_exportacion debe ser {$sumaTipos} (suma de valores de venta con tip_afe_igv = 40).");
                    }
                },
            ],

            'montos.igv' => [
                'nullable',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;

                    $details = request('details') ?? [];
                    $tiposConIgv = ['10'];

                    $sumaIgv = '0';
                    foreach ($details as $detail) {
                        $tipAfe = $detail['tip_afe_igv'] ?? null;
                        if (in_array($tipAfe, $tiposConIgv)) {
                            $igv = (string)($detail['igv'] ?? 0);
                            $sumaIgv = bcadd($sumaIgv, $igv, 2);
                        }
                    }

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $sumaIgv, 2) !== 0) {
                        $fail("El IGV debe ser {$sumaIgv} (suma de IGV de items gravados).");
                    }
                },
            ],

            'montos.igv_gratuitas' => [
                'nullable',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;

                    $details = request('details') ?? [];
                    $tiposGratuitosConIgv = ['11','12','13','14','15','16','17'];

                    $sumaIgvGratuitas = '0';
                    foreach ($details as $detail) {
                        $tipAfe = $detail['tip_afe_igv'] ?? null;
                        if (in_array($tipAfe, $tiposGratuitosConIgv)) {
                            $igv = (string)($detail['igv'] ?? 0);
                            $sumaIgvGratuitas = bcadd($sumaIgvGratuitas, $igv, 2);
                        }
                    }

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $sumaIgvGratuitas, 2) !== 0) {
                        $fail("El igv_gratuitas debe ser {$sumaIgvGratuitas} (suma de IGV de items gratuitos afectos).");
                    }
                },
            ],

            'montos.total_impuestos' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    $details = request('details') ?? [];
                    $tiposConImpuestos = ['10','20','30','40']; // Solo tipos onerosos

                    $sumaTotalImpuestos = '0';
                    foreach ($details as $detail) {
                        $tipAfe = $detail['tip_afe_igv'] ?? null;

                        if (in_array($tipAfe, $tiposConImpuestos)) {
                            $totalImpuestos = (string)($detail['total_impuestos'] ?? 0);
                            $sumaTotalImpuestos = bcadd($sumaTotalImpuestos, $totalImpuestos, 2);
                        }
                    }

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $sumaTotalImpuestos, 2) !== 0) {
                        $fail("El total_impuestos debe ser {$sumaTotalImpuestos} (suma de impuestos de items onerosos).");
                    }
                },
            ],

            'montos.valor_venta' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    $details = request('details') ?? [];
                    $tiposOnerosos = ['10','20','30','40']; // Solo tipos onerosos (no gratuitos)

                    $sumaValorVenta = '0';
                    foreach ($details as $detail) {
                        $tipAfe = $detail['tip_afe_igv'] ?? null;
                        if (in_array($tipAfe, $tiposOnerosos)) {
                            $valorVenta = (string)($detail['mto_valor_venta'] ?? 0);
                            $sumaValorVenta = bcadd($sumaValorVenta, $valorVenta, 2);
                        }
                    }

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $sumaValorVenta, 2) !== 0) {
                        $fail("El valor_venta debe ser {$sumaValorVenta} (suma de valores de venta onerosos: gravadas + exoneradas + inafectas + exportación).");
                    }
                },
            ],

            'montos.sub_total' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    $montos = request('montos') ?? [];
                    $valorVenta = (string)($montos['valor_venta'] ?? 0);
                    $totalImpuestos = (string)($montos['total_impuestos'] ?? 0);

                    $esperado = bcadd($valorVenta, $totalImpuestos, 2);

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $esperado, 2) !== 0) {
                        $fail("El sub_total debe ser {$esperado} (valor_venta + total_impuestos).");
                    }
                },
            ],

            'montos.imp_venta' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    $montos = request('montos') ?? [];
                    $subTotal = (string)($montos['sub_total'] ?? 0);

                    $valorRecibido = (string)$value;
                    if (bccomp($valorRecibido, $subTotal, 2) !== 0) {
                        $fail("El imp_venta debe ser {$subTotal} (igual al sub_total).");
                    }
                },
            ],

            // ---- DETALLES ----
            'details' => 'required|array|min:1',
            'details.*.cod_producto' => 'sometimes|nullable|string|max:55',
            'details.*.unidad' => 'required|string',
            'details.*.descripcion' => 'required|string|min:3|max:500|regex:/^(?!\s*$)[\s\S]{0,}$/',
            'details.*.cantidad'           => 'required|numeric|gt:0|regex:/^\d{1,12}(\.\d{1,10})?$/',
            'details.*.mto_valor_unitario' => [
                'required',
                'numeric',
                'regex:/^\d{1,12}(\.\d{1,10})?$/',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $details = request('details') ?? [];
                    $tipAfe = $details[$index]['tip_afe_igv'] ?? null;

                    if (!$tipAfe) return;

                    $gratuitos = ['11','12','13','14','15','16','17','21','31','32','33','34','35','36','37'];
                    $noGratuitos = ['10','20','30','40'];

                    if (in_array($tipAfe, $gratuitos) && (float)$value !== 0.0) {
                        $fail("El campo mto_valor_unitario debe ser 0 para el tipo de afectación {$tipAfe}.");
                    }

                    if (in_array($tipAfe, $noGratuitos) && (float)$value <= 0) {
                        $fail("El campo mto_valor_unitario debe ser mayor a 0 para el tipo de afectación {$tipAfe}.");
                    }
                },
            ],
            'details.*.mto_valor_venta' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,10})?$/',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $details = request('details') ?? [];

                    $cantidad  = $details[$index]['cantidad'] ?? null;
                    $unitario  = $details[$index]['mto_valor_unitario'] ?? null;
                    $gratuito  = $details[$index]['mto_valor_gratuito'] ?? null;
                    $tipAfe    = $details[$index]['tip_afe_igv'] ?? null;

                    if (!$tipAfe || is_null($cantidad)) return;

                    $gratuitos   = ['11','12','13','14','15','16','17','21','31','32','33','34','35','36','37'];
                    $noGratuitos = ['10','20','30','40'];

                    // Convertir todo a string para bcmath
                    $cantidad = (string)$cantidad;
                    $unitario = $unitario !== null ? (string)$unitario : null;
                    $gratuito = $gratuito !== null ? (string)$gratuito : null;
                    $valor    = (string)$value;

                    // Para gratuitos: mto_valor_venta = cantidad × mto_valor_gratuito
                    if (in_array($tipAfe, $gratuitos)) {
                        if ($gratuito === null) {
                            $fail("El campo mto_valor_gratuito es obligatorio para el tipo de afectación {$tipAfe}.");
                            return;
                        }

                        $esperado = bcmul($cantidad, $gratuito, 10);

                        if (bccomp($valor, $esperado, 10) !== 0) {
                            $fail("El mto_valor_venta debe ser exactamente cantidad × mto_valor_gratuito ({$esperado}) para el tipo de afectación {$tipAfe}.");
                        }
                    }

                    // Para no gratuitos: mto_valor_venta = cantidad × mto_valor_unitario
                    if (in_array($tipAfe, $noGratuitos)) {
                        if ($unitario === null) {
                            $fail("El campo mto_valor_unitario es obligatorio para el tipo de afectación {$tipAfe}.");
                            return;
                        }

                        $esperado = bcmul($cantidad, $unitario, 10);

                        if (bccomp($valor, $esperado, 10) !== 0) {
                            $fail("El mto_valor_venta debe ser exactamente cantidad × mto_valor_unitario ({$esperado}) para el tipo de afectación {$tipAfe}.");
                        }
                    }
                },
            ],
            'details.*.mto_base_igv' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,10})?$/',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $details = request('details') ?? [];

                    $mtoValorVenta = $details[$index]['mto_valor_venta'] ?? null;

                    if ($mtoValorVenta === null) {
                        $fail("El campo mto_valor_venta es requerido para calcular mto_base_igv.");
                        return;
                    }

                    // Convertir ambos valores a string para bcmath
                    $valorBase = (string)$value;
                    $valorVenta = (string)$mtoValorVenta;

                    // Comparar que sean exactamente iguales
                    if (bccomp($valorBase, $valorVenta, 10) !== 0) {
                        $fail("El mto_base_igv debe ser igual al mto_valor_venta ({$valorVenta}).");
                    }
                },
            ],
            'details.*.porcentaje_igv' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $details = request('details') ?? [];
                    $tipAfe = $details[$index]['tip_afe_igv'] ?? null;

                    if (!$tipAfe) return;

                    // Reglas según catálogo
                    if (in_array($tipAfe, ['10','11','12','13','14','15','16','17']) && (float)$value !== 18.00) {
                        $fail("El porcentaje de IGV debe ser 18% para el tipo de afectación {$tipAfe}.");
                    }

                    if (in_array($tipAfe, ['20','21','30','31','32','33','34','35','36','37','40']) && (float)$value != 0) {
                        $fail("El porcentaje de IGV debe ser 0% para el tipo de afectación {$tipAfe}.");
                    }
                },
            ],
            'details.*.igv' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,10})?$/',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $details = request('details') ?? [];

                    $mtoBaseIgv = $details[$index]['mto_base_igv'] ?? null;
                    $porcentajeIgv = $details[$index]['porcentaje_igv'] ?? null;

                    if ($mtoBaseIgv === null || $porcentajeIgv === null) {
                        return;
                    }

                    // Convertir todo a string para bcmath
                    $baseIgv = (string)$mtoBaseIgv;
                    $porcentaje = (string)$porcentajeIgv;
                    $igvValue = (string)$value;

                    // Calcular IGV: (mto_base_igv * porcentaje_igv) / 100
                    // Ejemplo: (200 * 18) / 100 = 3600 / 100 = 36
                    $multiplicacion = bcmul($baseIgv, $porcentaje, 10);
                    $esperado = bcdiv($multiplicacion, '100', 10);

                    // Comparar con precisión de 10 decimales
                    if (bccomp($igvValue, $esperado, 10) !== 0) {
                        $fail("El IGV debe ser exactamente {$esperado}. Cálculo: (mto_base_igv * porcentaje_igv) ÷ 100.");
                    }
                },
            ],
            'details.*.tip_afe_igv' => [
                'required',
                'string',
                'size:2', // Solo acepta exactamente 2 caracteres
                function ($attribute, $value, $fail) {
                    $codigosValidos = [
                        // No gratuitos
                        '10', '20', '30', '40',
                        // Gratuitos
                        '11','12','13','14','15','16','17',
                        '21',
                        '31','32','33','34','35','36','37'
                    ];

                    if (!in_array($value, $codigosValidos)) {
                        $fail("El tipo de afectación {$value} no es válido. Códigos permitidos: " . implode(', ', $codigosValidos));
                        return;
                    }

                    $index = explode('.', $attribute)[1] ?? null;
                    $details = request('details') ?? [];
                    $montos = request('montos') ?? [];

                    $gratuitos = ['11','12','13','14','15','16','17','21','31','32','33','34','35','36','37'];
                    $noGratuitos = ['10','20','30','40'];

                    $mtoValorGratuito = $details[$index]['mto_valor_gratuito'] ?? null;

                    // Caso gratuito → debe existir y ser numérico
                    if (in_array($value, $gratuitos)) {
                        if (!array_key_exists('mto_valor_gratuito', $details[$index])) {
                            $fail("El campo mto_valor_gratuito es obligatorio cuando tip_afe_igv es {$value}.");
                        } elseif (is_null($mtoValorGratuito) || $mtoValorGratuito === '') {
                            $fail("El campo mto_valor_gratuito no puede estar vacío para el tipo de afectación {$value}.");
                        } elseif (!is_numeric($mtoValorGratuito)) {
                            $fail("El campo mto_valor_gratuito debe ser numérico para el tipo de afectación {$value}.");
                        }
                    }

                    // Caso no gratuito → no debe enviarse
                    if (in_array($value, $noGratuitos)) {
                        if (!is_null($mtoValorGratuito) && $mtoValorGratuito !== '') {
                            $fail("El campo mto_valor_gratuito no debe enviarse para el tipo de afectación {$value}.");
                        }
                    }

                    // **NUEVAS VALIDACIONES**: Verificar que existan los campos de montos correspondientes

                    // Si hay gravadas (10), debe existir oper_gravadas e igv
                    if ($value === '10') {
                        if (!isset($montos['oper_gravadas']) || $montos['oper_gravadas'] === null || $montos['oper_gravadas'] === '') {
                            $fail("El campo montos.oper_gravadas es obligatorio cuando existe un item con tip_afe_igv = 10.");
                        }
                        if (!isset($montos['igv']) || $montos['igv'] === null || $montos['igv'] === '') {
                            $fail("El campo montos.igv es obligatorio cuando existe un item con tip_afe_igv = 10.");
                        }
                    }

                    // Si hay exoneradas (20), debe existir oper_exoneradas
                    if ($value === '20') {
                        if (!isset($montos['oper_exoneradas']) || $montos['oper_exoneradas'] === null || $montos['oper_exoneradas'] === '') {
                            $fail("El campo montos.oper_exoneradas es obligatorio cuando existe un item con tip_afe_igv = 20.");
                        }
                    }

                    // Si hay inafectas (30), debe existir oper_inafectas
                    if ($value === '30') {
                        if (!isset($montos['oper_inafectas']) || $montos['oper_inafectas'] === null || $montos['oper_inafectas'] === '') {
                            $fail("El campo montos.oper_inafectas es obligatorio cuando existe un item con tip_afe_igv = 30.");
                        }
                    }

                    // Si hay exportación (40), debe existir oper_exportacion
                    if ($value === '40') {
                        if (!isset($montos['oper_exportacion']) || $montos['oper_exportacion'] === null || $montos['oper_exportacion'] === '') {
                            $fail("El campo montos.oper_exportacion es obligatorio cuando existe un item con tip_afe_igv = 40.");
                        }
                    }

                    // Si hay gratuitos, debe existir oper_gratuitas
                    if (in_array($value, $gratuitos)) {
                        if (!isset($montos['oper_gratuitas']) || $montos['oper_gratuitas'] === null || $montos['oper_gratuitas'] === '') {
                            $fail("El campo montos.oper_gratuitas es obligatorio cuando existe un item gratuito con tip_afe_igv = {$value}.");
                        }
                    }

                    // Si hay gratuitos afectos a IGV (11,12,13,14,15,16,17), debe existir igv_gratuitas
                    $gratuitosConIgv = ['11','12','13','14','15','16','17'];
                    if (in_array($value, $gratuitosConIgv)) {
                        if (!isset($montos['igv_gratuitas']) || $montos['igv_gratuitas'] === null || $montos['igv_gratuitas'] === '') {
                            $fail("El campo montos.igv_gratuitas es obligatorio cuando existe un item gratuito afecto a IGV con tip_afe_igv = {$value}.");
                        }
                    }
                },
            ],
            // Validación para total_impuestos (suma de IGV)
            'details.*.total_impuestos' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,10})?$/',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $details = request('details') ?? [];

                    $igv = $details[$index]['igv'] ?? null;
                    $factorIcbper = $details[$index]['factor_icbper'] ?? null;
                    $icbper = $details[$index]['icbper'] ?? null;

                    // --- Validaciones cruzadas ---
                    if ($factorIcbper && $icbper === null) {
                        return $fail("Si envías factor_icbper también debes enviar icbper.");
                    }

                    if ($icbper && $factorIcbper === null) {
                        return $fail("Si envías icbper también debes enviar factor_icbper.");
                    }

                    // --- Calcular el total esperado ---
                    $esperado = '0';

                    if ($igv !== null) {
                        $esperado = bcadd($esperado, (string)$igv, 10);
                    }

                    if ($icbper !== null && $factorIcbper !== null) {
                        $esperado = bcadd($esperado, (string)$icbper, 10);
                    }

                    $totalImpuestosValue = (string)$value;

                    // Comparar con precisión de 10 decimales
                    if (bccomp($totalImpuestosValue, $esperado, 10) !== 0) {
                        $fail("El total_impuestos debe ser igual al ({$esperado}).");
                    }
                },
            ],
            // Validación para mto_precio_unitario
            'details.*.mto_precio_unitario' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,10})?$/',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1] ?? null;
                    $details = request('details') ?? [];

                    $valorVenta = $details[$index]['mto_valor_venta'] ?? null;
                    $igv = $details[$index]['igv'] ?? null;
                    $cantidad = $details[$index]['cantidad'] ?? null;
                    $tipAfe = $details[$index]['tip_afe_igv'] ?? null;

                    if ($valorVenta === null || $igv === null || $cantidad === null || $tipAfe === null) {
                        return;
                    }

                    // Tipos de afectación gratuitos/inafectos
                    $tiposGratuitos = ['11','12','13','14','15','16','17','21','31','32','33','34','35','36','37'];

                    // Convertir a string para bcmath
                    $precioUnitarioValue = (string)$value;

                    // Si es tipo gratuito, el precio unitario debe ser 0
                    if (in_array($tipAfe, $tiposGratuitos)) {
                        if (bccomp($precioUnitarioValue, '0', 10) !== 0) {
                            $fail("El mto_precio_unitario debe ser 0 para el tipo de afectación {$tipAfe} (gratuito/inafecto).");
                        }
                    } else {
                        // Si no es gratuito, calcular: (ValorVenta + TotalImpuestos) / Cantidad
                        $valorVentaStr = (string)$valorVenta;
                        $igvStr = (string)$igv;
                        $cantidadStr = (string)$cantidad;

                        $suma = bcadd($valorVentaStr, $igvStr, 10);
                        $esperado = bcdiv($suma, $cantidadStr, 10);

                        // Comparar con precisión de 10 decimales
                        if (bccomp($precioUnitarioValue, $esperado, 10) !== 0) {
                            $fail("El mto_precio_unitario debe ser exactamente {$esperado}. Cálculo: (mto_valor_venta + total_impuestos) ÷ cantidad.");
                        }
                    }
                },
            ],
            'details.*.mto_valor_gratuito' => [
                'nullable',
                'numeric',
                'gt:0',
                'regex:/^\d{1,12}(\.\d{1,10})?$/'
            ],
            'details.*.icbper' => [
                'nullable',
                'numeric',
                'gt:0',
                'regex:/^\d{1,2}(\.\d{1,2})?$/'
            ],
            'details.*.factor_icbper' => [
                'nullable',
                'numeric',
                'gt:0',
                'regex:/^\d{1,2}(\.\d{1,2})?$/'
            ],

            // ---- OBSERVACIONES ----
            'observations' => 'nullable|array',
            'observations.*' => 'string|max:255',

            // ---- LEGENDS ----
            'legends' => 'required|array|min:1',
            'legends.*.code' => 'required|string|regex:/^\d{4}$/',
            'legends.*.value' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            // ---- CLIENTE ----
            'client.tipo_doc.required' => 'El tipo de documento del cliente es obligatorio.',
            'client.tipo_doc.in' => 'El tipo de documento del cliente no es válido según el catálogo 06 de SUNAT.',
            'client.num_doc.required' => 'El número de documento del cliente es obligatorio.',
            'client.razon_social.required' => 'La razón social o nombre completo del cliente es obligatoria.',
            'client.razon_social.string' => 'La razón social o nombre completo del cliente debe ser texto.',
            'client.razon_social.max' => 'La razón social del cliente no debe exceder los 150 caracteres.',
            'client.direccion.required' => 'La dirección del cliente es obligatoria para facturas.',
            'client.direccion.string' => 'La dirección del cliente debe ser un texto válido.',
            'client.direccion.max' => 'La dirección del cliente no debe exceder los 200 caracteres.',

            // ---- EMPRESA ----
            'company.ruc.required' => 'El RUC de la empresa es obligatorio.',
            'company.ruc.string' => 'El RUC de la empresa debe ser texto.',
            'company.ruc.digits' => 'El RUC de la empresa debe tener exactamente 11 dígitos.',
            'company.razon_social.required' => 'La razón social de la empresa es obligatoria.',
            'company.razon_social.string' => 'La razón social de la empresa debe ser texto.',
            'company.razon_social.max' => 'La razón social de la empresa no debe exceder los 255 caracteres.',
            'company.nombre_comercial.string' => 'El nombre comercial de la empresa debe ser texto.',
            'company.nombre_comercial.max' => 'El nombre comercial de la empresa no debe exceder los 255 caracteres.',
            'company.address.ubigeo.required' => 'El ubigeo de la dirección de la empresa es obligatorio.',
            'company.address.ubigeo.string' => 'El ubigeo de la dirección de la empresa debe ser texto.',
            'company.address.ubigeo.size' => 'El ubigeo de la dirección debe tener exactamente 6 caracteres.',
            'company.address.departamento.required' => 'El departamento de la empresa es obligatorio.',
            'company.address.departamento.string' => 'El departamento de la empresa debe ser texto.',
            'company.address.departamento.max' => 'El departamento de la empresa no debe exceder los 100 caracteres.',
            'company.address.provincia.required' => 'La provincia de la empresa es obligatoria.',
            'company.address.provincia.string' => 'La provincia de la empresa debe ser texto.',
            'company.address.provincia.max' => 'La provincia de la empresa no debe exceder los 100 caracteres.',
            'company.address.distrito.required' => 'El distrito de la empresa es obligatorio.',
            'company.address.distrito.string' => 'El distrito de la empresa debe ser texto.',
            'company.address.distrito.max' => 'El distrito de la empresa no debe exceder los 100 caracteres.',
            'company.address.urbanizacion.max' => 'La urbanización de la empresa no debe exceder los 100 caracteres.',
            'company.address.urbanizacion.string' => 'La urbanización de la empresa debe ser texto.',
            'company.address.direccion.required' => 'La dirección de la empresa es obligatoria.',
            'company.address.direccion.string' => 'La dirección de la empresa debe ser texto.',
            'company.address.direccion.max' => 'La dirección de la empresa no debe exceder los 255 caracteres.',
            'company.address.cod_local.required' => 'El código de local de la empresa es obligatorio.',
            'company.address.cod_local.string' => 'El código de local de la empresa debe ser texto.',
            'company.address.cod_local.max' => 'El código de local no debe exceder los 25 caracteres.',

            // ---- CABECERA ----
            'ubl_version.required' => 'La versión UBL es obligatoria.',
            'ubl_version.string' => 'La versión UBL debe ser texto.',
            'ubl_version.in' => 'La versión UBL debe ser 2.1.',
            'fecha_vencimiento.date_format' => 'La fecha de vencimiento debe estar en formato válido (YYYY-MM-DDTHH:mm:ss.sssZ).',
            'tipo_operacion.required' => 'El tipo de operación es obligatorio.',
            'tipo_operacion.string' => 'El tipo de operación debe ser texto.',
            'tipo_operacion.max' => 'El tipo de operación no debe exceder 4 caracteres.',
            'tipo_doc.required' => 'El tipo de comprobante es obligatorio.',
            'tipo_doc.in' => 'El tipo de comprobante debe ser Factura (01), Boleta (03), Nota de Crédito (07) o Nota de Débito (08).',
            'serie.required' => 'La serie del comprobante es obligatoria.',
            'serie.string' => 'La serie del comprobante debe ser texto.',
            'serie.max' => 'La serie no debe exceder 4 caracteres.',
            'correlativo.required' => 'El correlativo es obligatorio.',
            'correlativo.string' => 'El correlativo debe ser texto.',
            'correlativo.max' => 'El correlativo no debe exceder 8 caracteres.',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
            'fecha_emision.date_format' => 'La fecha de emisión debe estar en formato válido (YYYY-MM-DDTHH:mm:ss.sssZ).',
            'forma_pago.required' => 'La forma de pago es obligatoria.',
            'forma_pago.string' => 'La forma de pago debe ser texto.',
            'forma_pago.in' => 'La forma de pago debe ser Contado o Crédito.',
            'tipo_moneda.required' => 'El tipo de moneda es obligatorio.',
            'tipo_moneda.string' => 'El tipo de moneda debe ser texto.',
            'tipo_moneda.size' => 'El tipo de moneda debe tener exactamente 3 caracteres (ejemplo: PEN, USD).',

            // ---- MONTOS ----
            'montos.*.regex' => 'Cada monto debe tener hasta 12 enteros y 2 decimales.',
            'montos.total_impuestos.required' => 'El total de impuestos es obligatorio.',
            'montos.valor_venta.required' => 'El valor de venta es obligatorio.',
            'montos.sub_total.required' => 'El subtotal es obligatorio.',
            'montos.imp_venta.required' => 'El importe de venta es obligatorio.',

            // ---- DETALLES ----
            'details.required' => 'Debe ingresar al menos un detalle.',
            'details.array' => 'Los detalles deben estar en un arreglo.',
            'details.*.cod_producto.string' => 'El código del producto debe ser texto.',
            'details.*.cod_producto.max' => 'El código del producto no debe exceder los 55 caracteres.',
            'details.*.unidad.required' => 'La unidad de medida es obligatoria.',
            'details.*.unidad.string' => 'La unidad de medida debe ser texto.',
            'details.*.descripcion.required' => 'La descripción es obligatoria.',
            'details.*.descripcion.string' => 'La descripción debe ser texto.',
            'details.*.descripcion.min' => 'La descripción debe tener al menos 3 caracteres.',
            'details.*.descripcion.max' => 'La descripción no debe exceder 500 caracteres.',
            'details.*.descripcion.regex' => 'La descripción no puede estar vacía o solo con espacios.',
            'details.*.cantidad.required' => 'La cantidad es obligatoria.',
            'details.*.cantidad.numeric' => 'La cantidad debe ser un valor numérico.',
            'details.*.cantidad.gt' => 'La cantidad debe ser mayor a 0.',
            'details.*.cantidad.regex' => 'La cantidad debe tener hasta 12 enteros y 10 decimales.',
            'details.*.mto_valor_unitario.required' => 'El valor unitario es obligatorio.',
            'details.*.mto_valor_unitario.numeric' => 'El valor unitario debe ser un valor numérico.',
            'details.*.mto_valor_unitario.gt' => 'El valor unitario debe ser mayor a 0.',
            'details.*.mto_valor_unitario.regex' => 'El valor unitario debe tener hasta 12 enteros y 10 decimales.',
            'details.*.mto_valor_venta.required' => 'El valor de venta es obligatorio.',
            'details.*.mto_valor_venta.numeric' => 'El valor de venta debe ser un valor numérico.',
            'details.*.mto_valor_venta.gt' => 'El valor de venta debe ser mayor a 0.',
            'details.*.mto_valor_venta.regex' => 'El valor de venta debe tener hasta 12 enteros y 10 decimales.',
            'details.*.mto_base_igv.required' => 'La base imponible del IGV es obligatoria.',
            'details.*.mto_base_igv.numeric' => 'La base imponible del IGV debe ser un valor numérico.',
            'details.*.mto_base_igv.gt' => 'La base imponible del IGV debe ser mayor a 0.',
            'details.*.mto_base_igv.regex' => 'La base imponible del IGV debe tener hasta 12 enteros y 10 decimales.',
            'details.*.porcentaje_igv.required' => 'El porcentaje de IGV es obligatorio.',
            'details.*.porcentaje_igv.numeric' => 'El porcentaje de IGV debe ser un valor numérico.',
            'details.*.porcentaje_igv.gt' => 'El porcentaje de IGV debe ser mayor a 0.',
            'details.*.porcentaje_igv.regex' => 'El porcentaje de IGV debe tener hasta 3 enteros y 5 decimales.',
            'details.*.igv.required' => 'El IGV es obligatorio.',
            'details.*.igv.numeric' => 'El IGV debe ser un valor numérico.',
            'details.*.igv.gt' => 'El IGV debe ser mayor a 0.',
            'details.*.igv.regex' => 'El IGV debe tener hasta 12 enteros y 10 decimales.',
            'details.*.tip_afe_igv.required' => 'El tipo de afectación IGV es obligatorio.',
            'details.*.tip_afe_igv.string' => 'El tipo de afectación IGV debe ser un texto.',
            'details.*.tip_afe_igv.in' => 'El tipo de afectación IGV no es válido según el catálogo 07 de SUNAT.',
            'details.*.total_impuestos.required' => 'El total de impuestos es obligatorio.',
            'details.*.total_impuestos.numeric' => 'El total de impuestos debe ser un valor numérico.',
            'details.*.total_impuestos.gt' => 'El total de impuestos debe ser mayor a 0.',
            'details.*.total_impuestos.regex' => 'El total de impuestos debe tener hasta 12 enteros y 10 decimales.',
            'details.*.mto_precio_unitario.required' => 'El precio unitario es obligatorio.',
            'details.*.mto_precio_unitario.numeric' => 'El precio unitario debe ser un valor numérico.',
            'details.*.mto_precio_unitario.gt' => 'El precio unitario debe ser mayor a 0.',
            'details.*.mto_precio_unitario.regex' => 'El precio unitario debe tener hasta 12 enteros y 10 decimales.',
            'details.*.mto_valor_gratuito.numeric' => 'El valor gratuito debe ser un valor numérico.',
            'details.*.mto_valor_gratuito.gt' => 'El valor gratuito debe ser mayor a 0.',
            'details.*.mto_valor_gratuito.regex' => 'El valor gratuito debe tener hasta 12 enteros y 10 decimales.',

            // ---- OBSERVACIONES ----
            'observations.array' => 'Las observaciones deben estar en un arreglo.',
            'observations.*.string' => 'Cada observación debe ser un texto.',
            'observations.*.max' => 'Cada observación no debe exceder 255 caracteres.',

            // ---- LEGENDS ----
            'legends.required' => 'Debe ingresar al menos una leyenda.',
            'legends.array' => 'Las leyendas deben estar en un arreglo.',
            'legends.*.code.required' => 'El código de la leyenda es obligatorio.',
            'legends.*.code.string' => 'El código de la leyenda debe ser un texto.',
            'legends.*.code.regex' => 'El código de la leyenda debe tener exactamente 4 dígitos.',
            'legends.*.value.required' => 'El valor de la leyenda es obligatorio.',
            'legends.*.value.string' => 'El valor de la leyenda debe ser un texto.',
            'legends.*.value.max' => 'El valor de la leyenda no debe exceder 255 caracteres.',
        ];
    }

    /**
     * @throws Exception
     */
    public function toDTO(): InvoiceData
    {
        return new InvoiceData(
            client: new ClientData(...$this->input('client')),
            company: new CompanyData(
                ...array_merge(
                    $this->input('company'),
                    ['address' => new AddressData(...$this->input('company.address'))]
                )
            ),
            ubl_version: $this->input('ubl_version'),
            fecha_vencimiento: new DateTime($this->input('fecha_vencimiento')),
            tipo_operacion: $this->input('tipo_operacion'),
            tipo_doc: $this->input('tipo_doc'),
            serie: $this->input('serie'),
            correlativo: $this->input('correlativo'),
            fecha_emision: new DateTime($this->input('fecha_emision')),
            forma_pago: $this->input('forma_pago'),
            tipo_moneda: $this->input('tipo_moneda'),
            montos: new InvoiceMontosData(
                oper_gravadas: $this->input('montos.oper_gravadas'),
                oper_exoneradas: $this->input('montos.oper_exoneradas'),
                oper_inafectas: $this->input('montos.oper_inafectas'),
                oper_gratuitas: $this->input('montos.oper_gratuitas'),
                oper_exportacion: $this->input('montos.oper_exportacion'),
                igv: $this->input('montos.igv'),
                igv_gratuitas: $this->input('montos.igv_gratuitas'),
                total_impuestos: $this->input('montos.total_impuestos'),
                valor_venta: $this->input('montos.valor_venta'),
                sub_total: $this->input('montos.sub_total'),
                imp_venta: $this->input('montos.imp_venta'),
            ),
            details: array_map(
                fn(array $detail) => new InvoiceDetailData(
                    cod_producto: $detail['cod_producto'] ?? null,
                    unidad: $detail['unidad'],
                    descripcion: $detail['descripcion'],
                    cantidad: (float)$detail['cantidad'],
                    mto_valor_unitario: (float)$detail['mto_valor_unitario'],
                    mto_valor_venta: (float)$detail['mto_valor_venta'],
                    mto_base_igv: (float)$detail['mto_base_igv'],
                    porcentaje_igv: (float)$detail['porcentaje_igv'],
                    igv: (float)$detail['igv'],
                    tip_afe_igv: $detail['tip_afe_igv'],
                    total_impuestos: (float)$detail['total_impuestos'],
                    mto_precio_unitario: (float)$detail['mto_precio_unitario'],
                    mto_valor_gratuito: $detail['mto_valor_gratuito'] ?? null,
                    icbper: $detail['icbper'] ?? null,
                    factor_icbper: $detail['factor_icbper'] ?? null,
                ),
                $this->input('details', [])
            ),
            observations: $this->input('observations')
                ? array_map(fn($obs) => (string)$obs, $this->input('observations', []))
                : null,
            legends: array_map(
                fn(array $legend) => new LegendData(
                    code: $legend['code'],
                    value: $legend['value'],
                ),
                $this->input('legends', [])
            ),
        );
    }
}
