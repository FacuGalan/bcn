# BCN Pymes -- Manual de Usuario

> Manual completo del sistema BCN Pymes para administradores de comercio.
> Version: 0.1.x | Ultima actualizacion: 2026-07-01 (pedidos por mostrador: filtros de estado en barra superior, ordenamiento por columna, badges de estado pedido vibrantes, cliente opcional con default "Consumidor final", teclas rapidas en editor)

---

## Tabla de Contenidos

- [1. Introduccion](#1-introduccion)
- [2. Panel de Control (Dashboard)](#2-panel-de-control-dashboard)
- [3. Ventas](#3-ventas)
  - [3.1 Nueva Venta (Punto de Venta)](#31-nueva-venta-punto-de-venta)
  - [3.2 Listado de Ventas](#32-listado-de-ventas)
  - [3.3 Programa de Puntos](#33-programa-de-puntos)
  - [3.4 Cupones](#34-cupones)
  - [3.5 Modificar Pagos en Ventas Registradas](#35-modificar-pagos-en-ventas-registradas)
  - [3.6 Reportes de Ventas](#36-reportes-de-ventas)
- [4. Pedidos por Mostrador](#4-pedidos-por-mostrador)
  - [4.1 Vistas: Lista y Kanban](#41-vistas-lista-y-kanban)
  - [4.2 Vista Lista](#42-vista-lista)
  - [4.3 Vista Kanban](#43-vista-kanban)
- [5. Compras](#5-compras)
- [6. Stock e Inventario](#6-stock-e-inventario)
  - [6.1 Inventario por Sucursal](#61-inventario-por-sucursal)
  - [6.2 Movimientos de Stock](#62-movimientos-de-stock)
  - [6.3 Inventario General](#63-inventario-general-todas-las-sucursales)
  - [6.4 Recetas](#64-recetas)
  - [6.5 Produccion](#65-produccion)
  - [6.6 Produccion por Lote](#66-produccion-por-lote)
- [7. Cajas](#7-cajas)
  - [7.1 Gestion de Cajas](#71-gestion-de-cajas)
  - [7.2 Turno Actual](#72-turno-actual)
  - [7.3 Historial de Turnos](#73-historial-de-turnos)
  - [7.4 Movimientos Manuales](#74-movimientos-manuales)
  - [7.5 Ajustes Post-Cierre](#75-ajustes-post-cierre)
  - [7.6 Pagos Pendientes de Facturar](#76-pagos-pendientes-de-facturar)
- [8. Tesoreria](#8-tesoreria)
  - [8.1 Gestion de Tesoreria](#81-gestion-de-tesoreria)
  - [8.2 Reportes de Tesoreria](#82-reportes-de-tesoreria)
- [9. Bancos](#9-bancos)
  - [9.1 Resumen de Cuentas](#91-resumen-de-cuentas)
  - [9.2 Gestion de Cuentas](#92-gestion-de-cuentas)
  - [9.3 Movimientos](#93-movimientos)
  - [9.4 Transferencias](#94-transferencias)
  - [9.5 Conciliaciones](#95-conciliaciones)
- [10. Articulos](#10-articulos)
  - [10.1 Gestion de Articulos](#101-gestion-de-articulos)
    - [Importar y exportar articulos desde Excel](#importar-y-exportar-articulos-desde-excel)
  - [10.2 Categorias](#102-categorias)
  - [10.3 Etiquetas](#103-etiquetas)
  - [10.4 Asignar Etiquetas](#104-asignar-etiquetas)
  - [10.5 Cambio Masivo de Precios](#105-cambio-masivo-de-precios)
  - [10.6 Grupos Opcionales](#106-grupos-opcionales)
  - [10.7 Asignar Opcionales](#107-asignar-opcionales)
- [11. Clientes](#11-clientes)
  - [11.1 Gestion de Clientes](#111-gestion-de-clientes)
  - [11.2 Cobranzas y Cuenta Corriente](#112-cobranzas-y-cuenta-corriente)
- [12. Configuracion](#12-configuracion)
  - [12.1 Datos de la Empresa](#121-datos-de-la-empresa)
  - [12.2 Usuarios](#122-usuarios)
  - [12.3 Roles y Permisos](#123-roles-y-permisos)
  - [12.4 Formas de Pago](#124-formas-de-pago)
  - [12.5 Formas de Pago por Sucursal](#125-formas-de-pago-por-sucursal)
  - [12.6 Listas de Precios](#126-listas-de-precios)
  - [12.7 Promociones](#127-promociones)
  - [12.8 Promociones Especiales](#128-promociones-especiales)
  - [12.9 Monedas](#129-monedas)
  - [12.10 Impresoras](#1210-impresoras)
  - [12.11 Integraciones de Pago](#1211-integraciones-de-pago)
  - [12.12 Personalizar 2da Pantalla (por Sucursal)](#1212-personalizar-2da-pantalla-por-sucursal)
  - [12.13 Monitor Llamador de Pedidos (por Sucursal)](#1213-monitor-llamador-de-pedidos-por-sucursal)
  - [12.14 Consultor de Precios (por Sucursal)](#1214-consultor-de-precios-por-sucursal)
- [13. Fiscal](#13-fiscal)
  - [13.1 Posicion Fiscal](#131-posicion-fiscal)
  - [13.2 Libros IVA](#132-libros-iva)
  - [13.3 Movimientos Fiscales](#133-movimientos-fiscales)
  - [13.4 Importar Padron](#134-importar-padron)
- [14. Flujos de Trabajo Comunes](#14-flujos-de-trabajo-comunes)
  - [14.1 Abrir el comercio por la manana](#141-abrir-el-comercio-por-la-manana)
  - [14.2 Realizar una venta tipica](#142-realizar-una-venta-tipica)
  - [14.3 Cobrar deuda de un cliente](#143-cobrar-deuda-de-un-cliente)
  - [14.4 Cerrar caja al final del dia](#144-cerrar-caja-al-final-del-dia)
  - [14.5 Hacer inventario fisico](#145-hacer-inventario-fisico)
  - [14.6 Cambiar precios masivamente](#146-cambiar-precios-masivamente)
  - [14.7 Crear una promocion](#147-crear-una-promocion)
- [Glosario](#glosario)

---

## 1. Introduccion

### Que es BCN Pymes

BCN Pymes es un sistema integral de gestion comercial disenado para pequenas y medianas empresas. Permite administrar todos los aspectos de un negocio: ventas, compras, stock, cajas, tesoreria, clientes, articulos y mas. El sistema funciona completamente desde el navegador web y se adapta tanto a computadoras de escritorio como a dispositivos moviles.

### Conceptos basicos

Antes de comenzar a usar el sistema, es importante comprender los siguientes conceptos:

- **Comercio**: Es su empresa o negocio. Todo el sistema gira en torno al comercio al que usted pertenece. Un comercio puede tener una o varias sucursales.

- **Sucursal**: Cada ubicacion fisica de su comercio. Si tiene un solo local, tendra una unica sucursal. Cada sucursal tiene sus propias cajas, su propio stock y puede tener configuraciones de precios y formas de pago independientes.

- **Caja**: Representa un punto de cobro dentro de una sucursal. Puede ser una caja registradora fisica o simplemente un puesto de trabajo donde se realizan cobros. Las cajas registran todos los movimientos de dinero (ingresos por ventas, egresos por compras, movimientos manuales).

- **Turno**: Es el periodo de tiempo durante el cual una caja esta operativa. Un turno comienza cuando se abre la caja (con un saldo inicial) y termina cuando se cierra (con un arqueo). Durante un turno se registran todas las operaciones de esa caja.

- **Grupo de Cierre**: Permite agrupar varias cajas para cerrarlas juntas al final del dia. Opcionalmente, un grupo puede usar un fondo comun de efectivo.

### Navegacion del sistema

Al iniciar sesion, encontrara:

- **Menu lateral izquierdo**: Contiene todos los modulos del sistema organizados en categorias (Ventas, Compras, Stock, Cajas, etc.). Haga clic en una categoria para ver sus sub-opciones.
- **Barra superior (navbar)**: Muestra los modulos principales del sistema como pestanas horizontales. A la derecha encontrara selectores de sucursal y caja, ademas de su perfil de usuario. El desplegable de perfil incluye las opciones **"Instalar App"** (para instalar BCN Pymes como app PWA en el dispositivo) e **"Instalar pantalla cliente"** (para instalar la pantalla del segundo monitor como app independiente).
- **Banda de sub-items**: Debajo del navbar, al seleccionar un modulo que tiene sub-secciones, aparece una banda compacta con los items hijos. El item activo se resalta con subrayado naranja y texto en negrita.
- **Area de contenido principal**: Ocupa la mayor parte de la pantalla y muestra la pagina o modulo seleccionado.

Cuando hay muchos modulos o sub-items que no caben en el ancho de la pantalla, ambas bandas (navbar y sub-items) son desplazables horizontalmente sin barra de scroll visible. El sistema centra automaticamente el item activo al cargar o navegar. Si hay contenido fuera de vista, aparece una flecha indicadora en el borde correspondiente. Tambien es posible desplazarse pasando el cursor cerca de los bordes laterales.

### Selector de sucursal y caja

En la barra superior del sistema encontrara dos selectores fundamentales:

- **Selector de Sucursal**: Si su comercio tiene multiples sucursales, este selector le permite cambiar entre ellas. Todos los datos mostrados (ventas, stock, cajas, etc.) corresponderan a la sucursal seleccionada. Si solo tiene una sucursal, este selector puede no aparecer.

- **Selector de Caja**: Le permite elegir en cual caja esta trabajando. Este selector es especialmente importante para las operaciones de ventas y cobranzas, ya que los movimientos de dinero se registran en la caja seleccionada.

> **Importante**: Siempre verifique que tiene la sucursal y la caja correctas seleccionadas antes de realizar operaciones. Cambiar de caja durante una venta en curso limpiara el carrito.

---

## 2. Panel de Control (Dashboard)

Al ingresar al sistema, la primera pantalla que vera es el Panel de Control de la sucursal activa. Este panel le brinda una vision general rapida del estado de su negocio.

### Que ve al entrar

En la parte superior se muestra el nombre de la sucursal activa junto con su codigo. Si la sucursal es la principal, aparecera una etiqueta amarilla indicandolo.

### Selector de periodo

A la derecha del nombre de la sucursal encontrara tres botones para cambiar el periodo de analisis:

- **Hoy**: Muestra las metricas del dia actual.
- **Semana**: Muestra las metricas desde el lunes hasta hoy.
- **Mes**: Muestra las metricas desde el primer dia del mes hasta hoy.

### Metricas disponibles

El dashboard se divide en varias secciones con indicadores clave:

**Primera fila de KPIs (indicadores principales):**

1. **Ventas totales**: Monto total facturado en el periodo seleccionado, cantidad de operaciones realizadas y la variacion porcentual respecto al periodo anterior (si las ventas subieron o bajaron). Una flecha verde indica aumento; roja, disminucion.

2. **Ticket Promedio**: El valor promedio de cada venta. Si hubo ventas canceladas, se indica la cantidad.

3. **Facturado AFIP**: El total de comprobantes fiscales emitidos (facturas A, B, C) menos las notas de credito. Debajo se muestra la cantidad de facturas y notas de credito por separado.

4. **Cajas Abiertas**: Muestra cuantas cajas estan operativas sobre el total (por ejemplo, "2/3" significa 2 de 3 cajas abiertas). Debajo se muestra el saldo total en efectivo de las cajas abiertas.

**Segunda fila de metricas:**

5. **Compras**: Total de compras realizadas en el periodo.
6. **Cuenta Corriente Pendiente**: Saldo total que los clientes deben por ventas a cuenta corriente.
7. **Cobros Recibidos**: Total de dinero recibido en concepto de cobros de deudas de clientes.
8. **Descuentos por Promociones**: Total de descuentos aplicados por promociones en el periodo.

**Secciones detalladas:**

- **Formas de Pago**: Desglose de las ventas por cada forma de pago utilizada (efectivo, debito, credito, etc.), mostrando el total, la cantidad de operaciones y que porcentaje fue facturado.

- **Alertas de Stock**: Lista de articulos cuyo stock esta por debajo del minimo configurado, y articulos sin existencia. Esto le permite actuar rapidamente para reponer mercaderia.

- **Ultimas Ventas**: Las 5 ventas mas recientes con su numero, cliente, total, estado, hora y forma de pago.

- **Ventas por Hora** (solo disponible en el periodo "Hoy"): Muestra la distribucion de ventas por hora del dia para identificar los horarios de mayor actividad.

- **Promociones mas utilizadas**: Las 5 promociones que mas se aplicaron en el periodo, con la cantidad de usos y el descuento total que generaron.

- **Comprobantes Fiscales**: Detalle de facturas emitidas agrupadas por tipo (Factura A, Factura B, etc.), mostrando la cantidad, el total neto gravado y el IVA.

- **Cobros de Cuenta Corriente**: Resumen de cobros incluyendo intereses aplicados, descuentos y saldo a favor generado.

- **Estado de Cajas**: Lista de todas las cajas de la sucursal con su estado (abierta/cerrada) y saldo actual.

### Como interpretar cada indicador

- Los valores en **verde** indican resultados positivos (ventas subieron, pagos recibidos).
- Los valores en **rojo** indican valores a prestar atencion (ventas canceladas, stock critico, diferencias de caja).
- La **variacion porcentual** compara el periodo actual con el periodo anterior de igual duracion. Por ejemplo, si selecciona "Hoy", compara con el dia anterior.

> **Consejo**: Revise el dashboard al inicio de cada dia para tener una vision clara de como va el negocio. Preste especial atencion a las alertas de stock.

---

## 3. Ventas

### 3.1 Nueva Venta (Punto de Venta)

La pantalla de Nueva Venta es el punto de venta (POS) del sistema. Es la herramienta principal para registrar ventas a clientes.

#### Requisito previo: Caja operativa

Para realizar ventas, la caja seleccionada debe estar abierta con un turno activo. Si la caja no esta operativa, el sistema mostrara un aviso indicando el problema y ofrecera opciones para resolverlo (abrir turno, activar caja o seleccionar otra caja).

#### Estructura de la pantalla

La pantalla se divide en dos columnas:

- **Columna izquierda (75%)**: Busqueda de articulos y detalle del carrito (lista de items agregados).
- **Columna derecha (25%)**: Selector de cliente, configuracion de la venta (lista de precios, forma de venta, forma de pago, cuotas) y resumen de totales con el boton de cobro.

#### Como buscar articulos

En la parte superior izquierda encontrara el campo **"Buscar Articulo"**. Puede buscar de las siguientes formas:

1. **Por nombre**: Escriba al menos 3 caracteres del nombre del articulo. La busqueda es inteligente y acepta multiples palabras (por ejemplo, "coca 2L" buscara articulos cuyo nombre contenga "coca" Y "2L").
2. **Por codigo**: Escriba el codigo interno del articulo.
3. **Por codigo de barras**: Escriba o escanee el codigo de barras en el campo dedicado "Cod. Barra" ubicado a la derecha del buscador principal.
4. **Por categoria**: La busqueda tambien encuentra coincidencias en el nombre de la categoria.

Al escribir, aparecera un desplegable con los resultados. Use las flechas del teclado para navegar entre los resultados y presione **Enter** para seleccionar el articulo resaltado.

**Uso con scanner de codigos de barras:** El sistema detecta automaticamente cuando se esta usando un scanner (por la velocidad de ingreso). Los codigos escaneados se procesan en una cola interna, lo que permite escanear multiples productos rapidamente sin esperar a que el sistema procese cada uno. Si escanea el mismo producto dos veces seguidas, ambas lecturas se registran correctamente.

#### Como agregar al carrito

1. Busque el articulo como se describio anteriormente.
2. Opcionalmente, modifique la **cantidad** antes de seleccionar (use el campo "Cantidad" a la derecha del buscador, o presione la tecla `*` para saltar a ese campo).
3. Haga clic en el articulo del desplegable de resultados o presione Enter.
4. El articulo se agregara al carrito con la cantidad indicada.
5. Si el articulo tiene **opcionales** configurados (por ejemplo, "Con queso", "Tamano grande"), se abrira automaticamente un wizard paso a paso para que usted seleccione las opciones deseadas para cada grupo de opcionales.

> **Nota:** Al seleccionar un articulo desde el buscador, el primer clic agrega el articulo y bloquea momentaneamente los clics siguientes hasta que el modal se cierra. Esto evita duplicados por doble-clic o clics rapidos accidentales.

**Articulos pesables:** Si el articulo esta marcado como pesable (productos que se venden por peso como carnes, frutas, quesos), al seleccionarlo se abrira un modal especial donde puede:
- Ingresar la **cantidad** (en la unidad de medida del articulo: kg, gr, lt, etc.) y el sistema calcula automaticamente el valor.
- O ingresar el **valor** ($) y el sistema calcula automaticamente la cantidad.
Los dos campos estan sincronizados: al modificar uno, el otro se actualiza en tiempo real. Para ingresar decimales puede usar tanto punto (`.`) como coma (`,`) como separador. Presione Enter o el boton "Agregar" para confirmar.

**En el carrito, para cada articulo puede:**

- **Modificar la cantidad**: Use los botones + y - junto a la cantidad, o edite el numero directamente.
- **Ajustar el precio manualmente**: Junto al precio unitario de cada item aparecen dos botones pequenos:
  - **$** (boton azul): Permite establecer un precio fijo diferente al de lista.
  - **%** (boton verde): Permite aplicar un descuento porcentual al articulo.

  Al hacer clic, aparecera un popover donde ingresa el valor y presiona "OK" o Enter. Para quitar un ajuste manual, haga clic en la etiqueta violeta "manual" que aparece en lugar de los botones.

- **Editar opcionales**: Si el articulo tiene opcionales seleccionados, aparecera un icono de lapiz para modificarlos.
- **Eliminar**: Haga clic en el icono de papelera rojo a la izquierda del item.

#### Promociones

El sistema aplica automaticamente las promociones configuradas. Al agregar articulos al carrito, el sistema calcula en tiempo real:

- **Promociones especiales** (NxM, combos, menus): Se muestran con fondo verde en la fila del articulo, indicando cuantas unidades estan en promocion (por ejemplo "2/3" significa 2 de 3 unidades consumidas por la promo). El nombre de la promocion se muestra debajo del articulo.

- **Promociones comunes** (descuento porcentual, monto fijo, etc.): Se muestran con un badge azul en la columna "Promo" indicando el monto descontado.

Las promociones aplicadas se resumen en un panel debajo del carrito, mostrando el nombre de cada promocion y su descuento.

#### Invitar / Cortesia

El sistema permite marcar items o la venta completa como **cortesia** (regalo al cliente). El consumo se registra con trazabilidad completa (stock, motivo, usuario), pero el total cobrable pasa a $0.

**Permisos requeridos:**
- `func.ventas.invitar_renglon` -- para invitar items individuales.
- `func.ventas.invitar_venta` -- para invitar la venta completa.

**Invitar un item individual:**

Junto a los botones de ajuste de precio de cada articulo en el carrito aparece un boton de regalo (icono regalo). Al hacer clic:
- Si el item **no esta invitado**: se abre un mini-modal con un campo de motivo (obligatorio, texto libre, maximo 500 caracteres). Al confirmar, el item queda con precio $0, se muestra un badge verde "Cortesia" en la fila, y el precio original aparece tachado al lado.
- Si el item **ya esta invitado**: se abre un mini-modal de confirmacion "Quitar invitacion". Al confirmar, el item recupera su precio original y vuelve al motor de promociones.
- Sin permiso: el boton aparece deshabilitado.

**Invitar la venta completa:**

En la columna derecha, junto al boton "Descuentos", aparece el boton **"Invitar / Cortesia"**:
- Si **ningun item** esta invitado: al hacer clic se abre un modal con campo de motivo obligatorio. Al confirmar, todos los items quedan invitados con ese mismo motivo.
- Si **todos los items** estan invitados: al hacer clic se abre un modal de confirmacion para quitar la cortesia a todos de una vez.

**Indicadores visuales:**

- Cada item invitado muestra badge verde "Cortesia", precio original tachado y "$0" en la columna de precio.
- El panel de totales muestra la linea adicional "Total invitado: $X" con el monto monetario regalado.
- Una venta donde todos los items estan invitados se procesa sin requerir forma de pago (total cobrable = $0).

**Comportamiento:**

- El motivo de la invitacion es obligatorio. No se puede confirmar sin ingresar texto.
- Los items invitados quedan **excluidos del motor de beneficios**: no participan en promociones NxM, combos, calculos de monto minimo de cupon ni en el descuento general.
- El stock se descuenta normalmente (el bien fue consumido aunque no se cobro).
- Una venta totalmente invitada (`es_invitacion_total=true`) se procesa sin afectar el saldo de caja (no genera movimiento de caja ni se requiere caja abierta para el cobro). El registro de la venta queda en estado "completada" para trazabilidad.
- Las ventas con cortesia aparecen con badge verde "Cortesia" en el listado de ventas.

#### Selector de cliente

En la columna derecha, el campo **"Cliente"** le permite buscar y seleccionar un cliente:

1. Escriba el nombre, CUIT o telefono del cliente.
2. Seleccione el cliente del desplegable.
3. Una vez seleccionado, vera el nombre, la condicion de IVA y el tipo de factura que se emitira (A, B o C).
4. Si no selecciona ningun cliente, la venta se registra como "Consumidor Final".

El boton **"+"** junto al campo de busqueda permite dar de alta un nuevo cliente sin salir del punto de venta. El formulario de alta rapida ofrece dos modos:
- **Manual**: Ingrese nombre, razon social, CUIT, email, telefono, direccion y condicion de IVA.
- **Por CUIT (consulta ARCA)**: Ingrese el CUIT y el sistema consultara automaticamente el padron de ARCA para completar los datos fiscales (razon social, condicion de IVA).

Para quitar el cliente seleccionado, haga clic en la X junto a su nombre.

#### Formas de pago

Debajo del cliente encontrara los selectores de:

- **Lista de Precios**: Seleccione la lista de precios a aplicar. La "Base" es la lista por defecto. Otras listas pueden tener ajustes (recargos o descuentos) sobre los precios base.

- **Forma de Venta**: Indica como se realizo la venta (Local, Delivery, etc.).

- **Forma de Pago**: Seleccione como pagara el cliente (Efectivo, Debito, Credito, Mixta, etc.). Cada forma de pago puede tener un recargo o descuento asociado que se aplica automaticamente al total.

- **Cuotas**: Si la forma de pago seleccionada permite cuotas (por ejemplo, tarjeta de credito), aparecera un selector de cuotas mostrando la cantidad de cuotas disponibles, el recargo de cada opcion y el valor de cada cuota.

#### Venta a cuenta corriente

Para realizar una venta a cuenta corriente:

1. Seleccione un cliente que tenga cuenta corriente habilitada.
2. Al seleccionar una forma de pago que lo permita, el sistema le permitira cargar la venta a la cuenta del cliente.
3. La deuda quedara registrada y podra cobrarse posteriormente desde el modulo de Cobranzas.

#### Pago mixto

Si selecciona una forma de pago de tipo "Mixta", al presionar el boton de cobro se abrira un modal donde podra desglosar el pago en multiples formas:

1. Seleccione una forma de pago para la primera porcion.
2. Indique el monto.
3. Haga clic en "Agregar".
4. Repita para las demas porciones.
5. El sistema calculara automaticamente los ajustes (recargos/descuentos) de cada forma de pago.
6. Cuando el monto pendiente llegue a cero, podra confirmar la venta.

Si aplica una percepcion fiscal (CUIT agente + cliente RI), el modal de pago mixto muestra ademas:
- Una linea **"Percepcion (se cobra con la parte fiscal): +$Y"** en azul.
- Un recuadro **"Total a pagar"** con el monto final incluyendo la percepcion.

La percepcion se distribuye automaticamente entre los pagos del desglose que tienen factura fiscal habilitada, en proporcion a los bienes de cada pago. El monto que se envia al proveedor de pago (por ejemplo, MercadoPago QR) ya incluye la percepcion que le corresponde: lo cobrado por cada medio coincide exactamente con lo facturado.

**Pago mixto con QR MercadoPago**: si el desglose incluye una forma de pago con integracion QR (por ejemplo, parte en efectivo + parte con QR Mercado Pago), el sistema solo cobra la porcion de integracion a traves del QR. El flujo es el siguiente:

1. Arme el desglose con todas las formas de pago. La porcion con QR indica el monto exacto a cobrar por esa via.
2. Al confirmar el desglose, el sistema abre el **modal "Esperando pago"** por el monto de la porcion QR.
3. El cliente escanea el QR y confirma el pago desde su app.
4. Una vez confirmado el cobro QR, el sistema materializa la venta con todos los pagos del desglose (efectivo, QR, etc.) en un solo paso.
5. Los otros medios de pago del desglose (por ejemplo, efectivo) quedan registrados en la venta junto con el cobro QR.

> **Importante**: una venta que incluye un cobro QR confirmado **no puede anularse ni modificarse** (ver restriccion mas abajo). Si el QR se cancela o expira antes de confirmarse, la venta no se crea y el desglose queda disponible para reintentar.

#### Facturacion fiscal

Si la sucursal tiene facturacion fiscal habilitada, la venta puede generar automaticamente un comprobante fiscal (factura A, B o C segun el cliente). El tipo de comprobante se muestra junto al nombre del cliente seleccionado.

Puede activar o desactivar la emision de factura fiscal mediante un checkbox en el resumen de totales. Si la sucursal esta configurada para facturacion automatica, la factura se emitira por defecto.

#### Percepciones fiscales aplicadas

Cuando el CUIT configurado en el punto de venta de la caja actua como **agente de percepcion** y el cliente es **Responsable Inscripto**, el sistema calcula automaticamente la percepcion (IIBB y/o IVA) sobre la base gravada de la venta. Esta percepcion:

- Se suma al total que el cliente debe pagar (lo que cobra el cajero == lo que se factura a AFIP).
- Se muestra como una linea adicional **"Percepcion (X%): +$Y"** en el resumen de totales, debajo de los descuentos y ajustes de forma de pago.
- Se informa a AFIP en el campo `Tributos[]` del comprobante fiscal.
- Se registra automaticamente en el ledger fiscal del comercio como percepcion aplicada (deuda a depositar ante el fisco).

Si el cliente no es Responsable Inscripto, o si el CUIT del punto de venta no esta configurado como agente de percepcion, no se calcula ningun adicional y el flujo es el habitual.

**Reactividad**: la percepcion se recalcula automaticamente al seleccionar o cambiar el cliente, al tildar o destildar el checkbox de factura fiscal, y al modificar el carrito o la forma de pago.

**Notas de credito**: si se anula una venta que tenia percepcion aplicada, la nota de credito incluye el mismo desglose de tributos del comprobante original.

#### Resumen de totales

En la parte inferior de la columna derecha se muestra el resumen:

- **Subtotal**: Suma de todos los items sin descuentos.
- **Desc. promos**: Total de descuentos aplicados por promociones.
- **Total productos**: Subtotal menos descuentos.
- **Recargo/Descuento por forma de pago**: Si la forma de pago tiene un ajuste asociado.
- **Recargo por cuotas**: Si se seleccionaron cuotas con recargo.
- **Percepcion (X%)**: Solo aparece si el CUIT del punto de venta actua como agente de percepcion y el cliente es Responsable Inscripto. Muestra el monto adicional que se percibe y se suma al total.
- **TOTAL**: Monto final a cobrar (incluye la percepcion si corresponde).
- **Desglose de IVA**: Puede expandir esta seccion para ver el detalle de IVA.

#### Proceso de cobro

1. Verifique que todos los datos estan correctos (articulos, cantidades, precios, cliente, forma de pago).
2. Presione el boton **"Cobrar"** (o la tecla **F2**).
3. Segun la forma de pago:
   - **Efectivo**: Se abrira un modal para ingresar el monto recibido y calcular el vuelto.
   - **Moneda extranjera**: Se abrira un modal con el tipo de cambio vigente para calcular el equivalente.
   - **Integracion de pago (QR dinamico o estatico)**: Si la forma de pago tiene una integracion de cobro configurada (por ejemplo, Mercado Pago QR), se abrira el **modal de espera de pago** (ver abajo).
   - **Otros**: La venta se procesa directamente.
4. Confirme la operacion.
5. El sistema registrara la venta, actualizara el stock, registrara el movimiento de caja y, si corresponde, emitira el comprobante fiscal.

#### Cobro con integracion de pago (QR dinamico, QR estatico o QR de monto libre)

Cuando la forma de pago seleccionada tiene una integracion de cobro activa (por ejemplo, Mercado Pago QR), el flujo es diferente al cobro tradicional: **el pago se confirma primero y el comprobante se crea despues**. Este mecanismo aplica en todos los puntos de cobro del sistema: Nueva Venta, Pedidos por Mostrador (desglose desde el editor o cobro rapido) y confirmacion de pagos planificados.

Existen tres modos de cobro QR, configurables por forma de pago (ver seccion 12.4):

- **QR dinamico**: el sistema genera un QR unico por venta con el monto exacto. El cajero o el cliente lo escanean desde la pantalla.
- **QR estatico**: se usa el QR fisico impreso del mostrador (el que MP asigna al POS de la caja al sincronizarla). El sistema empuja el monto a ese POS y el cliente escanea el QR impreso que ya esta fijo en el mostrador. No hay una imagen de QR nueva en pantalla: el modal muestra la imagen del QR del POS para que el cajero la identifique y le indique al cliente donde escanear.
- **QR de monto libre**: se muestra la imagen del QR "Cobrar" que el comercio cargo en la configuracion de la forma de pago. El cliente escanea ese QR con su app de Mercado Pago e ingresa el monto por su cuenta. No hay deteccion automatica (el sistema no crea ninguna orden en MP): el cajero confirma el pago manualmente una vez que ve la acreditacion en su app de MP.

**Flujo paso a paso (Nueva Venta) — modos QR dinamico y QR estatico:**

1. Seleccione la forma de pago con integracion (identificada visualmente en la lista) y presione **"Cobrar"** (F2).
2. El sistema registra la orden de cobro y abre el **modal "Esperando pago"**.
3. El modal muestra:
   - El monto a cobrar.
   - **QR dinamico**: el codigo QR generado para esa venta (el cliente lo escanea desde la pantalla).
   - **QR estatico**: la imagen del QR impreso del POS de la caja (el cliente escanea el QR fisico del mostrador).
   - Un countdown de expiracion.
4. El cliente escanea el QR con su app (Mercado Pago u otra billetera compatible) y confirma el pago.
5. El sistema detecta automaticamente la confirmacion y:
   - Cierra el modal.
   - Registra la venta con todos sus datos.
   - Asocia la transaccion de cobro a la venta.

   Cuando el comercio tiene el **webhook de Mercado Pago configurado**, la confirmacion es **instantanea**: MP avisa al sistema en tiempo real y el modal se cierra al instante sin esperar ningun ciclo de consulta. Si el webhook no esta configurado, el sistema consulta el estado del pago cada 3 segundos como respaldo.

6. Si el cajero presiona **"Cancelar cobro"** o el QR expira sin pago, el modal se cierra y no se crea ninguna venta. Si el tiempo de espera vence sin que el sistema detecte el pago automaticamente, la orden expira y el modal lo indica.

**Confirmacion manual (modos QR dinamico y QR estatico, solo si el usuario tiene el permiso habilitado):** si el sistema no detecto el pago automaticamente (por ejemplo, si el webhook no llego y el countdown ya termino), aparece un enlace discreto **"El pago no se detecto automaticamente"** en la parte inferior del modal. Al hacer clic, se abre un panel de advertencia ambar con el texto "Confirma solo si verificaste que el cliente pago". Presionar **"Si, el cliente pago"** confirma el cobro manualmente. Esta accion queda registrada con el usuario que la realizo para auditoria. El botton **"Volver"** descarta el panel sin hacer nada.

**Flujo paso a paso (Nueva Venta) — modo QR de monto libre:**

1. Seleccione la forma de pago configurada en modo "QR de monto libre" y presione **"Cobrar"** (F2).
2. El sistema abre el **modal "Esperando pago"** mostrando:
   - El monto a cobrar (referencia para el cajero).
   - La imagen del QR "Cobrar" de Mercado Pago que el comercio cargo en la configuracion.
   - Un mensaje indicando que el cliente debe escanear e ingresar el monto en su app.
   - Un countdown de expiracion (referencia; no hay deteccion automatica).
3. El cajero le muestra el QR al cliente. El cliente lo escanea con su app de Mercado Pago, ingresa el monto que el cajero le indica y confirma el pago desde su app.
4. El cajero verifica en su propia app de Mercado Pago que el pago fue acreditado.
5. El cajero presiona **"Confirmar pago recibido"** (boton verde, siempre visible). El sistema registra la venta, asocia la transaccion y cierra el modal. Esta confirmacion queda auditada.

> El boton "Confirmar pago recibido" en el modo QR de monto libre lo puede presionar cualquier operario de la caja sin necesidad de permisos adicionales, ya que es la unica forma de cerrar el cobro (no hay deteccion automatica ni webhook). Es distinto al panel de fallback de los otros modos, que si requiere el permiso `integraciones_pago.confirmar_manual`.

**Si la facturacion fiscal falla con el cobro ya confirmado**: el pago queda registrado igual (el cobro ya entro) pero la factura queda pendiente de emision. Aparece un aviso indicando que el cobro fue exitoso y que la facturacion puede reintentarse desde **Cajas → Pagos Pendientes de Facturacion**.

> **Si la caja usa pantalla cliente (solo modo QR dinamico)**: el QR generado se muestra automaticamente en el segundo monitor orientado al cliente (ver seccion "Pantalla orientada al cliente" mas abajo). El cajero ve un modal compacto indicando que el QR esta en la pantalla del cliente.

#### Pantalla orientada al cliente (segundo monitor)

Si la caja activa tiene habilitada la opcion "Usa pantalla cliente" (configurable en Configuracion → Empresa → Cajas), aparece un **boton flotante** en la parte inferior de la pantalla de nueva venta con el texto **"Conectar pantalla cliente"**.

**Configurar y usar la pantalla cliente:**

1. Conecte el segundo monitor al equipo y configure el sistema operativo en modo **"Extender"** (no duplicar).
2. Abra Chrome o Edge en el puesto de cobro.
3. Haga clic en el boton **"Conectar pantalla cliente"** (borde inferior, centrado). El sistema abrira una ventana nueva posicionada en el segundo monitor.
4. La ventana del cliente mostrara el logo y nombre de la sucursal con el texto "Listo para cobrar" mientras no haya cobro en curso. La apariencia visual (colores, animacion, tamano del logo) sigue la configuracion de personalizacion de la sucursal (ver seccion 12.12).
5. Al iniciar un cobro con QR, el codigo QR se mostrara automaticamente en la pantalla del cliente a pantalla completa. El cajero vera un modal compacto.
6. Al completarse o cancelarse el cobro, la pantalla del cliente vuelve al estado de espera.

El boton cambia su apariencia segun el estado de conexion:
- **Gris oscuro**: pantalla no conectada. Clic conecta (en navegador normal, abre una ventana nueva posicionada en el segundo monitor).
- **Verde con punto pulsante**: pantalla conectada. Clic desconecta.

> **Si usa el sistema como app instalada (PWA)**: al hacer clic en "Conectar pantalla cliente" no se abre ninguna ventana. En su lugar, el boton queda gris con el texto "Pantalla cliente desconectada" como indicador de estado. Para usar la pantalla del cliente en este modo, la app "Pantalla Cliente" debe abrirse manualmente desde su icono de app (debe estar instalada; ver seccion 12.12). Una vez que esa app este abierta, la deteccion es automatica: el boton pasara a verde en pocos segundos sin ninguna accion adicional del cajero.

**Botones flotantes en la ventana del cliente:**

Dentro de la ventana de la pantalla cliente aparecen tres botones flotantes en la esquina inferior derecha:
- **Pantalla completa**: activa o desactiva el modo fullscreen de la ventana.
- **Enviar a la 2da pantalla**: mueve la ventana al segundo monitor y la pone en fullscreen automaticamente (requiere que el navegador haya obtenido el permiso "Gestion de ventanas").
- **Instalar pantalla cliente**: instala la pantalla cliente como una app PWA independiente en el sistema operativo, con icono propio en la barra de tareas. Util para tener la pantalla del cliente siempre disponible sin necesidad de una pestana del navegador. Solo aparece cuando la pagina se abre en una ventana normal del navegador (no cuando ya corre como app instalada).

> Requiere Chrome o Edge con permisos de "Gestion de ventanas" (el navegador solicitara el permiso la primera vez que use la funcion de posicionamiento automatico). Si el navegador no soporta la API, la ventana se abre de todas formas y puede arrastrarse manualmente al segundo monitor.

#### Limpiar carrito

Presione **F3** o use el boton correspondiente para vaciar el carrito. El sistema le pedira confirmacion antes de borrar todos los items.

#### Consulta de precios (modo consulta)

Active el modo consulta presionando el boton con el icono de billete (o **Ctrl+3**). En este modo, al buscar y seleccionar un articulo, en lugar de agregarlo al carrito se abrira un modal mostrando toda la informacion de precios del articulo (precio base, precio con lista aplicada, IVA). Para salir del modo consulta, presione **Esc** o haga clic en la X junto al buscador.

#### Buscar en detalle

Active el modo de busqueda en detalle presionando el boton con icono de lista (o **Ctrl+4**). En este modo, al buscar un articulo, se resaltara en el carrito si ya esta agregado. Util para ventas con muchos items.

#### Agregar concepto

El boton con icono de billete verde (o **Ctrl+5**) permite agregar un concepto libre al carrito (por ejemplo, un servicio, cargo o item que no es un articulo del inventario). Se abrira un modal donde:

- **Descripcion**: Texto que identifica el concepto (obligatorio).
- **Categoria**: Categoria opcional del concepto. Se usa para determinar el IVA correspondiente.
- **Importe**: Monto a cobrar por el concepto.

Al agregar el concepto, aparece en el carrito igual que un articulo. La venta se confirma normalmente; los conceptos libres no afectan el stock. En tickets e impresiones se muestra la descripcion ingresada.

#### Atajos de teclado

| Atajo | Accion |
|-------|--------|
| **Ctrl+1** | Enfocar busqueda de articulos |
| **Ctrl+2** | Enfocar campo de cantidad |
| **Ctrl+3** | Activar modo consulta de precios |
| **Ctrl+4** | Activar busqueda en detalle |
| **Ctrl+5** | Agregar concepto |
| **Ctrl+6** | Enfocar busqueda de cliente |
| **Ctrl+7** | Enfocar lista de precios |
| **Ctrl+8** | Abrir buscador avanzado de articulos |
| **Ctrl+9** | Agregar articulo rapido |
| **F2** | Iniciar cobro |
| **F3** | Limpiar carrito |
| **F4** | Abrir modal de descuentos |
| **\*** (asterisco) | Saltar entre campo de busqueda y campo de cantidad |
| **Flechas arriba/abajo** | Navegar resultados de busqueda |
| **Enter** | Seleccionar articulo resaltado |
| **Esc** | Desactivar modo consulta o busqueda |

---

### 3.2 Listado de Ventas

Esta pantalla muestra todas las ventas realizadas, permitiendo buscarlas, filtrarlas, ver sus detalles, anularlas o reimprimir comprobantes.

#### Que ve al entrar

- Un encabezado con el titulo "Listado de Ventas" y un boton **"Nueva Venta"** que lo lleva al punto de venta.
- Un panel de filtros.
- Una tabla con las ventas.

#### Filtros disponibles

Los filtros se organizan en dos grupos:

**Filtros principales** (siempre visibles):
- **Buscar**: Por numero de venta, numero de ticket, nombre de cliente o numero de factura fiscal.
- **Fecha Desde / Fecha Hasta**: Rango de fechas para acotar la busqueda.
- **Estado**: Todas, Completada, Pendiente, Cancelada.
- **Forma de Pago**: Permite filtrar por la forma de pago utilizada.

**Filtros avanzados** (colapsables, se despliegan con el boton "Mas filtros"):
- **Comprobante Fiscal**: Todas, Con factura, Sin factura.
- **Caja**: Caja actual o Todas mis cajas.

- **Limpiar filtros**: Restaura todos los filtros a sus valores por defecto.

En dispositivos moviles, los filtros se colapsan detras de un boton "Filtros" para ahorrar espacio.

#### Informacion de cada venta

La tabla muestra para cada venta:

- Numero de venta y fecha/hora.
- Nombre del cliente (o "Consumidor Final").
- Forma de pago utilizada.
- Total de la venta.
- Estado (completada, pendiente, cancelada) con un badge de color.
- **Badge "Cortesia"** (verde emerald): se muestra cuando la venta es una invitacion total (`es_invitacion_total=true`). Permite identificar rapidamente las ventas de cortesia sin necesidad de abrir el detalle.
- Si tiene comprobante fiscal, se indica el tipo y numero.
- Botones de accion.

#### Acciones disponibles

Para cada venta en la lista, puede:

- **Ver detalle**: Abre un modal con toda la informacion de la venta: items vendidos con cantidades y precios, descuentos aplicados, datos del cliente, forma de pago, observaciones, comprobantes fiscales asociados, y los movimientos de caja y stock generados.

- **Reimprimir**: Permite reimprimir el ticket de la venta o el comprobante fiscal. Se abre un modal de confirmacion donde puede seleccionar que tipo de documento reimprimir.

- **Anular venta**: Si tiene el permiso correspondiente, puede cancelar una venta. El sistema le mostrara un modal donde debe ingresar un motivo de anulacion. El modal muestra el estado detallado de cada pago antes de confirmar:
  - Por cada pago se indica su estado: Activo, Anulado, Facturado, Pendiente de facturar o Error ARCA, junto con el numero de comprobante y el monto facturado si corresponde.
  - Al confirmar la anulacion:
    - Se revierte el stock (los articulos vuelven al inventario).
    - Se registra un contraasiento en la caja.
    - Si tiene factura fiscal, se emite automaticamente una Nota de Credito.
    - La venta queda marcada como "Cancelada".

> **Importante**: La anulacion de una venta es irreversible. Verifique cuidadosamente antes de confirmar.

> **Restriccion por cobro QR confirmado**: Si la venta tiene un cobro realizado por integracion (QR MercadoPago) ya confirmado, el sistema **no permitira anularla**. Vera el mensaje: "No se puede anular ni modificar: esta venta tiene un cobro por integracion (QR) ya confirmado. La devolucion debe hacerse desde el proveedor de pago." Esto se debe a que el dinero ya fue acreditado en la cuenta de MercadoPago y el sistema aun no tiene mecanismo de devolucion automatica. La anulacion del comprobante fiscal (parte fiscal unicamente) si sigue siendo posible y no se ve afectada por esta restriccion.

### 3.3 Programa de Puntos

**Ruta**: Ventas → Programa de Puntos

Sistema de fidelizacion que permite a los clientes acumular puntos con sus compras y canjearlos como descuento o por articulos especificos.

#### Configuracion (Tab Configuracion)
- **Activar/desactivar** el programa globalmente
- **Modo**: Global (saldo unico) o Por Sucursal (saldo independiente por sucursal)
- **Monto por punto**: Cuantos $ debe gastar el cliente para ganar 1 punto
- **Valor del punto**: Cuanto vale 1 punto en $ al momento de canjear
- **Minimo para canje**: Cantidad minima de puntos para habilitar el canje
- **Redondeo**: Como se redondean los puntos fraccionarios (hacia abajo, al mas cercano, hacia arriba)
- **Activacion por sucursal**: Cada sucursal puede tener el programa activo o inactivo

#### Consulta de Puntos (Tab Consulta)
- Buscar un cliente por nombre, CUIT o telefono
- Ver saldo actual, puntos acumulados historicos y puntos canjeados historicos
- Historial de movimientos paginado con filtros por tipo y rango de fechas

#### Ajustes Manuales (Tab Ajustes)
- Requiere permiso especial "Ajuste manual de puntos"
- Permite sumar o restar puntos a un cliente con motivo obligatorio
- Queda registro de quien hizo el ajuste y por que

#### Como funcionan los puntos en el POS
- Al seleccionar un cliente en Nueva Venta, aparece un badge con su saldo de puntos
- Boton **Descuentos** (F4) abre el modal de Descuentos y Beneficios:
  - **Descuento general**: Aplicar un % o monto fijo de descuento a toda la venta
  - **Aplicar cupon**: Ingresar codigo de cupon para validar y aplicar descuento
  - **Canjear puntos**: Indicar monto $ a pagar con puntos
- Boton **Pts** en cada renglon: para articulos canjeables con puntos
- Los puntos se acumulan automaticamente al completar la venta
- El ticket muestra puntos ganados, usados y saldo actual

### 3.4 Cupones

**Ruta**: Ventas → Cupones

Gestion de cupones de descuento para clientes.

#### Listado (Tab Listado)
- Ver todos los cupones con filtros por tipo (puntos/promocional), estado (vigente/inactivo/vencido/agotado) y busqueda por codigo
- Editar, activar o desactivar cupones existentes

#### Crear Cupon (Tab Crear)
- **Tipo Promocional**: Cupon libre, lo puede usar cualquier cliente
- **Tipo Desde Puntos**: El cliente "compra" el cupon con sus puntos. Solo el puede usarlo.
- **Modo descuento**: Porcentaje o monto fijo
- **Aplica a**: Total de la venta o articulos especificos
  - Si aplica a articulos especificos, se puede definir la **cantidad** de unidades que cubre por articulo (vacio = todas las unidades)
  - Ejemplo: cupon "1 hamburguesa gratis" → si hay 3 hamburguesas en la venta, solo descuenta 1
- **Formas de pago validas**: Se puede restringir a que formas de pago aplica (ej: solo efectivo y transferencia). Si no se selecciona ninguna, aplica a todas. Si la venta se paga con una forma no valida, el cupon no se puede usar.
- **Uso maximo**: Ilimitado o cantidad fija de usos
- **Fecha vencimiento**: Opcional

#### Historial de Uso (Tab Historial)
- Ver todos los usos de cupones con fecha, venta, cliente, sucursal y monto descontado

### 3.5 Modificar Pagos en Ventas Registradas

**Ruta**: Ventas → Listado de Ventas → Ver detalle → boton lapiz sobre cada pago

Permite dividir un pago existente de una venta ya registrada en uno o varios pagos nuevos con formas de pago distintas, sin necesidad de anular la venta completa. El **total de la venta es siempre inmutable**: la suma de los pagos nuevos debe ser igual al monto del pago original que se modifica.

> **Importante**: Requiere el permiso **"Cambiar forma de pago en ventas registradas"** (`func.cambiar_forma_pago_venta`). Para operar sobre ventas de turnos ya cerrados se necesita ademas **"Cambiar forma de pago sobre turnos cerrados"** (`func.cambiar_forma_pago_turno_cerrado`).

#### Desglose de pagos en el detalle de venta

En el modal de detalle de venta, la seccion de pagos muestra los pagos en formato card (en movil) o tabla (en escritorio). Cada pago activo tiene un **boton lapiz azul** (Modificar). Los pagos anulados o con cobros CC aplicados muestran el boton deshabilitado con un tooltip explicando el motivo.

Si el pago incluye una percepcion fiscal (cliente RI, CUIT agente), el detalle del cobro muestra una linea adicional **"Percepcion: +$Y"** en amarillo debajo del monto base. En la vista movil aparece como linea separada dentro de la card del pago; en escritorio aparece como texto secundario bajo el monto final de la columna importe.

Badges de estado de facturacion por pago:
- **Facturado**: el pago tiene comprobante fiscal emitido (con numero de comprobante).
- **Pendiente de facturar**: la FC nueva no pudo emitirse por un error de ARCA; queda en cola de reintento. Incluye boton "Reintentar" (requiere permiso `func.reintentar_facturacion`).
- **Error ARCA**: se intento reintentar pero el usuario decidio sacarlo del circuito automatico.
- **Sin facturar**: el pago no tiene facturacion asociada.

#### Bloqueos: cuando no se puede modificar

El boton de modificar queda deshabilitado si:
- El pago esta anulado.
- El pago tiene cobros de cuenta corriente ya aplicados (hay que anularlos primero desde Clientes → Cobranzas).
- La venta esta cancelada.
- La venta tiene puntos canjeados por terceros.
- El pago pertenece a un turno cerrado y no se tiene el permiso `func.cambiar_forma_pago_turno_cerrado`.
- **El pago fue cobrado por integracion QR (MercadoPago) y ya fue confirmado**: el boton aparece deshabilitado con el tooltip "No se puede modificar: este pago se cobro por integracion (QR) y ya fue confirmado. La devolucion debe hacerse desde el proveedor de pago." La devolucion debe gestionarse directamente desde el panel de MercadoPago.

#### Cobros CC aplicados

Si un pago CC tiene cobros imputados, debajo del pago aparece la seccion **"Ver cobros aplicados"** con fecha, numero de recibo, monto aplicado, intereses y saldo pendiente.

#### Flujo paso a paso: modificar un pago (division mixta)

1. Haga clic en el **boton lapiz** del pago a modificar.
2. Se abre el **modal "Modificar forma de pago"** con las siguientes secciones:
   - **Pago original**: muestra en modo solo-lectura la forma de pago, monto, condicion fiscal, turno y usuario.
   - **Desglose de pagos nuevos**: grilla de formas de pago con buscador (identico al selector de Nueva Venta). Por cada pago nuevo en el desglose se puede configurar:
     - Monto asignado.
     - Checkbox **"Aplicar ajuste"**: aplica el recargo/descuento de la forma de pago (por defecto OFF).
     - Checkbox **"Facturar este pago"**: indica si se debe emitir comprobante fiscal para este pago (por defecto segun la configuracion de la forma de pago).
     - Cuotas (si la forma de pago lo permite).
     - El total del desglose debe igualar exactamente el monto del pago original; el modal muestra la diferencia en tiempo real.
   - Se pueden agregar mas filas para dividir el pago en 3 o mas formas de pago.
3. Complete el campo **"Motivo del cambio"** (minimo 10 caracteres, obligatorio).
4. Si el pago pertenece a un **turno cerrado**, aparece un banner ambar (ver abajo).
5. Haga clic en **"Confirmar cambio"**.
6. El sistema procesa la operacion en dos fases (ver "Emision fiscal y fases" mas abajo).
7. Al completarse, un toast verde confirma el resultado. Si la Fase B fallo, el toast es rojo con el error de ARCA.

#### Emision fiscal: regla binaria

El sistema decide automaticamente si emitir documentos fiscales basandose en una comparacion simple:

- **monto_facturado_viejo == monto_facturado_nuevo**: no se emite ningun documento fiscal. Si el pago viejo tenia comprobante, los pagos nuevos con flag "Facturar" heredan ese `comprobante_fiscal_id`.
- **monto_facturado_viejo != monto_facturado_nuevo**: se emite siempre una **NC** (por el monto facturado del pago viejo) y una **FC nueva** (por la suma de pagos nuevos con "Facturar" tildado). Esto ocurre independientemente del flag de facturacion automatica de la sucursal; el usuario ya decidio tildar o no cada pago.

Para omitir la emision de NC cuando la diferencia es positiva se requiere el permiso `func.modificar_pagos_sin_nc`.

#### Fases de procesamiento

**Fase A (atomica)**: anulacion del pago original + reversiones contables (caja, cuenta empresa, cuenta corriente si aplica) + creacion de pagos nuevos + emision de NC si corresponde. Si cualquier paso falla, se hace rollback total y no cambia nada.

**Fase B (post-commit)**: emision de la FC nueva sobre los pagos con "Facturar" tildado. Si ARCA falla en esta fase, los pagos quedan en estado `pendiente_de_facturar` y aparece un toast rojo con el mensaje de error. Los pagos siguen siendo validos contablemente; solo queda pendiente la facturacion fiscal.

#### Turno cerrado: ajuste post-cierre

Si el pago a modificar pertenece a un turno ya cerrado, aparece un **banner ambar** indicando que:
- Los movimientos de reversion y los nuevos movimientos se asignan al turno actual abierto.
- El cierre historico no se modifica.
- La operacion queda registrada en el **Reporte de Ajustes Post-Cierre** (ver seccion 6.5).

#### Historial de cambios en el modal de detalle

Al final del modal de detalle de la venta aparece la seccion colapsable **"Historial de cambios en pagos"** con una linea de tiempo de cada modificacion:
- Tipo de operacion, fecha y hora, usuario.
- Descripcion narrativa (por ejemplo, "Cambio de Debito Visa $300 a Transferencia Galicia $300").
- Motivo ingresado.
- Links a NC o FC generadas si aplica.
- Badge "Post-cierre" si afecto un turno cerrado.

### 3.6 Reportes de Ventas

**Ruta**: Ventas → Reportes
**Permiso requerido**: `func.ver_reportes_ventas`

Pantalla de analisis de ventas acotada a la sucursal activa. Permite generar reportes sobre el historico de ventas por periodo. Arranca con el reporte de Corteisas; el selector de tipo queda preparado para futuros reportes bajo el mismo modulo.

> Al cambiar de sucursal o de tipo de reporte, los resultados previos se limpian automaticamente.

#### Filtros

| Filtro | Descripcion |
|---|---|
| Tipo de reporte | Selector del reporte a generar. Actualmente: "Corteisas (invitaciones)" |
| Desde | Fecha de inicio del periodo (por defecto: primer dia del mes actual) |
| Hasta | Fecha de fin del periodo (por defecto: hoy) |

Luego de configurar los filtros, hacer clic en **"Generar Reporte"** para ejecutar el analisis.

#### Reporte de Corteisas (invitaciones)

Muestra todas las cortesias (renglones marcados como invitacion con precio $0) registradas en el periodo y sucursal activos. No incluye ventas anuladas.

**KPIs de cabecera** (4 tarjetas):

| KPI | Descripcion |
|---|---|
| Total invitado | Suma monetaria de todos los montos regalados en el periodo |
| Comprobantes | Cantidad de ventas distintas que contienen al menos una cortesia |
| Renglones | Total de lineas individuales marcadas como cortesia |
| Articulos | Suma de unidades (cantidad) de items invitados |

Si no hay cortesias en el periodo, se muestra un estado vacio en lugar de los desgloses.

**Desglose por usuario que invita**: tabla ordenada de mayor a menor por monto invitado. Columnas: Usuario, Monto invitado, Renglones, Comprobantes. Si el usuario fue dado de baja del sistema aparece como "Usuario eliminado".

**Desglose por articulo invitado**: tabla ordenada de mayor a menor por monto invitado. Columnas: Articulo, Cantidad, Monto invitado, Renglones. Los conceptos libres (sin articulo del catalogo asociado) se agrupan bajo "Concepto libre".

**Listado detallado**: tabla renglon por renglon con todas las cortesias del periodo. Columnas: Fecha, Comprobante, Articulo, Cantidad, Monto, Motivo, Usuario.

---

## 4. Pedidos por Mostrador

El modulo de Pedidos por Mostrador permite gestionar pedidos que se toman en el local antes de convertirlos en venta. Es util para negocios con preparacion previa (gastronomia, panaderias, kioscos con encargos) donde el pedido existe como documento operativo independiente hasta que se cobra y factura.

Un pedido tiene dos estados independientes: el **estado del pedido** (ciclo de vida operativo) y el **estado del pago** (situacion de cobro).

### 4.1 Vistas: Lista y Kanban

**Ruta**: Menu > Pedidos por Mostrador

La pagina ocupa toda la altura de la pantalla disponible (fullscreen), igual que la vista de Nueva Venta. No hay scroll vertical de pagina; el contenido interno del area de lista o kanban scrollea de forma independiente.

El **header** es una sola fila compacta de 36px que contiene, de izquierda a derecha:

- **Contador de pedidos**: texto "{N} pedidos" con el total visible segun filtros activos.
- **Badge "X nuevos"**: aparece en amarillo pulsante cuando ingresan pedidos nuevos desde otras terminales. Click → resetea el contador y actualiza la lista.
- **Boton "X en borrador"** (solo si hay borradores): badge amarillo. Al hacer click se despliega un panel con la lista de borradores. Click fuera del panel o tecla Escape lo cierra. Si no hay borradores, el boton no aparece.
- **Chips de filtros activos**: por cada filtro activo (estado pedido, estado pago, busqueda) aparece un chip removible con el nombre del filtro. Click en la X del chip limpia ese filtro.
- **Search inline** (solo pantallas medianas y grandes): campo de busqueda directamente en el header. En mobile el campo se traslada al panel de filtros.
- **Selector "Estado del pedido"** y **Selector "Estado de pago"**: siempre visibles en la barra superior, al lado del buscador. Permiten filtrar sin abrir el panel de filtros.
- **Boton Filtros**: abre el panel colapsable que contiene exclusivamente el rango de fechas (Desde / Hasta).
- **Boton Refrescar**: icono de flecha circular. Fuerza un fetch inmediato desde el servidor (util cuando la conexion WebSocket esta caida). Durante la recarga muestra un spinner en lugar del icono.
- **Toggle Lista / Kanban**: icono de lineas horizontales = Lista / icono de grilla = Kanban. El boton activo aparece resaltado. La preferencia se guarda en el dispositivo y se restaura al volver.
- **Boton Nuevo**: abre el modal de creacion de pedido.

- **Vista Lista** (por defecto): tabla paginada con todos los estados, incluyendo Cancelado y Facturado.
- **Vista Kanban**: tablero de columnas para los estados operativos activos. Los pedidos Cancelados y Facturados no aparecen en el Kanban.

#### Atajos de teclado

| Atajo | Accion |
|-------|--------|
| `Ctrl+N` / `Cmd+N` | Abre el modal de Nuevo Pedido |
| `Ctrl+K` / `Cmd+K` | Pone el foco en el campo de busqueda |
| `/` | Pone el foco en el campo de busqueda |
| `Escape` | Cierra el panel de borradores si esta abierto |

> Los atajos `Ctrl+N`, `/` y `Escape` no se disparan si el foco esta dentro de un campo de texto.

### 4.2 Vista Lista

**Ruta**: Menu > Pedidos por Mostrador

La lista muestra todos los pedidos de la sucursal activa. Si hay una **caja activa** seleccionada, la lista se filtra automaticamente para mostrar solo los pedidos de esa caja; sin caja activa, se muestran los pedidos de toda la sucursal. Se actualiza en **tiempo real** via WebSocket (Reverb/Echo): cuando otro usuario o terminal crea, cambia el estado, cobra o cancela un pedido, la lista se refresca instantaneamente sin necesidad de recargar la pagina. Como respaldo defensivo, la pagina tambien se refresca automaticamente cada 60 segundos si la conexion WebSocket estuviera caida.

#### Badge de pedidos nuevos

Si mientras la pagina esta abierta ingresan pedidos nuevos (desde otras terminales o canales), aparece el **badge "X nuevos"** en el header con animacion pulsante. Al hacer click sobre el badge, el contador se resetea y la lista se actualiza mostrando todos los pedidos al dia. El badge no aparece si no hay pedidos nuevos desde que se abrio la pagina.

#### Resaltado en vivo de pedidos nuevos o modificados

Cualquier pedido que llega o cambia via WebSocket mientras la pagina esta abierta recibe un **resaltado visual con animacion de pulso naranja intenso** tanto en la Vista Lista como en la Vista Kanban:

- **Vista Lista**: la fila del pedido alterna entre un fondo naranja suave y un fondo naranja marcado con borde izquierdo color naranja. La animacion dura 1.8 segundos por ciclo y llama significativamente la atencion.
- **Vista Kanban**: la card del pedido muestra una triple capa de sombra naranja pulsante, un anillo de borde de 3px y un leve aumento de escala en el pico de la animacion.

El resaltado se activa para cualquier tipo de cambio recibido en tiempo real: pedido creado, estado cambiado, pago cobrado, cancelado o convertido en venta.

> **La terminal donde se origina el cambio no recibe su propio resaltado.** Solo las otras terminales o dispositivos conectados a la misma sucursal ven el efecto. Esto evita que el operario que acaba de crear o modificar un pedido vea innecesariamente el parpadeo en su propia pantalla.

Para quitar el resaltado de un pedido puntual, **haga click sobre la fila o la card**. El resaltado se quita al instante. Si no se hace click, el resaltado permanece hasta que se recargue la pagina.

> El estado de resaltado se guarda solo en memoria del navegador y no persiste tras recargar la pagina. El badge "X nuevos" y el resaltado son mecanismos complementarios: el badge cuenta pedidos ingresados, el resaltado senala visualmente cualquier cambio.

#### Filtros disponibles

Los filtros de **Estado del pedido** y **Estado de pago** estan siempre visibles en la barra superior junto al buscador; no requieren abrir el panel de filtros. El panel colapsable ("Filtros") contiene exclusivamente el rango de fechas.

| Filtro | Opciones | Ubicacion | Comportamiento por defecto |
|--------|----------|-----------|---------------------------|
| Busqueda | Numero, identificador, beeper, nombre o telefono de cliente | Barra superior | Vacio |
| Estado pedido | Solo activos / Todos / Borrador / Confirmado / En preparacion / Listo / Entregado / Facturado / Cancelado | Barra superior | Solo activos |
| Estado pago | Todos / Pendiente / Parcial / Pagado | Barra superior | Todos |
| Fecha desde | Fecha | Panel colapsable | Ultimos 7 dias |
| Fecha hasta | Fecha | Panel colapsable | Hoy |

> "Solo activos" excluye los estados Facturado y Cancelado. Es la vista operativa del dia.

#### Columnas de la tabla

Cada fila muestra: numero de pedido, identificador/beeper, cliente, fecha/hora, estado del pedido (badge de color solido), estado del pago (badge de tinte suave), badge verde "Cortesia" si el pedido es una invitacion total, y acciones.

Los badges de **estado del pedido** son de color solido y vibrante para facilitar la lectura de un vistazo. Los badges de **estado de pago** usan un tinte suave para diferenciarse visualmente de los anteriores.

> Cuando la sucursal tiene activa la **numeracion de display** (turno), el numero visible del pedido en la fila es el numero de turno (corto y reseteable), no el correlativo permanente. El correlativo permanente sigue disponible como referencia interna pero no se muestra en la fila de la lista.

#### Ordenamiento de la tabla

Las columnas de la tabla son clickeables para ordenar el listado:

| Columna | Campo de orden |
|---------|---------------|
| N° | Numero de pedido |
| Cliente | Nombre efectivo (catalogo o temporal) |
| Fecha | Fecha del pedido |
| Total | Total final |
| Estado | Estado del pedido (orden logico del flujo) |
| Pago | Estado de pago (orden logico) |

El primer click en una columna ordena de forma ascendente; el segundo click en la misma columna invierte a descendente. La columna activa muestra un icono de flecha indicando la direccion. Los estados se ordenan por su orden logico en el flujo operativo, no alfabeticamente.

En **dispositivos moviles** el ordenamiento se controla con un selector de campo y un boton de direccion (ascendente / descendente) que aparecen en el panel de filtros.

#### Acciones por fila

Las acciones disponibles dependen del estado del pedido y los permisos del usuario. En dispositivos moviles se muestran solo como iconos; en escritorio incluyen texto.

| Accion | Condicion | Permiso requerido |
|--------|-----------|-------------------|
| Ver detalle | Siempre disponible | Ninguno adicional |
| Editar (rapido) | Estado activo no terminal con estado de pago pendiente (sin cobros materializados) | Ninguno adicional |
| Entregar (rapido) | Pedido en estado confirmado, en preparacion o listo | Ninguno adicional |
| Cambiar estado | Pedido no cancelado ni facturado | Ninguno adicional |
| Cobrar (badge de estado de pago clickeable) | Pedido activo con saldo pendiente o pagos planificados | `func.pedidos_mostrador.cobrar` |
| Convertir en venta | Pedido confirmado, en preparacion, listo o entregado | `func.pedidos_mostrador.convertir_venta` |
| Comandar | Siempre disponible en pedidos no cancelados ni facturados | Ninguno adicional |
| Reimprimir precuenta | Siempre disponible | Ninguno adicional |
| Cancelar | Pedido no cancelado ni facturado | `func.pedidos_mostrador.cancelar` |
| Invitar item | Pedido editable (desde el editor) | `func.pedidos_mostrador.invitar_renglon` |
| Invitar pedido completo | Pedido editable (desde el editor o modal cobro) | `func.pedidos_mostrador.invitar_pedido` |

> Si el usuario no tiene el permiso correspondiente, al intentar la accion vera un mensaje de error y el modal no se abrira.

#### Accion rapida: Editar

El boton **"Editar"** (icono lapiz, color ambar) aparece en la fila cuando el pedido es editable: cualquier estado activo no terminal (borrador, confirmado, en preparacion, listo, entregado) con estado de pago `pendiente` (sin cobros materializados). Una vez que el pedido tiene pagos activos, la edicion ya no esta disponible.

Al hacer click abre el editor full-screen con todos los datos del pedido precargados para modificar items, cliente, descuentos y demas campos.

En moviles el boton aparece a la izquierda del boton "Ver detalle"; en escritorio figura en primer lugar del grupo de acciones secundarias (izquierda de "Ver detalle").

#### Accion rapida: Entregar

El boton **"Entregar"** (icono check verde, color emerald) aparece en cada fila cuando el estado actual del pedido admite la transicion a `entregado` (es decir: confirmado, en preparacion o listo). Al hacer click, aparece una confirmacion rapida. Si se acepta, el pedido pasa directamente a estado entregado sin necesidad de abrir el modal de cambio de estado.

No se requiere que el pedido este cobrado para entregarlo. El cobro y la entrega son independientes: el operario puede entregar el pedido aunque tenga saldo pendiente. El gate de cobro solo aplica al convertir el pedido en venta.

Si la sucursal tiene activada la opcion de conversion automatica al entregar (`pedido_conversion_automatica_al_entregar = true`), el sistema convierte el pedido en venta automaticamente como efecto secundario del cambio de estado (la conversion si requiere cobertura completa).

En moviles el boton muestra solo el icono; en escritorio muestra icono y texto.

#### Badge de estado de pago clickeable (cobro rapido)

El badge de **estado de pago** (Pendiente / Parcial / Pagado) que se muestra en la columna de estado funciona como boton de cobro cuando el pedido tiene saldo pendiente y el usuario tiene el permiso `func.pedidos_mostrador.cobrar`. Al pasar el cursor sobre el badge, aparece un icono de signo $ a su derecha. Al hacer click sobre el badge (o el icono), se lanza el flujo de cobro rapido. Cuando el pedido ya esta pagado, el badge es solo informativo (no clickeable).

Este patron reemplaza al boton "Cobrar" suelto que existia anteriormente en la fila de acciones.

**Comportamiento del cobro rapido segun el estado del pedido:**

- **Si el pedido tiene pagos planificados sin integración**: confirma todos los pagos planificados de una vez sin abrir ningun modal. Cada pago genera su movimiento en la caja activa. Al terminar aparece un mensaje de confirmacion indicando cuantos pagos se confirmaron.
- **Si el pedido tiene pagos planificados con forma de pago integrada (QR)**: en lugar de confirmar en lote, abre el **modal "Cobrar pendiente"** para que el operario los confirme de a uno. Cada pago con QR dispara su propio flujo de espera antes de materializarse (ver abajo). Esto es necesario porque cada cobro QR requiere que el cliente escanee el codigo y confirme.
- **Si no tiene planificados**: abre directamente el **desglose de formas de pago** superpuesto sobre el listado, sin entrar al editor full-screen. El operario define el desglose (incluyendo pago mixto, cuotas, recargos, multi-moneda y vuelto) y al confirmar el saldo queda cobrado con los pagos activos resultantes.

> El desglose de formas de pago se puede abrir desde cualquier estado activo del pedido (confirmado, en preparacion, listo, entregado), no solo desde el estado borrador o confirmado con pago pendiente. Siempre que haya saldo pendiente, el badge es clickeable.

#### Modal: Ver detalle

Muestra la informacion completa del pedido:

- **Encabezado**: numero, identificador, beeper, sucursal, caja, fecha, usuario que lo creo.
- **Cliente**: nombre (del catalogo o temporal), telefono.
- **Items**: tabla con nombre del articulo/concepto, opcionales seleccionados, cantidad, precio unitario y subtotal. Cada item con `comandado_at = null` muestra un **badge ambar "Nuevo"** a la derecha del nombre, excepto cuando el pedido esta en estado borrador (en borrador todos los items son nuevos y el badge seria redundante).
- **Pagos cobrados**: lista de pagos en estado activo con forma de pago y monto.
- **Pagos planificados**: lista de pagos configurados pero aun no cobrados, con forma de pago y monto.
- **Observaciones**: si el pedido tiene notas.
- **Venta asociada**: si el pedido ya fue convertido, muestra el numero de la venta resultante con enlace.
- **Boton "Comandar"** (color azul): envia el pedido a cocina. El comportamiento depende del estado de comanda:
  - Si todos los items ya estan comandados: reimprime la comanda completa directamente (sin modal).
  - Si ningun item fue comandado antes: envia la comanda completa directamente (sin modal).
  - Si hay mezcla (algunos comandados y algunos nuevos): abre el modal "Comandar pedido" para elegir entre enviar solo los nuevos o todo el pedido.
- **Boton "Reimprimir precuenta"**.

> Al comandar, si el pedido estaba en estado **Confirmado**, avanza automaticamente a **En preparacion**. Si estaba en **Listo** o **Entregado**, regresa a **En preparacion** (el service fuerza el estado con log de auditoria).

#### Modal: Comandar pedido

Se abre cuando el pedido tiene **mezcla de items**: algunos ya fueron comandados previamente (tienen timestamp de envio) y otros son nuevos (agregados despues de la ultima comanda). En este caso el sistema no puede decidir automaticamente y pregunta al operario.

El modal muestra dos opciones grandes:

- **Comandar solo los nuevos (N)**: imprime un ticket de cocina parcial con solo los items nuevos. El ticket incluye el header destacado "AGREGADO" para que cocina sepa que es un complemento del pedido original. Solo marca como comandados los items nuevos.
- **Comandar todo el pedido (N+M)**: imprime el ticket completo con todos los items (nuevos y ya comandados). Util cuando el operario quiere asegurarse que cocina tenga el pedido completo visible.

Ambas opciones hacen avanzar o regresar el pedido a estado **En preparacion** si corresponde. Un boton **"Cancelar"** cierra el modal sin ejecutar ninguna accion.

> Si todos los items ya estan comandados (reimpresion) o ningun item fue comandado antes (primera comanda), el modal no aparece. El sistema ejecuta directamente la comanda completa.

#### Accion "Comandar" en la lista

El boton **"Comandar"** (color azul) aparece en cada fila de la lista, en las cards del Kanban y en el modal "Ver detalle". El tooltip del boton cambia segun el estado de comanda del pedido:

| Estado de comanda | Tooltip | Comportamiento |
|-------------------|---------|----------------|
| Sin comandar (ningun item enviado) | "Comandar pedido" | Ejecuta comanda completa directamente |
| Parcial (algunos enviados, otros nuevos) | "Comandar (hay items nuevos)" | Abre el modal de eleccion |
| Comandado (todos enviados) | "Reimprimir comanda" | Reimprime la comanda completa directamente |

#### Modal: Cambiar estado

Permite avanzar el pedido en su ciclo de vida. Las transiciones posibles desde cada estado son:

| Estado actual | Transiciones disponibles |
|---------------|--------------------------|
| Borrador | Confirmado |
| Confirmado | En preparacion, Listo, Entregado |
| En preparacion | Listo, Entregado |
| Listo | Entregado |
| Entregado | (solo conversion a venta via modal propio) |

> Los estados Cancelado y Facturado no aparecen en este modal. Cancelado tiene su propio modal con motivo obligatorio. Facturado solo se alcanza al convertir el pedido en venta.

El modal incluye un campo de **observacion opcional** para dejar una nota sobre el cambio de estado.

#### Modal: Cobrar pendiente

Se muestra cuando el pedido tiene cobro parcial (pagos activos ya materializados) y no tiene pagos planificados pendientes. Requiere permiso `func.pedidos_mostrador.cobrar`.

El modal muestra un **panel de resumen** con cuatro valores:
- **Total del pedido**: monto total a cobrar.
- **Cobrado**: suma de pagos activos (ya efectivizados en caja).
- **Planificado**: suma de pagos en estado planificado (configurados, sin cobrar).
- **Pendiente**: total - cobrado (los planificados no cuentan como cobrado).

Debajo del resumen aparece la **lista de pagos planificados** (si los hay), con forma de pago, monto, cuotas y referencia si las tiene. Por cada pago planificado hay dos botones:

- **Cobrar**: materializa el pago. El comportamiento depende de si la forma de pago tiene integracion:
  - **Sin integracion**: crea un `MovimientoCaja` en la caja activa, cambia el estado del pago a `activo` y recalcula el `estado_pago` del pedido.
  - **Con integracion (QR)**: cierra el modal "Cobrar pendiente" y abre el **modal "Esperando pago"** con el QR por el monto exacto del pago planificado. El pago se materializa (caja, estado_pago) solo si el cliente confirma el cobro escaneando el QR. Si el QR se cancela o expira, el pago queda planificado y editable; el modal "Cobrar pendiente" se reabre para reintentar o modificar. Una vez confirmado el cobro, el modal "Cobrar pendiente" se reabre con el estado actualizado para continuar con los pagos planificados restantes.
- **Eliminar**: borra el pago planificado sin generar movimiento de caja.

Si el saldo pendiente es mayor a cero, el modal muestra ademas el boton **"Definir pagos"** que abre el desglose de formas de pago directamente sobre el listado (igual que el cobro rapido desde la fila).

> Los pagos planificados se eliminan del sistema al ser cobrados o eliminados. No generan contraasientos porque nunca afectaron la caja.

#### Desglose de formas de pago (cobro rapido)

Se abre superpuesto sobre el listado (sin entrar al editor full-screen) cuando el operario hace click en el badge de estado de pago de la fila o la card del Kanban (cuando es clickeable), o desde el boton "Definir pagos" del modal "Cobrar pendiente".

El desglose funciona identico al que se usa dentro del editor:
- Se puede elegir una sola forma de pago o varias (pago mixto).
- Soporta cuotas con calculo de recargo automatico.
- Permite cargar pagos en moneda extranjera con tipo de cambio.
- Muestra el vuelto si el monto entregado supera el total.
- El total base del desglose es el **saldo pendiente** del pedido (total - cobrado - planificado), no el total original.
- Si la forma de pago seleccionada tiene una integracion de cobro (QR dinamico o estatico), al confirmar el desglose se abre el **modal "Esperando pago"** con el QR correspondiente. El pedido se cobra y asocia a la transaccion solo cuando el cliente confirma el pago. Si el cobro se cancela o expira, el pedido no se modifica y el desglose se reabre automaticamente para reintentar o cambiar la forma de pago.

Al confirmar, cada fila del desglose se agrega como pago activo (estado `activo`) directamente al pedido. Si el desglose tiene una sola forma de pago, el pago queda registrado con esa FP individual. Si tiene varias, cada pago queda con su FP especifica (no se usa una FP "mixta" artificial).

> Al cerrar el desglose sin confirmar, el modal se cierra y el pedido no se modifica.

#### Modal: Convertir en venta

Convierte el pedido en una venta definitiva del sistema. Requiere permiso `func.pedidos_mostrador.convertir_venta`.

**Gate de cobro previo al modal**: antes de mostrar el modal de confirmacion, el sistema verifica que el pedido tenga cobertura completa (pagos activos + planificados cubren el total). Si hay saldo sin cubrir, en vez de abrir el modal se abre directamente el **desglose de cobro rapido**. Una vez completado el cobro al 100%, el modal de conversion se abre automaticamente. Si el operario cierra el cobro sin completarlo, la accion de conversion se descarta.

Los pedidos con pagos planificados que cubran el total se consideran cubiertos: la conversion los materializa todos automaticamente.

El modal muestra un **resumen** con el numero de pedido, identificador, cliente, total y estado de pago actual.

**Restricciones**:

- Si hay pagos planificados pero no hay monto pendiente libre (el total esta cubierto entre cobrados y planificados), el modal muestra un aviso de que esos pagos planificados se materializaran automaticamente al convertir.

Al confirmar, `PedidoMostradorService::convertirEnVenta()` ejecuta las siguientes acciones en una transaccion:
1. Materializa todos los pagos planificados restantes (crea `MovimientoCaja` por cada uno).
2. Crea la Venta con todos sus detalles y pagos copiados desde el pedido.
3. Marca el pedido con `estado_pedido = facturado` y registra `venta_id` y `convertido_at`.

#### Modal: Cancelar

Permite cancelar un pedido. Requiere permiso `func.pedidos_mostrador.cancelar`.

El modal muestra un resumen del pedido (numero, identificador, cliente, total) y una advertencia si el pedido tiene pagos activos (ya cobrados), indicando que se generaran contraasientos de caja para revertirlos.

Requiere ingresar un **motivo de cancelacion** con al menos 5 caracteres. El boton de confirmar esta deshabilitado hasta que se cumpla este requisito.

Al confirmar, `PedidoMostradorService::cancelarPedido()`:
1. Genera contraasientos en `movimientos_caja` por cada pago activo (revierte el cobro).
2. Marca el pedido con `estado_pedido = cancelado`, registra `motivo_cancelacion`, `cancelado_at` y `cancelado_por_usuario_id`.
3. Revierte el stock descontado al momento de la confirmacion (contraasientos en `movimientos_stock`).

### 4.3 Vista Kanban

La vista Kanban muestra los pedidos activos organizados en cuatro columnas segun su estado operativo:

| Columna | Color del header | Estados incluidos |
|---------|-----------------|-------------------|
| Confirmado | Azul | `confirmado` |
| En preparacion | Ambar | `en_preparacion` |
| Listo | Verde | `listo` |
| Entregado | Esmeralda | `entregado` |

Cada columna muestra en su header el **contador de pedidos** en ese estado.

> Los pedidos en estado Cancelado y Facturado no aparecen en el Kanban. Para verlos, usar la Vista Lista.

Al igual que la Vista Lista, el Kanban filtra automaticamente por caja activa cuando hay una caja seleccionada. Los eventos en tiempo real de otras cajas se ignoran silenciosamente para no perturbar el tablero.

#### Cards del Kanban

Cada card muestra:
- Numero del pedido (si la sucursal usa numeracion de display, muestra el numero de turno; caso contrario, el correlativo permanente)
- Numero de beeper (si el pedido tiene uno asignado)
- Nombre del cliente (o indicador de cliente temporal)
- Total del pedido
- Estado de pago con color: **Pagado** (verde), **Parcial** (ambar), **Pendiente** (rojo)
- Badge verde "Cortesia" si el pedido es una invitacion total

Botones disponibles directamente en la card:
- **Ver**: abre el modal de detalle.
- **Editar** (icono lapiz, ambar): aparece solo si el pedido es editable (estado activo no terminal con pago pendiente). Abre el editor full-screen.
- **Badge de estado de pago (clickeable)**: cuando el pedido tiene saldo pendiente, el badge funciona como boton de cobro rapido (igual que en la Vista Lista). Al pasar el cursor aparece el icono $.
- **Cancelar**: abre el modal de cancelacion con motivo obligatorio.

#### Drag and drop entre columnas

Las cards se pueden arrastrar de una columna a otra para cambiar el estado del pedido. El sistema solo permite soltar la card en una columna cuyo estado sea una transicion legal desde el estado actual (las mismas reglas que el modal "Cambiar estado"). Si el destino no es una transicion valida, el drag se bloquea visualmente y la card no se puede soltar ahi.

Al soltar una card en una columna valida, el estado del pedido se actualiza de inmediato. Si el servidor rechaza el cambio (por ejemplo, una condicion de carrera), la card vuelve a su columna original automaticamente.

> El drag and drop **no puede cancelar** pedidos. La cancelacion requiere siempre ingresar un motivo y se hace exclusivamente desde el boton "Cancelar" en la card.

#### Reordenamiento dentro de una columna

Ademas del drag entre columnas, las cards se pueden arrastrar **dentro de la misma columna** para cambiar su posicion. Al soltar en una posicion distinta, el nuevo orden queda guardado en el servidor: si otro operario recarga la pagina o si la vista Kanban se actualiza en tiempo real, los pedidos se muestran en el orden que se establecio. El orden es independiente por columna.

Al mover una card a otra columna, el sistema la ubica al final de la columna de destino segun el orden natural del pedido (sin necesidad de soltarla en una posicion exacta).

#### Nuevo Pedido y Edicion de Pedido

El boton **"Nuevo Pedido"** en la barra de acciones abre el formulario de alta como un **modal de pantalla completa** superpuesto sobre la lista. No hay una ruta dedicada para el alta ni la edicion.

Para editar un pedido existente (cualquier estado activo no terminal con pago pendiente), hay tres caminos equivalentes:
- El boton **"Editar"** (icono lapiz, ambar) directo en la fila de la Vista Lista o en la card del Kanban.
- El boton **"Editar pedido"** dentro del modal de detalle.
- Cualquiera de los dos abre el mismo formulario full-screen precargado con los datos del pedido.

##### Identificacion del pedido

- **Identificador** (texto libre): nombre del cliente, numero de mesa o cualquier referencia operativa. Opcional.
- **Numero de beeper**: obligatorio al confirmar si la sucursal tiene activado el uso de beepers (`sucursal.usa_beepers = true`). No se valida al guardar como borrador.

##### Seleccion de cliente

El cliente es **opcional**. Si no se elige ninguna opcion ni se ingresan datos temporales, el pedido se registra automaticamente como **"Consumidor final"**.

El pedido admite las siguientes vias para asociar un cliente:

1. **Cliente del catalogo**: busqueda por nombre o telefono. Al seleccionar, el sistema usa su lista de precios asignada y acumula puntos al convertir.
2. **Alta rapida de cliente**: boton "+" junto al campo de busqueda, abre el modal de cliente rapido para registrarlo sin salir del formulario.
3. **Cliente temporal**: se ingresa nombre libre (telefono opcional) sin crear un registro permanente. Para convertirlo en cliente del catalogo, usar el alta rapida desde el buscador de cliente.

##### Carrito de articulos

Identico al carrito de Nueva Venta. Incluye:

- Busqueda con scanner de codigo de barras o por nombre.
- Alta rapida de articulo desde el formulario.
- Busqueda avanzada con filtros (modal).
- Articulos pesables con ingreso de peso exacto.
- Opcionales editables por item (wizard de grupos opcionales).
- Concepto libre (item sin articulo del catalogo, con descripcion manual).
- Ajuste manual de precio por item.
- Descuento individual por item.
- Boton de regalo (icono regalo) por item para marcar como cortesia (ver seccion Invitar / Cortesia mas abajo).

Al editar un pedido existente (que ya fue confirmado o esta en un estado posterior), los items que todavia no fueron enviados a cocina muestran un **badge ambar "Nuevo"** a la derecha del nombre. Esto ayuda al operario a identificar rapidamente que items son nuevos respecto a la ultima comanda enviada. Los pedidos en borrador no muestran el badge (todos los items son nuevos por definicion).

##### Invitar / Cortesia

El editor de pedidos soporta invitaciones (cortesias) identico a Nueva Venta, con los mismos dos flujos: por item individual y para el pedido completo.

**Permisos requeridos:**
- `func.pedidos_mostrador.invitar_renglon` -- para invitar items individuales.
- `func.pedidos_mostrador.invitar_pedido` -- para invitar el pedido completo.

**Invitar un item individual:**

Cada item del carrito tiene un boton de regalo (icono regalo) en la fila de controles. Al hacer clic:
- Si el item **no esta invitado**: se abre un mini-modal con campo de motivo obligatorio. Al confirmar, el item queda con precio $0, badge verde "Cortesia", precio original tachado.
- Si el item **ya esta invitado**: se abre un mini-modal de confirmacion "Quitar invitacion". Al confirmar, el precio original se restaura y el item vuelve al motor de promociones.
- Sin permiso: el boton aparece deshabilitado.

**Invitar el pedido completo:**

En la columna lateral del editor, junto al boton "Descuentos", aparece el boton **"Invitar / Cortesia"**:
- Si **ningun item** esta invitado: se abre un modal con campo de motivo obligatorio para invitar todos a la vez con el mismo motivo.
- Si **todos los items** estan invitados: se abre un modal de confirmacion para quitar la cortesia a todos.

Ademas, en el **modal de cobro** (al hacer clic en "Cobrar"), aparece un switch **"Invitar pedido completo"** en la parte superior del desglose de pagos. Al activarlo aparece el campo de motivo. Al confirmar, todos los items quedan invitados y el pedido se procesa sin requerir formas de pago.

**Indicadores visuales:**

- Items invitados: badge verde "Cortesia" + precio original tachado + "$0".
- Panel de totales: linea "Total invitado: $X".
- En el listado de pedidos: badge verde "Cortesia" en la fila (lista) y en la card (kanban) para pedidos con `es_invitacion_total=true`.

**Reversibilidad:**

El operario puede quitar la invitacion (por item o total) mientras el pedido este en estado **borrador** o **confirmado con estado de pago pendiente**. Una vez cobrado o convertido en venta, la invitacion queda fija.

**Comportamiento:**

- El motivo es obligatorio. Sin texto no se puede confirmar.
- Los items invitados se excluyen del motor de beneficios (promos NxM, cupones, descuento general).
- El stock se descuenta normalmente.
- Un pedido totalmente invitado se procesa sin requerir formas de pago (total cobrable = $0). Pasa directamente a `estado_pago=pagado`.
- Al convertir el pedido en venta, la informacion de invitacion se propaga a la venta resultante.

##### Configuracion del pedido

- **Lista de precios**: override manual de la lista aplicable.
- **Descuento general**: porcentaje o monto fijo sobre el total.
- **Cupon**: campo para ingresar un codigo de cupon de descuento.
- **Canje de puntos**: si el cliente tiene puntos disponibles, se puede aplicar canje.
- **Observaciones**: texto libre para instrucciones de preparacion u otras notas.

##### Totales en vivo

El panel derecho muestra subtotal, IVA, descuentos aplicados y total final, actualizados al instante con cada cambio del carrito.

##### Botones de accion

| Boton | Atajo | Comportamiento |
|-------|-------|----------------|
| Cancelar | `Esc` | Cierra el modal sin guardar. |
| Guardar borrador | `Ctrl+G` | Guarda sin asignar numero ni descontar stock. El pedido queda en estado borrador. No valida beeper. Solo disponible en alta o cuando el pedido esta en estado borrador. |
| Confirmar (cobrar) | `F2` | Asigna numero correlativo, descuenta stock, abre el desglose de formas de pago. Valida beeper si la sucursal lo requiere. Si la sucursal tiene `imprime_comanda_automatico=true`, el pedido avanza automaticamente a **En preparacion** y todos los items quedan marcados como comandados. |
| Confirmar sin cobrar | `F3` | Confirma el pedido sin registrar pago. El pedido queda con estado de pago pendiente. |
| Descuentos | `F4` | Abre el modal de descuentos generales. |
| Guardar cambios (edicion) | -- | En modo edicion, persiste los cambios sobre el pedido existente. |

##### Atajos de teclado del editor

| Atajo | Accion |
|-------|--------|
| `F2` | Confirmar pedido (abre cobro) |
| `F3` | Confirmar sin cobrar |
| `F4` | Abrir modal de descuentos |
| `Ctrl+G` | Guardar como borrador (solo en alta o edicion de borrador) |
| `Ctrl+1` | Enfocar buscador de articulos |
| `Ctrl+6` | Enfocar campo de cliente |
| `Ctrl+B` | Alternar entre Panel tactil y vista Detalle |
| `Esc` | Cerrar el editor sin guardar |

> Los atajos de accion (`F2`, `F3`, `F4`, `Ctrl+G`) no se disparan si hay un modal secundario abierto (pago, descuentos, concepto libre, etc.).

> Al guardar o confirmar exitosamente, el modal se cierra y la lista se refresca automaticamente.

---

## 5. Compras

El modulo de Compras permite registrar las compras a proveedores, lo cual actualiza automaticamente el stock y registra los movimientos de caja.

#### Que ve al entrar

- Encabezado con el titulo "Compras" y un boton **"Nueva Compra"**.
- Panel de filtros similar al de ventas.
- Tabla con las compras registradas.

#### Filtros disponibles

- **Buscar**: Por numero de comprobante o nombre de proveedor.
- **Estado**: Todas, Completada, Pendiente, Cancelada.
- **Forma de Pago**: Efectivo, Debito, Credito, Cuenta Corriente.
- **Fecha Desde / Fecha Hasta**: Rango de fechas.

#### Como registrar una compra

1. Haga clic en **"Nueva Compra"**.
2. Se abrira un modal con el formulario de compra.
3. **Seleccione un proveedor** (obligatorio): Busque por nombre o CUIT.
4. **Agregue articulos**: Busque y seleccione los articulos comprados. Para cada articulo indique:
   - Cantidad comprada.
   - Precio unitario sin IVA (el costo de compra).
   - El IVA se calcula automaticamente segun la configuracion del articulo.
5. El sistema calcula automaticamente el subtotal, el IVA (credito fiscal) y el total.
6. **Seleccione la forma de pago**:
   - Si paga al contado, seleccione efectivo, debito o credito, y la caja correspondiente.
   - Si es a cuenta corriente, no necesita seleccionar caja.
7. Haga clic en **"Procesar Compra"**.

#### Diferencias con las ventas

- Las compras **aumentan** el stock (las ventas lo disminuyen).
- Las compras generan **egresos** en la caja (las ventas generan ingresos).
- El IVA en compras es **credito fiscal** (a favor de su empresa).
- El proveedor es **obligatorio** (en ventas el cliente es opcional).

#### Ver detalle y anular

- **Ver detalle**: Muestra la informacion completa de la compra (proveedor, articulos, montos, forma de pago).
- **Anular compra**: Revierte el stock y registra un contraasiento en la caja. Se requiere un motivo de anulacion.
- **Registrar pago**: Para compras a cuenta corriente, puede registrar pagos parciales o totales.

---

## 6. Stock e Inventario

> **Nota:** Todas las cantidades de stock soportan hasta 3 decimales, lo que permite manejar articulos pesables (por ejemplo, 1.500 kg) y fracciones de unidades.

### 6.1 Inventario por Sucursal

Esta pantalla muestra el estado del stock de todos los articulos en la sucursal seleccionada.

#### Que ve al entrar

- Tres tarjetas resumen en la parte superior:
  - **Total de articulos** con stock registrado.
  - **Alertas bajo minimo**: Articulos cuyo stock esta por debajo del minimo configurado.
  - **Sin existencia**: Articulos con stock cero o negativo.
- Panel de filtros y tabla de stock.

#### Filtros disponibles

- **Buscar**: Por nombre o codigo de articulo.
- **Alerta**: Todos, Bajo minimo, Sin stock.
- **Modo de stock**: Todos, Unitario, Por receta.
- **Tipo**: Todos, Articulo, Materia prima.

#### Informacion de cada registro

Para cada articulo se muestra:

- Nombre y codigo del articulo.
- Tipo (articulo o materia prima).
- Modo de stock (unitario, por receta).
- Cantidad actual en stock.
- Stock minimo y maximo configurado.
- Indicador visual de alerta (rojo si esta por debajo del minimo, amarillo si esta cerca).

#### Acciones disponibles

- **Ajuste de stock**: Permite sumar o restar una cantidad al stock actual. Se abre un modal donde indica:
  - Cantidad del ajuste (positiva para sumar, negativa para restar).
  - Motivo del ajuste.

  Esto es util para corregir diferencias detectadas sin necesidad de hacer un inventario completo.

- **Inventario fisico**: Permite registrar la cantidad real contada fisicamente. El sistema calcula automaticamente la diferencia entre el stock registrado y el fisico, generando el ajuste necesario. Se abre un modal donde indica:
  - Cantidad fisica contada.
  - Observaciones.

- **Configurar umbrales**: Permite definir el stock minimo y maximo para el articulo en la sucursal. Se abre un modal con:
  - Cantidad minima: El sistema alertara cuando el stock caiga por debajo de este valor.
  - Cantidad maxima: Limite superior de referencia.

---

### 6.2 Movimientos de Stock

Esta pantalla muestra el historial detallado de todos los movimientos de stock de la sucursal.

#### Que ve al entrar

- Tres tarjetas resumen del dia:
  - Total de entradas del dia.
  - Total de salidas del dia.
  - Cantidad de movimientos del dia.
- Panel de filtros y tabla de movimientos.

#### Filtros disponibles

- **Buscar**: Por nombre o codigo de articulo.
- **Tipo de movimiento**: Venta, Compra, Ajuste Manual, Inventario Fisico, Transferencia (entrada/salida), Anulacion de Venta, Anulacion de Compra, Devolucion, Carga Inicial, Produccion (entrada/salida), Anulacion de Produccion.
- **Fecha Desde / Fecha Hasta**: Rango de fechas (por defecto muestra el mes en curso).

#### Informacion de cada movimiento

- Fecha y hora.
- Articulo (nombre y codigo).
- Tipo de movimiento (con icono indicativo).
- Entrada (cantidad que ingreso al stock) o Salida (cantidad que salio).
- Stock resultante despues del movimiento.
- Referencia: enlace a la operacion que genero el movimiento (venta, compra, produccion, etc.).
- Usuario que realizo la operacion.

#### Acciones disponibles

Desde esta pantalla tambien puede realizar:

- **Carga de stock**: Registrar un ingreso manual de stock. Se abre un modal donde busca el articulo por nombre o codigo, indica la cantidad, el concepto (por que ingresa el stock) y observaciones.

- **Descarga de stock**: Registrar una salida manual de stock. Similar a la carga pero para egresos (por ejemplo, merma, roturas, consumo interno).

- **Inventario fisico**: Registrar la cantidad real de un articulo. El sistema calcula la diferencia y genera el movimiento correspondiente (entrada o salida).

> El buscador de articulos en los tres modales (carga, descarga e inventario) filtra por nombre y codigo de los articulos del catalogo activo de la sucursal.

---

### 6.3 Inventario General (todas las sucursales)

Esta pantalla permite realizar un inventario fisico masivo, ingresando las cantidades contadas para multiples articulos en una sola operacion.

#### Que ve al entrar

- Panel de filtros para acotar los articulos a inventariar.
- Tabla con todos los articulos que tienen stock en la sucursal activa. Cada fila muestra:
  - Codigo y nombre del articulo.
  - Stock actual registrado.
  - Un campo editable para ingresar la cantidad contada fisicamente.

#### Filtros disponibles

- **Buscar**: Por nombre o codigo.
- **Tipo**: Todos, Articulos, Materia Prima.
- **Categorias**: Permite seleccionar una o mas categorias para filtrar.
- **Etiquetas**: Permite filtrar por etiquetas asignadas a los articulos.

#### Como hacer inventario general

1. Filtre los articulos que desea inventariar (por ejemplo, una categoria especifica).
2. Para cada articulo, ingrese la cantidad fisica contada en el campo correspondiente.
3. Los articulos con cantidades ingresadas se marcan visualmente.
4. Opcionalmente, agregue observaciones globales.
5. Haga clic en **"Procesar Inventario"**.
6. Se abrira un modal de confirmacion mostrando cuantos articulos seran ajustados.
7. Confirme la operacion.
8. El sistema generara un movimiento de "Inventario Fisico" por cada articulo donde haya diferencia entre el stock registrado y el contado.
9. Se mostrara un resumen con los resultados: cuantos articulos coincidieron, cuantos tenian faltante y cuantos tenian sobrante.

> **Consejo**: Use los filtros para dividir el inventario por secciones (por ejemplo, primero la categoria "Bebidas", luego "Alimentos", etc.). Esto facilita el conteo fisico.

---

### 6.4 Recetas

Las recetas definen la composicion de un articulo o un opcional en terminos de materias primas u otros articulos. Se utilizan para la produccion y para el descuento automatico de stock de ingredientes.

#### Que ve al entrar

- Filtros para buscar recetas.
- Tabla con todas las recetas registradas.

#### Filtros disponibles

- **Buscar**: Por nombre del articulo u opcional.
- **Tipo**: Todos, Articulo, Opcional.
- **Estado**: Todos, Con receta, Sin receta.

#### Informacion de cada receta

- Nombre del articulo u opcional al que pertenece la receta.
- Tipo (Articulo u Opcional; si es opcional muestra el grupo al que pertenece).
- Lista de ingredientes con sus cantidades.
- Cantidad producida por receta (cuantas unidades del producto final resultan de los ingredientes indicados).
- Notas adicionales.

#### Acciones disponibles

- **Editar receta**: Abre un editor donde puede:
  - Agregar o quitar ingredientes (buscar articulos por nombre o codigo).
  - Modificar la cantidad de cada ingrediente.
  - Cambiar la cantidad producida.
  - Agregar notas.

- **Eliminar receta**: Elimina la receta (pide confirmacion).

- **Copiar receta**: Permite copiar una receta existente a otros articulos u opcionales del mismo tipo. Util cuando varios productos comparten la misma composicion.

- **Nueva receta**: Permite crear una receta desde cero en dos pasos:
  1. Armar la receta (ingredientes y cantidades).
  2. Asignar la receta a uno o mas articulos/opcionales.

---

### 6.5 Produccion

La pantalla de Produccion permite fabricar articulos que tienen receta, descontando automaticamente las materias primas del stock.

#### Que ve al entrar

- Un buscador de articulos (solo muestra articulos con receta activa).
- Una tabla con los articulos disponibles para producir, mostrando su categoria y codigo.

#### Como producir un articulo

1. Busque o seleccione el articulo a producir de la tabla.
2. Se abrira un modal mostrando:
   - Nombre del articulo.
   - La receta con todos sus ingredientes.
   - Stock disponible de cada ingrediente.
   - Cantidad a producir (editable; al cambiarla, se recalculan las cantidades de ingredientes proporcionalmente).
   - Puede ajustar la cantidad real de cada ingrediente si difiere de la receta.
3. Opciones:
   - **Producir directamente**: Ejecuta la produccion de inmediato.
   - **Agregar a cola**: Agrega el articulo a una cola de produccion para procesarlo en lote junto con otros articulos.

#### Cola de produccion (lote)

Si agrega varios articulos a la cola:

1. El sistema consolida los ingredientes de todos los articulos.
2. Al confirmar el lote, se muestra un resumen de todos los ingredientes necesarios y su disponibilidad de stock.
3. Puede agregar observaciones al lote.
4. Al confirmar, se procesan todas las producciones juntas.

#### Historial de produccion

Desde un boton en la pantalla puede acceder al historial de producciones, con filtros por fecha. Cada registro muestra:
- Articulo producido.
- Cantidad producida.
- Fecha y hora.
- Usuario que lo realizo.
- Estado (activo o anulado).

Puede **ver el detalle** de cada produccion (ingredientes consumidos) y **anular** una produccion (esto revierte los movimientos de stock).

---

### 6.6 Produccion por Lote

Esta pantalla ofrece una interfaz alternativa para produccion masiva, donde puede armar un lote de produccion con multiples articulos antes de confirmarlo.

#### Que ve al entrar

- Un buscador de articulos con receta.
- Un area de preview donde se muestra el articulo seleccionado con su receta.
- El lote en construccion (articulos agregados).
- La consolidacion de ingredientes del lote.

#### Flujo de trabajo

1. **Buscar y seleccionar un articulo**: Al seleccionarlo, se muestra su receta como preview.
2. **Definir la cantidad**: Ajuste la cantidad a producir.
3. **Agregar al lote**: El articulo se agrega al lote.
4. Repita los pasos 1-3 para cada articulo que desee producir.
5. El sistema muestra en tiempo real la **consolidacion de ingredientes**: una lista unificada de todas las materias primas necesarias con su stock disponible. Si algun ingrediente no tiene stock suficiente, se senala con un indicador rojo.
6. Puede editar las cantidades reales de ingredientes si difieren de la receta.
7. Agregue observaciones opcionales.
8. Haga clic en **"Confirmar Lote"** para procesar todas las producciones.

> **Consejo**: La Produccion por Lote es ideal cuando necesita preparar muchos productos diferentes al mismo tiempo (por ejemplo, al inicio de la jornada en una panaderia o cocina). La consolidacion de ingredientes le permite verificar que tiene suficiente materia prima antes de empezar.

---

## 7. Cajas

### 7.1 Gestion de Cajas

Esta pantalla muestra todas las cajas de la sucursal seleccionada y permite realizar operaciones sobre ellas.

#### Que ve al entrar

Una lista de tarjetas, una por cada caja de la sucursal. Cada tarjeta muestra:
- Nombre de la caja y su numero.
- Estado actual: Abierta (verde) o Cerrada (gris).
- Saldo actual (si esta abierta).
- Ultimos 5 movimientos.

#### Acciones disponibles

Para cada caja, segun su estado:

**Si la caja esta cerrada:**
- **Abrir caja**: Se abre un modal donde indica el saldo inicial (el dinero en efectivo con el que comienza el turno). Al confirmar, la caja pasa a estado "abierta" y se registra el movimiento de apertura.

**Si la caja esta abierta:**
- **Cerrar caja**: Inicia el proceso de cierre. El sistema realiza un arqueo automatico mostrando:
  - Saldo inicial del turno.
  - Total de ingresos (ventas, cobros, ingresos manuales).
  - Total de egresos (compras, egresos manuales, transferencias).
  - Saldo esperado (calculado por el sistema).
  - Diferencia (si existe) entre el saldo esperado y el declarado.

  Al confirmar, la caja se cierra y se genera un registro de cierre de turno.

- **Registrar movimiento manual**: Permite registrar un ingreso o egreso de dinero que no corresponde a una venta ni compra. Se abre un modal con:
  - Tipo: Ingreso o Egreso.
  - Monto.
  - Concepto.
  - Forma de pago.
  - Referencia (opcional).
  - Observaciones (opcional).

- **Ver movimientos**: Abre un panel con todos los movimientos de la caja paginados, mostrando fecha, concepto, tipo (ingreso/egreso), monto y usuario.

- **Ver terminal** (solo si la caja tiene una terminal Point de Mercado Pago asignada): Muestra el ID de la terminal Point vinculada a esa caja y, si las credenciales de la integracion Point estan configuradas en la sucursal, consulta el modo de operacion actual del dispositivo en Mercado Pago.

---

### 7.2 Turno Actual

Esta pantalla es el centro de control del turno vigente. Muestra el estado en tiempo real de todas las cajas operativas de la sucursal.

#### Que ve al entrar

Las cajas se muestran agrupadas por **grupo de cierre** (si estan configurados). Para cada caja se muestra:

- Nombre y numero.
- Estado (abierta, cerrada, nunca abierta).
- Saldo actual.
- Resumen de movimientos del turno:
  - Total de ventas (por forma de pago).
  - Total de cobros.
  - Total de movimientos manuales.
  - Ingresos y egresos.

#### Vista agrupada vs. detallada

Puede alternar entre:
- **Vista agrupada**: Muestra totales por concepto de pago (Efectivo, Debito, Credito, etc.).
- **Vista detallada**: Muestra cada movimiento individual.

#### Acciones disponibles

- **Activar/Desactivar caja**: Permite pausar o reactivar una caja durante el turno sin cerrarla. Una caja desactivada no puede recibir operaciones pero mantiene su turno abierto.

- **Abrir turno**: Para cajas cerradas, inicia un nuevo turno. Se abre un modal donde:
  - Para cajas individuales: Ingresa el fondo inicial.
  - Para grupos de cierre: Puede abrir todas las cajas del grupo a la vez, con un fondo comun o fondos individuales.

- **Cerrar turno**: Cierra el turno con un arqueo completo. El modal de cierre muestra:
  - Resumen de todos los movimientos del turno.
  - Campo para declarar el saldo real contado.
  - Calculo automatico de la diferencia.
  - Para grupos de cierre: Puede cerrar todas las cajas del grupo a la vez.
  - Soporte para multiples monedas (si hay monedas extranjeras).

- **Ver detalle de movimientos**: Para cada caja, puede ver un desglose completo de todas las operaciones agrupadas por concepto de pago y por tipo de operacion.

---

### 7.3 Historial de Turnos

Muestra el registro historico de todos los cierres de turno.

#### Que ve al entrar

- Panel de filtros.
- Tabla con los cierres de turno.

#### Filtros disponibles

- **Fecha Desde / Fecha Hasta**: Rango de fechas (por defecto, ultimo mes).
- **Caja**: Filtrar por una caja especifica.
- **Usuario**: Filtrar por quien cerro el turno.
- **Tipo**: Individual o Grupal.
- **Estado**: Activos, Revertidos, Todos.

#### Informacion de cada cierre

- Fecha y hora de cierre.
- Tipo (individual o grupal).
- Caja(s) involucrada(s).
- Usuario que realizo el cierre.
- Saldo inicial, ingresos, egresos, saldo esperado, saldo declarado y diferencia.
- Estado (activo o revertido).

#### Ver detalle

Al hacer clic en "Ver detalle", se abre un modal con toda la informacion del cierre:
- Datos generales (fecha, usuario, tipo, grupo de cierre).
- Detalle por caja (si es grupal).
- Todos los movimientos incluidos en el turno (ventas, cobros, movimientos manuales).
- Totales por forma de pago.
- Si fue revertido: motivo y usuario de la reversion.

---

### 7.4 Movimientos Manuales

Permite realizar operaciones manuales entre cajas y tesoreria.

#### Que ve al entrar

Tres pestanas:

**Pestana Transferencia:**
Permite transferir efectivo entre cajas de la misma sucursal.
- Seleccione la caja destino.
- Ingrese el monto a transferir.
- Indique el motivo.
- Opcionalmente, seleccione una moneda diferente a la principal.
- El sistema valida que la caja origen tenga saldo suficiente.

**Pestana Ingreso:**
Registra un ingreso manual de efectivo a la caja.
- Indique el monto.
- Escriba el motivo.
- Seleccione el origen: puede venir de tesoreria u otro origen.
- Opcionalmente, seleccione una moneda extranjera.

**Pestana Egreso:**
Registra un egreso manual de efectivo de la caja.
- Indique el monto.
- Escriba el motivo.
- Seleccione el destino: puede ir a tesoreria u otro destino.
- Opcionalmente, seleccione una moneda extranjera.

En la parte inferior se muestra el historial reciente de movimientos manuales y transferencias.

> **Importante**: Todas las operaciones requieren confirmacion antes de ejecutarse. Los movimientos a/desde tesoreria afectan los saldos de ambas partes.

---

### 7.5 Ajustes Post-Cierre

**Ruta**: Cajas → Ajustes Post-Cierre

**Permiso requerido**: `func.ver_ajustes_post_cierre`

Esta pantalla lista todos los cambios de forma de pago que fueron aplicados sobre ventas pertenecientes a **turnos ya cerrados**. Cada vez que un usuario modifica un pago de una venta cuyo turno original ya cerro, queda registrado aqui como "ajuste post-cierre". Los movimientos contables del cambio (caja, cuenta empresa, cuenta corriente) van al turno actual; el cierre historico no se modifica.

#### Que ve al entrar

- Encabezado con el titulo "Ajustes Post-Cierre".
- Panel de filtros.
- Cards en movil y tabla en escritorio con la lista de ajustes.

#### Filtros disponibles

- **Fecha Desde / Fecha Hasta**: Rango de fechas del ajuste.
- **Usuario**: Quien realizo el cambio.
- **Turno afectado**: Turno cerrado al que pertenecia el pago original.
- **Tipo de operacion**: Cambio de pago, Pago agregado, Pago eliminado.

#### Informacion de cada registro

- Fecha y hora del ajuste.
- Usuario que realizo la operacion.
- Numero de venta afectada (link al detalle).
- Turno original (fecha de cierre).
- Forma de pago anterior y forma de pago nueva (con flecha →).
- Diferencia de monto (delta positivo o negativo).
- Motivo ingresado.
- Link "Ver detalle" que abre el modal de detalle de la venta afectada.

> **Para que sirve**: Este reporte permite a supervisores y contadores auditar y conciliar los movimientos contables que ocurrieron fuera de los turnos registrados.

---

### 7.6 Pagos Pendientes de Facturar

**Ruta**: Cajas → Pagos Pendientes de Facturar

**Permiso requerido**: `func.ver_pagos_pendientes_facturacion`

Lista los pagos de ventas que quedaron en estado `pendiente_de_facturar` o `error_arca` porque la emision de la FC nueva fallo durante la Fase B del cambio de forma de pago (ver seccion 3.5). Permite reintentar la facturacion o sacar el pago del circuito automatico.

#### Que ve al entrar

- Encabezado con el titulo "Pagos Pendientes de Facturar".
- Panel de filtros.
- Cards en movil y tabla en escritorio con la lista de pagos.

#### Filtros disponibles

- **Estado**: Pendiente de facturar, Error ARCA, Todos.
- **Fecha Desde / Fecha Hasta**: Rango de fechas del pago.
- **Sucursal**: Para comercios con varias sucursales.

#### Informacion de cada registro

- Numero de venta y fecha.
- Forma de pago.
- Monto a facturar.
- Estado (badge "Pendiente de facturar" o "Error ARCA").
- Fecha y descripcion del ultimo intento fallido.
- Acciones disponibles.

#### Acciones disponibles

- **Reintentar facturacion** (boton azul): vuelve a intentar emitir la FC sobre ese pago. Requiere permiso `func.reintentar_facturacion`. Si ARCA responde con exito, el pago pasa a estado `facturado` y desaparece de la lista. Si falla nuevamente, permanece con el nuevo mensaje de error.

- **Marcar como error** (boton rojo): saca el pago del circuito automatico con un motivo obligatorio. El pago pasa a estado `error_arca` y ya no aparece en los reintentos automaticos. Util cuando ARCA tiene un problema irrecuperable y el operador decide emitir el comprobante por otro medio.

> **Nota**: El boton "Reintentar" tambien aparece inline en el detalle de cada venta junto al badge "Pendiente de facturar" del pago correspondiente.

---

## 8. Tesoreria

### 8.1 Gestion de Tesoreria

La Tesoreria es la caja fuerte central de la sucursal, donde se resguarda el efectivo que no esta en las cajas operativas.

#### Que ve al entrar

- **Saldo actual de tesoreria** en la parte superior.
- **Saldos en monedas extranjeras** (si aplica).
- **Estadisticas del dia**: Provisiones realizadas, rendiciones recibidas, depositos bancarios.
- Cuatro pestanas de contenido.

#### Pestanas disponibles

**Movimientos:**
Historial de todos los movimientos de la tesoreria con filtros por:
- Tipo (ingreso/egreso).
- Fecha desde/hasta.
- Concepto.

Cada movimiento muestra: fecha, concepto, tipo, monto, saldo anterior, saldo posterior, usuario y observaciones.

**Cajas:**
Muestra las rendiciones pendientes de las cajas. Cuando un turno se cierra, el efectivo queda "pendiente de rendicion" hasta que tesoreria lo recibe.

**Arqueos:**
Historial de arqueos de tesoreria. Un arqueo compara el saldo registrado con el saldo real contado.

**Depositos:**
Historial de depositos bancarios realizados desde tesoreria.

#### Acciones disponibles

- **Provisionar fondo a caja**: Enviar efectivo desde tesoreria a una caja (por ejemplo, para reponer el fondo de una caja que se quedo sin cambio). Se abre un modal donde selecciona la caja destino, el monto y, opcionalmente, la moneda.

- **Recibir rendicion**: Cuando una caja cierra su turno, genera una rendicion pendiente. Desde tesoreria puede aceptar o rechazar la rendicion. Al aceptarla, el monto se suma al saldo de tesoreria.

- **Registrar deposito bancario**: Registra que se tomo dinero de tesoreria y se deposito en una cuenta bancaria. Se abre un modal donde selecciona la cuenta, el monto, la fecha, el numero de comprobante y observaciones.

- **Realizar arqueo**: Declara el saldo real contado en tesoreria. El sistema calcula la diferencia con el saldo registrado. Soporta arqueo en multiples monedas.

- **Ingreso**: Registra un ingreso de efectivo a la tesoreria. Puede ser:
  - **Desde cuenta empresa**: Seleccione una cuenta bancaria o billetera como origen. El monto se descuenta automaticamente de esa cuenta.
  - **Ingreso externo**: Sin cuenta de origen (por ejemplo, un aporte del propietario o efectivo que no proviene de cajas ni cuentas).

---

### 8.2 Reportes de Tesoreria

Genera reportes detallados sobre los movimientos de tesoreria.

#### Tipos de reporte disponibles

1. **Libro de Tesoreria**: Muestra todos los movimientos en un periodo con saldos progresivos. Incluye saldo inicial, cada movimiento con su fecha, concepto, tipo, monto y saldo resultante, y un resumen con totales de ingresos, egresos y saldo final.

2. **Resumen de Cajas**: Muestra las provisiones y rendiciones por caja en un periodo. Para cada caja: cantidad y total de provisiones, cantidad y total de rendiciones, diferencias (sobrantes y faltantes), y balance neto.

3. **Trazabilidad de Efectivo**: Sigue el flujo del efectivo desde su ingreso (ventas, cobros) hasta su destino final (deposito bancario, provision a cajas).

4. **Reporte de Arqueos**: Historial de arqueos con las diferencias encontradas.

#### Como generar un reporte

1. Seleccione el tipo de reporte.
2. Defina el rango de fechas.
3. Opcionalmente filtre por caja o concepto.
4. Haga clic en **"Generar Reporte"**.
5. Los datos se muestran en pantalla con un resumen y una tabla detallada.

---

## 9. Bancos

### 9.1 Resumen de Cuentas

Muestra una vision general de todas las cuentas bancarias y billeteras digitales de la empresa.

#### Que ve al entrar

- **Totales por moneda**: Tarjetas mostrando el total acumulado en cada moneda (por ejemplo, pesos argentinos, dolares).
- **Lista de cuentas**: Cada cuenta con su nombre, tipo (banco o billetera digital), saldo actual y moneda.
- **Ultimos 10 movimientos**: Historial reciente de movimientos globales.

---

### 9.2 Gestion de Cuentas

Permite crear, editar y administrar las cuentas bancarias y billeteras digitales.

#### Que ve al entrar

- Filtros por tipo y estado.
- Tabla con las cuentas registradas.

#### Crear una cuenta

Haga clic en **"Nueva Cuenta"** y complete el formulario:

- **Nombre**: Un nombre descriptivo (ej: "Cuenta Corriente Banco Nacion").
- **Tipo**: Banco o Billetera Digital.
- **Subtipo** (opcional): Clasificacion adicional.
- **Para tipo Banco**:
  - Banco (nombre de la entidad).
  - Numero de cuenta.
  - CBU.
  - Alias.
  - Titular.
- **Moneda**: En que moneda opera la cuenta.
- **Color** (opcional): Color identificativo para la interfaz.
- **Sucursales**: A que sucursales esta disponible la cuenta.

#### Acciones disponibles

- **Editar**: Modifica los datos de la cuenta.
- **Activar/Desactivar**: Cambia el estado de la cuenta.
- **Eliminar**: Solo posible si la cuenta no tiene movimientos registrados. Si tiene movimientos, desactivela en su lugar.
- **Conciliar** (solo cuentas vinculadas a proveedor): acceso directo a la pantalla de Conciliaciones con esa cuenta preseleccionada. Aparece unicamente en cuentas que tienen un identificador externo de proveedor (por ejemplo, cuentas de Mercado Pago vinculadas desde la configuracion de Integraciones de Pago).

#### Conciliacion automatica diaria (cuentas vinculadas)

Al editar una cuenta vinculada a un proveedor (con identificador externo), aparece el toggle **"Conciliacion automatica diaria"**. Si se activa, el sistema prepara automaticamente cada dia la conciliacion del dia anterior. La corrida queda siempre en estado "Pendiente de revision" para que un usuario la revise y aplique manualmente; nunca se aplica sola.

---

### 9.3 Movimientos

Muestra el historial de movimientos de una cuenta bancaria o billetera digital.

#### Que ve al entrar

- Selector de cuenta (debe seleccionar una cuenta para ver sus movimientos).
- Una vez seleccionada la cuenta, se muestran:
  - Datos de la cuenta (nombre, banco, CBU, saldo actual).
  - Filtros de movimientos.
  - Tabla de movimientos.

#### Filtros disponibles

- **Tipo**: Ingreso o Egreso.
- **Concepto**: Filtrar por concepto de movimiento.
- **Estado**: Activo o Anulado.
- **Fecha Desde / Fecha Hasta**.

#### Registrar un movimiento

1. Haga clic en **"Nuevo Movimiento"**.
2. Complete el formulario:
   - **Tipo**: Ingreso o Egreso.
   - **Monto**.
   - **Concepto**: Seleccione de la lista de conceptos predefinidos.
   - **Descripcion**: Detalle de la operacion.
   - **Observaciones** (opcional).
3. Confirme.

#### Anular un movimiento

Para cada movimiento puede hacer clic en "Anular". Se abrira un modal donde debe ingresar el motivo de la anulacion. El sistema generara un contraasiento que revierte el saldo.

---

### 9.4 Transferencias

Permite transferir dinero entre cuentas bancarias o billeteras digitales.

#### Que ve al entrar

- Formulario de nueva transferencia.
- Historial de transferencias realizadas.

#### Como realizar una transferencia

1. **Cuenta Origen**: Seleccione la cuenta de donde sale el dinero.
2. **Cuenta Destino**: Seleccione la cuenta que recibe el dinero. Solo se muestran cuentas de la misma moneda.
3. **Monto**: Ingrese el monto a transferir.
4. **Concepto**: Describa el motivo de la transferencia.
5. Haga clic en **"Transferir"**.

El sistema genera dos movimientos: un egreso en la cuenta origen y un ingreso en la cuenta destino.

---

### 9.5 Conciliaciones

Permite comparar el historial de movimientos registrado en el sistema contra los movimientos reales de la cuenta en el proveedor de pago (por ejemplo, Mercado Pago), detectar diferencias y registrar los ajustes necesarios (comisiones, retiros, devoluciones, acreditaciones) para que el saldo del sistema converja al saldo real del proveedor.

Solo se pueden conciliar cuentas vinculadas a un proveedor (con identificador externo). Requiere el permiso **"Conciliaciones"** (ver y crear) y, para aplicar o descartar, el permiso adicional **"bancos.conciliaciones.aplicar"**.

#### Que ve al entrar

- Filtros por cuenta y por estado de corrida.
- Listado de corridas de conciliacion: cuenta, periodo (desde / hasta), estado (badge), origen (manual o automatica), contadores de filas y fecha.
- Boton **"Nueva conciliacion"**.

#### Crear una conciliacion

1. Haga clic en **"Nueva conciliacion"**.
2. En el modal seleccione:
   - **Cuenta**: solo aparecen las cuentas conciliables (con identificador de proveedor y configuracion de produccion activa).
   - **Periodo**: fecha desde y hasta (por defecto los ultimos 7 dias).
3. Confirme. El sistema crea la corrida en estado **"Generando reporte del proveedor..."** y comienza a solicitar el reporte de movimientos al proveedor de forma asincrona.
4. La pantalla se refresca sola mientras el reporte se genera. Cuando el proveedor responde, el sistema matchea los movimientos y la corrida pasa a **"Pendiente de revision"**.

> Si el proveedor tarda mas de 60 minutos en generar el reporte, la corrida pasa a estado **"Error"**. En ese caso puede crear una corrida nueva para el mismo periodo.

> Solo puede haber una corrida activa (generando o pendiente de revision) por cuenta al mismo tiempo.

#### Revisar y clasificar los movimientos

Al abrir el detalle de una corrida en estado "Pendiente de revision" vera las filas del reporte del proveedor clasificadas en grupos:

- **Conciliados**: cobros que el sistema ya registro y que aparecen en el reporte del proveedor. Si el proveedor cobro una comision por ese cobro, el sistema propone un egreso de comision por cada uno.
- **Solo en el proveedor**: movimientos que estan en el proveedor pero el sistema no los tiene. Segun el tipo:
  - Acreditaciones, rendiciones y cobros externos → el sistema propone un **ingreso**.
  - Devoluciones, contracargos y retiros a banco → el sistema propone un **egreso**.
- **Solo en el sistema**: cobros que el sistema registro pero no aparecen en el reporte del proveedor. Se muestran como alerta; el sistema no genera ajuste automatico (puede ser diferencia de timing del reporte).
- **Ya registrado**: filas de corridas anteriores ya aplicadas. No se vuelven a proponer.

Cada fila propuesta tiene un toggle **"Generar movimiento / Ignorar"** que puede cambiar individualmente o por grupo completo (acciones masivas). Por defecto todas las propuestas arrancan en "Generar movimiento".

#### Ajuste inicial (primera conciliacion de una cuenta)

Si la cuenta no tiene ninguna conciliacion aplicada anteriormente, aparece un campo opcional **"Saldo real total en el proveedor"**: el saldo ACTUAL de la cuenta tal como se ve en la app del proveedor (disponible + a liberar + reserva). Si lo completa, el sistema aplica primero todos los movimientos de la conciliacion y despues registra un ajuste inicial por la diferencia, dejando el saldo del sistema exactamente igual al real.

#### Aplicar los ajustes

Haga clic en **"Aplicar ajustes"**. El sistema genera en el ledger un movimiento por cada fila que tenga la accion "Generar movimiento" (las filas en "Ignorar" no generan nada). Los movimientos quedan vinculados a la corrida de conciliacion como origen. La corrida pasa al estado **"Aplicada"**.

> Hacer clic dos veces o recargar la pagina no duplica los movimientos: el sistema tiene guard de estado.

#### Descartar una corrida

Haga clic en **"Descartar"**. La corrida pasa al estado **"Descartada"** sin tocar el ledger. Puede crear una nueva corrida para el mismo periodo si lo necesita.

---

## 10. Articulos

### 10.1 Gestion de Articulos

Es el catalogo central de productos de su comercio.

#### Que ve al entrar

- Panel de filtros.
- Tabla con todos los articulos mostrando: codigo, nombre, categoria, precio, stock en la sucursal actual, tipo y estado.
- Botones en el header: **"Nuevo Articulo"**, **"Plantilla"** e **"Importar"** (en escritorio). En movil, el boton **"⋯"** agrupa las opciones "Descargar plantilla", "Importar" y "Cambio masivo de precios".

#### Filtros disponibles

- **Buscar**: Por nombre, codigo o codigo de barras.
- **Tipo**: Todos, Articulos, Materia Prima.
- **Categorias**: Seleccion multiple de categorias.
- **Etiquetas**: Filtrar por etiquetas asignadas.

#### Crear un articulo

Haga clic en **"Nuevo Articulo"** y complete el formulario:

- **Codigo**: Se genera automaticamente segun la categoria (si tiene prefijo), o puede ingresarlo manualmente.
- **Codigo de Barras** (opcional): Para uso con lectores de codigo de barras.
- **Nombre**: Nombre del articulo.
- **Descripcion** (opcional).
- **Categoria**: Busque y seleccione la categoria. El selector tiene busqueda inteligente (escriba parte del nombre para filtrar). Si la categoria no existe, puede crearla al instante con el boton **"+"** junto al selector (ingrese nombre y prefijo opcional).
- **Unidad de Medida**: Unidad, Kilogramo, Litro, etc.
- **Es materia prima**: Marque si el articulo es una materia prima (se usa en recetas pero no se vende directamente).
- **Pesable**: Marque si el articulo se vende por peso (kg, gr, lt, etc.). Al agregarlo en el punto de venta se abrira un modal para ingresar la cantidad o el valor. Los articulos pesables usan modo de stock "unitario" automaticamente.
- **Vendible**: Si el articulo aparece en el punto de venta.
- **Tipo de IVA**: Seleccione la alicuota de IVA (21%, 10.5%, exento, etc.).
- **Precio IVA Incluido**: Indica si el precio que ingresa ya incluye IVA.
- **Precio base**: El precio de venta sin ajustes de lista.
- **Precio por sucursal** (opcional): Si esta sucursal tiene un precio diferente al base.
- **Modo de stock**: Ninguno (no controla stock), Unitario (control directo), o Por Receta (se descuenta por ingredientes).
- **Sucursales**: En que sucursales esta disponible el articulo.
- **Etiquetas**: Asigne etiquetas para clasificar el articulo.
- **Activo**: Si el articulo esta habilitado.

#### Editar un articulo

Haga clic en el boton de edicion del articulo. Se abre el mismo formulario con los datos cargados.

#### Acciones adicionales

- **Gestionar opcionales**: Permite ver y modificar los grupos de opcionales asignados al articulo (ej: "Tamano", "Agregados"). Desde aqui puede asignar nuevos grupos o quitar los existentes.

- **Gestionar receta**: Abre el editor de recetas del articulo. Si no tiene receta, permite crearla. Si ya tiene, permite editarla.

- **Ver historial de precios**: Muestra el registro de todos los cambios de precio que ha tenido el articulo.

- **Eliminar**: Solo si el articulo no tiene operaciones asociadas (ventas, compras, stock). De lo contrario, desactivelo.

#### Importar y exportar articulos desde Excel

Permite cargar o actualizar multiples articulos a la vez desde un archivo .xlsx, y exportar el catalogo actual para editarlo en Excel.

**Paso 1: descargar la plantilla**

Haga clic en el boton **"Plantilla"** en el header. Se abrira un modal con dos opciones:

- **Plantilla vacia**: Descarga el archivo con solo los encabezados y validaciones (para carga inicial de articulos).
- **Con datos actuales**: Descarga el catalogo completo de la sucursal activa con todos los articulos ya cargados (para edicion masiva). Las filas de articulos eliminados se muestran con fondo rojo e incluyen una columna extra **"Eliminado"** con valor "Si"; estas filas son solo informativas.

El archivo descargado es `plantilla_articulos.xlsx` y contiene las siguientes columnas:

| Col | Nombre | Requerido | Descripcion |
|---|---|---|---|
| A | ID | No modificar | Identificador interno. Dejar vacio en filas nuevas. |
| B | Codigo | No | Se autogenera con el prefijo de la categoria si queda vacio en una fila nueva. |
| C | Codigo de barras | No | Texto libre. Usar formato TEXT en Excel para evitar notacion cientifica. |
| D | Nombre | Si | Nombre del articulo. |
| E | Descripcion | No | Descripcion detallada. |
| F | Categoria | No | Nombre de la categoria activa. Dropdown con categorias disponibles. |
| G | Unidad | No | Unidad de medida (default: "unidad"). |
| H | Tipo IVA | No | Nombre del tipo de IVA activo. Dropdown con opciones disponibles. |
| I | Precio IVA incluido | No | "Si" o "No". |
| J | Materia prima | No | "Si" o "No". |
| K | Pesable | No | "Si" o "No". |
| L | Activo | No | "Si" o "No". Opera sobre la sucursal activa. |
| M | Vendible | No | "Si" o "No". Opera sobre la sucursal activa. |
| N | Modo stock | No | "ninguno", "unitario" o "receta". Opera sobre la sucursal activa. |
| O | Precio | No | Precio efectivo para la sucursal activa. |

Las columnas con lista de opciones tienen un dropdown nativo de Excel para facilitar la edicion.

**Paso 2: completar y subir el archivo**

1. Complete o edite el archivo .xlsx.
2. Haga clic en **"Importar"** en el header.
3. En el modal que se abre, seleccione el archivo.
4. El sistema realizara una previsualizacion (dry-run) mostrando cuantos articulos seran creados, actualizados y cuantas filas tienen errores, sin persistir nada todavia.
5. Revise el resumen y haga clic en **"Confirmar importacion"** para aplicar los cambios.

**Comportamiento de la importacion**

- **Fila con ID**: busca el articulo existente y lo actualiza (nombre, codigo, campos del pivot). Permite renombrar el articulo o cambiar su codigo.
- **Fila sin ID**: crea un articulo nuevo y lo vincula a la sucursal activa.
- **Cambio de categoria**: si la nueva categoria tiene un prefijo distinto, el codigo del articulo se regenera automaticamente.
- **Articulos eliminados (soft-delete)**: si se indica un ID de articulo eliminado, la fila se ignora con un mensaje de error (los articulos eliminados no se restauran por importacion).
- **Errores por fila**: cada fila se procesa en forma independiente. Un error en una fila no detiene el resto de la importacion. Al finalizar se muestra un detalle de los errores encontrados.

**Comportamiento del precio al importar**

- Si el precio coincide con el precio base global del articulo: el override de sucursal queda sin valor (NULL) y no se genera historial.
- Si el precio difiere del precio efectivo anterior: se actualiza el override de sucursal y se registra el cambio en el historial de precios con origen "importacion".
- Si el precio coincide con el precio efectivo anterior: no hay ningun cambio.
- Si la celda de precio queda vacia: el override de sucursal se elimina (el articulo pasa a usar el precio base global); si habia un override anterior, el cambio se registra en historial.

---

### 10.2 Categorias

Las categorias organizan los articulos en grupos logicos (ej: "Bebidas", "Alimentos", "Limpieza").

#### Que ve al entrar

- Filtros de busqueda y estado.
- Tabla con las categorias.
- Botones en el header: **"Nueva Categoria"**, **"Plantilla"** e **"Importar"**.

#### Crear una categoria

Haga clic en **"Nueva Categoria"** y complete:

- **Nombre**: El nombre de la categoria.
- **Prefijo** (opcional): Un prefijo corto (ej: "BEB") que se usara para generar automaticamente los codigos de los articulos de esta categoria.
- **Color**: Color identificativo que se usara en la interfaz.
- **Icono** (opcional): Icono representativo.
- **Activo**: Si la categoria esta habilitada.

#### Acciones

- **Editar**: Modifica los datos.
- **Activar/Desactivar**: Cambia el estado.
- **Eliminar**: Solo si no tiene articulos asociados.

#### Importar categorias desde Excel

Permite cargar multiples categorias de una sola vez desde un archivo .xlsx.

**Paso 1: descargar la plantilla**

Haga clic en el boton **"Plantilla"** en el header. Se descargara el archivo `plantilla_categorias.xlsx` con dos columnas:

| Columna | Requerido | Descripcion |
|---|---|---|
| Nombre | Si | Nombre unico de la categoria (maximo 100 caracteres) |
| Prefijo | No | Prefijo para codigos automaticos (maximo 10 caracteres, se guarda en MAYUSCULAS) |

La plantilla incluye dos filas de ejemplo (en gris italica) que deben reemplazarse o eliminarse.

**Paso 2: completar y subir el archivo**

1. Complete la plantilla con sus categorias (una por fila, a partir de la fila 2).
2. Haga clic en **"Importar"** en el header.
3. En el modal que se abre, seleccione el archivo .xlsx completado.
4. Haga clic en **"Importar"** para procesar.

**Comportamiento de la importacion**

- Si el nombre de la categoria **no existe** en el sistema: se crea con color azul por defecto y estado activo.
- Si el nombre **ya existe**: se actualiza unicamente el prefijo con el valor del archivo.
- Las filas con errores (nombre vacio, nombre demasiado largo, prefijo demasiado largo) se reportan individualmente sin interrumpir el resto de la importacion.
- Al terminar, se muestra un resumen con: categorias creadas, categorias actualizadas y errores por fila (si los hubiera).

---

### 10.3 Etiquetas

Las etiquetas permiten clasificar los articulos de forma flexible, complementando las categorias. Se organizan en **grupos de etiquetas**.

#### Estructura

- **Grupo de etiquetas**: Agrupa etiquetas relacionadas (ej: grupo "Temporada" con etiquetas "Verano", "Invierno", "Todo el ano").
- **Etiqueta**: Una clasificacion individual dentro de un grupo.

#### Que ve al entrar

- Filtros y una vista de acordeon donde cada grupo se expande para mostrar sus etiquetas.

#### Crear un grupo de etiquetas

Haga clic en **"Nuevo Grupo"** y complete:
- Nombre del grupo.
- Codigo (identificador corto).
- Descripcion.
- Color.

#### Crear una etiqueta

Dentro de un grupo, haga clic en **"Agregar Etiqueta"** y complete:
- Nombre de la etiqueta.
- Codigo.
- Color (opcional; hereda del grupo si no se especifica).

#### Acciones

Para grupos y etiquetas: Editar, Activar/Desactivar, Eliminar.

---

### 10.4 Asignar Etiquetas

Permite asignar etiquetas a articulos de forma masiva. Ofrece dos modos de operacion:

**Modo "Etiqueta a Articulos":**
1. Seleccione una etiqueta del listado.
2. Se muestran todos los articulos. Los que ya tienen la etiqueta aparecen marcados.
3. Marque o desmarque articulos para asignar o quitar la etiqueta.
4. Haga clic en **"Guardar Asignaciones"**.

**Modo "Articulo a Etiquetas":**
1. Seleccione un articulo del listado.
2. Se muestran todas las etiquetas disponibles. Las que ya tiene asignadas aparecen marcadas.
3. Marque o desmarque etiquetas.
4. Haga clic en **"Guardar Asignaciones"**.

---

### 10.5 Cambio Masivo de Precios

Permite modificar los precios de multiples articulos a la vez, con un asistente paso a paso.

#### Paso 1: Configurar el ajuste

- **Tipo de ajuste**: Descuento o Recargo.
- **Tipo de valor**: Porcentual (ej: 10%) o Fijo (ej: $100).
- **Valor del ajuste**: El porcentaje o monto a aplicar.
- **Tipo de redondeo**: Sin redondeo, Entero mas cercano, Decena mas cercana, Centena mas cercana.
- **Alcance**: Se aplica a la sucursal actual.
- **Modo de aplicacion**: Aplicar ahora o Programar para una fecha y hora futuras.

Haga clic en **"Siguiente"** para pasar al paso 2.

#### Paso 2: Seleccionar y previsualizar

- **Filtros de articulos**: Por categoria, etiqueta y tipo de articulo.
- **Tabla de preview**: Muestra todos los articulos que seran afectados con:
  - Nombre y codigo.
  - Precio actual.
  - Precio nuevo (calculado con el ajuste configurado).
  - Diferencia.
  - Puede editar manualmente el precio nuevo de cualquier articulo individual.
  - Puede agregar articulos adicionales que no estan en los filtros.

- **Totales**: Cantidad de articulos afectados, suma de precios viejos y suma de precios nuevos.

Haga clic en **"Aplicar Cambios"** para confirmar.

#### Cambios programados

Si eligio "Programar", los cambios se crearan como pendientes y se ejecutaran automaticamente en la fecha y hora indicadas. Puede ver y gestionar los cambios programados desde un panel dedicado, con la posibilidad de cancelar un cambio antes de que se ejecute.

---

### 10.6 Grupos Opcionales

Los grupos opcionales permiten ofrecer personalizaciones a los articulos (ej: "Tamano de bebida", "Ingredientes extra", "Tipo de pan").

#### Que ve al entrar

- Filtros de busqueda.
- Tabla con los grupos opcionales, mostrando: nombre, tipo, obligatorio/opcional, rango de seleccion, cantidad de opciones activas.

#### Crear un grupo opcional

Haga clic en **"Nuevo Grupo"** y complete:

- **Nombre**: Nombre del grupo (ej: "Tamano").
- **Descripcion** (opcional).
- **Tipo**: Seleccionable (el cliente elige) o Incluido (se aplica automaticamente).
- **Obligatorio**: Si el vendedor debe seleccionar al menos una opcion.
- **Minimo de seleccion**: Cantidad minima de opciones que se deben elegir.
- **Maximo de seleccion**: Cantidad maxima de opciones permitidas (dejar vacio para ilimitado).
- **Orden**: Orden de aparicion en el wizard de opcionales.
- **Opciones del grupo**: Dentro del mismo formulario, agregue las opciones disponibles:
  - Nombre de la opcion (ej: "Grande", "Mediano", "Chico").
  - Precio adicional (ej: $200 por "Grande").

#### Acciones disponibles

- **Editar**: Modifica el grupo y sus opciones.
- **Gestionar recetas**: Para cada opcion del grupo, puede definir una receta de ingredientes que se descontaran del stock cuando se seleccione esa opcion.
- **Disponibilidad por sucursal**: Permite configurar que opciones estan disponibles en cada sucursal.
- **Asignacion masiva**: Permite asignar el grupo a multiples articulos a la vez.
- **Eliminar**: Elimina el grupo y todas sus opciones.

---

### 10.7 Asignar Opcionales

Permite gestionar la asignacion de grupos opcionales a articulos.

#### Que ve al entrar

- Filtros de busqueda.
- Tabla con los articulos, mostrando cuantos grupos opcionales tiene asignado cada uno.

#### Filtros

- **Buscar**: Por nombre o codigo de articulo.
- **Asignacion**: Todos, Con grupos, Sin grupos.

#### Como asignar

1. Haga clic en un articulo de la lista.
2. Se abre un modal mostrando:
   - Los grupos ya asignados con sus opciones.
   - Un boton para agregar un nuevo grupo.
3. Para **agregar un grupo**: Haga clic en "Agregar Grupo", busque el grupo deseado y seleccionelo. Se asignara automaticamente para todas las sucursales.
4. Para **quitar un grupo**: Haga clic en el boton de eliminar junto al grupo. Se pedira confirmacion.

---

## 11. Clientes

### 11.1 Gestion de Clientes

Administra la base de datos de clientes del comercio.

#### Que ve al entrar

- Panel de filtros.
- Tabla con los clientes mostrando: nombre, CUIT, condicion IVA, telefono, email, si tiene cuenta corriente, saldo de deuda y estado.

#### Filtros disponibles

- **Buscar**: Por nombre, razon social, CUIT, email o telefono.
- **Estado**: Todos, Activos, Inactivos.
- **Sucursal**: Filtrar por sucursal asignada.
- **Condicion IVA**: Filtrar por condicion fiscal.
- **Cuenta Corriente**: Todos, Con C/C, Sin C/C, Con deuda.
- **Vinculacion**: Todos, Con proveedor vinculado, Sin proveedor.

#### Crear un cliente

Haga clic en **"Nuevo Cliente"**. Puede elegir entre dos modos de alta:

**Modo Manual:**
Complete el formulario con:
- Nombre (obligatorio).
- Razon Social.
- CUIT.
- Email.
- Telefono.
- Direccion.
- Condicion de IVA.
- Lista de Precios asignada (opcional).
- Cuenta Corriente: Activela para permitir ventas a credito. Configure:
  - Limite de credito.
  - Dias de credito.
  - Tasa de interes mensual.
- **Domicilio fiscal**: Seccion opcional que define la jurisdiccion del cliente para las percepciones de Ingresos Brutos. Permite seleccionar la provincia (codigo ISO 3166-2) y la localidad. Si no se completa, el sistema aplica la logica del flag "Percibir a clientes no empadronados" configurado en el CUIT agente (ver Configuracion → CUIT → Impuestos).
- Sucursales donde esta habilitado el cliente.
- Tambien es proveedor: Si el cliente es tambien proveedor, puede vincularlo o crear un nuevo proveedor automaticamente.

**Modo por CUIT (consulta ARCA/AFIP):**
Ingrese el CUIT y el sistema consultara automaticamente el padron de ARCA para completar los datos fiscales (razon social, condicion de IVA, direccion).

#### Acciones disponibles

- **Editar**: Modifica los datos del cliente.
- **Perfil fiscal**: Abre un modal para configurar las percepciones provinciales (Ingresos Brutos) que se le aplican a ese cliente en particular. Disponible en movil y escritorio.
  - Se listan las jurisdicciones ya configuradas con su alicuota, base minima, numero de padron y vigencia. Cada fila indica el **origen** de la alicuota: "Manual" (cargada a mano) o "Padron" (proveniente del importador de padron ARBA/AGIP).
  - Boton **"Agregar jurisdiccion"**: busca y selecciona un impuesto de IIBB del catalogo del sistema. Una vez agregado, se puede cargar: alicuota (%), base minima, numero de padron/constancia, vigente desde/hasta, y marcar al cliente como **Exento** para esa jurisdiccion.
  - Si el cliente esta marcado como Exento para un IIBB, no se le percibe ese impuesto aunque el CUIT sea agente.
  - Si el cliente tiene alicuota cargada (manual o proveniente del padron importado), esa alicuota pisa la alicuota fija configurada en el CUIT agente.
  - Los perfiles con origen "Manual" tienen prioridad maxima: el importador de padron (Fiscal → Importar padron) nunca los pisa.
  - La percepcion de IVA es automatica y NO se configura aqui; aplica a todo Responsable Inscripto sin excepcion.
- **Configurar sucursales**: Permite asignar listas de precios diferentes por sucursal para el mismo cliente.
- **Ver historial**: Muestra el historial de ventas del cliente.
- **Activar/Desactivar**: Cambia el estado del cliente.
- **Eliminar**: Solo si no tiene operaciones asociadas.
- **Importar clientes**: Permite importar clientes desde un archivo, seleccionando las sucursales destino.

---

### 11.2 Cobranzas y Cuenta Corriente

Permite gestionar el cobro de deudas de clientes con cuenta corriente.

#### Que ve al entrar

- Panel de filtros.
- Tabla con los clientes que tienen cuenta corriente, mostrando: nombre, deuda total, saldo a favor, cantidad de ventas pendientes, antiguedad de la deuda.

#### Filtros disponibles

- **Buscar**: Por nombre o CUIT.
- **Estado**: Con deuda, Sin deuda, Todos.
- **Antiguedad**: Todos, 0-30 dias, 31-60 dias, 61-90 dias, mas de 90 dias.

#### Como registrar un cobro

1. Haga clic en **"Cobrar"** junto al cliente.
2. Se abre un modal de cobro mostrando:
   - Datos del cliente y su deuda total.
   - **Saldo a favor** (si tiene, puede aplicarlo).
   - **Ventas pendientes**: Lista de ventas impagas con fecha, numero, monto original, saldo pendiente e intereses acumulados.

3. **Seleccion de ventas**:
   - **Modo FIFO** (predeterminado): El sistema asigna automaticamente el pago a las ventas mas antiguas primero.
   - **Modo Manual**: Usted selecciona manualmente que ventas desea cobrar.

4. **Monto a cobrar**: Ingrese el monto que el cliente paga. Puede ser parcial o total.

5. **Descuento** (opcional): Si le otorga un descuento sobre la deuda.

6. **Formas de pago**: Agregue una o mas formas de pago (desglose):
   - Seleccione la forma de pago.
   - Indique el monto.
   - Si aplica, seleccione las cuotas.
   - Haga clic en "Agregar".
   - Repita para pago mixto.

7. **Anticipo**: Si el monto cobrado excede la deuda seleccionada, el excedente se registra como saldo a favor del cliente.

8. **Saldo a favor**: Si el cliente tiene saldo a favor previo, puede indicar cuanto de ese saldo aplicar al cobro actual.

9. Haga clic en **"Confirmar Cobro"**.

#### Ver cuenta corriente

Haga clic en **"Ver C/C"** junto al cliente para ver el historial completo de su cuenta corriente: ventas a credito, cobros recibidos, intereses, descuentos y saldo a favor, con el saldo progresivo.

#### Reporte de antiguedad

El boton **"Reporte de Antiguedad"** genera un informe de todas las deudas clasificadas por rango de dias: 0-30, 31-60, 61-90 y mas de 90 dias. Esto es util para identificar deudas viejas que requieren atencion.

---

## 12. Configuracion

### 12.1 Datos de la Empresa

La configuracion de la empresa se organiza en pestanas. La pestana activa se persiste en la URL (`?tab=cuits`, `?tab=sucursales`, etc.), por lo que al recargar la pagina o compartir el enlace se vuelve a la misma pestana.

#### Pestana "Empresa"

Datos generales del comercio:
- Nombre de la empresa.
- Direccion.
- Telefono.
- Email.
- Logo (puede subir una imagen).

#### Pestana "CUITs"

Gestion de los CUITs fiscales del comercio (una empresa puede tener varios CUITs):
- Numero de CUIT.
- Razon Social.
- Nombre de Fantasia.
- Direccion fiscal.
- Provincia y Localidad.
- Condicion de IVA.
- Numero de IIBB (Ingresos Brutos).
- Fecha de inicio de actividades.
- Entorno AFIP: Testing o Produccion.
- Certificado digital y clave privada para facturacion electronica.
- **Puntos de Venta**: Cada CUIT puede tener multiples puntos de venta numerados, necesarios para la emision de comprobantes fiscales.

Cada CUIT en la lista expone tres botones de accion adicionales (accesibles desde el menu de acciones de la fila):

**Boton "Impuestos"**: abre el modal de configuracion impositiva del CUIT. Permite:
- Buscar impuestos del catalogo del sistema (percepciones y retenciones de IVA e IIBB por jurisdiccion, ganancias, creditos y debitos, SIRCREB) y agregarlos al CUIT.
- Crear impuestos personalizados que no esten en el catalogo del sistema.
- Para cada impuesto configurado, editar: alicuota (%), base minima (umbral de base imponible para aplicar la percepcion), numero de inscripcion, vigente desde, vigente hasta.
- Marcar si el CUIT actua como agente de percepcion y/o agente de retencion para ese impuesto.
- Para los impuestos de tipo IIBB, aparece ademas el flag **"Percibir a clientes no empadronados"**: si esta activo, el sistema percibe la alicuota fija del agente a todo cliente Responsable Inscripto que no tenga perfil fiscal propio cargado para ese IIBB; si esta desactivado (valor por defecto), solo se percibe a los clientes que tienen alicuota cargada manualmente o proveniente de padron.
- Quitar un impuesto de la configuracion del CUIT.
- Nota: el IVA del CUIT no se gestiona aqui; lo determina la condicion de IVA asignada al CUIT.

> **Percepcion automatica en ventas**: si marca el CUIT como agente de percepcion para un impuesto (por ejemplo, percepcion de IIBB provincial o percepcion de IVA), el sistema calculara y cobrara automaticamente esa percepcion al facturar a clientes Responsables Inscriptos desde los puntos de venta de ese CUIT.
> - Para **IVA**: la alicuota fija del agente aplica a todo RI sin excepcion (percepcion automatica).
> - Para **IIBB provincial**: la alicuota se refina por el perfil fiscal del cliente (ver accion "Perfil fiscal" en el modulo Clientes). Si el cliente tiene alicuota propia, pisa la fija del agente; si esta exento, no se percibe; si no tiene perfil, el comportamiento depende del flag "Percibir a clientes no empadronados" del agente.
> Los impuestos del catalogo ya tienen asignado su codigo de tributo AFIP (campo interno `codigo_arca`) para informarlo correctamente en el comprobante electronico.

**Boton "Domicilios"**: abre el modal de domicilios fiscales del CUIT. Permite:
- Ver la lista de domicilios declarados ante AFIP para ese CUIT.
- Agregar nuevos domicilios indicando tipo (fiscal, comercial, otro), direccion, provincia (ISO 3166-2), localidad y coordenadas (latitud/longitud).
- Editar y eliminar domicilios existentes.
- Marcar un domicilio como principal.
- La provincia del domicilio determina la jurisdiccion de IIBB para los comprobantes emitidos desde los puntos de venta asociados a ese domicilio.

**Boton "Puntos de Venta"**: abre el modal de puntos de venta del CUIT. Permite:
- Ver los puntos de venta numerados del CUIT.
- Asignar a cada punto de venta un domicilio fiscal (de los cargados para ese CUIT). Esta asignacion es la que determina la jurisdiccion de Ingresos Brutos de cada comprobante.

#### Pestana "Sucursales"

Gestion de las sucursales del comercio:
- Nombre de la sucursal.
- Nombre publico (como se muestra en los comprobantes).
- Direccion, telefono y email de la sucursal.
- **Domicilio estructurado** (Fase 9): provincia (ISO 3166-2) y localidad seleccionable del padron GeoRef. Reemplaza progresivamente el texto libre de direccion/localidad para permitir referencias exactas. La localidad elegida queda vinculada como `localidad_id`.
- **Picker de Google Maps** (requiere API key configurada): permite ubicar geograficamente el domicilio de la sucursal. Aparece en el formulario de edicion como un boton **"Abrir mapa"**. El mapa NO se carga al entrar al formulario; recien se carga al pulsar ese boton (evitando costos de API si no se necesita). Una vez abierto, el domicilio se puede ubicar de cuatro formas:
  1. **Autocomplete de direccion**: escribir la calle y numero en el buscador interno del mapa. El buscador queda restringido a Argentina y, cuando se eligio localidad, al area de esa localidad (~20 km a la redonda).
  2. **Clic en el mapa**: clic sobre cualquier punto del mapa para colocar el marcador ahi.
  3. **Arrastre del marcador**: arrastrar el pin naranja (con el icono BCN) al punto exacto.
  4. **Usar mi ubicacion actual**: boton que lee la ubicacion GPS del dispositivo.
  - El flujo invertido implica que elegir primero la **Provincia** y luego la **Localidad** centra y acota el mapa automaticamente a esa zona antes de abrir el buscador de calles.
  - Una vez ubicado el punto, las **coordenadas** (latitud y longitud) se muestran debajo del mapa y se guardan junto con el domicilio.
  - El boton **"Ocultar mapa"** cierra el panel del mapa sin perder las coordenadas ya guardadas.
  - El enlace **"Ingresar coordenadas manualmente"** permite tipear lat/lng numericos directamente (util si el mapa ya esta abierto y se prefiere precision decimal).
  - **Sin API key** configurada: el campo del mapa no aparece; se muestran dos inputs de texto simples para latitud y longitud (comportamiento original).
- **Logo de la sucursal**: dropzone moderno con preview. Acciones disponibles:
  - **Sin logo**: area punteada con icono de subida y texto "Subir logo". Clic (o arrastre) para seleccionar el archivo.
  - **Con logo**: muestra preview de la imagen. Al pasar el cursor aparecen dos botones: **cambiar logo** (subir uno nuevo) y **quitar logo** (eliminar el actual); si se selecciono un archivo nuevo aun sin guardar, el segundo boton descarta esa seleccion y vuelve al logo guardado.
  - Formatos aceptados: PNG, JPG o WebP. Tamano maximo: 2 MB.
  - Recomendacion de proporcion: **imagen cuadrada (1:1)**, preferentemente PNG con fondo transparente, minimo 400x400 px.
  - Al guardar la sucursal (nombre, domicilio o logo), el cache de catalogos se invalida automaticamente para que los cambios se reflejen de inmediato en toda la aplicacion.
- **Configuracion de la sucursal** (boton de engranaje):
  - Clave de autorizacion para operaciones sensibles.
  - Tipo de impresion de factura (termica, A4, ambos).
  - Agrupacion de articulos en venta e impresion.
  - Control de stock en ventas: Bloquea (no permite vender sin stock), Advierte (avisa pero permite), No controla.
  - Control de stock en produccion: Igual al anterior.
  - Facturacion fiscal automatica.
  - **Numeracion de pedidos (turno)**: activa un numero de display reseteable (independiente del correlativo permanente) para mostrar en el monitor llamador, la comanda y el kanban. Modos de reinicio: "Automatico por horario" (configurable con una o varias horas de reset diario, por defecto 06:00) o "Manual" (boton reiniciar cuando empieza una nueva tanda). Al reiniciar manualmente se requiere confirmacion.
  - Configuracion de WhatsApp (envio de comandas y notificaciones).
- **Personalizar 2da pantalla** (visible solo si alguna caja de la sucursal tiene "Usa pantalla orientada al cliente" activado): abre el modal de personalizacion de la pantalla cliente. Ver seccion 12.12.
- **Llamador**: abre el modal de configuracion del monitor llamador de pedidos (pantalla publica). Siempre visible. Ver seccion 12.13.
- **Consultor de precios**: abre el modal de configuracion del consultor de precios (pantalla publica de scanner). Siempre visible. Ver seccion 12.14.

#### Pestana "Cajas"

Gestion de las cajas de cada sucursal:
- Lista de cajas agrupadas por sucursal.
- Estado de cada caja.
- **Configuracion de caja**:
  - Nombre de la caja (editable, maximo 15 caracteres). Permite reemplazar los nombres por defecto ("Caja 1", "Caja 2") por nombres personalizados como "Mostrador" o "Delivery". El formulario muestra un contador de caracteres en tiempo real.
  - Limite de efectivo (monto maximo de efectivo que debe tener la caja).
  - Modo de carga inicial: Manual (el usuario ingresa el saldo al abrir) o Monto Fijo (se carga automaticamente un monto predeterminado).
  - Monto fijo inicial (si corresponde).
  - **Usa pantalla orientada al cliente**: activa el boton "Conectar pantalla cliente" en el punto de venta de esta caja. Cuando esta habilitado y se inicia un cobro con QR, el codigo se muestra en el segundo monitor orientado al cliente en lugar del modal del cajero. Requiere monitores en modo "Extender" y navegador Chrome o Edge.
- **Puntos de venta**: Asignacion de puntos de venta fiscales a cada caja, con un punto de venta por defecto.
- **Grupos de cierre**: Permite crear grupos de cajas que se cierran juntas:
  - Nombre del grupo.
  - Sucursal.
  - Cajas que lo componen.
  - Fondo comun: Si las cajas del grupo comparten un fondo de efectivo.

---

### 12.2 Usuarios

Gestion de los usuarios del sistema.

#### Que ve al entrar

- Filtros por nombre, estado y rol.
- Tabla con los usuarios: nombre, username, email, rol, estado.

#### Crear un usuario

Haga clic en **"Nuevo Usuario"** y complete:

- Nombre completo.
- Username (nombre de acceso al sistema).
- Email.
- Telefono.
- Contrasena y confirmacion.
- Rol: Seleccione el rol del usuario (define sus permisos).
- Activo: Si puede acceder al sistema.
- **Sucursales**: Seleccione a que sucursales tiene acceso el usuario.
- **Cajas**: Para cada sucursal, seleccione que cajas puede operar.

#### Acciones

- Editar, Activar/Desactivar.
- Cambiar contrasena (puede generar una aleatoria visible).

> **Nota**: El rol "Super Administrador" tiene acceso completo al sistema y no puede ser eliminado.

---

### 12.3 Roles y Permisos

Define los roles del sistema y los permisos que tiene cada uno.

#### Que ve al entrar

- Lista de roles con cantidad de usuarios y permisos asignados.

#### Crear un rol

Haga clic en **"Nuevo Rol"** e ingrese:
- Nombre del rol.
- **Permisos de menu**: Controlan a que secciones del sistema puede acceder el usuario. Estan organizados jerarquicamente: un permiso padre (ej: "Ventas") y permisos hijos (ej: "Nueva Venta", "Listado de Ventas").
- **Permisos funcionales**: Controlan acciones especificas dentro de las secciones (ej: "Anular ventas", "Modificar precios", "Ver costos").

#### Acciones

- Editar: Modifica nombre y permisos.
- Eliminar: Solo si no tiene usuarios asignados.

> **Nota**: El rol "Super Administrador" siempre tiene todos los permisos y no puede ser modificado.

---

### 12.4 Formas de Pago

Configura las formas de pago aceptadas por el comercio.

#### Que ve al entrar

- Filtros de busqueda y estado.
- Tabla con las formas de pago.

#### Crear una forma de pago

Haga clic en **"Nueva Forma de Pago"** y complete:

**Para formas de pago simples:**
- **Nombre** (ej: "Efectivo", "Visa Debito", "Mercado Pago QR").
- **Concepto de pago**: Clasifica como entra el dinero (Efectivo, Tarjeta Debito, Tarjeta Credito, Transferencia, Billetera Digital, etc.).
- **Descripcion** (opcional).
- **Ajuste porcentual**: Recargo (+) o descuento (-) que se aplica al total. Por ejemplo, "+3%" para tarjeta de credito o "-5%" para efectivo.
- **Permite cuotas**: Si esta forma de pago ofrece pagos en cuotas.
- **Factura fiscal**: Si esta forma de pago genera factura fiscal por defecto.
- **Cuenta empresa**: Cuenta bancaria o billetera asociada (opcional). Si la forma de pago tiene una integracion de cobro con credenciales de produccion configuradas, este campo se autocompleta automaticamente con la cuenta real del proveedor (ver mas abajo). El valor sugerido es editable.
- **Moneda**: Moneda de la forma de pago (util para pagos en moneda extranjera).
- **Sucursales**: En que sucursales esta disponible.

#### Integraciones de pago

Si el concepto seleccionado lo permite (por ejemplo: Billetera Digital, Transferencia), aparece el bloque **"Integraciones de pago"** debajo de los campos principales.

Este bloque permite vincular la forma de pago con una o mas integraciones configuradas en el comercio (por ejemplo, Mercado Pago - QR). Al cobrar, el punto de venta usara esa vinculacion para procesar el pago digitalmente.

**Agregar una integracion:**

1. Haga clic en **"+ Agregar integracion"**.
2. En la fila nueva seleccione la **integracion** del desplegable (muestra las integraciones activas del comercio).
3. Elija el **modo de cobro**: determina como se procesa el pago al usar esta forma de pago en el punto de venta.
   - **QR dinamico**: el sistema genera un QR unico por venta que se muestra en pantalla. El cliente lo escanea desde la pantalla del cajero o del monitor del cliente.
   - **QR estatico**: el sistema empuja el monto al POS de la caja y el cliente escanea el QR fisico impreso del mostrador (el que queda fijo en el local). No se genera un QR nuevo en pantalla.
   - **QR de monto libre**: se muestra al cliente una imagen del QR "Cobrar" de Mercado Pago que usted carga en esta configuracion. El cliente escanea el QR e ingresa el monto en su app. El cajero confirma el pago manualmente.
   - **Point**: el sistema envia el monto a la terminal fisica (posnet) asignada a la caja. El cliente paga pasando la tarjeta o escaneando el QR que muestra el propio aparato. El cajero no ve ningun QR en pantalla.

   Al elegir **QR de monto libre**, aparece el campo **"Imagen del QR de cobro de Mercado Pago"**: haga clic en el area o arrastre una imagen (JPG, PNG o WebP, maximo 4 MB) con el QR que descargo desde la app de Mercado Pago en la seccion "Cobrar con QR". El sistema re-procesa y almacena la imagen de forma segura.

   > **Importante**: el QR debe estar configurado en Mercado Pago en modo **"monto abierto"** (el cliente ingresa el importe). Si el QR esta en modo "monto fijo / lo define el cajero", al escanearlo la app de MP le pedira al cliente que avise al cajero y no podra ingresar el monto. Para configurarlo, en la app de Mercado Pago vaya a Cobrar con QR → configuracion de su local o caja → elija "monto abierto", descargue ese QR y subalo aca.

4. Si selecciona el modo **Point**, aparece el campo **"Medio de pago en la terminal"**:
   - **Abierto (el cliente elige)**: no se preselecciona ningun medio; la terminal muestra todas las opciones al cliente. Es el valor por defecto.
   - **Tarjeta de credito**: el posnet arranca preseleccionando credito. Las cuotas elegidas en el desglose de la venta se envian como cuotas de tarjeta.
   - **Tarjeta de debito**: el posnet arranca preseleccionando debito.
   - **QR**: el posnet muestra su propio QR al cliente.
5. Si la forma de pago tiene mas de una integracion, marque **"Principal"** en la que desea que se use por defecto cuando el punto de venta no pregunta al cajero.

**Quitar una integracion:** haga clic en el icono de eliminar a la derecha de la fila.

> Las formas de pago mixtas no admiten integraciones de pago. El bloque solo aparece en formas de pago simples con concepto compatible.

#### Cuenta vinculada automaticamente desde la integracion

Cuando se selecciona una integracion que tiene credenciales de **produccion** configuradas, el sistema busca la cuenta del proveedor de pago que fue creada automaticamente y pre-selecciona el campo **"Cuenta empresa"** con esa cuenta. Aparece un aviso azul: "Cuenta vinculada automaticamente desde la integracion de pago".

- El valor es **editable**: el usuario puede cambiarlo o dejarlo en blanco.
- Si ya habia una cuenta seleccionada manualmente, el sistema no la sobreescribe.
- Si la integracion esta en modo **Test** o no tiene credenciales guardadas, no se sugiere ninguna cuenta.
- Esta cuenta es la que recibe los movimientos de saldo cuando los cobros por esa integracion se confirman (ver comportamiento de saldo mas abajo).

**Para formas de pago mixtas:**
Active el switch "Es mixta" para crear una forma de pago que permite combinar multiples conceptos (por ejemplo, parte en efectivo y parte con tarjeta). Seleccione los conceptos de pago permitidos (minimo 2).

#### Gestion de cuotas

Si una forma de pago permite cuotas, puede configurar las opciones:
- Cantidad de cuotas (ej: 3, 6, 12).
- Recargo por cuotas (porcentaje).
- Descripcion (ej: "3 cuotas sin interes").

#### Ordenar formas de pago

El boton **"Ordenar"** permite cambiar el orden en que aparecen las formas de pago en los selectores del punto de venta mediante arrastrar y soltar.

---

### 12.5 Formas de Pago por Sucursal

Permite personalizar las formas de pago para cada sucursal.

#### Que ve al entrar

- Selector de sucursal.
- Tabla con todas las formas de pago y su estado en la sucursal seleccionada.

#### Acciones disponibles

- **Activar/Desactivar**: Habilita o deshabilita una forma de pago en la sucursal. Una forma de pago desactivada no aparecera en el punto de venta de esa sucursal.

- **Configurar ajuste**: Permite definir un ajuste porcentual (recargo/descuento) diferente al general para la sucursal especifica. Por ejemplo, si "Visa Credito" tiene un recargo general del 3%, puede configurar un 5% solo para esta sucursal.

- **Configurar cuotas**: Permite personalizar los recargos de cuotas para la sucursal. Cada plan de cuotas puede tener un recargo diferente al general.

- **Configurar factura fiscal**: Permite definir si la forma de pago genera factura fiscal de forma diferente al valor general.

---

### 12.6 Listas de Precios

Las listas de precios permiten tener diferentes niveles de precios para los mismos articulos (ej: "Precio Mostrador", "Precio Mayorista", "Precio Empleados").

#### Que ve al entrar

- Filtros y tabla con las listas existentes.

#### Crear una lista de precios

El wizard de creacion tiene 5 pasos:

**Paso 1 - Datos basicos:**
- Nombre de la lista.
- Codigo (opcional).
- Descripcion.
- Sucursal a la que pertenece.
- Prioridad (determina cual lista se aplica primero si varias son validas).

**Paso 2 - Configuracion de precios:**
- Tipo y porcentaje de ajuste: Recargo (+%) o Descuento (-%) sobre la lista base.
- Redondeo: Sin redondeo, Entero, Decena o Centena mas cercana.
- Aplicar promociones: Si las promociones se aplican a ventas con esta lista.
- Alcance de promociones: A toda la venta o excluyendo articulos con precio especifico en esta lista.
- **Lista estatica (congelar precios)**: Toggle disponible solo en listas no-base. Al activarlo, los precios se calculan una sola vez al grabar y quedan congelados: no cambian aunque varie el precio base de los articulos. Ver seccion "Listas estaticas" mas abajo.

> Al ingresar el tipo de ajuste y el porcentaje en este paso, el wizard avanza correctamente sin errores.

**Paso 3 - Vigencia:**
- Fecha desde y hasta (opcional).
- Dias de la semana en que aplica.
- Horario de aplicacion (hora desde y hasta).
- Cantidad minima y maxima de items.

**Paso 4 - Condiciones:**
Defina condiciones que deben cumplirse para que la lista aplique:
- Por forma de pago (ej: solo para pago en efectivo).
- Por forma de venta (ej: solo para delivery).
- Por canal de venta.
- Por total de compra (monto minimo y/o maximo).

**Paso 5 - Articulos especificos:**
Opcionalmente, defina precios especificos para articulos individuales que difieren del ajuste general de la lista. Busque articulos y asigne un precio fijo o un porcentaje de ajuste particular.

#### Acciones

- Editar, Activar/Desactivar, Eliminar.
- **Actualizar precios** (icono de recarga, color ambar, solo en listas estaticas): Abre un modal de confirmacion y, al aceptar, regenera el snapshot de precios aplicando los precios base actuales de los articulos. Los precios ingresados manualmente por el usuario se preservan; solo se recalculan los que surgieron del ajuste porcentual de la lista.

#### Listas estaticas

Una lista estatica congela sus precios en un snapshot calculado al momento de grabar. A diferencia de una lista dinamica, los cambios posteriores al precio base de los articulos no se reflejan automaticamente en esta lista.

**Comportamiento:**
- Al grabar o editar una lista con el toggle estatico activado, el sistema aplica la jerarquia completa de ajustes (precio especifico por articulo > ajuste por articulo > ajuste por categoria > ajuste del encabezado) sobre todos los articulos activos de la sucursal y persiste el precio resultante como precio fijo.
- En el listado, las listas estaticas muestran un badge **Estatica** color ambar y la leyenda "Actualizada: hace X" (tiempo desde el ultimo snapshot).
- Articulos incorporados al catalogo despues del snapshot no quedan cubiertos por la lista estatica. En ventas, si el articulo no esta en el snapshot, el sistema usa automaticamente el precio de la lista base.
- Al actualizar precios (boton de recarga), solo se recalculan los articulos cuyo precio proviene del ajuste porcentual; los precios cargados manualmente en el paso 5 se mantienen.
- Si se edita una lista y se desactiva el toggle "estatica", los snapshots previos se eliminan y la lista pasa a operar de forma dinamica.

> **Nota**: La **lista base** es la lista de precios por defecto y no puede ser desactivada ni eliminada. Los precios base de los articulos pertenecen a esta lista.

---

### 12.7 Promociones

Las promociones comunes aplican descuentos o recargos sobre articulos individuales en la venta.

#### Tipos de promocion

- **Descuento %**: Descuento porcentual sobre el precio.
- **Descuento $**: Descuento de un monto fijo.
- **Precio Fijo**: Establece un precio fijo sin importar el precio de lista.
- **Recargo %**: Recargo porcentual.
- **Recargo $**: Recargo de un monto fijo.
- **Descuento Escalonado**: Descuento que varia segun la cantidad comprada (ej: de 1 a 5 unidades 5%, de 6 a 10 unidades 10%).

#### Wizard de creacion (5 pasos)

**Paso 1 - Tipo:**
Seleccione el tipo de promocion.

**Paso 2 - Configuracion basica:**
- Nombre y descripcion.
- Codigo de cupon (opcional; si lo tiene, el cliente debe ingresar el codigo para que aplique).
- Valor del descuento/recargo.
- Para escalonados: defina los rangos de cantidad y el descuento de cada rango.

**Paso 3 - Alcance:**
- Sucursales donde aplica.
- Alcance de articulos: Todos los articulos, o una seleccion especifica de articulos y/o categorias (permite seleccion multiple).

**Paso 4 - Condiciones:**
- Forma de venta.
- Canal de venta.
- Formas de pago (seleccion multiple).
- Monto minimo de la venta.
- Cantidad minima y maxima de articulos.
- Vigencia (fechas, dias de la semana, horarios).
- Usos maximos.
- Combinable: Si puede aplicarse junto con otras promociones.

**Paso 5 - Prioridad y simulador:**
- Prioridad de la promocion (numero menor = mayor prioridad).
- Vista de promociones competidoras que podrian aplicar a los mismos articulos.
- **Simulador de venta**: Permite probar la promocion antes de activarla. Agregue articulos a un carrito virtual y verifique que el descuento se aplica correctamente.

---

### 12.8 Promociones Especiales

Las promociones especiales permiten configurar mecanicas avanzadas como NxM (lleva 3 paga 2), combos y menus.

#### Tipos de promocion especial

- **NxM basico**: El cliente lleva N unidades de uno o mas articulos y/o categorias seleccionadas y se bonifica M unidades (gratis o con descuento). Ejemplo: "Lleva 3, paga 2". Soporta seleccion multiple de articulos y categorias.
- **NxM avanzado**: Permite definir grupos "trigger" (que disparan la promo) y grupos "reward" (que se bonifican) con articulos diferentes.
- **Combo**: Un conjunto de articulos con un precio especial (fijo o con descuento porcentual). Ejemplo: "Hamburguesa + papas + bebida por $5000".
- **Menu**: Similar al combo pero con opciones en cada grupo. Ejemplo: "Menu ejecutivo: elija 1 entrada, 1 plato principal y 1 postre".

#### Wizard de creacion (4 pasos)

**Paso 1 - Tipo:**
Seleccione el tipo de promocion especial.

**Paso 2 - Configuracion:**
Depende del tipo:
- Para NxM: Cantidad que lleva, cantidad que se bonifica, tipo de beneficio (gratis o descuento porcentual), articulos y/o categorias aplicables (seleccion multiple). Puede configurar escalas (ej: lleva 3 bonifica 1, lleva 6 bonifica 2).
- Para Combo: Articulos que componen el combo con sus cantidades y un precio total.
- Para Menu: Grupos de opciones (ej: "Entrada", "Plato principal", "Postre"), con los articulos disponibles en cada grupo, la cantidad a elegir y el precio total del menu.

**Paso 3 - Condiciones:**
Similar a las promociones comunes: vigencia, dias, horarios, forma de venta, canal, formas de pago (seleccion multiple), usos maximos.

**Paso 4 - Prioridad y simulador:**
Prioridad de la promocion y simulador para probarla antes de activarla.

#### Acciones adicionales

- **Duplicar**: Crea una copia de la promocion (util para crear variantes).
- Editar, Activar/Desactivar, Eliminar.

---

### 12.9 Monedas

Gestiona las monedas aceptadas por el comercio y los tipos de cambio.

#### Seccion Monedas

Lista de monedas configuradas. La moneda principal (generalmente ARS - Peso Argentino) esta marcada como tal.

**Crear o editar moneda:**
- Codigo (3 letras, ej: USD, EUR, BRL).
- Nombre completo.
- Simbolo (ej: $, US$, EUR).
- Cantidad de decimales.
- Orden de aparicion.
- Es principal: Solo una moneda puede ser la principal.

#### Seccion Tipos de Cambio

Permite registrar las cotizaciones de compra y venta entre monedas.

**Registrar tipo de cambio:**
- Moneda de origen.
- Moneda de destino.
- Tasa de compra (a cuanto compra su comercio).
- Tasa de venta (a cuanto vende su comercio).
- Fecha de vigencia.

Los tipos de cambio se utilizan automaticamente en el punto de venta cuando un cliente paga en moneda extranjera.

---

### 12.10 Impresoras

Configura las impresoras del sistema para la emision de tickets, facturas y otros documentos.

#### Que ve al entrar

- Filtros por nombre y tipo.
- Tabla con las impresoras configuradas.

#### Crear una impresora

Haga clic en **"Nueva Impresora"**:

- El sistema puede **detectar automaticamente** las impresoras instaladas en su computadora. Si las detecta, las mostrara en una lista para que seleccione una.
- **Nombre**: Nombre amigable (ej: "Impresora Caja 1").
- **Nombre del sistema**: El nombre tecnico de la impresora en el sistema operativo.
- **Tipo**: Termica (para tickets) o Laser/Inkjet (para facturas A4).
- **Formato de papel**: 58mm, 80mm (termicas) o A4, Carta (laser/inkjet).
- **Ancho de caracteres**: Se calcula automaticamente segun el formato.
- **Activa**: Si esta habilitada para imprimir.

#### Asignaciones

Despues de crear la impresora, configure para que tipo de documentos se usara y en que sucursales/cajas:
- Asigne la impresora a combinaciones especificas de sucursal + caja.
- Defina que tipos de documentos imprime: tickets de venta, facturas, comandas de cocina, etc.

#### Configuracion por sucursal

Cada sucursal puede tener su propia configuracion de impresion:
- Impresion automatica al cerrar venta.
- Impresion automatica de factura fiscal.
- Abrir cajon de efectivo al cobrar en efectivo.
- Corte automatico de papel.
- Texto de pie del ticket.
- Texto legal para facturas.

---

### 12.11 Integraciones de Pago

Permite conectar las sucursales y cajas del comercio con pasarelas de pago externas. Cada producto de Mercado Pago (QR y Point) es una integracion independiente con sus propias credenciales y configuracion.

#### Que ve al entrar

- Pestana de la sucursal activa con las integraciones disponibles.
- Para cada integracion: estado de conexion (conectada / no conectada), modo (Test o Produccion) y acciones.
- Si hay una integracion configurada: botones de sincronizacion y lista de cajas activas.

#### Mercado Pago - QR (QR dinamico y QR estatico)

##### Configurar la integracion QR

Haga clic en **"Configurar"** junto a Mercado Pago - QR:

1. **Modo**: Seleccione "Test" para pruebas o "Produccion" para operar con dinero real.
2. **User ID Externo**: ID numerico de la cuenta de Mercado Pago del comercio.
3. **Access Token (Produccion)**: Token de produccion de MP.
4. **Access Token (Test)**: Token de pruebas de MP.
5. Haga clic en **"Probar Conexion"** para verificar que las credenciales son validas antes de guardar.
6. Haga clic en **"Guardar"**.

> **Atencion**: Si cambia el modo (Test a Produccion o viceversa) o el User ID, el sistema borra automaticamente los IDs de Store y POS guardados localmente, ya que las cuentas de test y produccion son cuentas de MP distintas y sus recursos no se comparten. Debera volver a sincronizar sucursal y cajas.

##### Configurar la direccion de la sucursal

Mercado Pago requiere coordenadas geograficas, localidad y provincia para registrar la sucursal como "Store". Si la sucursal aun no tiene estos datos, vera un aviso y el boton **"Editar Direccion"**.

El modal usa el mismo picker de Google Maps disponible en Configuracion → Sucursales (Fase 9): primero se elige la provincia, luego la localidad del catalogo GeoRef, y finalmente se ubica el punto en el mapa. Las coordenadas se obtienen automaticamente al colocar o arrastrar el marcador, o al usar el boton **"Usar mi ubicacion"**. No es necesario tipear latitud y longitud manualmente.

Los campos obligatorios para poder sincronizar con Mercado Pago son:
- Direccion (calle y numero).
- Provincia (selector con provincias argentinas).
- Localidad (del catalogo GeoRef, dependiente de la provincia elegida).
- Coordenadas (latitud y longitud, obtenidas desde el mapa).

> Para mas detalle sobre el funcionamiento del picker (autocomplete, arrastre del marcador, "Usar mi ubicacion"), consulte la seccion **12.2 Sucursales**.

##### Sincronizar la sucursal con Mercado Pago

1. Asegurese de que la sucursal tenga direccion, localidad, provincia y coordenadas configuradas.
2. Haga clic en **"Sincronizar Sucursal"**.
3. El sistema crea (o actualiza si ya existia) la sucursal como "Store" en la cuenta de MP.
4. Al completarse, vera el mensaje "Sucursal sincronizada con Mercado Pago".

##### Sincronizar una caja con Mercado Pago (QR)

1. La sucursal debe estar sincronizada primero.
2. En la lista de cajas de la integracion, haga clic en **"Sincronizar"** junto a la caja deseada.
3. El sistema crea (o actualiza) la caja como "POS" en MP y guarda la URL del codigo QR estatico.
4. Al completarse, vera el mensaje "Caja sincronizada con Mercado Pago".

Una vez sincronizada, cada caja muestra dos botones junto al QR del POS:
- **Ver QR**: abre la imagen del QR estatico del POS en una nueva pestana.
- **Imprimir QR**: abre el PDF imprimible del QR para que pueda imprimirlo y dejarlo fijo en el mostrador.

> En movil los botones muestran solo el icono; en escritorio muestran icono y etiqueta.

#### Cobro con integracion de pago (QR dinamico, QR estatico o QR de monto libre)

Una vez que la sucursal y la caja estan sincronizadas y la forma de pago tiene la integracion asignada (ver seccion 12.4), el cobro por QR esta disponible en todos los puntos de cobro del sistema: Nueva Venta, Pedidos por Mostrador (desglose desde el editor, cobro rapido desde el listado y confirmacion de pagos planificados).

El **modo de cobro** se define al configurar la forma de pago (ver seccion 12.4):
- **QR dinamico**: genera un QR unico por venta con el monto exacto. Expira y es de un solo uso. Ver el flujo completo en la seccion **3.1 — Cobro con integracion de pago**.
- **QR estatico**: usa el QR fisico del mostrador. El sistema envia el monto al POS de la caja y el cliente escanea el QR impreso que ya esta fijo. No caduca el QR (es permanente), pero la orden de cobro si tiene vencimiento. Ver el flujo completo en la seccion **3.1 — Cobro con integracion de pago**.
- **QR de monto libre**: no requiere sincronizacion de sucursal ni caja en Mercado Pago. El modal muestra la imagen del QR "Cobrar" configurada en la forma de pago; el cliente escanea e ingresa el monto en su app; el cajero confirma manualmente. Ver el flujo completo en la seccion **3.1 — Cobro con integracion de pago**.

#### Mercado Pago - Point (posnet fisico)

Point es un producto de Mercado Pago separado del QR. Requiere sus **propias credenciales** (Access Token de la aplicacion Point, distinta a la del QR) aunque sean de la misma cuenta de Mercado Pago.

Al cobrar con Point, el monto se empuja directamente a la terminal fisica asignada a la caja. El cliente paga pasando la tarjeta o escaneando el QR que muestra el propio aparato. El cajero ve el modal "Esperando pago en la terminal" sin ningun QR en pantalla.

##### Configurar la integracion Point

Haga clic en **"Configurar"** junto a Mercado Pago - Point:

1. **Modo**: "Test" o "Produccion".
2. **User ID Externo**: ID de la cuenta MP (puede ser el mismo que el QR si es la misma cuenta).
3. **Access Token (Produccion)** y **Access Token (Test)** correspondientes a la aplicacion Point.
4. Haga clic en **"Probar Conexion"** y luego en **"Guardar"**.

##### Vincular terminales a las cajas

Dentro de la integracion Point, una vez guardadas las credenciales, aparece la seccion **"Terminales por caja"**:

1. Haga clic en **"Buscar terminales"**: el sistema consulta a Mercado Pago y lista los dispositivos Point vinculados a la cuenta.
2. Para cada caja que no tiene terminal asignada, seleccione la terminal en el desplegable y haga clic en **"Vincular"**.
   - Al vincular, el sistema activa el modo integrado (PDV) en el dispositivo: a partir de ese momento el posnet acepta cobros enviados desde el sistema en lugar de operarse manualmente.
3. Para quitar la asignacion, haga clic en **"Desvincular"**.

Cada caja muestra su estado de terminal:
- Punto verde + ID de la terminal: la caja tiene una terminal asignada.
- "Sin terminal": la caja aun no tiene una terminal Point vinculada.

> Si la caja no tiene terminal vinculada, la forma de pago Point no estara disponible para esa caja en el punto de venta.

##### Cobro con Point

Con la integracion configurada y la terminal vinculada a la caja, al usar una forma de pago con modo Point en el punto de venta:

1. El sistema envia el monto a la terminal fisica de la caja.
2. El modal muestra **"Esperando pago en la terminal"** (sin QR: el aparato gestiona la interaccion con el cliente).
3. El cliente paga en el posnet con tarjeta o QR del propio aparato.
4. Al confirmar MP via webhook, el modal cierra y la venta se materializa automaticamente.
5. Si el cajero cancela, el sistema envia la cancelacion a MP con el estado "en terminal" permitido.

Las cuotas seleccionadas en el desglose de la venta se envian como cuotas de tarjeta de credito (solo cuando el medio configurado es "Tarjeta de credito").

##### Pruebas con Point en ambiente Test

Point se prueba con un posnet fisico vinculado a credenciales de TEST de la aplicacion Point. No se realiza ningun pago fisico; en cambio, los distintos estados del cobro (aprobado, rechazado, cancelado, expirado) se simulan enviando eventos a la order desde el panel de desarrolladores de MP o via API:

```
POST https://api.mercadopago.com/v1/orders/{order_id}/events
{ "status": "processed" }   → simula pago aprobado
{ "status": "failed" }      → simula pago rechazado
{ "status": "canceled" }    → simula cancelacion
```

Mercado Pago envia el webhook real al sistema en respuesta a esos eventos.

> Para operar con dinero real en el posnet se requiere una cuenta de PRODUCCION y credenciales de produccion.

#### Confirmacion en tiempo real (webhook — aplica a QR y Point)

Cuando Mercado Pago esta configurado con un **Webhook Secret**, las confirmaciones de cobro llegan al sistema de forma instantanea: MP envia una notificacion al servidor en el momento en que el cliente aprueba el pago, y el modal de espera del cajero se cierra al instante sin necesidad de esperar ciclos de consulta. Esto aplica tanto para cobros por QR como por Point, ya que ambos usan el mismo topic de webhook ("orders").

Para activar esta funcionalidad, complete el campo **"Webhook Secret"** en el modal de configuracion de cada integracion. La URL de webhook del sistema es: `https://{su-dominio}/api/integraciones/mercadopago/webhook`.

Debajo del campo "Webhook Secret" hay un boton **"¿Como obtener el Webhook Secret?"** que despliega la guia paso a paso: en la aplicacion creada en Mercado Pago ir a "Notificaciones" → "Webhooks" → "Configurar notificaciones", asegurarse de estar en la pestaña **"Modo productivo"**, pegar la URL del sistema (el panel la muestra con un boton "Copiar"), seleccionar unicamente el evento **"Order (Mercado Pago)"**, guardar y copiar la clave secreta generada al campo del formulario. Cada aplicacion de MP (QR, Point) tiene su propio webhook y su propia clave: los pasos se repiten en cada una.

> Si el Webhook Secret no esta configurado, el sistema igual funciona correctamente: consulta el estado del pago cada 3 segundos como respaldo. La diferencia es solo en la velocidad de deteccion de la confirmacion.

#### Expiracion automatica de cobros

Si un cobro queda esperando pago y el tiempo configurado vence, el sistema lo expira automaticamente sin requerir intervencion manual. El modal del cajero se cierra y muestra un aviso de tiempo agotado. No se genera ninguna venta ni movimiento de caja. El cajero puede iniciar un nuevo cobro desde el mismo punto de cobro. Aplica tanto a cobros por QR como por Point.

#### Cuenta empresa y saldo del proveedor

Al guardar credenciales de una integracion en modo **Produccion**, el sistema crea automaticamente (o reutiliza si ya existe) una **Cuenta Empresa** que representa la cuenta real del proveedor (por ejemplo, la cuenta de Mercado Pago del comercio). Esta cuenta aparece en Tesoreria → Cuentas Empresa junto con el saldo acumulado.

**Como se actualiza el saldo:**

- Cuando un cobro por integracion se **confirma** (via webhook automatico, polling o confirmacion manual), el sistema registra un movimiento de ingreso en esa cuenta con el concepto "Cobro por integracion de pago".
- El ingreso se registra en el momento de la confirmacion del cobro, no al emitir el comprobante. Esto refleja el instante en que el dinero realmente entro al proveedor.
- Si el comercio tiene dos sucursales que usan cuentas de Mercado Pago distintas, cada cobro impacta la cuenta de su propia sucursal.
- Si dos sucursales comparten la misma cuenta de Mercado Pago (mismo User ID Externo), sus cobros convergen al saldo de una sola Cuenta Empresa.
- Los cobros en modo **Test** no generan ningun movimiento ni afectan el saldo.

**Anular una venta cobrada por integracion:**

Al anular la venta, el movimiento de la cuenta del proveedor **no se revierte**. Esto es correcto: el dinero sigue en Mercado Pago salvo que el comercio haga una devolucion manual desde el panel del proveedor. El sistema refleja este comportamiento real.

#### Permisos requeridos

| Permiso | Descripcion |
|---|---|
| `func.integraciones_pago.administrar` | Configurar y sincronizar integraciones, vincular terminales Point (acceso al modulo de configuracion) |
| `integraciones_pago.confirmar_manual` | Confirmar manualmente un cobro en los modos QR dinamico, QR estatico y Point cuando el sistema no lo detecto automaticamente. Habilita el panel de fallback en el modal "Esperando pago". No aplica al modo QR de monto libre (cuya confirmacion manual es la unica forma de cerrar el cobro y no requiere permiso). Asignar solo a cajeros supervisores de confianza. |

---

### 12.12 Personalizar 2da Pantalla (por Sucursal)

Permite definir la apariencia de la pantalla orientada al cliente (segundo monitor) de forma independiente para cada sucursal.

> Este boton aparece solo en la tarjeta de una sucursal que tenga al menos una caja con "Usa pantalla orientada al cliente" activado.

#### Abrir el modal

En **Configuracion → Empresa → Sucursales**, localice la tarjeta de la sucursal y haga clic en **"Personalizar 2da pantalla"**. Se abre un modal con las opciones de configuracion y una vista previa en tiempo real.

#### Opciones disponibles

| Opcion | Valores | Descripcion |
|---|---|---|
| Mostrar logo | Si / No | Si se muestra el logo de la sucursal (o empresa si no tiene logo propio). |
| Mostrar nombre | Si / No | Si se muestra el nombre publico de la sucursal. |
| Color de fondo | Hex (ej: #222036) | Color de fondo de la pantalla idle. |
| Animacion | Ninguna / Respiracion / Aurora | Efecto visual sobre el fondo durante el estado de espera. "Respiracion" pulsa la opacidad suavemente; "Aurora" genera un degradado animado. Ambas respetan la preferencia del sistema operativo de reducir animaciones. |
| Color de acento | Hex (ej: #22d3ee) | Color usado en textos y bordes destacados. |
| Color de texto | Automatico / Hex | "Automatico" calcula el color (blanco o negro) segun la luminancia del fondo para garantizar contraste. Con un hex fijo puede elegir el color manualmente. |
| Mensaje en espera | Texto libre | Texto que se muestra en la pantalla cuando no hay cobro en curso (por defecto: "Listo para cobrar"). |
| Tamano del logo | Pequeno / Mediano / Grande | Controla el tamano del logo en la pantalla idle. |

#### Vista previa en vivo

El modal incluye una miniatura de la pantalla cliente que se actualiza al instante con cada cambio que realice, antes de guardar.

#### Guardar

Haga clic en **"Guardar"**. Los cambios se aplican de inmediato en la proxima vez que la pantalla cliente se conecte o se recargue. La config se transmite automaticamente via BroadcastChannel cuando el host (POS) esta conectado.

#### Instalar la 2da pantalla como app

Haga clic en su perfil de usuario (esquina superior derecha del navbar) y seleccione **"Instalar pantalla cliente"**. El sistema abrira `/pantalla-cliente?instalar=1` en una pestana nueva. En esa pagina aparecera un cartel destacado con el boton **"Instalar ahora"** que dispara el dialogo nativo de instalacion del navegador.

Si el navegador detecta que la app ya esta instalada (o no soporta instalacion PWA), el cartel lo indicara con un boton **"Entendido"** para cerrarlo. Al completarse la instalacion con exito, el cartel cambia a un mensaje de confirmacion.

El boton esta disponible siempre en el desplegable de perfil (no se oculta aunque la app ya este instalada), ya que desde la app principal no es posible detectar con fiabilidad si la PWA de pantalla cliente esta instalada en el dispositivo.

La pantalla cliente y la app principal (BCN Pymes) tienen scopes de PWA distintos y no superpuestos, por lo que pueden instalarse como apps separadas al mismo tiempo en el mismo navegador.

> **Flujo recomendado con la app principal instalada**: instale ambas apps. El sistema principal corre bajo `/app`; la pantalla cliente, bajo `/pantalla-cliente`. Una vez instaladas, el cajero abre la pantalla cliente desde su icono; la deteccion es automatica.

---

### 12.13 Monitor Llamador de Pedidos (por Sucursal)

Pantalla publica full-screen pensada para TV o tablet fija en el salon: muestra en dos columnas los pedidos **En preparacion** (ambar) y **Listo / Retirar** (verde) y se actualiza en tiempo real sin requerir login.

#### Abrir el modal de configuracion

En **Configuracion → Empresa → Sucursales**, localice la tarjeta de la sucursal y haga clic en **"Llamador"**. Se abre el modal con las opciones de vinculacion y personalizacion.

#### Activar o desactivar el llamador

El toggle **"Usar monitor llamador"** en la parte superior del modal activa la pantalla publica. Cuando esta apagado, ningun evento se emite (los cambios de estado de pedidos no llegan al monitor). Al guardar con el toggle en "apagado", la pantalla mostrara un aviso de que la funcion no esta activa.

#### Vincular un dispositivo

La seccion izquierda del modal muestra tres formas equivalentes de vincular un dispositivo (TV, tablet, celular):

| Metodo | Como usarlo |
|---|---|
| **Escanear QR** | Abra la camara o un lector QR en el dispositivo y escanee. El dispositivo navegara a la URL exacta del llamador. |
| **URL de pantalla publica** | Copie la URL y abra manualmente en el navegador del dispositivo. |
| **Para tipear a mano** | URL corta generica (ej: `midominio.com/ll`) mas el **codigo de vinculacion** de 6 caracteres que se ingresa en pantalla. Util para tipear en el control remoto de una TV. |

El **codigo de vinculacion** (6 caracteres en alfabeto sin ambiguedades) se muestra en el modal y puede copiarse. Al abrir la URL corta generica, la pantalla pide el codigo; al ingresarlo, el dispositivo queda vinculado y guarda el token en su almacenamiento local para sesiones futuras.

#### Regenerar token

El boton **"Regenerar token"** genera nuevas credenciales. Todos los dispositivos vinculados anteriormente pierden acceso y deben vincularse de nuevo ingresando el nuevo codigo. Usarlo solo si la URL se filtro. El token es compartido entre el llamador y el consultor de precios de la misma sucursal: regenerar aqui invalida ambas pantallas.

#### Personalizacion

La columna derecha del modal permite personalizar:

| Opcion | Descripcion |
|---|---|
| Titulo | Texto que aparece en el encabezado del monitor (ej: "Pedidos", "Turnos"). Maximo 40 caracteres. |
| Mostrar logo | Solo visible si la sucursal tiene logo cargado. |
| Sonido al pasar a "Listo" | Emite un chime cuando un pedido llega a la columna "Listo / Retirar". |
| Tamano de los pedidos | Compacto / Normal / Grande (densidad base). Si hay muchos pedidos, el tamano se reduce automaticamente para que entren todos sin scroll. |
| Color de fondo | Color hex del fondo de la pantalla. |
| Color columna "En preparacion" | Color del encabezado de la columna izquierda. |
| Color columna "Listo / Retirar" | Color del encabezado de la columna derecha. |

#### Comportamiento de la pantalla llamador

- Al abrir la URL por primera vez, la pantalla pide el codigo de vinculacion. Una vez ingresado, el token queda guardado y no vuelve a pedirse.
- La pantalla carga el estado inicial de los pedidos (cold start) y luego recibe actualizaciones en tiempo real via WebSocket (Reverb).
- Al pasar un pedido a "Listo / Retirar", suena el chime si el sonido esta activado.
- **Desbloqueo de audio**: la primera vez que el monitor se carga, aparece una pantalla de toque para habilitar el audio del navegador (los navegadores bloquean el audio sin interaccion del usuario). Al tocar, el audio queda habilitado y la pantalla pasa al monitor.
- **Pantalla completa**: al tocar la pantalla, intenta activar el modo fullscreen del navegador.
- **Auto-fit**: si los pedidos no entran en la pantalla al tamano configurado, el sistema los achica hasta que entren todos sin scroll.
- **Instalable como PWA**: la URL del llamador tiene manifest propio con icono de marca propio (campana en naranja `#FFAF22` sobre fondo navy). Desde Chrome o Edge se puede instalar como app independiente (icono en el escritorio o barra de apps del sistema).

#### Rutas del llamador

| URL | Descripcion |
|---|---|
| `/llamador/{token}` | Acceso directo con el token ya resuelto (generado por QR o URL larga). |
| `/llamador` o `/ll` | URL generica: pide el codigo de vinculacion en pantalla. |
| `/ll/{codigo}` | URL con el codigo incluido; la pantalla lo canjea automaticamente. |

---

### 12.14 Consultor de Precios (por Sucursal)

Pantalla publica orientada a un **scanner de codigo de barras** conectado a una PC o tablet en el mostrador. El cliente puede acercar un producto al scanner y ver instantaneamente su nombre, precio y promociones activas.

#### Abrir el modal de configuracion

En **Configuracion → Empresa → Sucursales**, localice la tarjeta de la sucursal y haga clic en **"Consultor de precios"**. Se abre el modal con las opciones de vinculacion y personalizacion.

#### Activar o desactivar el consultor

El toggle **"Usar consultor de precios"** activa la pantalla. Cuando esta apagado, los endpoints de busqueda devuelven 404 (no se expone informacion de precios).

#### Vincular un dispositivo

Identico al llamador: QR, URL larga, URL corta + codigo. El **codigo de vinculacion es el mismo** que el del llamador (token compartido por sucursal). Regenerar el token desde cualquiera de los dos modales invalida ambas pantallas.

#### Personalizacion

| Opcion | Descripcion |
|---|---|
| Titulo | Texto en el encabezado (ej: "Consultá tu precio"). Maximo 40 caracteres. |
| Mostrar logo | Solo si la sucursal tiene logo. |
| Color de fondo | Color hex del fondo. |
| Color de acento | Color del precio destacado y los bordes de resultado. |
| Mensaje en espera | Frase que se muestra cuando no hay ninguna busqueda activa (ej: "Escanee un articulo"). |
| Duracion del resultado | Segundos que el resultado permanece en pantalla antes de volver a la frase de espera (1-60 s). |

#### Comportamiento de la pantalla consultor

- La pantalla mantiene un **campo de texto invisible siempre enfocado** que captura lo que escribe el scanner. Cuando el scanner envia un codigo de barras (o el usuario tipea texto y presiona Enter), el sistema busca el articulo.
- Si el articulo se encuentra, muestra: nombre, unidad de medida, precio en grande y la lista de promociones activas en las que participa (tanto promociones normales como promociones especiales NxM/combo/menu). Luego de N segundos (configurables), la pantalla vuelve a la frase de espera.
- Si el articulo no se encuentra, muestra "No se encontro el articulo" brevemente.
- La busqueda funciona por codigo de barras exacto, codigo interno exacto o por nombre (parcial).
- **Pantalla completa automatica**: en la primera interaccion (toque en pantalla o primer escaneo), la pantalla entra a modo fullscreen sin requerir accion adicional del operador.
- **Sonido de exito**: al encontrar un precio se reproduce un arpegio ascendente breve (Do-Mi-Sol). Es distinto del chime de atencion del llamador de pedidos.
- **Instalable como PWA**: la URL del consultor tiene manifest propio con icono de marca propio (codigo de barras en naranja `#FFAF22` sobre fondo navy).

#### Rutas del consultor

| URL | Descripcion |
|---|---|
| `/precios/{token}` | Acceso directo con el token. |
| `/precios` o `/pr` | URL generica: pide el codigo de vinculacion en pantalla. |
| `/pr/{codigo}` | URL con codigo incluido; se canjea automaticamente. |

---

## 13. Fiscal

El modulo Fiscal centraliza la informacion tributaria del comercio. Agrupa la posicion de IVA, los libros de IVA ventas/compras, el ledger de movimientos fiscales y el importador de padron de percepcion IIBB, consultables por CUIT y periodo mensual. Requiere permiso `func.fiscal.*` (asignado a Administrador y Super Administrador).

Acceso desde el menu lateral: **Fiscal → Posicion fiscal**, **Fiscal → Libros IVA**, **Fiscal → Movimientos fiscales** y **Fiscal → Importar padron**.

### 13.1 Posicion Fiscal

**Ruta**: `/fiscal/posicion`

Muestra el estado tributario del comercio para un CUIT y un periodo (mes/ano).

#### Que ve al entrar

- Selector de CUIT (desplegable con los CUITs del comercio).
- Selector de periodo (mes y ano, con flechas de navegacion).
- Dos paneles: **Posicion de IVA** y **Posicion de IIBB** (Ingresos Brutos).

#### Panel de Posicion de IVA

Presenta el calculo del saldo de IVA del periodo:

| Campo | Descripcion |
|---|---|
| Debito fiscal | IVA facturado a clientes (suma de todos los comprobantes del periodo) |
| Credito fiscal | IVA pagado en compras de facturas A |
| Saldo tecnico | Debito menos credito fiscal |
| Percepciones de IVA sufridas | Percepciones de IVA que retuvieron proveedores o el proveedor de pago (a cuenta de IVA) |
| Retenciones de IVA sufridas | Retenciones de IVA sufridas (a cuenta) |
| Total a cuenta | Suma de percepciones y retenciones sufridas |
| Saldo | Saldo tecnico menos total a cuenta |
| **IVA a pagar** | Si el saldo es positivo: monto a pagar a AFIP |
| **Saldo a favor** | Si el saldo es negativo: saldo a favor del comercio |

Las percepciones y retenciones que el CUIT aplica como agente (deuda a depositar) se informan por separado y NO forman parte del saldo de IVA propio.

#### Panel de Posicion de IIBB

Muestra el resumen por jurisdiccion (provincia). Para cada jurisdiccion:
- Base imponible (ingresos netos gravados del periodo)
- Percepciones sufridas a cuenta
- Retenciones sufridas a cuenta
- Total a cuenta
- Percepciones y retenciones aplicadas como agente (deuda a depositar)

La jurisdiccion surge del domicilio fiscal del punto de venta del comprobante, no de la ubicacion fisica de la sucursal.

---

### 13.2 Libros IVA

**Ruta**: `/fiscal/libros`

Subdiarios de IVA: el detalle de todos los comprobantes del periodo, ordenados por fecha y numero. Util para la preparacion de la declaracion jurada mensual.

#### Que ve al entrar

- Selector de CUIT y selector de periodo (igual que Posicion Fiscal).
- Dos solapas: **Libro IVA Ventas** y **Libro IVA Compras**.

#### Libro IVA Ventas

Lista todos los comprobantes fiscales autorizados del CUIT en el periodo. Para cada comprobante muestra:
- Fecha de emision.
- Tipo y numero (ej: FA 00001-00000123).
- Razon social del receptor.
- Neto gravado, neto no gravado, neto exento.
- IVA (suma de todas las alicuotas).
- Otros tributos.
- Total del comprobante.

El pie de la tabla muestra los totales del periodo.

**Exportar CSV**: boton "Exportar CSV" descarga un archivo compatible con planillas de calculo con una fila por comprobante.

#### Libro IVA Compras

Lista las compras con atribucion fiscal registradas en el periodo (aquellas que tienen un CUIT del comercio asignado y percepciones/retenciones cargadas). Muestra credito fiscal de IVA, percepciones y retenciones sufridas por cada compra.

> El modulo de compras esta en desarrollo; este libro se completa a medida que se cargan percepciones en cada factura de proveedor.

---

### 13.3 Movimientos Fiscales

**Ruta**: `/fiscal/movimientos` | **Permiso**: `func.fiscal.movimientos`

Ledger fiscal del comercio: lista todos los impuestos sufridos y aplicados registrados para un CUIT y periodo. Permite dar de alta movimientos manualmente (retenciones o percepciones sufridas fuera de los flujos automaticos de ventas, compras o conciliacion de MercadoPago) y anularlos por contraasiento.

#### Que ve al entrar

La pantalla carga con el primer CUIT activo y el mes actual preseleccionados. Muestra una tabla (desktop) o tarjetas (movil) con los movimientos del periodo, ordenados por fecha descendente. Cada fila muestra:

- Fecha del movimiento.
- Nombre del impuesto.
- Naturaleza (Percepcion, Retencion o Tributo).
- Sentido (Sufrido / Aplicado).
- Base imponible (si fue informada).
- Monto.
- Estado (Activo / Anulado).

#### Filtros disponibles

| Filtro | Descripcion |
|---|---|
| CUIT | Selecciona el CUIT del comercio a consultar |
| Periodo | Mes y ano (campo tipo mes, formato AAAA-MM) |
| Sentido | Todos / Sufrido / Aplicado |
| Naturaleza | Todas / Percepcion / Retencion / Tributo / Debito fiscal / Credito fiscal |
| Incluir anulados | Casilla de verificacion; por defecto oculta los movimientos anulados |

Todos los filtros se sincronizan con la URL, de modo que la vista es compartible y recargable.

#### Alta manual de un movimiento

Boton **Alta manual** (esquina superior derecha). Se abre un modal con los siguientes campos:

| Campo | Obligatorio | Descripcion |
|---|---|---|
| CUIT | Si | CUIT del comercio al que se imputa el movimiento. Se pre-carga con el CUIT del filtro activo |
| Impuesto | Si | Selector agrupado: primero aparecen los impuestos ya configurados para ese CUIT, luego el resto del catalogo. Si se elige un impuesto con naturaleza default compatible, la naturaleza se pre-carga automaticamente |
| Sentido | Si | Sufrido (el comercio lo pago) o Aplicado (el comercio lo cobro como agente) |
| Naturaleza | Si | Percepcion, Retencion o Tributo (el debito/credito fiscal de IVA se genera solo desde comprobantes y compras, por eso no aparece en el alta manual) |
| Fecha | Si | Fecha del comprobante o certificado. Determina el periodo fiscal AAAA-MM |
| N° de certificado | No | Numero de certificado de retencion o percepcion. Hasta 50 caracteres |
| Base imponible | No | Monto sobre el que se calcula el impuesto |
| Alicuota (%) | No | Alicuota aplicada. Si se completan base y alicuota, el monto se sugiere automaticamente (base × alicuota / 100) pero es editable |
| Monto | Si | Monto del impuesto. Debe ser mayor a cero |
| Observaciones | No | Texto libre hasta 1000 caracteres |

Luego de completar los datos, hacer clic en **Registrar** para guardar. El movimiento queda en estado Activo y se refleja en la Posicion Fiscal del periodo correspondiente.

**Caso de uso tipico**: una empresa cliente que actua como agente de retencion de IIBB aplica una retencion al momento de pagar una factura. Esa retencion no llega por ningun flujo automatico del sistema, asi que se carga desde esta pantalla como Retencion sufrida.

#### Anulacion de un movimiento

Los movimientos en estado Activo tienen un boton **Anular** (texto en rojo en desktop, link al pie de la tarjeta en movil). Al hacer clic se abre un modal de confirmacion con un campo opcional de motivo. Al confirmar:

- Se genera un **contraasiento** inmutable: una nueva fila en el ledger que cancela el movimiento original.
- Tanto el movimiento original como el contraasiento quedan con estado Anulado.
- La Posicion Fiscal del periodo se actualiza automaticamente (solo cuenta movimientos Activos).

La anulacion es siempre total (no se puede anular parcialmente un movimiento).

> Los movimientos originados automaticamente desde comprobantes, compras o conciliacion de MercadoPago tambien aparecen en esta pantalla y pueden anularse del mismo modo.

---

### 13.4 Importar Padron

**Ruta**: `/fiscal/padrones` | **Permiso**: `func.fiscal.configuracion`

Pantalla para importar el padron oficial de percepcion de Ingresos Brutos de ARBA (Buenos Aires) o AGIP (CABA) y actualizar automaticamente el perfil fiscal de los clientes del comercio segun la alicuota que les corresponde en el padron.

> Esta pantalla es de uso administrativo y no afecta el flujo de ventas directamente. Una vez importado el padron, las percepciones de IIBB en ventas se calculan automaticamente usando las alicuotas del padron.

#### Que ve al entrar

Una pantalla de dos paneles:

- **Panel izquierdo (indicaciones)**: explica como funciona el padron, que archivos acepta y las reglas de precedencia (el ajuste manual no se pisa).
- **Panel derecho (controles)**: selector de agencia, campo para subir el archivo comprimido y boton de importar.

Debajo de los paneles, luego de una importacion exitosa, aparece un **resumen de resultados** con cuatro metricas:

| Metrica | Descripcion |
|---|---|
| Clientes actualizados | Total de perfiles de clientes creados o actualizados (nuevo + actualizado) |
| Nuevos perfiles | Clientes que no tenian perfil fiscal para ese impuesto y lo recibieron |
| Perfiles actualizados | Clientes que ya tenian perfil de padron y se actualizo la alicuota/vigencia |
| Omitidos (carga manual) | Clientes que tenian ajuste manual: no se pisaron |

Al pie del resumen se informa ademas cuantas filas totales tenia el padron, cuantas eran de percepcion validas y cuantos CUIT del padron no son clientes del comercio.

#### Como importar el padron paso a paso

1. Descargue el padron oficial del sitio de la agencia (ARBA o AGIP). El archivo descargado viene comprimido (`.zip`).
2. En la pantalla, seleccione la **Agencia** correspondiente:
   - **ARBA — Buenos Aires**: para percepcion de IIBB de la Provincia de Buenos Aires (`perc_iibb_ar_b`).
   - **AGIP — CABA**: para percepcion de IIBB de la Ciudad Autonoma de Buenos Aires (`perc_iibb_ar_c`).
3. Haga clic en el campo de archivo y seleccione el archivo `.zip` tal cual lo descargo de la agencia. **No hace falta descomprimirlo**. Tambien se acepta `.gz`. El sistema descomprime automaticamente por streaming.
4. Durante la subida se muestra una **barra de progreso** con el porcentaje completado.
5. Una vez subido el archivo, haga clic en **Importar padron**.
6. El sistema procesa el archivo (puede demorar segun el tamano del padron) y muestra el resumen de resultados.

#### Reglas que aplica el sistema al importar

- **Solo percepcion**: el padron de ARBA contiene filas de percepcion (P) y de retencion (R). El sistema solo procesa las de percepcion; la retencion es del lado de compras y se ignora. AGIP incluye ambas alicuotas en el mismo archivo; solo se usa la alicuota de percepcion.
- **Exencion conservadora**: si la alicuota del padron es 0,00 o si la fila indica una baja del sujeto, el cliente queda marcado como exento para ese impuesto (no se le percibe). Nunca se asume percepcion ante la duda.
- **El ajuste manual tiene prioridad**: si el perfil de un cliente fue cargado a mano desde la pantalla Clientes → Perfil fiscal, el padron no lo pisa. Ese cliente aparece en el contador "Omitidos (carga manual)".
- **Idempotente**: importar el mismo padron dos veces no duplica perfiles. La clave de unicidad es cliente + impuesto + fecha de vigencia desde.
- **Solo clientes del comercio**: el padron incluye toda la provincia o CABA. El sistema descarta al vuelo todos los CUIT que no sean clientes registrados del comercio; solo actualiza los que coinciden.

#### Restricciones del archivo

- Solo se aceptan archivos comprimidos: `.zip` (formato oficial de las agencias) o `.gz`.
- El archivo `.txt` plano del padron completo (~92 MB) no se acepta por la web; debe subirse comprimido (~7 MB).
- Tamano maximo: 100 MB (comprimido).
- Si se sube un archivo con extension incorrecta, el sistema muestra el mensaje: "El padron debe subirse comprimido (.zip o .gz)."

#### Mensajes de validacion

| Situacion | Mensaje |
|---|---|
| No se selecciono archivo | "Selecciona el archivo comprimido del padron." |
| Formato incorrecto (no .zip ni .gz) | "El padron debe subirse comprimido (.zip o .gz)." |
| Archivo supera 100 MB | "El archivo supera el tamano maximo permitido (100 MB)." |
| El impuesto no esta en el catalogo | Error con detalle del problema |

> **Alternativa via servidor**: si el archivo es muy grande o la subida web no es practica, el administrador del servidor puede usar el comando artisan `php artisan fiscal:importar-padron /ruta/al/archivo --agencia=arba --comercio=ID` que acepta `.txt`, `.zip` o `.gz` directamente desde el servidor.

---

## 14. Flujos de Trabajo Comunes

### 14.1 Abrir el comercio por la manana

1. Inicie sesion en el sistema.
2. Verifique que la sucursal correcta este seleccionada en la barra superior.
3. Vaya a **Cajas > Turno Actual**.
4. Para cada caja que necesite operar:
   - Haga clic en **"Abrir Turno"**.
   - Ingrese el saldo inicial (el dinero en efectivo que coloca en la caja al comenzar).
   - Confirme.
5. Si tiene grupos de cierre configurados, puede abrir todas las cajas del grupo a la vez.
6. Seleccione la caja con la que trabajara en el selector de la barra superior.
7. El sistema esta listo para operar.

---

### 14.2 Realizar una venta tipica

1. Vaya a **Ventas > Nueva Venta** (o haga clic en "Nueva Venta" desde el listado).
2. Verifique que la caja este operativa (indicador verde).
3. Busque el primer articulo por nombre o codigo y seleccionelo.
4. Si el articulo tiene opcionales, complete el wizard de opcionales.
5. Repita para cada articulo.
6. Si el cliente tiene cuenta en el sistema, busquelo y seleccionelo.
7. Seleccione la forma de pago.
8. Verifique el total.
9. Presione **F2** o haga clic en **"Cobrar"**.
10. Si es efectivo, ingrese el monto recibido y verifique el vuelto.
11. Confirme la venta.
12. El ticket se imprimira automaticamente (si hay impresora configurada).

---

### 14.3 Cobrar deuda de un cliente

1. Vaya a **Clientes > Cobranzas**.
2. Busque al cliente por nombre o CUIT.
3. Haga clic en **"Cobrar"** junto al cliente.
4. Revise las ventas pendientes.
5. Ingrese el monto que el cliente paga.
6. Agregue la forma de pago en el desglose.
7. Verifique que el monto pendiente sea cero.
8. Haga clic en **"Confirmar Cobro"**.
9. El saldo del cliente se actualizara automaticamente.

---

### 14.4 Cerrar caja al final del dia

1. Vaya a **Cajas > Turno Actual**.
2. Haga clic en **"Cerrar Turno"** en la caja que desea cerrar.
3. Revise el resumen del turno:
   - Saldo inicial.
   - Total de ingresos y egresos.
   - Saldo esperado.
4. Cuente el dinero fisico en la caja.
5. Declare el saldo real contado (para cada moneda si corresponde).
6. El sistema calculara la diferencia (sobrante o faltante).
7. Agregue observaciones si es necesario.
8. Confirme el cierre.
9. Si usa tesoreria, el saldo generara una rendicion pendiente que debera ser recibida en tesoreria.

---

### 14.5 Hacer inventario fisico

**Para un inventario rapido (pocos articulos):**

1. Vaya a **Stock > Inventario por Sucursal**.
2. Busque el articulo.
3. Haga clic en **"Inventario Fisico"**.
4. Ingrese la cantidad contada.
5. Confirme. El sistema ajustara la diferencia.

**Para un inventario general (muchos articulos):**

1. Vaya a **Stock > Inventario General**.
2. Use los filtros para seleccionar la seccion a inventariar (por ejemplo, una categoria).
3. Para cada articulo, ingrese la cantidad contada en el campo correspondiente.
4. Una vez que termine de contar, haga clic en **"Procesar Inventario"**.
5. Revise el resumen de diferencias y confirme.

---

### 14.6 Cambiar precios masivamente

1. Vaya a **Articulos > Cambio Masivo de Precios**.
2. **Paso 1**: Configure el ajuste:
   - Seleccione "Recargo" si quiere aumentar precios o "Descuento" si quiere bajarlos.
   - Ingrese el porcentaje o monto.
   - Seleccione el redondeo deseado.
   - Elija si aplicar ahora o programar para mas tarde.
3. Haga clic en "Siguiente".
4. **Paso 2**: Filtre y revise los articulos afectados:
   - Aplique filtros por categoria o etiqueta si no quiere afectar todos los articulos.
   - Revise el precio actual y el precio nuevo de cada articulo.
   - Ajuste manualmente cualquier precio que necesite un valor diferente.
5. Haga clic en **"Aplicar Cambios"** y confirme.

---

### 14.7 Crear una promocion

**Ejemplo: "10% de descuento en bebidas pagando en efectivo"**

1. Vaya a **Configuracion > Promociones**.
2. Haga clic en **"Nueva Promocion"**.
3. **Paso 1**: Seleccione "Descuento %".
4. **Paso 2**: Nombre: "10% Bebidas Efectivo". Valor: 10.
5. **Paso 3**: Sucursal(es). Alcance: Categoria > Seleccionar "Bebidas".
6. **Paso 4**: Forma de pago: Seleccionar "Efectivo". Active "Combinable" si puede sumarse con otras promos.
7. **Paso 5**: Revise la prioridad. Use el simulador para verificar que funciona correctamente.
8. Guarde la promocion.

**Ejemplo: "Lleva 3, paga 2 en alfajores"**

1. Vaya a **Configuracion > Promociones Especiales**.
2. Haga clic en **"Nueva Promocion"**.
3. **Paso 1**: Seleccione "NxM basico".
4. **Paso 2**: Lleva: 3, Bonifica: 1, Beneficio: Gratis. Aplica a: Articulo > Buscar "Alfajor".
5. **Paso 3**: Configure vigencia y condiciones.
6. **Paso 4**: Verifique con el simulador.
7. Guarde la promocion.

---

## Glosario

| Termino | Definicion |
|---------|------------|
| **Ajuste de stock** | Modificacion manual de la cantidad de un articulo en stock, con un motivo registrado. |
| **Arqueo** | Proceso de contar el dinero fisico y compararlo con el saldo registrado por el sistema. |
| **ARCA/AFIP** | Administracion Federal de Ingresos Publicos de Argentina. El sistema puede consultar el padron y emitir comprobantes fiscales electronicos. |
| **Carrito** | Lista de articulos seleccionados en una venta antes de confirmarla. |
| **CBU** | Clave Bancaria Uniforme; identificador unico de una cuenta bancaria en Argentina. |
| **Comprobante fiscal** | Factura A, B o C emitida electronicamente ante ARCA. |
| **Concepto de pago** | Clasificacion del medio de pago (efectivo, debito, credito, transferencia). |
| **Condicion de IVA** | Situacion fiscal del cliente o proveedor (Responsable Inscripto, Monotributo, Consumidor Final, etc.). |
| **Contraasiento** | Movimiento que revierte una operacion anterior (se usa al anular ventas, compras o producciones). |
| **Cuenta corriente** | Linea de credito que el comercio otorga a un cliente, permitiendole comprar y pagar despues. |
| **CUIT** | Clave Unica de Identificacion Tributaria; numero fiscal asignado a personas y empresas en Argentina. |
| **Desglose de pagos** | Division de un pago en multiples formas de pago (pago mixto). |
| **Etiqueta** | Clasificador libre que se asigna a articulos para filtrar y organizar. |
| **Forma de pago** | Medio por el cual el cliente paga (ej: Efectivo, Visa Debito, Mercado Pago). |
| **Forma de pago mixta** | Forma de pago que permite combinar multiples medios en una sola operacion. |
| **Forma de venta** | Modalidad de la venta (Local, Delivery, Take Away, etc.). |
| **Grupo de cierre** | Conjunto de cajas que se cierran juntas al final del turno. |
| **Grupo opcional** | Conjunto de opciones que se pueden agregar a un articulo (ej: tamano, ingredientes extra). |
| **Inventario fisico** | Conteo real de la mercaderia y comparacion con el stock registrado en el sistema. |
| **IIBB** | Ingresos Brutos. Impuesto provincial sobre los ingresos de la actividad comercial. |
| **IVA** | Impuesto al Valor Agregado. |
| **Lista de precios** | Conjunto de precios con ajustes (recargos o descuentos) sobre los precios base. |
| **Materia prima** | Articulo que se usa como ingrediente en recetas pero que tipicamente no se vende directamente. |
| **Modo stock** | Como el sistema controla el stock de un articulo: Ninguno, Unitario o Por Receta. |
| **Nota de Credito** | Comprobante fiscal que anula total o parcialmente una factura. |
| **NxM** | Tipo de promocion: "Lleva N, paga M" (o bonifica la diferencia). |
| **Opcional** | Opcion dentro de un grupo opcional que puede tener precio adicional y receta propia. |
| **Produccion** | Proceso de fabricar un articulo a partir de sus ingredientes (segun receta). |
| **Posicion fiscal** | Estado tributario de un CUIT en un periodo: cuanto debe de IVA e IIBB, descontando creditos y retenciones sufridas. |
| **Punto de venta** | Numero habilitado ante ARCA para emitir comprobantes fiscales. |
| **Receta** | Composicion de un articulo en terminos de ingredientes y cantidades. |
| **Rendicion** | Proceso por el cual una caja entrega el efectivo del turno cerrado a tesoreria. |
| **Saldo a favor** | Monto que el comercio le debe al cliente (por ejemplo, si pago de mas). |
| **Sucursal** | Cada ubicacion fisica del comercio. |
| **Tesoreria** | Caja fuerte central de la sucursal donde se resguarda el efectivo no asignado a cajas operativas. |
| **Retencion** | Descuento que retiene un agente de retencion del pago a un proveedor, a cuenta de un impuesto. |
| **Percepcion** | Importe adicional que cobra un agente de percepcion sobre una operacion, a cuenta de un impuesto. |
| **Ticket** | Comprobante no fiscal de una venta. |
| **Tipo de cambio** | Cotizacion de una moneda respecto a otra (tasa de compra y tasa de venta). |
| **Turno** | Periodo operativo de una caja, desde su apertura hasta su cierre con arqueo. |
| **Wizard** | Asistente paso a paso que guia al usuario en un proceso complejo. |
