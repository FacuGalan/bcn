# Playbook — Expiración de cobros + Confirmación manual (Integraciones de Pago, Fase 8)

> Para el Claude Code / operador del **servidor**. Qué hace falta para que los
> cobros por QR pendientes **expiren solos** y para que la **confirmación manual**
> funcione. Spoiler: si `precios:procesar-programados` ya corre en el server, la
> expiración **no requiere configurar nada nuevo**.

## Qué es

- **Expiración (RF-16):** comando `php artisan integraciones-pago:expirar-pendientes`
  que cada minuto marca como `expirado` las transacciones de cobro que quedaron
  `pendiente` y cuyo `expira_en` ya venció (timeout por sucursal, default 5 min).
  Por cada una broadcastea por Reverb para que el modal "Esperando pago" que
  todavía está abierto **cierre solo** y muestre "tiempo agotado". Itera todos
  los comercios (multi-tenant). No anula ninguna venta (con el modelo "cobro
  primero" la venta nunca se creó).
- **Confirmación manual (RF-12):** en el modal de espera, el cajero con permiso
  puede forzar la confirmación si el pago no se detectó automáticamente.

## El scheduler (clave: NO hay cron nuevo)

Laravel usa **una sola** entrada de cron que dispara TODAS las tareas
programadas. Ambos comandos están registrados en el mismo lugar
(`bootstrap/app.php → withSchedule()`):

```
* * * * *  php artisan precios:procesar-programados
* * * * *  php artisan integraciones-pago:expirar-pendientes   ← nuevo (Fase 8)
```

La única entrada de cron que el server necesita (y que ya debe existir porque
`precios:procesar-programados` ya funciona) es:

```cron
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

**Conclusión:** tras hacer `git pull` de la rama/deploy, `expirar-pendientes`
corre automáticamente. **No hay que tocar el crontab.**

## Verificación en el server

```bash
# 1) Ambos comandos deben aparecer registrados (Next Due en <60s):
php artisan schedule:list
#   Esperado:
#   * * * * *  php artisan precios:procesar-programados
#   * * * * *  php artisan integraciones-pago:expirar-pendientes

# 2) Confirmar que el cron de Laravel existe (la entrada schedule:run):
crontab -l | grep schedule:run
#   Si NO aparece, ESTE es el único cron a agregar (y también faltaría para precios).

# 3) Probar el comando a mano (seguro, idempotente; no expira nada vigente):
php artisan integraciones-pago:expirar-pendientes
#   Si hay vencidas, imprime "Comercio X: N transacción(es) expirada(s)".

# 4) Tras deploy, limpiar cache de rutas/config (incluye el schedule):
php artisan optimize
```

> El comando usa `->withoutOverlapping()`: si una corrida tarda, la siguiente no
> se solapa. Es seguro que corra cada minuto en paralelo a precios.

## Confirmación manual — sin pasos de deploy

- Es UI (Blade + Alpine inline) → **no requiere `npm run build`**, solo el `git pull`.
- Aparece en el modal de cobro solo para usuarios con el permiso
  **`integraciones_pago.confirmar_manual`** (por default: Administrador y Super
  Administrador; asigná el permiso al rol Encargado si querés que cobre en caja).
- Al confirmar, la transacción queda en estado **`confirmado_manual`** (distinto
  de `confirmado`) y se registra **qué usuario** la confirmó en
  `integraciones_pago_eventos` (evento `confirmado_manual`).

## Troubleshooting

| Síntoma | Causa probable | Solución |
|--------|----------------|----------|
| Los cobros no expiran nunca | El cron `schedule:run` no está corriendo | `crontab -l \| grep schedule:run`; agregarlo si falta (mismo que necesita precios) |
| `schedule:list` no muestra el comando | Deploy sin el nuevo `bootstrap/app.php`, o config cacheada | `git pull` + `php artisan optimize` (o `config:clear`) |
| El modal no cierra solo al expirar | Reverb no llega al browser | Mismo requisito que Fase 6 (ver `integraciones-pago-webhook-deploy.md`); igual el `wire:poll` de 3s lo cierra al leer el estado local `expirado` |
| No aparece "Confirmar manualmente" en el modal | El usuario no tiene el permiso | Asignar `integraciones_pago.confirmar_manual` al rol; `php artisan optimize:clear` (cache de permisos) |
| Expira antes de tiempo | `timeout_segundos` de la sucursal muy bajo | Ajustar en Configuración → Integraciones de Pago (default 300s) |

## Alcance / notas

- La expiración es una **red de seguridad**: el camino normal es webhook (Fase 6)
  o polling. El job solo limpia transacciones que nadie confirmó dentro del
  timeout.
- El modal de cobro ahora reacciona primero al **estado local** de la
  transacción: si el job la expiró (o el webhook la confirmó server-side), el
  próximo `wire:poll` (≤3s) lo detecta sin re-consultar a Mercado Pago.

Ref: `.claude/specs/integraciones-pago-mercadopago.md` (Fase 8 — RF-12 / RF-16).
Relacionado: `.claude/docs/integraciones-pago-webhook-deploy.md` (Fase 6).
