# Circuito de Compras, Costos y Precios — Documento de revisión

> **Para**: contador y gerencia.
> **Propósito**: describir cómo va a funcionar el nuevo circuito de compras del
> sistema — la carga de facturas de proveedores, el tratamiento fiscal, cómo se
> calcula el costo de cada artículo, cómo se define la utilidad y cómo se llega
> al precio de venta — para validar el planteo ANTES de construirlo.
> Marcar todo lo que parezca incorrecto o incompleto. Al final hay una lista de
> preguntas puntuales que necesitan respuesta del contador.
> **Fecha**: 02/07/2026 — borrador para revisión.

---

## 1. Resumen del circuito completo

```
Factura del proveedor
   → carga en el sistema (borrador, se puede completar en varias sesiones)
   → confirmación (un solo acto: entra la mercadería, se calculan los costos,
     se registra el crédito fiscal y la deuda con el proveedor)
   → el costo actualizado llega a cada artículo
   → el sistema compara el margen real contra la utilidad deseada
   → propone precios de venta nuevos (el usuario decide, o automático si el
     artículo está configurado así)
   → la deuda se paga (al contado en el momento, o por cuenta corriente después)
```

---

## 2. Carga de la compra

### 2.1 Datos del comprobante

Cada compra registra:

- **Proveedor** y **tipo de comprobante** (Factura A, B, C, o comprobante no
  fiscal / remito interno).
- **Número real del comprobante del proveedor** (ej. `0003-00012345`), además
  del número interno del sistema. El sistema **impide cargar dos veces la misma
  factura** del mismo proveedor (si se cargó mal y se anuló, sí se puede volver
  a cargar).
- **Tres fechas distintas**, cada una con su función:
  - *Fecha del comprobante*: la que figura en la factura. **Es la que manda para
    el período fiscal** (en qué mes va el crédito de IVA).
  - *Fecha de vencimiento*: para el control de la deuda con el proveedor
    (cuánto falta / cuánto está vencido).
  - *Fecha de carga*: cuándo se cargó en el sistema (automática).
- **A nombre de qué CUIT propio** se recibió la factura (el sistema maneja más
  de un CUIT por comercio). El sistema valida la coherencia: por ejemplo, si el
  CUIT comprador es monotributista, no permite cargar una Factura A.
- **Desglose de IVA por alícuota** tal cual figura al pie de la factura (neto
  gravado al 21%, al 10,5%, exento, etc.). Esto se carga del comprobante físico,
  no se deduce: así el Libro IVA Compras cuadra **contra la factura**, sin
  diferencias de redondeo.

### 2.2 Compra fiscal / no fiscal

Un interruptor "compra no fiscal" permite cargar compras sin comprobante fiscal
(compras informales, gastos menores, mercadería sin factura). En ese caso el
sistema **no calcula ningún impuesto**: no hay IVA, no hay percepciones, no va
nada al libro fiscal. Lo pagado es directamente el costo.

### 2.3 Borrador y confirmación

- La compra se carga como **borrador**: no mueve stock, no genera costos, no
  registra nada fiscal ni deuda. Una factura larga puede cargarse en varias
  sesiones. Un borrador se puede corregir o eliminar libremente.
- Al **confirmar**, en un solo acto ocurre todo: entra el stock, se calculan
  los costos, se registra el crédito fiscal y nace la deuda (o el pago).
- Una compra confirmada **no se puede editar**: si tiene un error, se anula y
  se vuelve a cargar. (Se pueden retocar solo campos sin efecto, como
  observaciones o fecha de vencimiento.)
- Se puede separar **quién carga de quién confirma**: un empleado puede tener
  permiso para cargar borradores y otro (encargado/dueño) para confirmar.

### 2.4 Carga por código del proveedor

Cada artículo puede tener asociado el **código con el que lo identifica cada
proveedor** (el mismo artículo puede comprarse a varios proveedores, cada uno
con su código y sus condiciones). Al cargar la factura se puede buscar el
artículo por ese código, y el sistema precarga los **descuentos habituales** de
ese proveedor y el **factor de bultos** (ver 3.5).

---

## 3. Cómo se calcula el COSTO de cada renglón

Este es el corazón del circuito. La regla general:

> **El costo del artículo se guarda siempre NETO (sin IVA)** cuando el IVA es
> recuperable, y **completo (todo lo pagado)** cuando no lo es. Es el criterio
> contable de "costo computable": el IVA solo forma parte del costo cuando no
> se puede tomar como crédito fiscal.

### 3.1 La regla del IVA en el costo

| Situación | ¿El IVA integra el costo? |
|---|---|
| Comprador Responsable Inscripto + Factura A (discrimina IVA) | **No** — el IVA es crédito fiscal, el costo es el neto |
| Comprador RI + Factura B (no discrimina) | **Sí** — todo lo pagado es costo, no se descompone un IVA teórico |
| Comprador RI + compra no fiscal | **Sí** — todo lo pagado es costo |
| Comprador Monotributista (cualquier comprobante) | **Sí** — el monotributo no computa crédito de IVA |

### 3.2 Descuentos en cascada por renglón

Los proveedores suelen dar descuentos encadenados ("10 + 5 + 3"). El sistema
los aplica **en cascada** (uno sobre el resultado del anterior), no sumados:

> Ejemplo: renglón de $1.000 con descuentos 10% + 5% + 3%
> $1.000 × 0,90 × 0,95 × 0,97 = **$829,35**
> (sumar 18% daría $820 — el sistema NO hace eso; la cascada es lo que
> facturan los proveedores)

Además, si la factura tiene un **descuento global al pie**, se reparte
proporcionalmente entre los renglones según su importe, para que el costo
unitario de cada artículo sea el real.

### 3.3 Conceptos al pie de la factura (flete, impuestos internos, etc.)

Las facturas suelen traer renglones que no son mercadería: flete, impuestos
internos, envases. Cada uno se carga con una marca: **¿forma parte del costo?**

- **Sí** (ej. flete que cobra el proveedor, impuestos internos de bebidas):
  el importe se reparte proporcionalmente entre los artículos de la factura y
  engrosa su costo. Es el criterio de "costo de mercadería puesta en el local".
  *Caso argentino clave*: los impuestos internos de bebidas SON costo real —
  no son IVA ni percepción, no se recuperan.
- **No** (ej. un cargo financiero que se decide no costear): completa el total
  de la factura pero no toca el costo de los artículos.

### 3.4 Percepciones sufridas

Las percepciones que el proveedor aplica en la factura (percepción de IVA,
de IIBB) **no forman parte del costo** para un Responsable Inscripto: son
**pagos a cuenta** de impuestos, y van al registro fiscal como tales (igual
que ya funciona el resto del sistema impositivo). Se cargan desde la factura:
qué impuesto, base, alícuota y monto.

### 3.5 Bultos vs. unidades

Se puede comprar en "bultos" del proveedor y stockear en unidades propias:
un factor de conversión por artículo-proveedor hace la cuenta.

> Ejemplo: 2 bultos de 12 unidades a $1.200 el bulto
> → entran **24 unidades** al stock, a un costo de **$100 por unidad**.

### 3.6 La cadena completa, resumida

```
Precio del renglón en la factura
  − descuentos en cascada del renglón
  − parte proporcional del descuento global de la factura
  + parte proporcional de los conceptos que SÍ son costo (flete, internos)
  = costo facturado del renglón
  → si el comprobante discrimina IVA y el comprador es RI: ese neto ES el costo
    (el IVA se toma como crédito fiscal)
  → si no: todo lo pagado es el costo
  ÷ factor de bultos
  = COSTO UNITARIO NETO por unidad de stock
```

---

## 4. Los tres costos de cada artículo

Cada artículo mantiene **tres costos en paralelo**, por sucursal y también el
consolidado del comercio:

1. **Costo último**: el de la última compra confirmada (de cualquier
   proveedor). **Es el costo RECTOR: el que se usa para calcular utilidades y
   precios de venta.** Guarda de qué proveedor, qué compra y qué fecha viene.
2. **Costo promedio ponderado (PPP)**: promedia el stock existente con cada
   compra nueva. Sirve para valuar el inventario y el costo de lo vendido.
   > Ejemplo: hay 10 unidades a $100 y se compran 5 a $130
   > → PPP nuevo = (10×100 + 5×130) ÷ 15 = **$110**
   Si el artículo todavía no tiene costo cargado (arranque del sistema), la
   primera compra lo fija directamente.
3. **Costo de reposición**: manual, para quien quiere fijar "lo que me
   costaría hoy" sin esperar una compra. Si no se carga, el sistema usa el
   costo último en su lugar.

Todo cambio de costo queda en un **historial**: cuánto valía, cuánto pasó a
valer, qué % cambió, de qué compra/proveedor vino, quién y cuándo. Nunca se
pierde el rastro.

Además, por cada proveedor se guarda **su** último costo y la fecha de la
última compra — se puede comparar qué proveedor tiene mejor precio.

### ¿Qué pasa al anular una compra?

Si la compra anulada era la que había fijado el costo último vigente, el
sistema **restaura el costo anterior** (y lo deja anotado en el historial).
El promedio no se recalcula hacia atrás — la próxima compra lo corrige solo.

---

## 5. De la utilidad al precio de venta

### 5.1 Cómo se define la utilidad deseada

La utilidad es un **porcentaje de recargo (markup) sobre el costo neto**, y se
define en cascada, de lo general a lo particular:

1. **Utilidad general del comercio** (ej. 30%) — configuración base.
2. **Utilidad por categoría** (opcional — ej. bebidas 40%) — pisa la general.
3. **Utilidad por artículo** (opcional) — pisa a las dos anteriores.

Para cada artículo rige la más específica que exista.

### 5.2 Cómo se calcula el precio sugerido

Los precios del sistema son **finales, con IVA incluido** (como se muestran al
público). El precio sugerido se arma así:

```
precio sugerido = costo neto × (1 + utilidad %) × (1 + IVA %)  → redondeo
```

> Ejemplo: costo neto $100, utilidad 40%, IVA 21%
> $100 × 1,40 × 1,21 = **$169,40** → redondeo configurable (ej. a $169 o $170)

El redondeo se aplica **al precio final**, y el sistema muestra el **margen
real después del redondeo** (redondear para abajo come margen; se ve cuánto).

### 5.3 Margen real

Para cada artículo el sistema muestra el margen **real** que se está obteniendo
con el precio de venta actual y el costo vigente (la cuenta inversa: al precio
se le quita el IVA y se compara contra el costo neto). También se muestran los
equivalentes informativos: coeficiente (precio ÷ costo) y margen sobre venta.

### 5.4 Revisión de precios después de cada compra

Al confirmar una compra que cambió costos, el sistema **lista los artículos
cuyo margen quedó por debajo de la utilidad deseada**: costo viejo → nuevo,
precio actual, margen real, precio sugerido. El usuario tilda cuáles actualizar
y aplica en lote. **El costo nuevo nunca pisa el precio solo.**

La revisión se puede cerrar y retomar después desde la compra (siempre calcula
contra los valores vigentes en ese momento).

**Excepción opcional**: cada artículo puede marcarse como "precio administrado
por utilidad" — esos sí se repricean **automáticamente** al confirmar la compra
(con el redondeo configurado), y quedan informados en el resumen. Es opcional
artículo por artículo, apagado por defecto.

---

## 6. El lado fiscal de la compra

- Compra con **Factura A** (comprador RI): el IVA discriminado se registra
  como **crédito fiscal por alícuota**, en el período de la **fecha del
  comprobante**. Alimenta el Libro IVA Compras y la posición mensual de IVA
  que el sistema ya lleva.
- Las **percepciones sufridas** se registran como pagos a cuenta del impuesto
  que corresponda (IVA, IIBB por jurisdicción), igual que el criterio ya
  validado del resto del sistema.
- Compra con **Factura B/C o no fiscal**: no genera crédito fiscal alguno.
- **Anulación** de una compra de un período ya cerrado: la reversa fiscal se
  registra en **el período de la anulación** (como una nota de crédito), no
  reescribiendo el período original. Mismo criterio ya aplicado en ventas.
- Si se carga una factura con **fecha vieja** (período ya presentado), el
  sistema avisa pero no bloquea (el crédito tardío es computable; lo decide
  el contador).

---

## 7. Nota de crédito del proveedor (devoluciones parciales)

Si se devuelve parte de la mercadería (o el proveedor emite una NC por otro
motivo), se carga una **Nota de Crédito** referida a la factura original:

- **Stock**: salen las unidades devueltas.
- **Fiscal**: se revierte el crédito de IVA proporcional, en el período de la NC.
- **Deuda**: la NC baja el saldo pendiente de esa factura; si la factura ya
  estaba paga (o la NC es mayor), queda **saldo a favor** con el proveedor.
- **Costos**: una devolución parcial **no** recalcula los costos del artículo.

---

## 8. Pagos y cuenta corriente del proveedor

### 8.1 Cuenta corriente

Cada proveedor puede habilitarse para trabajar en **cuenta corriente**, con el
mismo esquema que la cuenta corriente de clientes (que ya funciona): un libro
de movimientos donde cada compra suma deuda y cada pago la baja, con extracto,
saldo y antigüedad de la deuda. Nada se borra: las correcciones se hacen con
contraasientos, todo queda auditable.

Se puede configurar **días de pago** por proveedor (ej. 30 días) para que el
sistema proponga el vencimiento de cada factura y muestre la deuda por
antigüedad (a vencer, vencida 0-30, 31-60, etc.).

### 8.2 Formas de pagar

- **Al contado** al confirmar la compra.
- **Todo a cuenta corriente**, para pagar después.
- **Cuenta corriente con un pago inicial parcial** (señé una parte).
- **Órdenes de pago** posteriores: se elige el proveedor, se ven sus facturas
  pendientes, y se paga una, varias o parte de una (el sistema propone
  aplicar el dinero a las más viejas primero, o se elige a mano). Un pago
  puede combinar **varias formas de pago**.
- **Anticipos**: pagos sin factura, que quedan como saldo a favor y se
  aplican a compras futuras.

### 8.3 De dónde sale la plata

- Por defecto, el pago sale de la **caja activa** del usuario (validando que
  esté abierta y tenga saldo).
- Con un **permiso especial** (pensado para encargados/tesorería), se puede
  pagar además desde: **otra caja**, el **efectivo de la Tesorería** de la
  sucursal, o una **cuenta bancaria/billetera de la empresa** (transferencia).
  Cada origen valida su propio saldo, y cada movimiento queda registrado donde
  corresponde (caja, tesorería o cuenta).

### 8.4 Anulaciones

- Una orden de pago se puede anular: el dinero "vuelve" a su origen (con el
  asiento inverso correspondiente) y las facturas recuperan su saldo pendiente.
- Si el pago salió de una caja cuyo turno ya se cerró, **no** se puede anular
  por esa vía (el cierre es intocable). Si salió de tesorería o de una cuenta,
  sí se puede anular siempre.
- Al anular una **compra que ya tiene pagos**, el usuario elige qué hacer con
  ellos: anularlos también (caso "todo fue un error de carga") o dejarlos como
  **saldo a favor** con el proveedor (caso "la plata realmente salió").

---

## 9. Todas las configuraciones posibles

| Configuración | Dónde | Efecto |
|---|---|---|
| Utilidad general (%) | Configuración del comercio | Markup por defecto de todos los artículos |
| Utilidad por categoría (%) | Ficha de la categoría | Pisa la general para esa categoría |
| Utilidad por artículo (%) | Ficha del artículo | Pisa a las dos anteriores |
| Precio administrado por utilidad | Ficha del artículo (apagado por defecto) | Reprecio automático al confirmar compras |
| Costo de reposición | Ficha del artículo (manual) | Costo alternativo informativo |
| Redondeo de precios sugeridos | En la revisión de precios | A cuánto se redondea el precio final |
| Proveedor con cuenta corriente | Ficha del proveedor | Habilita deuda y pagos por cta cte |
| Días de pago del proveedor | Ficha del proveedor | Propone vencimientos / antigüedad |
| Código y descuentos habituales por proveedor | Ficha del artículo (por proveedor) | Precarga en la carga de facturas |
| Factor de bultos por proveedor | Ficha del artículo (por proveedor) | Conversión bulto → unidades |
| Permisos por usuario | Roles y permisos | Quién carga, quién confirma, quién paga, quién paga desde tesorería/cuentas, quién VE los costos y márgenes (dato sensible), quién aplica cambios de precios |

---

## 10. Preguntas puntuales para el contador

1. **Costo computable**: ¿confirma el criterio? RI + Factura A ⇒ costo neto
   (IVA a crédito); Factura B / no fiscal / monotributo ⇒ todo lo pagado es
   costo, sin descomponer un IVA teórico. (Sección 3.1)
2. **Percepciones sufridas por un comprador NO inscripto** (ej. CUIT
   monotributista del comercio): hoy planteamos cargarlas solo como parte del
   total a pagar, sin tratamiento fiscal ni inclusión en el costo. ¿Es
   aceptable, o deberían integrar el costo? (Sección 3.4)
3. **Impuestos internos** (bebidas): los tratamos como costo real prorrateado
   entre los artículos de la factura. ¿Correcto? ¿Hay otros conceptos de pie
   de factura con tratamiento especial que debamos contemplar? (Sección 3.3)
4. **Factura de un período ya presentado**: el sistema avisa y deja computar el
   crédito en el período de la fecha del comprobante. ¿Prefiere que el crédito
   tardío se compute en el período de la CARGA en lugar del período original?
   (Sección 6)
5. **Anulaciones y NC cross-período**: la reversa fiscal siempre en el período
   de la anulación/NC (nunca reabriendo el período original). ¿Confirma?
   (Secciones 6 y 7)
6. **Costo último como rector** para margen y precios (no el promedio):
   ¿comparte el criterio comercial? El promedio queda para valuación. (Sección 4)
7. **PPP y devoluciones**: la NC parcial no recalcula el promedio (la próxima
   compra lo corrige). ¿Aceptable como simplificación? (Sección 7)

## 11. Preguntas para gerencia

1. ¿Quiénes van a poder **ver costos y márgenes**? (Es un permiso separado —
   definir qué roles lo tienen.)
2. ¿Se separa **cargar** de **confirmar** compras en la operatoria real?
   ¿Qué roles confirman?
3. ¿Quiénes tendrán el permiso de **pagar desde tesorería/cuentas** además de
   la caja propia?
4. La **utilidad general** del comercio arranca en un valor por defecto (30%):
   definir el real, y qué categorías necesitan utilidad propia.
5. ¿El circuito de **revisión de precios post-compra** (lista + aplicar en
   lote) refleja cómo quieren trabajar, o prefieren más automático (marcar
   muchos artículos como "precio administrado por utilidad")?

---

*Documento generado a partir de la especificación técnica
`compras-costos-precios` (decisiones D1-D20, 02/07/2026). Cualquier cambio que
surja de esta revisión se incorpora a la especificación antes de construir.*
