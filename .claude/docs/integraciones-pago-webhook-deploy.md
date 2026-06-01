# Playbook — Webhook Mercado Pago + Reverb (Integraciones de Pago, Fase 6)

> Para el Claude Code / operador del **servidor**. Qué configurar para que el
> cobro por QR confirme en tiempo real vía webhook de MP (en lugar de depender
> solo del polling). Si esto NO se configura, el cobro **igual funciona** por
> polling cada 3s mientras el cajero espera con el modal abierto; el webhook
> agrega confirmación instantánea + robustez (registra el pago aunque el cajero
> cierre el navegador) + menos llamadas a la API de MP.

## Qué es

- Endpoint **único y global**: `POST /api/integraciones/mercadopago/webhook`
  (grupo `api`, sin auth ni CSRF). Resuelve a qué comercio/sucursal pertenece
  la notificación por el `user_id` MP del payload, usando la tabla
  `mercadopago_collector_index` (conexión `config`).
- Al confirmarse el pago, el webhook confirma la transacción server-side y
  **broadcastea por Reverb** al canal
  `comercios.{id}.integraciones-pago.transaccion.{txId}`. El navegador del
  cajero (modal "Esperando pago") reacciona al instante.
- No usa colas: el evento es `ShouldBroadcastNow` (síncrono). **No** hace falta
  `queue:work` para esto.

## Requisitos en el servidor

1. **Ruta pública alcanzable.** El reverse proxy (Apache/nginx) debe enrutar
   `POST https://<DOMINIO>/api/integraciones/mercadopago/webhook` a la app. No
   debe estar detrás de auth/IP-whitelist (MP llega desde sus IPs). Verificar:
   ```bash
   curl -i -X POST https://<DOMINIO>/api/integraciones/mercadopago/webhook \
     -H "Content-Type: application/json" -d '{}'
   # Esperado: HTTP 200 {"status":"ignored"} (payload vacío → ignora, no error)
   ```

2. **Reverb corriendo** (ya se usa para Pedidos en tiempo real). Verificar que
   el proceso esté arriba y detrás del proxy wss:
   ```bash
   php artisan reverb:start   # o el servicio/supervisor que lo mantiene vivo
   ```
   En `.env`: `BROADCAST_CONNECTION=reverb` + `REVERB_*` seteadas. En el build
   del front: `VITE_REVERB_HOST=<DOMINIO>`, `VITE_REVERB_PORT=443`,
   `VITE_REVERB_SCHEME=https` (cliente conecta a `wss://<DOMINIO>/app/{key}`).
   Si se cambian las `VITE_*`, hay que **`npm run build`**.

3. **Caches Laravel** tras deploy (el webhook usa rutas nuevas):
   ```bash
   php artisan optimize    # route:cache incluido — sin esto la ruta nueva puede no resolver
   ```
   > El cambio del modal de espera es Alpine inline en Blade: **no** requiere
   > `npm run build` por sí mismo (solo si se tocaron las `VITE_REVERB_*`).

## Configuración en el panel de Mercado Pago (por aplicación / sucursal)

Cada sucursal usa **su propia aplicación MP** (su propio access token). Por cada
una:

1. En el panel de MP → la aplicación → **Webhooks / Notificaciones**:
   - URL de producción: `https://<DOMINIO>/api/integraciones/mercadopago/webhook`
     (la **misma** URL para todas las sucursales; la app resuelve por `user_id`).
   - Evento/tópico: **Órdenes** (Orders API). NO "Pagos"/"merchant_order".
2. MP genera una **clave secreta de firma** (signing secret). Copiarla.
3. En BCN Pymes → **Configuración → Integraciones de Pago** (de esa sucursal) →
   editar la integración → pegar la clave en **webhook_secret** y guardar.
   - Se guarda **encriptada** (cast `encrypted`).
   - Si se deja vacío: el sistema **omite** la verificación de firma pero igual
     valida el pago re-consultando la order a la API de MP con el token de la
     sucursal (defensa suficiente). Cargar el secret es lo recomendado.

> **Importante (resolución multi-tenant):** la app sabe a qué sucursal pertenece
> la notificación por el `user_id` MP, vía `mercadopago_collector_index` (DB
> `config`). Ese índice se sincroniza solo al **guardar** la config de la
> sucursal (Configuración → Integraciones de Pago). Si una sucursal ya estaba
> configurada de antes, **re-guardar** su integración una vez para asegurar la
> entrada del índice.

## Probar sin esperar una venta real

- **Desde el panel de MP**: usar "Simular notificación" apuntando a la URL. Debe
  responder 200. (Si no hay una order/transacción real con ese id, responderá
  `{"status":"sin_match"}` con 200 — es correcto, significa que llegó y resolvió
  el tenant pero no encontró transacción.)
- **Desde dev con túnel**: `ngrok http 8000` (o el puerto local) y registrar la
  URL del túnel en MP para probar contra localhost.
- **Flujo real**: iniciar un cobro QR, pagar con una cuenta de prueba MP. El
  modal debe cerrarse solo (vía Reverb) sin esperar el polling.

## Verificación / troubleshooting

```bash
# Eventos de auditoría de una transacción (reemplazar PREFIJO por el del comercio):
#   webhook_recibido → llegó la notificación; confirmado → se confirmó el cobro.
mysql -e "SELECT evento, created_at FROM <DB>.<PREFIJO>_integraciones_pago_eventos ORDER BY id DESC LIMIT 10;"

# Índice colector (DB config) — debe tener fila por sucursal configurada:
mysql -e "SELECT user_id_externo, modo, comercio_id, sucursal_id, activo FROM config.mercadopago_collector_index;"
```

| Síntoma | Causa probable | Solución |
|--------|----------------|----------|
| HTTP 401 al webhook | Firma inválida (secret no coincide) | Verificar que el `webhook_secret` cargado en la sucursal sea exactamente el del panel de MP de esa app |
| `{"status":"sin_match"}` siempre | No hay fila en `mercadopago_collector_index` para ese `user_id` | Re-guardar la integración de la sucursal (sincroniza el índice). Confirmar que `user_id_externo` esté cargado |
| Webhook responde 200 pero el modal no cierra solo | Reverb no llega al browser | Verificar `reverb:start` corriendo, `VITE_REVERB_*` correctas + `npm run build`, y que el proxy exponga `wss://<DOMINIO>/app/{key}` |
| Ruta da 404 | route:cache viejo | `php artisan optimize` (o `route:clear && route:cache`) |
| Llega pero no confirma | La order todavía no está `processed` en MP | El polling (3s) lo toma cuando MP confirme; revisar `integraciones_pago_eventos` |

## Alcance / pendientes

- El webhook NO crea la venta: confirma la transacción y avisa; el frontend
  materializa el cobrable (venta / pedido) al re-consultar. Es **agnóstico** del
  tipo de cobrable.
- Si el cajero cerró el navegador, el pago queda **confirmado sin cobrable** (la
  plata entró) → reconciliable desde auditoría. La UI de reconciliación es una
  fase futura.
- Job de expiración de transacciones vencidas + confirmación manual = **Fase 8**
  (todavía no implementado).

Ref: `.claude/specs/integraciones-pago-mercadopago.md` (Fase 6 — RF-08/RF-14/RF-18).
