<?php

namespace App\Services\ARCA;

use App\Models\Cuit;
use App\Models\PuntoVenta;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SoapClient;
use SoapFault;

/**
 * Servicio para comunicación con ARCA (AFIP)
 *
 * Maneja la autenticación y facturación electrónica usando:
 * - WSAA: Web Service de Autenticación y Autorización
 * - WSFE: Web Service de Facturación Electrónica
 */
class ARCAService
{
    // URLs de los servicios
    protected const WSAA_WSDL_TESTING = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL';
    protected const WSAA_WSDL_PRODUCCION = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL';

    protected const WSFE_WSDL_TESTING = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';
    protected const WSFE_WSDL_PRODUCCION = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL';

    protected const SERVICIO_WSFE = 'wsfe';

    protected Cuit $cuit;
    protected bool $modoTesting;
    protected ?string $token = null;
    protected ?string $sign = null;

    public function __construct(Cuit $cuit)
    {
        $this->cuit = $cuit;
        $this->modoTesting = $cuit->isEnTesting();
    }

    /**
     * Obtiene el último número de comprobante autorizado
     */
    public function obtenerUltimoComprobante(int $puntoVenta, int $tipoComprobante): int
    {
        $this->autenticar();

        $client = $this->getWSFEClient();

        $params = [
            'Auth' => $this->getAuthParams(),
            'PtoVta' => $puntoVenta,
            'CbteTipo' => $tipoComprobante,
        ];

        try {
            $result = $client->FECompUltimoAutorizado($params);

            if (isset($result->FECompUltimoAutorizadoResult->Errors)) {
                $error = $result->FECompUltimoAutorizadoResult->Errors->Err;
                throw new Exception("Error AFIP: {$error->Code} - {$error->Msg}");
            }

            return (int) $result->FECompUltimoAutorizadoResult->CbteNro;

        } catch (SoapFault $e) {
            Log::error('Error SOAP al obtener último comprobante', [
                'error' => $e->getMessage(),
                'cuit' => $this->cuit->numero_cuit,
            ]);
            throw new Exception('Error de comunicación con AFIP: ' . $e->getMessage());
        }
    }

    /**
     * Solicita CAE para un comprobante
     *
     * @param array $comprobante Datos del comprobante
     * @return array ['cae' => string, 'vencimiento' => string, 'numero' => int]
     */
    public function solicitarCAE(array $comprobante): array
    {
        $this->autenticar();

        $client = $this->getWSFEClient();

        // Obtener siguiente número de comprobante
        $ultimoNumero = $this->obtenerUltimoComprobante(
            $comprobante['punto_venta'],
            $comprobante['tipo_comprobante']
        );
        $nuevoNumero = $ultimoNumero + 1;

        // Construir detalle
        $detalleComprobante = $this->construirDetalleComprobante($comprobante, $nuevoNumero);

        // DEBUG: Mostrar detalle que se enviará
        Log::info('=== DEBUG ARCA: Detalle comprobante construido ===', [
            'comprobante_input' => $comprobante,
            'detalle_construido' => $detalleComprobante,
            'tiene_iva' => isset($detalleComprobante['Iva']),
        ]);

        // Construir request
        $request = [
            'Auth' => $this->getAuthParams(),
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => $comprobante['punto_venta'],
                    'CbteTipo' => $comprobante['tipo_comprobante'],
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => $detalleComprobante,
                ],
            ],
        ];

        try {
            $result = $client->FECAESolicitar($request);

            $response = $result->FECAESolicitarResult;

            // Verificar errores globales
            if (isset($response->Errors)) {
                $error = is_array($response->Errors->Err)
                    ? $response->Errors->Err[0]
                    : $response->Errors->Err;
                throw new Exception("Error AFIP: {$error->Code} - {$error->Msg}");
            }

            // Obtener detalle de la respuesta
            $detalle = $response->FeDetResp->FECAEDetResponse;

            // Verificar resultado del comprobante
            if ($detalle->Resultado !== 'A') {
                $observaciones = [];
                if (isset($detalle->Observaciones)) {
                    $obs = is_array($detalle->Observaciones->Obs)
                        ? $detalle->Observaciones->Obs
                        : [$detalle->Observaciones->Obs];
                    foreach ($obs as $o) {
                        $observaciones[] = "{$o->Code}: {$o->Msg}";
                    }
                }
                throw new Exception('Comprobante rechazado: ' . implode(', ', $observaciones));
            }

            return [
                'cae' => $detalle->CAE,
                'vencimiento' => $detalle->CAEFchVto,
                'numero' => (int) $detalle->CbteDesde,
                'resultado' => $detalle->Resultado,
                'response_raw' => json_encode($response),
            ];

        } catch (SoapFault $e) {
            Log::error('Error SOAP al solicitar CAE', [
                'error' => $e->getMessage(),
                'cuit' => $this->cuit->numero_cuit,
                'comprobante' => $comprobante,
            ]);
            throw new Exception('Error de comunicación con AFIP: ' . $e->getMessage());
        }
    }

    /**
     * Construye el detalle del comprobante para WSFE
     */
    protected function construirDetalleComprobante(array $comprobante, int $numero): array
    {
        $detalle = [
            'Concepto' => $comprobante['concepto'] ?? 1, // 1=Productos, 2=Servicios, 3=Productos y Servicios
            'DocTipo' => $comprobante['doc_tipo'],
            'DocNro' => $comprobante['doc_nro'],
            'CondicionIVAReceptorId' => $comprobante['condicion_iva_receptor'] ?? 5, // RG 5616 (5=CF por defecto)
            'CbteDesde' => $numero,
            'CbteHasta' => $numero,
            'CbteFch' => $comprobante['fecha'] ?? now()->format('Ymd'),
            'ImpTotal' => $comprobante['imp_total'],
            'ImpTotConc' => $comprobante['imp_tot_conc'] ?? 0, // No gravado
            'ImpNeto' => $comprobante['imp_neto'],
            'ImpOpEx' => $comprobante['imp_op_ex'] ?? 0, // Exento
            'ImpIVA' => $comprobante['imp_iva'],
            'ImpTrib' => $comprobante['imp_trib'] ?? 0, // Otros tributos
            'MonId' => $comprobante['mon_id'] ?? 'PES',
            'MonCotiz' => $comprobante['mon_cotiz'] ?? 1,
        ];

        // Fechas de servicio (obligatorio para concepto 2 o 3)
        if (($comprobante['concepto'] ?? 1) > 1) {
            $detalle['FchServDesde'] = $comprobante['fch_serv_desde'] ?? now()->format('Ymd');
            $detalle['FchServHasta'] = $comprobante['fch_serv_hasta'] ?? now()->format('Ymd');
            $detalle['FchVtoPago'] = $comprobante['fch_vto_pago'] ?? now()->format('Ymd');
        }

        // Agregar IVA si corresponde (no para comprobantes tipo C que no discriminan IVA)
        // Tipos C: Factura C (11), Nota de Débito C (12), Nota de Crédito C (13)
        $tiposSinIVA = [11, 12, 13];
        if (!empty($comprobante['iva']) && !in_array($comprobante['tipo_comprobante'], $tiposSinIVA)) {
            $detalle['Iva'] = ['AlicIva' => $comprobante['iva']];
        }

        // Comprobantes asociados (para notas de crédito/débito)
        if (!empty($comprobante['cbtes_asoc'])) {
            $detalle['CbtesAsoc'] = ['CbteAsoc' => $comprobante['cbtes_asoc']];
        }

        // Otros tributos
        if (!empty($comprobante['tributos'])) {
            $detalle['Tributos'] = ['Tributo' => $comprobante['tributos']];
        }

        return $detalle;
    }

    /**
     * Autentica con WSAA y obtiene token/sign
     */
    protected function autenticar(string $servicio = self::SERVICIO_WSFE): void
    {
        $entorno = $this->modoTesting ? 'testing' : 'produccion';
        $cacheKey = "arca_auth_{$this->cuit->id}_{$entorno}_{$servicio}";

        // Intentar obtener del cache
        $cached = Cache::get($cacheKey);
        if ($cached) {
            $this->token = $cached['token'];
            $this->sign = $cached['sign'];
            return;
        }

        // Generar TRA (Ticket de Requerimiento de Acceso)
        $tra = $this->generarTRA($servicio);

        // Firmar TRA con certificado
        $cms = $this->firmarTRA($tra);

        // Llamar a WSAA
        $loginResult = $this->llamarWSAA($cms);

        // Parsear respuesta
        $xml = simplexml_load_string($loginResult);
        $this->token = (string) $xml->credentials->token;
        $this->sign = (string) $xml->credentials->sign;

        // Calcular tiempo de expiración
        $expiration = Carbon::parse((string) $xml->header->expirationTime);
        $ttl = now()->diffInSeconds($expiration) - 60; // 1 minuto de margen

        // Guardar en cache
        Cache::put($cacheKey, [
            'token' => $this->token,
            'sign' => $this->sign,
        ], $ttl);

        Log::info('Autenticación AFIP exitosa', [
            'cuit' => $this->cuit->numero_cuit,
            'servicio' => $servicio,
            'expiracion' => $expiration->toDateTimeString(),
        ]);
    }

    /**
     * Genera el TRA (Ticket de Requerimiento de Acceso)
     */
    protected function generarTRA(string $servicio = self::SERVICIO_WSFE): string
    {
        $uniqueId = time();
        $generationTime = Carbon::now()->subMinutes(10)->format('c');
        $expirationTime = Carbon::now()->addMinutes(10)->format('c');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
    <header>
        <uniqueId>{$uniqueId}</uniqueId>
        <generationTime>{$generationTime}</generationTime>
        <expirationTime>{$expirationTime}</expirationTime>
    </header>
    <service>{$servicio}</service>
</loginTicketRequest>
XML;
    }

    /**
     * Firma el TRA con el certificado y clave privada
     */
    protected function firmarTRA(string $tra): string
    {
        $certificado = $this->cuit->getCertificadoContenido();
        $clave = $this->cuit->getClaveContenido();

        if (!$certificado || !$clave) {
            throw new Exception('Certificados no configurados para este CUIT');
        }

        // Crear archivo temporal para el TRA
        $traFile = tempnam(sys_get_temp_dir(), 'tra');
        file_put_contents($traFile, $tra);

        // Crear archivo temporal para la firma
        $cmsFile = tempnam(sys_get_temp_dir(), 'cms');

        // Firmar usando OpenSSL
        $certResource = openssl_x509_read($certificado);
        $keyResource = openssl_pkey_get_private($clave);

        if (!$certResource || !$keyResource) {
            @unlink($traFile);
            throw new Exception('Error al leer certificado o clave privada');
        }

        $result = openssl_pkcs7_sign(
            $traFile,
            $cmsFile,
            $certResource,
            $keyResource,
            [],
            \PKCS7_BINARY | \PKCS7_NOATTR
        );

        @unlink($traFile);

        if (!$result) {
            @unlink($cmsFile);
            throw new Exception('Error al firmar TRA: ' . openssl_error_string());
        }

        // Leer y procesar el archivo firmado
        $cms = file_get_contents($cmsFile);
        @unlink($cmsFile);

        // Extraer solo la parte CMS (quitar headers MIME)
        $parts = explode("\n\n", $cms);
        $cms = isset($parts[1]) ? $parts[1] : $cms;
        $cms = preg_replace('/\s+/', '', $cms);

        return $cms;
    }

    /**
     * Llama a WSAA para obtener el token
     */
    protected function llamarWSAA(string $cms): string
    {
        $wsdl = $this->modoTesting ? self::WSAA_WSDL_TESTING : self::WSAA_WSDL_PRODUCCION;

        $options = [
            'soap_version' => \SOAP_1_2,
            'trace' => true,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]),
        ];

        try {
            $client = new SoapClient($wsdl, $options);
            $result = $client->loginCms(['in0' => $cms]);

            return $result->loginCmsReturn;

        } catch (SoapFault $e) {
            $mensaje = $e->getMessage();

            // Si ya hay un token válido, esperar unos minutos
            if (str_contains($mensaje, 'alreadyAuthenticated') || str_contains($mensaje, 'ya posee un TA')) {
                Log::warning('WSAA: Ya existe un token válido, espere 10-15 minutos', [
                    'cuit' => $this->cuit->numero_cuit,
                ]);
                throw new Exception('Ya existe una sesión activa con AFIP. Espere 10-15 minutos antes de reintentar.');
            }

            // Error de fecha/hora del sistema
            if (str_contains($mensaje, 'generationTime') || str_contains($mensaje, 'expirationTime')) {
                Log::error('WSAA: Error de fecha/hora del sistema', [
                    'cuit' => $this->cuit->numero_cuit,
                    'error_original' => $mensaje,
                ]);
                throw new Exception('La fecha y hora de su computadora no están correctas. Por favor, verifique que la fecha, hora y zona horaria de su sistema estén configuradas correctamente y vuelva a intentar.');
            }

            Log::error('Error WSAA', [
                'error' => $mensaje,
                'cuit' => $this->cuit->numero_cuit,
            ]);
            throw new Exception('Error de autenticación AFIP: ' . $mensaje);
        }
    }

    /**
     * Limpia el caché de autenticación para forzar re-autenticación
     */
    public function limpiarCache(string $servicio = self::SERVICIO_WSFE): void
    {
        $entorno = $this->modoTesting ? 'testing' : 'produccion';
        $cacheKey = "arca_auth_{$this->cuit->id}_{$entorno}_{$servicio}";
        Cache::forget($cacheKey);
        $this->token = null;
        $this->sign = null;
    }

    /**
     * Obtiene el cliente SOAP para WSFE
     */
    protected function getWSFEClient(): SoapClient
    {
        $wsdl = $this->modoTesting ? self::WSFE_WSDL_TESTING : self::WSFE_WSDL_PRODUCCION;

        $options = [
            'soap_version' => \SOAP_1_2,
            'trace' => true,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]),
        ];

        return new SoapClient($wsdl, $options);
    }

    /**
     * Obtiene los parámetros de autenticación para WSFE
     */
    protected function getAuthParams(): array
    {
        return [
            'Token' => $this->token,
            'Sign' => $this->sign,
            'Cuit' => $this->cuit->numero_cuit,
        ];
    }

    /**
     * Mapeo de tipos de comprobante a códigos AFIP
     */
    public static function getTipoComprobanteAFIP(string $tipo): int
    {
        $tipos = [
            'factura_a' => 1,
            'nota_debito_a' => 2,
            'nota_credito_a' => 3,
            'factura_b' => 6,
            'nota_debito_b' => 7,
            'nota_credito_b' => 8,
            'factura_c' => 11,
            'nota_debito_c' => 12,
            'nota_credito_c' => 13,
            'factura_e' => 19,
            'nota_debito_e' => 20,
            'nota_credito_e' => 21,
            'factura_m' => 51,
            'nota_debito_m' => 52,
            'nota_credito_m' => 53,
            'recibo_a' => 4,
            'recibo_b' => 9,
            'recibo_c' => 15,
        ];

        return $tipos[$tipo] ?? throw new Exception("Tipo de comprobante no válido: {$tipo}");
    }

    /**
     * Mapeo de tipos de documento a códigos AFIP
     *
     * Acepta tanto nombres ('CUIT', 'DNI') como códigos numéricos ('80', '96')
     */
    public static function getTipoDocumentoAFIP(string $tipo): int
    {
        // Si ya es un código numérico, devolverlo directamente
        if (is_numeric($tipo)) {
            return (int) $tipo;
        }

        $tipos = [
            'CUIT' => 80,
            'CUIL' => 86,
            'CDI' => 87,
            'DNI' => 96,
            'CI' => 96,
            'LC' => 89,
            'LE' => 90,
            'PASAPORTE' => 94,
            'SIN_IDENTIFICAR' => 99,
        ];

        return $tipos[strtoupper($tipo)] ?? 99;
    }

    /**
     * Mapeo de alícuotas de IVA a códigos AFIP
     */
    public static function getAlicuotaIVA(float $porcentaje): int
    {
        $alicuotas = [
            0 => 3,      // 0%
            2.5 => 9,    // 2.5%
            5 => 8,      // 5%
            10.5 => 4,   // 10.5%
            21 => 5,     // 21%
            27 => 6,     // 27%
        ];

        return $alicuotas[$porcentaje] ?? 5; // Default 21%
    }

    /**
     * Verifica el estado del servicio
     */
    public function verificarEstadoServicio(): array
    {
        try {
            $client = $this->getWSFEClient();
            $result = $client->FEDummy();

            return [
                'AppServer' => $result->FEDummyResult->AppServer ?? 'N/A',
                'DbServer' => $result->FEDummyResult->DbServer ?? 'N/A',
                'AuthServer' => $result->FEDummyResult->AuthServer ?? 'N/A',
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}
