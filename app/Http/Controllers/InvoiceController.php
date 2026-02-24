<?php

namespace App\Http\Controllers;

use App\DTOs\Client\ClientData;
use App\DTOs\InvoiceDTO;
use App\Http\Requests\InvoiceDetailRequest;
use App\Http\Requests\InvoiceRequest;
use Carbon\Carbon;
use Exception;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Report\XmlUtils;
use Greenter\Ws\Services\BillSender;
use Greenter\Ws\Services\SoapClient;
use Greenter\Xml\Builder\InvoiceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{

    /**
     * @throws Exception
     */
    public function createXml(InvoiceRequest $request):JsonResponse
    {
        $dto = $request->toDTO();
        //var_dump($data->client->toArray());
//        return response()->json([
//            'message' => 'Cliente validado correctamente',
//            'data' => $dto,
//        ]);

        // Client
        $client = (new Client())
            ->setTipoDoc($dto->client->tipo_doc)
            ->setNumDoc($dto->client->num_doc)
            ->setRznSocial($dto->client->razon_social)
            ->setAddress((new Address())->setDireccion($dto->client->direccion));

        // Remittent
        $address = (new Address())
            ->setUbigueo($dto->company->address->ubigeo)
            ->setDepartamento($dto->company->address->departamento)
            ->setProvincia($dto->company->address->provincia)
            ->setDistrito($dto->company->address->distrito)
            ->setUrbanizacion($dto->company->address->urbanizacion)
            ->setDireccion($dto->company->address->direccion)
            ->setCodLocal($dto->company->address->cod_local); // Establishment code assigned by SUNAT, 0000 by default.


        $company = (new Company())
            ->setRuc($dto->company->ruc)
            ->setRazonSocial($dto->company->razon_social)
            ->setNombreComercial($dto->company->nombre_comercial)
            ->setAddress($address);

        $invoice = new Invoice();
        $invoice
            ->setUblVersion($dto->ubl_version)
            ->setFecVencimiento($dto->fecha_vencimiento)
            ->setTipoOperacion($dto->tipo_operacion)
            ->setTipoDoc($dto->tipo_doc)
            ->setSerie($dto->serie)
            ->setCorrelativo($dto->correlativo)
            ->setFechaEmision($dto->fecha_emision)
            ->setFormaPago(new FormaPagoContado())
            ->setTipoMoneda($dto->tipo_moneda)
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($dto->montos->oper_gravadas)
            ->setMtoOperExoneradas($dto->montos->oper_exoneradas)
            ->setMtoOperInafectas($dto->montos->oper_inafectas)
            ->setMtoOperGratuitas($dto->montos->oper_gratuitas)
            ->setMtoOperExportacion($dto->montos->oper_exportacion)
            ->setMtoIGV($dto->montos->igv)
            ->setMtoIGVGratuitas($dto->montos->igv_gratuitas)
            ->setTotalImpuestos($dto->montos->total_impuestos) // IGV + ISC + OTH
            ->setValorVenta($dto->montos->valor_venta)
            ->setSubTotal($dto->montos->sub_total)
            ->setMtoImpVenta($dto->montos->imp_venta);

        // Create details
        $details = [];
        //var_dump($dto->invoice->details);
        foreach ($dto->details as $detailData) {
            $detail = new SaleDetail();
            $detail
                ->setCodProducto($detailData->cod_producto)
                ->setUnidad($detailData->unidad)
                ->setDescripcion($detailData->descripcion)
                ->setCantidad($detailData->cantidad)
                ->setMtoValorUnitario($detailData->mto_valor_unitario)
                ->setMtoValorVenta($detailData->mto_valor_venta)
                ->setMtoBaseIgv($detailData->mto_base_igv)
                ->setPorcentajeIgv($detailData->porcentaje_igv)
                ->setIgv($detailData->igv)
                ->setTipAfeIgv($detailData->tip_afe_igv)
                ->setTotalImpuestos($detailData->total_impuestos)
                ->setMtoPrecioUnitario($detailData->mto_precio_unitario);

            if ($detailData->mto_valor_gratuito !== null) {
                $detail->setMtoValorGratuito($detailData->mto_valor_gratuito);
            }

//            if ($detailData->codProductoSunat !== null) {
//                $detail->setCodProdSunat($detailData->codProductoSunat);
//            }

            if ($detailData->factor_icbper!== null) {
                $detail->setFactorIcbper($detailData->factor_icbper);
            }

            if ($detailData->icbper!== null) {
                $detail->setIcbper($detailData->icbper);
            }

            $details[] = $detail;
        }
        $invoice->setDetails($details);

        if($dto->observations !== null) {
            foreach ($dto->observations as $observation) {
                $invoice->setObservacion($observation);
            }
        }

        $legends = [];
        foreach ($dto->legends as $legend) {
            $item = new Legend();
            $item->setCode($legend->code);
            $item->setValue($legend->value);
            $legends[] = $item;
        }
        $invoice->setLegends($legends);

        $builder = new InvoiceBuilder();
        $xml = $builder->build($invoice);

        return response()->json([
            "fileName" => $invoice->getName(),
            "extension" => ".xml",
            "xmlBase64" => base64_encode($xml),
            "companyRuc" => $company->getRuc(),
            "documentType" => $invoice->getTipoDoc(),
            "generatedAt" => Carbon::now('America/Lima')->toIso8601String(),
        ], 200);
    }

    public function sendXml(Request $request)
    {
        //Body inputs
        $urlService = $request->urlService;
        $usuario = $request->usuario;
        $contrasenia = $request->contrasenia;
        $fileName = $request->fileName;
        $contentFile = $request->contentFile;

        $soap = new SoapClient();
        $soap->setService($urlService);
        $soap->setCredentials($usuario, $contrasenia);
        $sender = new BillSender();
        $sender->setClient($soap);

        /**@var $result BillResult*/
        $result = $sender->send($fileName, base64_decode($contentFile));

        if (!$result->isSuccess()) {
            // Error en la conexion con el servicio de SUNAT
            return response()->json([
                "success" => false,
                "sunat_code_int" => (int)$result->getError()->getCode(),
                "sunat_code" => $result->getError()->getCode(),
                "estado_sunat" => null,
                "mensaje_sunat" => $result->getError()->getMessage(),
                "cdrZip" => null,
                "observaciones_sunat" => null
            ], 500);
        }

        $cdr = $result->getCdrResponse();

        // Verificar CDR (Factura aceptada o rechazada)
        $code = (int)$cdr->getCode();

        $status = 'ERROR_EXCEPCION';
        $cdrZip = null;
        $notes= null;

        if ($code === 0) {
            $status = 'ACEPTADO';
            $cdrZip = base64_encode($result->getCdrZip());
            $notes = $cdr->getNotes();
        } elseif ($code >= 2000 && $code <= 3999) {
            $status = 'RECHAZADO';
            $cdrZip = base64_encode($result->getCdrZip());
            $notes = $cdr->getNotes();
        } elseif ($code >= 1000 && $code <= 1999) {
            $status = 'ERROR_CONTRIBUYENTE';
            // cdrZip y observaciones se quedan null
        }

        return response()->json([
            "success" => true,
            "sunat_code_int" => $code,
            "sunat_code" => $cdr->getCode(),
            "estado_sunat" => $status,
            "mensaje_sunat" => $cdr->getDescription(),
            "cdrZip" => $cdrZip,
            "observaciones_sunat" => $notes,
        ], 200);
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'content' => ['required', 'string'],
        ];
    }
}
