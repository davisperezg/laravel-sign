<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Greenter\Report\XmlUtils;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use Illuminate\Http\Request;

class SignController extends Controller {

    public function signXml(Request $request) {
        $validated = $request->validate([
            'certificado' => 'required|file',
            'xml' => 'required|string',
            'fileName' => [
                'required',
                'string',
                'regex:/^\d{11}-\d{2}-[A-Z0-9]{1,4}-\d+$/', // Ejemplo: 10722312185-01-F001-00000009
            ],
        ], [
            'certificado.required' => 'No se ha enviado certificado',
            'xml.required' => 'El XML es obligatorio',
            'fileName.required' => 'El nombre de archivo es obligatorio',
            'fileName.regex' => 'El formato de fileName no es válido. Ejemplo: RUC-TIPO-SERIE-CORRELATIVO',
        ]);

        $certFile = $request->file('certificado')->getRealPath();
        $xml = base64_decode($validated['xml']);
        $fileName = $validated['fileName'];

        $signer = new SignedXml();
        $signer->setCertificateFromFile($certFile);
        $xmlSigned = $signer->signXml($xml);

        $xmlUtils = new XmlUtils();
        $hash = $xmlUtils->getHashSign($xmlSigned);

        return response()->json([
            'signedXml' => base64_encode($xmlSigned),
            'digest' => [
                'algorithm' => 'SHA-256',
                'value' => $hash,
            ],
            'fileName' => $fileName . '.xml',
            'signedAt' => Carbon::now('America/Lima')->toIso8601String(),
        ], 200);
    }
}
