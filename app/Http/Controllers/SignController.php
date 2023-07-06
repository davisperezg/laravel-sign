<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Greenter\XMLSecLibs\Sunat\SignedXml;
//use SoapClient;
use SoapHeader;
use SoapVar;
use Greenter\XMLSecLibs\Certificate\X509Certificate;
use Greenter\XMLSecLibs\Certificate\X509ContentType;
use PclZip;
use Greenter\Ws\Services\SoapClient;
use Greenter\Ws\Services\BillSender;

class SignController extends Controller {

    public function sendSunat(Request $request) {
        //Body request
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

        $xml = file_get_contents('C:/Users/keiner/Proyectos/factv2/backend-adm-rpum/20123456789-01-F001-1.xml');
        $result = $sender->send($fileName, $xml);

        if (!$result->isSuccess()) {
            // Error en la conexion con el servicio de SUNAT
            var_dump($result->getError());
            return;
            ////return response()->json([
            //    "result" => print_r($result->getError())
            //]);
        }

        $cdr = $result->getCdrResponse();
        //file_put_contents('R-'.$fileName.'.zip', $result->getCdrZip());
        
        // Verificar CDR (Factura aceptada o rechazada)
        $res_code = (int) $cdr->getCode();
        $res_estado = "";
        $res_obs = ["Esta es mi obs perzonalidad", "Que abuso on"];
        if ($res_code === 0) {
            $res_estado = 'ACEPTADA';
            //echo 'ESTADO: ACEPTADA' . PHP_EOL;
            if (count($cdr->getNotes()) > 0) {
                $res_estado = "ACEPTADA PERO INCLUYE OBSERVACIONES";
                //echo 'INCLUYE OBSERVACIONES:' . PHP_EOL;
                // Mostrar observaciones
                foreach ($cdr->getNotes() as $obs) {
                    array_push($res_obs, $obs);
                    //echo 'OBS: ' . $obs . PHP_EOL;
                }
            }
        } else if ($res_code >= 2000 && $res_code <= 3999) {
            $res_estado = 'RECHAZADA';
            echo 'ESTADO: RECHAZADA' . PHP_EOL;
        } else {
            /* Esto no debería darse, pero si ocurre, es un CDR inválido que debería tratarse como un error-excepción. */
            /* code: 0100 a 1999 */
            echo 'Excepción';
            $res_estado = 'Excepción';
        }        
        
        $json = array(
                "codigo_sunat" => $res_code,
                    "estado_sunat" => $res_estado,
                    "mensaje_sunat" => $cdr->getDescription(),
                    "observaciones_sunat" => $res_obs,
                    "cdrZip" => base64_encode($result->getCdrZip())
        );
          
        echo json_encode($json, JSON_UNESCAPED_UNICODE);exit;
                $decoded = json_decode();
$formatted_json = json_encode($decoded, JSON_PRETTY_PRINT);
        //echo $cdr->getDescription().PHP_EOL;
        return response()->json($formatted_json);
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

    public function test() {
        $urlService = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
        $soap = new SoapClient();
        $soap->setService($urlService);
        $soap->setCredentials('20000000001MODDATOS', 'moddatos');
        $sender = new BillSender();
        $sender->setClient($soap);

        $xml = file_get_contents('C:/Users/keiner/Proyectos/factv2/backend-adm-rpum/20123456789-01-F001-1.xml');
        $result = $sender->send('20123456789-01-F001-1', $xml);

        if (!$result->isSuccess()) {
            // Error en la conexion con el servicio de SUNAT
            var_dump($result->getError());
            return;
        }

        $cdr = $result->getCdrResponse();
        file_put_contents('R-10481211641-03-B001-8.zip', $result->getCdrZip());

        // Verificar CDR (Factura aceptada o rechazada)
        $code = (int) $cdr->getCode();

        if ($code === 0) {
            echo 'ESTADO: ACEPTADA' . PHP_EOL;
            if (count($cdr->getNotes()) > 0) {
                echo 'INCLUYE OBSERVACIONES:' . PHP_EOL;
                // Mostrar observaciones
                foreach ($cdr->getNotes() as $obs) {
                    echo 'OBS: ' . $obs . PHP_EOL;
                }
            }
        } else if ($code >= 2000 && $code <= 3999) {
            echo 'ESTADO: RECHAZADA' . PHP_EOL;
        } else {
            /* Esto no debería darse, pero si ocurre, es un CDR inválido que debería tratarse como un error-excepción. */
            /* code: 0100 a 1999 */
            echo 'Excepción';
        }

        echo $cdr->getDescription() . PHP_EOL;
        exit;
        $getXML = file_get_contents("C:/Users/keiner/Proyectos/php/api/utils/xml/10481211641-03-B001-8.xml", false);
        return response($getXML, 200, [
            'Content-Type' => 'Content-Type: text/xml; charset=ISO-8859-1'
        ]);
    }

    //index
    public function index() {
        //Enviar a sunat
        $RUC = "20100073723";
        $USUARIO_SOL = "MODDATOS";
        $CONTRASENA_SOL = "MODDATOS";
        $service = "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl";
        $fileName = "10481211641-03-B001-8.zip";
        $WSHeader = "
        " . $RUC . $USUARIO_SOL . "
        " . $CONTRASENA_SOL . "

        ";
        $argumentos = array(array("fileName" => $fileName, "contentFile" => (file_get_contents($XMLSignedInBase64))));

        $soap = new SoapClient($service, [
            "cache_wsdl" => WSDL_CACHE_NONE,
            "trace" => TRUE,
            "soap_version" => SOAP_1_1]
        );
        $headers = new SoapHeader("http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd", "Security", new SoapVar($WSHeader, XSD_ANYXML));
        $result = $soap->__soapCall("sendBill", $argumentos, null, $headers);
        echo $result;
        //return var_dump($getXMLInBase64);
        //file_put_contents("signed.xml", $xmlSigned);        
        return response()->json([
                    "message" => "El archivo xml esta firmado y en base64",
                    "xml" => "nothing",
        ]);
    }

    /**
     * //Body inputs
      $fileNameInput = $request->fileName;
      $contentFileInput = $request->contentFile;

      //Params
      $RUC = "20100073723";
      $USUARIO_SOL = "MODDATOSxx";
      $CONTRASENA_SOL = "MODDATOS";
      $service = "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl";

      $WSHeader1 = "
      " . $RUC . $USUARIO_SOL . "
      " . $CONTRASENA_SOL . "

      ";
      $WSHeader = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://service.sunat.gob.pe" xmlns:ns2="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
      <SOAP-ENV:Header>
      <ns2:Security>
      <ns2:UsernameToken>
      <ns2:Username>20123456789MODDATOS</ns2:Username>
      <ns2:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">moddatos</ns2:Password>
      </ns2:UsernameToken>
      </ns2:Security>
      </SOAP-ENV:Header>
      <SOAP-ENV:Body>
      <ns1:sendBill>
      <fileName>' . $fileNameInput . '</fileName>
      <contentFile>' . $contentFileInput . '</contentFile>
      </ns1:sendBill>
      </SOAP-ENV:Body>
      </SOAP-ENV:Envelope>';

      $argumentos = array(array("fileName" => $fileNameInput, "contentFile" => file_get_contents($contentFileInput)));

      return base64_encode(file_get_contents($contentFileInput));

      $headers = new SoapHeader("http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd", "Security", new SoapVar($WSHeader, XSD_ANYXML));
      $soap = new SoapClient("billService.wsdl", [
      "cache_wsdl" => WSDL_CACHE_NONE,
      "trace" => TRUE,
      "soap_version" => SOAP_1_1]
      );
      //echo var_dump($soap);
      $result = $soap->__soapCall("sendBill", $argumentos, null);
      $cdr = base64_encode($result->applicationResponse);

      return response($cdr, 200, [
      'Content-Type' => 'text/html'
      ]);
     */
}
