<?php

namespace App\Services\ARCA;

use App\Models\Cuit;
use App\Models\CondicionIva;
use Exception;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * Servicio para consultar el padrón de contribuyentes de ARCA (AFIP)
 *
 * Usa el web service ws_sr_constancia_inscripcion (personaServiceA5)
 * para obtener datos fiscales de un contribuyente por CUIT.
 */
class PadronARCAService extends ARCAService
{
    protected const PADRON_WSDL_TESTING = 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA5?WSDL';
    protected const PADRON_WSDL_PRODUCCION = 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA5?WSDL';

    protected const SERVICIO_PADRON = 'ws_sr_constancia_inscripcion';

    /**
     * Verifica si la consulta al padrón está disponible
     * (hay al menos un CUIT activo con certificados configurados)
     */
    public static function estaDisponible(): bool
    {
        return Cuit::activos()
            ->whereNotNull('certificado_path')
            ->whereNotNull('clave_path')
            ->where(function ($q) {
                $q->whereNull('fecha_vencimiento_certificado')
                  ->orWhere('fecha_vencimiento_certificado', '>', now());
            })
            ->exists();
    }

    /**
     * Obtiene el primer CUIT activo con certificados configurados
     */
    public static function obtenerCuitDisponible(): ?Cuit
    {
        return Cuit::activos()
            ->whereNotNull('certificado_path')
            ->whereNotNull('clave_path')
            ->where(function ($q) {
                $q->whereNull('fecha_vencimiento_certificado')
                  ->orWhere('fecha_vencimiento_certificado', '>', now());
            })
            ->first();
    }

    /**
     * Consulta los datos de un contribuyente por CUIT
     *
     * @return array Datos normalizados del contribuyente
     */
    public function consultarCuit(string $cuitConsulta): array
    {
        // Limpiar CUIT (solo números)
        $cuitConsulta = preg_replace('/\D/', '', $cuitConsulta);

        if (!Cuit::validarCuit($cuitConsulta)) {
            throw new Exception(__('El CUIT ingresado no es válido'));
        }

        // Autenticar con WSAA para el servicio de padrón
        $this->autenticar(self::SERVICIO_PADRON);

        $client = $this->getPadronClient();

        try {
            $result = $client->getPersona_v2([
                'token' => $this->token,
                'sign' => $this->sign,
                'cuitRepresentada' => $this->cuit->numero_cuit,
                'idPersona' => $cuitConsulta,
            ]);

            if (isset($result->personaReturn->errorConstancia)) {
                $errores = $result->personaReturn->errorConstancia->error ?? 'Error desconocido';
                if (is_array($errores)) {
                    $error = implode('; ', $errores);
                } elseif (is_object($errores)) {
                    $error = json_encode($errores);
                } else {
                    $error = (string) $errores;
                }
                throw new Exception(__('ARCA respondió con error: :error', ['error' => $error]));
            }

            $datosGenerales = $result->personaReturn->datosGenerales ?? null;
            if (!$datosGenerales) {
                throw new Exception(__('No se encontraron datos para el CUIT :cuit', ['cuit' => $cuitConsulta]));
            }

            return $this->parsearRespuesta($result->personaReturn, $cuitConsulta);

        } catch (SoapFault $e) {
            Log::error('Error SOAP al consultar padrón ARCA', [
                'error' => $e->getMessage(),
                'cuit_comercio' => $this->cuit->numero_cuit,
                'cuit_consulta' => $cuitConsulta,
            ]);

            $mensaje = $e->getMessage();

            if (str_contains($mensaje, 'No existe persona con ese Id')) {
                throw new Exception(__('No se encontró ningún contribuyente con el CUIT :cuit', ['cuit' => $cuitConsulta]));
            }

            throw new Exception(__('Error al consultar ARCA: :error', ['error' => $mensaje]));
        }
    }

    /**
     * Parsea la respuesta del servicio de padrón
     */
    protected function parsearRespuesta(object $personaReturn, string $cuitConsulta): array
    {
        $datosGenerales = $personaReturn->datosGenerales;

        // Nombre / Razón Social
        $denominacion = '';
        if (isset($datosGenerales->razonSocial)) {
            $denominacion = $datosGenerales->razonSocial;
        } elseif (isset($datosGenerales->apellido)) {
            $denominacion = trim($datosGenerales->apellido . ' ' . ($datosGenerales->nombre ?? ''));
        }

        // Domicilio fiscal
        $direccion = '';
        if (isset($datosGenerales->domicilioFiscal)) {
            $dom = $datosGenerales->domicilioFiscal;
            $partes = [];

            if (!empty($dom->direccion)) {
                $partes[] = $dom->direccion;
            }
            if (!empty($dom->localidad)) {
                $partes[] = $dom->localidad;
            }
            if (!empty($dom->descripcionProvincia)) {
                $partes[] = $dom->descripcionProvincia;
            }
            if (!empty($dom->codPostal)) {
                $partes[] = 'CP ' . $dom->codPostal;
            }

            $direccion = implode(', ', $partes);
        }

        // Condición IVA - mapear desde los datos de impuestos
        $condicionIvaId = $this->determinarCondicionIva($personaReturn);

        // Estado del contribuyente
        $estadoActivo = isset($datosGenerales->estadoClave) && $datosGenerales->estadoClave === 'ACTIVO';

        return [
            'cuit' => $cuitConsulta,
            'denominacion' => $denominacion,
            'direccion' => $direccion,
            'condicion_iva_id' => $condicionIvaId,
            'estado_activo' => $estadoActivo,
            'tipo_persona' => $datosGenerales->tipoPersona ?? null,
        ];
    }

    /**
     * Determina la condición de IVA a partir de los datos del padrón
     *
     * El WS devuelve impuestos inscriptos. Se mapea:
     * - Impuesto 30 (IVA) activo → Responsable Inscripto
     * - Impuesto 32 (IVA Exento) → Sujeto Exento
     * - Impuesto 20 (Monotributo) → Responsable Monotributo
     * - Si no tiene ninguno → Consumidor Final
     */
    protected function determinarCondicionIva(object $personaReturn): ?int
    {
        $impuestos = [];

        if (isset($personaReturn->datosRegimenGeneral->impuesto)) {
            $listaImpuestos = $personaReturn->datosRegimenGeneral->impuesto;
            if (!is_array($listaImpuestos)) {
                $listaImpuestos = [$listaImpuestos];
            }
            foreach ($listaImpuestos as $imp) {
                $impuestos[] = (int) ($imp->idImpuesto ?? 0);
            }
        }

        if (isset($personaReturn->datosMonotributo)) {
            // Si tiene datos de monotributo, es monotributista
            $condicion = CondicionIva::porCodigo(CondicionIva::RESPONSABLE_MONOTRIBUTO)->first();
            return $condicion?->id;
        }

        if (in_array(30, $impuestos)) {
            // Inscripto en IVA
            $condicion = CondicionIva::porCodigo(CondicionIva::RESPONSABLE_INSCRIPTO)->first();
            return $condicion?->id;
        }

        if (in_array(32, $impuestos)) {
            // IVA Exento
            $condicion = CondicionIva::porCodigo(CondicionIva::SUJETO_EXENTO)->first();
            return $condicion?->id;
        }

        // Default: Consumidor Final
        $condicion = CondicionIva::porCodigo(CondicionIva::CONSUMIDOR_FINAL)->first();
        return $condicion?->id;
    }

    /**
     * Obtiene el cliente SOAP para el servicio de padrón
     */
    protected function getPadronClient(): SoapClient
    {
        $wsdl = $this->modoTesting ? self::PADRON_WSDL_TESTING : self::PADRON_WSDL_PRODUCCION;

        $options = [
            'soap_version' => \SOAP_1_1,
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
}
