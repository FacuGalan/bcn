/**
 * Modulo de integracion con QZ Tray para BCN Pymes
 *
 * QZ Tray es una aplicacion gratuita que permite impresion silenciosa
 * desde el navegador a impresoras locales.
 *
 * Requiere:
 * - QZ Tray instalado en la PC del usuario (https://qz.io/download/)
 * - Certificado digital para firma de mensajes (opcional en desarrollo)
 */

window.QZIntegration = (function() {

    // Estado de conexion
    let qzConectado = false;
    let qzInicializando = false;

    /**
     * Inicializa la conexion con QZ Tray
     */
    async function inicializar() {
        if (qzInicializando) return false;
        if (qzConectado) return true;

        if (typeof qz === 'undefined') {
            console.warn('QZ Tray no esta cargado. Impresion silenciosa no disponible.');
            return false;
        }

        qzInicializando = true;

        try {
            // Configurar certificado para firma
            await configurarCertificado();

            // Conectar a QZ Tray
            if (!qz.websocket.isActive()) {
                await qz.websocket.connect();
            }

            qzConectado = true;
            qzInicializando = false;
            console.log('QZ Tray conectado exitosamente');
            return true;

        } catch (error) {
            console.error('Error conectando a QZ Tray:', error);
            qzConectado = false;
            qzInicializando = false;
            return false;
        }
    }

    /**
     * Configura el certificado digital para firma de mensajes
     */
    async function configurarCertificado() {
        // Certificado publico (puede ser auto-firmado en desarrollo)
        qz.security.setCertificatePromise(function(resolve, reject) {
            fetch('/qz/certificate-v2.txt?v=' + Date.now())
                .then(response => {
                    if (!response.ok) {
                        // Sin certificado, usar modo desarrollo
                        resolve('');
                        return;
                    }
                    return response.text();
                })
                .then(cert => resolve(cert || ''))
                .catch(() => resolve(''));
        });

        // Firma de mensajes via API del servidor
        qz.security.setSignatureAlgorithm("SHA512");
        qz.security.setSignaturePromise(function(toSign) {
            return function(resolve, reject) {
                fetch('/api/qz/sign', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ request: toSign })
                })
                .then(response => {
                    if (!response.ok) {
                        console.error('QZ Sign: Error HTTP', response.status);
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.signature) {
                        console.log('QZ Sign: Firma obtenida correctamente');
                        resolve(data.signature);
                    } else {
                        console.error('QZ Sign: Respuesta sin firma', data);
                        resolve('');
                    }
                })
                .catch(err => {
                    console.error('QZ Sign: Error en peticion', err);
                    resolve('');
                });
            };
        });
    }

    /**
     * Obtiene la lista de impresoras del sistema
     */
    async function obtenerImpresoras() {
        if (!qzConectado) {
            const conectado = await inicializar();
            if (!conectado) return [];
        }

        try {
            const impresoras = await qz.printers.find();
            return impresoras;
        } catch (error) {
            console.error('Error obteniendo impresoras:', error);
            return [];
        }
    }

    /**
     * Detecta impresoras y las envia al componente Livewire
     */
    async function detectarImpresoras() {
        const impresoras = await obtenerImpresoras();
        Livewire.dispatch('impresoras-detectadas', { impresoras: impresoras });
        return impresoras;
    }

    /**
     * Imprime contenido ESC/POS (impresoras termicas)
     */
    async function imprimirESCPOS(impresora, comandos, opciones = {}) {
        if (!qzConectado) {
            const conectado = await inicializar();
            if (!conectado) {
                throw new Error('QZ Tray no disponible');
            }
        }

        try {
            const config = qz.configs.create(impresora, {
                encoding: 'ISO-8859-1'
            });

            // Convertir comandos de base64 a formato QZ
            const data = comandos.map(cmd => {
                if (cmd.type === 'raw') {
                    return { type: 'raw', format: 'base64', data: cmd.data };
                }
                return cmd;
            });

            // Agregar corte si esta configurado
            if (opciones.cortar !== false) {
                data.push({ type: 'raw', format: 'base64', data: btoa('\x1D\x56\x01') });
            }

            // Agregar apertura de cajon si esta configurado
            if (opciones.abrir_cajon) {
                data.push({ type: 'raw', format: 'base64', data: btoa('\x1B\x70\x00\x19\xFA') });
            }

            await qz.print(config, data);
            console.log('Impresion ESC/POS exitosa');
            return true;

        } catch (error) {
            console.error('Error imprimiendo ESC/POS:', error);
            throw error;
        }
    }

    /**
     * Imprime contenido HTML (impresoras A4 y termicas)
     */
    async function imprimirHTML(impresora, html, opciones = {}) {
        if (!qzConectado) {
            const conectado = await inicializar();
            if (!conectado) {
                throw new Error('QZ Tray no disponible');
            }
        }

        try {
            // Determinar tamano de papel segun formato
            let configOpts = { units: 'mm' };

            if (opciones.formato === '80mm') {
                // Papel termico 80mm - sin rasterizar para maxima nitidez
                configOpts.size = { width: 72, height: null };
                configOpts.margins = { top: 0, right: 0, bottom: 0, left: 0 };
                configOpts.scaleContent = true;
                // Sin rasterize - usa impresion vectorial nativa del sistema
            } else if (opciones.formato === '58mm') {
                // Papel termico 58mm - sin rasterizar para maxima nitidez
                configOpts.size = { width: 48, height: null };
                configOpts.margins = { top: 0, right: 0, bottom: 0, left: 0 };
                configOpts.scaleContent = true;
                // Sin rasterize - usa impresion vectorial nativa del sistema
            } else if (opciones.formato === 'a4') {
                configOpts.size = { width: 210, height: 297 };
                configOpts.margins = { top: 10, right: 10, bottom: 10, left: 10 };
            } else {
                // Carta por defecto
                configOpts.size = { width: 216, height: 279 };
                configOpts.margins = { top: 10, right: 10, bottom: 10, left: 10 };
            }

            const config = qz.configs.create(impresora, configOpts);

            const data = [{ type: 'html', format: 'plain', data: html }];

            await qz.print(config, data);
            console.log('Impresion HTML exitosa en formato:', opciones.formato || 'default');
            return true;

        } catch (error) {
            console.error('Error imprimiendo HTML:', error);
            throw error;
        }
    }

    /**
     * Imprime un ticket de venta
     */
    async function imprimirTicketVenta(ventaId) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const response = await fetch(`/api/impresion/venta/${ventaId}/ticket`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Error obteniendo datos de impresion');
            }

            const data = await response.json();

            if (data.tipo === 'escpos') {
                await imprimirESCPOS(data.impresora, data.datos, data.opciones);
            } else {
                await imprimirHTML(data.impresora, data.datos, data.opciones);
            }

            mostrarNotificacion('Ticket impreso correctamente', 'success');
            return true;

        } catch (error) {
            console.error('Error imprimiendo ticket:', error);
            mostrarNotificacion('Error al imprimir ticket: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Imprime una factura fiscal
     */
    async function imprimirFactura(comprobanteId) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const response = await fetch(`/api/impresion/factura/${comprobanteId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Error obteniendo datos de impresion');
            }

            const data = await response.json();

            if (data.tipo === 'escpos') {
                await imprimirESCPOS(data.impresora, data.datos, data.opciones);
            } else {
                await imprimirHTML(data.impresora, data.datos, data.opciones);
            }

            mostrarNotificacion('Factura impresa correctamente', 'success');
            return true;

        } catch (error) {
            console.error('Error imprimiendo factura:', error);
            mostrarNotificacion('Error al imprimir factura: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Imprime un cierre de turno
     */
    async function imprimirCierreTurno(cierreId) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const response = await fetch(`/api/impresion/cierre-turno/${cierreId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Error obteniendo datos de impresion');
            }

            const data = await response.json();

            if (data.tipo === 'escpos') {
                await imprimirESCPOS(data.impresora, data.datos, data.opciones);
            } else {
                await imprimirHTML(data.impresora, data.datos, data.opciones);
            }

            mostrarNotificacion('Cierre de turno impreso correctamente', 'success');
            return true;

        } catch (error) {
            console.error('Error imprimiendo cierre de turno:', error);
            mostrarNotificacion('Error al imprimir cierre de turno: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Imprime un recibo de cobro
     */
    async function imprimirReciboCobro(cobroId) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const response = await fetch(`/api/impresion/recibo-cobro/${cobroId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Error obteniendo datos de impresion');
            }

            const data = await response.json();

            if (data.tipo === 'escpos') {
                await imprimirESCPOS(data.impresora, data.datos, data.opciones);
            } else {
                await imprimirHTML(data.impresora, data.datos, data.opciones);
            }

            mostrarNotificacion('Recibo de cobro impreso correctamente', 'success');
            return true;

        } catch (error) {
            console.error('Error imprimiendo recibo de cobro:', error);
            mostrarNotificacion('Error al imprimir recibo de cobro: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Prueba una impresora con un documento de prueba
     */
    async function probarImpresion(impresoraId, nombreSistema, tipo) {
        try {
            // Si tenemos ID, obtener datos del servidor
            if (impresoraId) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                const response = await fetch(`/api/impresion/prueba/${impresoraId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.tipo === 'escpos') {
                        await imprimirESCPOS(data.impresora, data.datos, data.opciones);
                    } else {
                        await imprimirHTML(data.impresora, data.datos, data.opciones);
                    }
                    mostrarNotificacion('Prueba de impresion enviada', 'success');
                    return true;
                }
            }

            // Fallback: prueba basica
            if (tipo === 'termica') {
                const comandosPrueba = [
                    { type: 'raw', data: btoa('\x1B\x40') },
                    { type: 'raw', data: btoa('\x1B\x61\x01') },
                    { type: 'raw', data: btoa('\x1B\x45\x01') },
                    { type: 'raw', data: btoa('PRUEBA DE IMPRESION\n') },
                    { type: 'raw', data: btoa('\x1B\x45\x00') },
                    { type: 'raw', data: btoa('========================\n') },
                    { type: 'raw', data: btoa('BCN Pymes\n') },
                    { type: 'raw', data: btoa('Impresora configurada\n') },
                    { type: 'raw', data: btoa('correctamente.\n') },
                    { type: 'raw', data: btoa('========================\n') },
                    { type: 'raw', data: btoa('\n\n\n') },
                ];
                await imprimirESCPOS(nombreSistema, comandosPrueba, { cortar: true });
            } else {
                const htmlPrueba = `
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                            h1 { color: #222036; }
                        </style>
                    </head>
                    <body>
                        <h1>PRUEBA DE IMPRESION</h1>
                        <p>BCN Pymes</p>
                        <p>Impresora configurada correctamente.</p>
                        <p>${new Date().toLocaleString()}</p>
                    </body>
                    </html>
                `;
                await imprimirHTML(nombreSistema, htmlPrueba, { formato: 'a4' });
            }

            mostrarNotificacion('Prueba de impresion enviada', 'success');
            return true;

        } catch (error) {
            mostrarNotificacion('Error en prueba: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Verifica si QZ Tray esta disponible
     */
    function estaDisponible() {
        return typeof qz !== 'undefined' && qzConectado;
    }

    /**
     * Verifica si QZ Tray esta cargado (aunque no conectado)
     */
    function estaCargado() {
        return typeof qz !== 'undefined';
    }

    /**
     * Muestra notificacion usando el sistema de toast del sistema
     */
    function mostrarNotificacion(mensaje, tipo) {
        if (typeof Livewire !== 'undefined') {
            Livewire.dispatch(tipo === 'success' ? 'toast-success' : 'toast-error', { message: mensaje });
        } else {
            console.log(`[${tipo}] ${mensaje}`);
        }
    }

    /**
     * Carga el script de QZ Tray desde CDN
     */
    function cargarQZScript() {
        return new Promise((resolve, reject) => {
            if (typeof qz !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.min.js';
            script.onload = resolve;
            script.onerror = () => reject(new Error('No se pudo cargar QZ Tray'));
            document.head.appendChild(script);
        });
    }

    // Listeners de eventos Livewire
    document.addEventListener('livewire:initialized', () => {
        // Detectar impresoras cuando se solicite
        Livewire.on('detectar-impresoras', detectarImpresoras);

        // Probar impresion
        Livewire.on('probar-impresion', (event) => {
            const data = Array.isArray(event) ? event[0] : event;
            probarImpresion(data.impresoraId, data.impresora, data.tipo);
        });

        // NOTA: El evento 'venta-completada' se maneja en nueva-venta.blade.php
        // para evitar duplicacion de impresiones
    });

    // Auto-inicializar cuando el DOM este listo
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            await cargarQZScript();
            await inicializar();
        } catch (error) {
            console.warn('QZ Tray no disponible:', error.message);
        }
    });

    /**
     * Conecta a QZ Tray (alias de inicializar para compatibilidad)
     */
    async function conectar() {
        return await inicializar();
    }

    // API publica
    return {
        inicializar,
        conectar,
        obtenerImpresoras,
        detectarImpresoras,
        imprimirESCPOS,
        imprimirHTML,
        imprimirTicketVenta,
        imprimirFactura,
        imprimirCierreTurno,
        imprimirReciboCobro,
        probarImpresion,
        estaDisponible,
        estaCargado,
        cargarQZScript
    };

})();
