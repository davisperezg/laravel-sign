<?php

namespace App\Http\Controllers;

use App\DTOs\InvoiceDTO;
use Exception;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\Observation;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Xml\Builder\InvoiceBuilder;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use Illuminate\Http\Request;

class InvoiceController extends Controller {

    /**
     * @throws Exception
     */
    public function createXML(Request $request) {
        $dto = InvoiceDTO::fromRequest($request->json()->all());

        // Client
        $client = (new Client())
                ->setTipoDoc($dto->client->tipoDoc)
                ->setNumDoc($dto->client->numDoc)
                ->setRznSocial($dto->client->razonSocial)
                ->setAddress((new Address())->setDireccion($dto->client->direccion));

        // Remittent
        $address = (new Address())
                ->setUbigueo($dto->company->address->ubigeo)
                ->setDepartamento($dto->company->address->departamento)
                ->setProvincia($dto->company->address->provincia)
                ->setDistrito($dto->company->address->distrito)
                ->setUrbanizacion($dto->company->address->urbanizacion)
                ->setDireccion($dto->company->address->direccion)
                ->setCodLocal($dto->company->address->codLocal); // Establishment code assigned by SUNAT, 0000 by default.


        $company = (new Company())
                ->setRuc($dto->company->ruc)
                ->setRazonSocial($dto->company->razonSocial)
                ->setNombreComercial($dto->company->nombreComercial)
                ->setAddress($address);

        $invoice = new Invoice();
        $invoice
            ->setUblVersion($dto->invoice->ublVersion)
            ->setFecVencimiento($dto->invoice->fechaVencimiento)
            ->setTipoOperacion($dto->invoice->tipoOperacion)
            ->setTipoDoc($dto->invoice->tipoDoc)
            ->setSerie($dto->invoice->serie)
            ->setCorrelativo($dto->invoice->correlativo)
            ->setFechaEmision($dto->invoice->fechaEmision)
            ->setFormaPago(new FormaPagoContado())
            ->setTipoMoneda($dto->invoice->tipoMoneda)
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($dto->invoice->montos->operGravadas)
            ->setMtoOperExoneradas($dto->invoice->montos->operExoneradas)
            ->setMtoOperInafectas($dto->invoice->montos->operInafectas)
            ->setMtoOperGratuitas($dto->invoice->montos->operGratuitas)
            ->setMtoOperExportacion($dto->invoice->montos->operExportacion)
            ->setMtoIGV($dto->invoice->montos->igv)
            ->setMtoIGVGratuitas($dto->invoice->montos->igvGratuitas)
            ->setTotalImpuestos($dto->invoice->montos->totalImpuestos) // IGV + ISC + OTH
            ->setValorVenta($dto->invoice->montos->valorVenta)
            ->setSubTotal($dto->invoice->montos->subTotal)
            ->setMtoImpVenta($dto->invoice->montos->impVenta);

        // Create details
        $details = [];
        //var_dump($dto->invoice->details);
        foreach ($dto->invoice->details as $detailData) {
            $detail = new SaleDetail();
            $detail
                ->setCodProducto($detailData->codProducto)
                ->setUnidad($detailData->unidad)
                ->setDescripcion($detailData->descripcion)
                ->setCantidad($detailData->cantidad)
                ->setMtoValorUnitario($detailData->mtoValorUnitario)
                ->setMtoValorVenta($detailData->mtoValorVenta)
                ->setMtoBaseIgv($detailData->mtoBaseIgv)
                ->setPorcentajeIgv($detailData->porcentajeIgv)
                ->setIgv($detailData->igv)
                ->setTipAfeIgv($detailData->tipAfeIgv)
                ->setTotalImpuestos($detailData->totalImpuestos)
                ->setMtoPrecioUnitario($detailData->mtoPrecioUnitario);

            if ($detailData->mtoValorGratuito !== null) {
                $detail->setMtoValorGratuito($detailData->mtoValorGratuito);
            }

            if ($detailData->codProductoSunat !== null) {
                $detail->setCodProdSunat($detailData->codProductoSunat);
            }

            $details[] = $detail;
        }
        $invoice->setDetails($details);

        $observations = [];
        foreach ($dto->invoice->observations as $observation) {
            $item = new Observation();
            $item->setValue($observation->observation);
            $observations[] = $item;
        }
        $invoice->setObservations($observations);

        $legends = [];
        foreach ($dto->invoice->legends as $legend) {
            $item = new Legend();
            $item->setCode($legend->code);
            $item->setValue($legend->value);
            $legends[] = $item;
        }
        $invoice->setLegends($legends);

        $builder = new InvoiceBuilder();
        $xml = $builder->build($invoice);

        return response([
            "xml" => $xml,
            "filename" => $invoice->getName(),
            "fileNameExtension" => $invoice->getName().'.xml',
        ], 200);
    }

    public function signXML(Request $request) {
        //Body inputs
        $xmlInput = $request->xml;
        $certificadoInput = $request->certificado;

        //Firmamos xml con el certificado
        $signer = new SignedXml();
        $signer->setCertificateFromFile($certificadoInput);
        $xmlSigned = $signer->signFromFile($xmlInput);

        return response($xmlSigned, 200, [
            'Content-Type' => 'Content-Type: text/xml; charset=ISO-8859-1'
        ]);
    }
}
