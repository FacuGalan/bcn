# BCN Pymes -- Manual de Usuario

> Manual completo del sistema BCN Pymes para administradores de comercio.
> Version: 0.1.x | Ultima actualizacion: 2026-04-13

---

## Tabla de Contenidos

- [1. Introduccion](#1-introduccion)
- [2. Panel de Control (Dashboard)](#2-panel-de-control-dashboard)
- [3. Ventas](#3-ventas)
  - [3.1 Nueva Venta (Punto de Venta)](#31-nueva-venta-punto-de-venta)
  - [3.2 Listado de Ventas](#32-listado-de-ventas)
  - [3.3 Programa de Puntos](#33-programa-de-puntos)
  - [3.4 Cupones](#34-cupones)
- [4. Compras](#4-compras)
- [5. Stock e Inventario](#5-stock-e-inventario)
  - [5.1 Inventario por Sucursal](#51-inventario-por-sucursal)
  - [5.2 Movimientos de Stock](#52-movimientos-de-stock)
  - [5.3 Inventario General](#53-inventario-general-todas-las-sucursales)
  - [5.4 Recetas](#54-recetas)
  - [5.5 Produccion](#55-produccion)
  - [5.6 Produccion por Lote](#56-produccion-por-lote)
- [6. Cajas](#6-cajas)
  - [6.1 Gestion de Cajas](#61-gestion-de-cajas)
  - [6.2 Turno Actual](#62-turno-actual)
  - [6.3 Historial de Turnos](#63-historial-de-turnos)
  - [6.4 Movimientos Manuales](#64-movimientos-manuales)
- [7. Tesoreria](#7-tesoreria)
  - [7.1 Gestion de Tesoreria](#71-gestion-de-tesoreria)
  - [7.2 Reportes de Tesoreria](#72-reportes-de-tesoreria)
- [8. Bancos](#8-bancos)
  - [8.1 Resumen de Cuentas](#81-resumen-de-cuentas)
  - [8.2 Gestion de Cuentas](#82-gestion-de-cuentas)
  - [8.3 Movimientos](#83-movimientos)
  - [8.4 Transferencias](#84-transferencias)
- [9. Articulos](#9-articulos)
  - [9.1 Gestion de Articulos](#91-gestion-de-articulos)
  - [9.2 Categorias](#92-categorias)
  - [9.3 Etiquetas](#93-etiquetas)
  - [9.4 Asignar Etiquetas](#94-asignar-etiquetas)
  - [9.5 Cambio Masivo de Precios](#95-cambio-masivo-de-precios)
  - [9.6 Grupos Opcionales](#96-grupos-opcionales)
  - [9.7 Asignar Opcionales](#97-asignar-opcionales)
- [10. Clientes](#10-clientes)
  - [10.1 Gestion de Clientes](#101-gestion-de-clientes)
  - [10.2 Cobranzas y Cuenta Corriente](#102-cobranzas-y-cuenta-corriente)
- [11. Configuracion](#11-configuracion)
  - [11.1 Datos de la Empresa](#111-datos-de-la-empresa)
  - [11.2 Usuarios](#112-usuarios)
  - [11.3 Roles y Permisos](#113-roles-y-permisos)
  - [11.4 Formas de Pago](#114-formas-de-pago)
  - [11.5 Formas de Pago por Sucursal](#115-formas-de-pago-por-sucursal)
  - [11.6 Listas de Precios](#116-listas-de-precios)
  - [11.7 Promociones](#117-promociones)
  - [11.8 Promociones Especiales](#118-promociones-especiales)
  - [11.9 Monedas](#119-monedas)
  - [11.10 Impresoras](#1110-impresoras)
- [12. Flujos de Trabajo Comunes](#12-flujos-de-trabajo-comunes)
  - [12.1 Abrir el comercio por la manana](#121-abrir-el-comercio-por-la-manana)
  - [12.2 Realizar una venta tipica](#122-realizar-una-venta-tipica)
  - [12.3 Cobrar deuda de un cliente](#123-cobrar-deuda-de-un-cliente)
  - [12.4 Cerrar caja al final del dia](#124-cerrar-caja-al-final-del-dia)
  - [12.5 Hacer inventario fisico](#125-hacer-inventario-fisico)
  - [12.6 Cambiar precios masivamente](#126-cambiar-precios-masivamente)
  - [12.7 Crear una promocion](#127-crear-una-promocion)
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
- **Barra superior**: Muestra el nombre de la pagina actual. A la derecha encontrara selectores de sucursal y caja, ademas de su perfil de usuario.
- **Area de contenido principal**: Ocupa la mayor parte de la pantalla y muestra la pagina o modulo seleccionado.

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

**Articulos pesables:** Si el articulo esta marcado como pesable (productos que se venden por peso como carnes, frutas, quesos), al seleccionarlo se abrira un modal especial donde puede:
- Ingresar la **cantidad** (en la unidad de medida del articulo: kg, gr, lt, etc.) y el sistema calcula automaticamente el valor.
- O ingresar el **valor** ($) y el sistema calcula automaticamente la cantidad.
Los dos campos estan sincronizados: al modificar uno, el otro se actualiza en tiempo real. Presione Enter o el boton "Agregar" para confirmar.

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

#### Facturacion fiscal

Si la sucursal tiene facturacion fiscal habilitada, la venta puede generar automaticamente un comprobante fiscal (factura A, B o C segun el cliente). El tipo de comprobante se muestra junto al nombre del cliente seleccionado.

Puede activar o desactivar la emision de factura fiscal mediante un checkbox en el resumen de totales. Si la sucursal esta configurada para facturacion automatica, la factura se emitira por defecto.

#### Resumen de totales

En la parte inferior de la columna derecha se muestra el resumen:

- **Subtotal**: Suma de todos los items sin descuentos.
- **Desc. promos**: Total de descuentos aplicados por promociones.
- **Total productos**: Subtotal menos descuentos.
- **Recargo/Descuento por forma de pago**: Si la forma de pago tiene un ajuste asociado.
- **Recargo por cuotas**: Si se seleccionaron cuotas con recargo.
- **TOTAL**: Monto final a cobrar.
- **Desglose de IVA**: Puede expandir esta seccion para ver el detalle de IVA.

#### Proceso de cobro

1. Verifique que todos los datos estan correctos (articulos, cantidades, precios, cliente, forma de pago).
2. Presione el boton **"Cobrar"** (o la tecla **F2**).
3. Segun la forma de pago:
   - **Efectivo**: Se abrira un modal para ingresar el monto recibido y calcular el vuelto.
   - **Moneda extranjera**: Se abrira un modal con el tipo de cambio vigente para calcular el equivalente.
   - **Otros**: La venta se procesa directamente.
4. Confirme la operacion.
5. El sistema registrara la venta, actualizara el stock, registrara el movimiento de caja y, si corresponde, emitira el comprobante fiscal.

#### Limpiar carrito

Presione **F3** o use el boton correspondiente para vaciar el carrito. El sistema le pedira confirmacion antes de borrar todos los items.

#### Consulta de precios (modo consulta)

Active el modo consulta presionando el boton con el icono de billete (o **Ctrl+3**). En este modo, al buscar y seleccionar un articulo, en lugar de agregarlo al carrito se abrira un modal mostrando toda la informacion de precios del articulo (precio base, precio con lista aplicada, IVA). Para salir del modo consulta, presione **Esc** o haga clic en la X junto al buscador.

#### Buscar en detalle

Active el modo de busqueda en detalle presionando el boton con icono de lista (o **Ctrl+4**). En este modo, al buscar un articulo, se resaltara en el carrito si ya esta agregado. Util para ventas con muchos items.

#### Agregar concepto

El boton con icono de billete verde (o **Ctrl+5**) permite agregar un concepto libre al carrito (por ejemplo, un servicio o cargo que no es un articulo del inventario). Se abrira un modal donde ingresa la descripcion, selecciona una categoria y establece el importe.

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

- **Buscar**: Por numero de venta, numero de ticket, nombre de cliente o numero de factura fiscal.
- **Estado**: Todas, Completada, Pendiente, Cancelada.
- **Forma de Pago**: Permite filtrar por la forma de pago utilizada.
- **Comprobante Fiscal**: Todas, Con factura, Sin factura.
- **Caja**: Caja actual o Todas mis cajas.
- **Fecha Desde / Fecha Hasta**: Rango de fechas para acotar la busqueda.
- **Limpiar filtros**: Restaura todos los filtros a sus valores por defecto.

En dispositivos moviles, los filtros se colapsan detras de un boton "Filtros" para ahorrar espacio.

#### Informacion de cada venta

La tabla muestra para cada venta:

- Numero de venta y fecha/hora.
- Nombre del cliente (o "Consumidor Final").
- Forma de pago utilizada.
- Total de la venta.
- Estado (completada, pendiente, cancelada) con un badge de color.
- Si tiene comprobante fiscal, se indica el tipo y numero.
- Botones de accion.

#### Acciones disponibles

Para cada venta en la lista, puede:

- **Ver detalle**: Abre un modal con toda la informacion de la venta: items vendidos con cantidades y precios, descuentos aplicados, datos del cliente, forma de pago, observaciones, comprobantes fiscales asociados, y los movimientos de caja y stock generados.

- **Reimprimir**: Permite reimprimir el ticket de la venta o el comprobante fiscal. Se abre un modal de confirmacion donde puede seleccionar que tipo de documento reimprimir.

- **Anular venta**: Si tiene el permiso correspondiente, puede cancelar una venta. El sistema le mostrara un modal donde debe ingresar un motivo de anulacion. Al anular:
  - Se revierte el stock (los articulos vuelven al inventario).
  - Se registra un contraasiento en la caja.
  - Si tiene factura fiscal, se emite automaticamente una Nota de Credito.
  - La venta queda marcada como "Cancelada".

> **Importante**: La anulacion de una venta es irreversible. Verifique cuidadosamente antes de confirmar.

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

---

## 4. Compras

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

## 5. Stock e Inventario

> **Nota:** Todas las cantidades de stock soportan hasta 3 decimales, lo que permite manejar articulos pesables (por ejemplo, 1.500 kg) y fracciones de unidades.

### 5.1 Inventario por Sucursal

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

### 5.2 Movimientos de Stock

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

- **Carga de stock**: Registrar un ingreso manual de stock. Se abre un modal donde busca el articulo, indica la cantidad, el concepto (por que ingresa el stock) y observaciones.

- **Descarga de stock**: Registrar una salida manual de stock. Similar a la carga pero para egresos (por ejemplo, merma, roturas, consumo interno).

- **Inventario fisico**: Registrar la cantidad real de un articulo. El sistema calcula la diferencia y genera el movimiento correspondiente (entrada o salida).

---

### 5.3 Inventario General (todas las sucursales)

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

### 5.4 Recetas

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

### 5.5 Produccion

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

### 5.6 Produccion por Lote

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

## 6. Cajas

### 6.1 Gestion de Cajas

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

---

### 6.2 Turno Actual

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

### 6.3 Historial de Turnos

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

### 6.4 Movimientos Manuales

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

## 7. Tesoreria

### 7.1 Gestion de Tesoreria

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

### 7.2 Reportes de Tesoreria

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

## 8. Bancos

### 8.1 Resumen de Cuentas

Muestra una vision general de todas las cuentas bancarias y billeteras digitales de la empresa.

#### Que ve al entrar

- **Totales por moneda**: Tarjetas mostrando el total acumulado en cada moneda (por ejemplo, pesos argentinos, dolares).
- **Lista de cuentas**: Cada cuenta con su nombre, tipo (banco o billetera digital), saldo actual y moneda.
- **Ultimos 10 movimientos**: Historial reciente de movimientos globales.

---

### 8.2 Gestion de Cuentas

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

---

### 8.3 Movimientos

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

### 8.4 Transferencias

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

## 9. Articulos

### 9.1 Gestion de Articulos

Es el catalogo central de productos de su comercio.

#### Que ve al entrar

- Panel de filtros.
- Tabla con todos los articulos mostrando: codigo, nombre, categoria, precio, stock en la sucursal actual, tipo y estado.

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

---

### 9.2 Categorias

Las categorias organizan los articulos en grupos logicos (ej: "Bebidas", "Alimentos", "Limpieza").

#### Que ve al entrar

- Filtros de busqueda y estado.
- Tabla con las categorias.

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

---

### 9.3 Etiquetas

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

### 9.4 Asignar Etiquetas

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

### 9.5 Cambio Masivo de Precios

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

### 9.6 Grupos Opcionales

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

### 9.7 Asignar Opcionales

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

## 10. Clientes

### 10.1 Gestion de Clientes

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
- Sucursales donde esta habilitado el cliente.
- Tambien es proveedor: Si el cliente es tambien proveedor, puede vincularlo o crear un nuevo proveedor automaticamente.

**Modo por CUIT (consulta ARCA/AFIP):**
Ingrese el CUIT y el sistema consultara automaticamente el padron de ARCA para completar los datos fiscales (razon social, condicion de IVA, direccion).

#### Acciones disponibles

- **Editar**: Modifica los datos del cliente.
- **Configurar sucursales**: Permite asignar listas de precios diferentes por sucursal para el mismo cliente.
- **Ver historial**: Muestra el historial de ventas del cliente.
- **Activar/Desactivar**: Cambia el estado del cliente.
- **Eliminar**: Solo si no tiene operaciones asociadas.
- **Importar clientes**: Permite importar clientes desde un archivo, seleccionando las sucursales destino.

---

### 10.2 Cobranzas y Cuenta Corriente

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

## 11. Configuracion

### 11.1 Datos de la Empresa

La configuracion de la empresa se organiza en pestanas:

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

#### Pestana "Sucursales"

Gestion de las sucursales del comercio:
- Nombre de la sucursal.
- Nombre publico (como se muestra en los comprobantes).
- Direccion, telefono y email de la sucursal.
- Logo de la sucursal (opcional).
- **Configuracion de la sucursal** (boton de engranaje):
  - Clave de autorizacion para operaciones sensibles.
  - Tipo de impresion de factura (termica, A4, ambos).
  - Agrupacion de articulos en venta e impresion.
  - Control de stock en ventas: Bloquea (no permite vender sin stock), Advierte (avisa pero permite), No controla.
  - Control de stock en produccion: Igual al anterior.
  - Facturacion fiscal automatica.
  - Configuracion de WhatsApp (envio de comandas y notificaciones).

#### Pestana "Cajas"

Gestion de las cajas de cada sucursal:
- Lista de cajas agrupadas por sucursal.
- Estado de cada caja.
- **Configuracion de caja**:
  - Nombre de la caja.
  - Limite de efectivo (monto maximo de efectivo que debe tener la caja).
  - Modo de carga inicial: Manual (el usuario ingresa el saldo al abrir) o Monto Fijo (se carga automaticamente un monto predeterminado).
  - Monto fijo inicial (si corresponde).
- **Puntos de venta**: Asignacion de puntos de venta fiscales a cada caja, con un punto de venta por defecto.
- **Grupos de cierre**: Permite crear grupos de cajas que se cierran juntas:
  - Nombre del grupo.
  - Sucursal.
  - Cajas que lo componen.
  - Fondo comun: Si las cajas del grupo comparten un fondo de efectivo.

---

### 11.2 Usuarios

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

### 11.3 Roles y Permisos

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

### 11.4 Formas de Pago

Configura las formas de pago aceptadas por el comercio.

#### Que ve al entrar

- Filtros de busqueda y estado.
- Tabla con las formas de pago.

#### Crear una forma de pago

Haga clic en **"Nueva Forma de Pago"** y complete:

**Para formas de pago simples:**
- **Nombre** (ej: "Efectivo", "Visa Debito", "Mercado Pago").
- **Concepto de pago**: Clasifica como entra el dinero (Efectivo, Tarjeta Debito, Tarjeta Credito, Transferencia, etc.).
- **Descripcion** (opcional).
- **Ajuste porcentual**: Recargo (+) o descuento (-) que se aplica al total. Por ejemplo, "+3%" para tarjeta de credito o "-5%" para efectivo.
- **Permite cuotas**: Si esta forma de pago ofrece pagos en cuotas.
- **Factura fiscal**: Si esta forma de pago genera factura fiscal por defecto.
- **Cuenta empresa**: Cuenta bancaria o billetera asociada (opcional).
- **Moneda**: Moneda de la forma de pago (util para pagos en moneda extranjera).
- **Sucursales**: En que sucursales esta disponible.

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

### 11.5 Formas de Pago por Sucursal

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

### 11.6 Listas de Precios

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

> **Nota**: La **lista base** es la lista de precios por defecto y no puede ser desactivada ni eliminada. Los precios base de los articulos pertenecen a esta lista.

---

### 11.7 Promociones

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

### 11.8 Promociones Especiales

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

### 11.9 Monedas

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

### 11.10 Impresoras

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

## 12. Flujos de Trabajo Comunes

### 12.1 Abrir el comercio por la manana

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

### 12.2 Realizar una venta tipica

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

### 12.3 Cobrar deuda de un cliente

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

### 12.4 Cerrar caja al final del dia

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

### 12.5 Hacer inventario fisico

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

### 12.6 Cambiar precios masivamente

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

### 12.7 Crear una promocion

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
| **IVA** | Impuesto al Valor Agregado. |
| **Lista de precios** | Conjunto de precios con ajustes (recargos o descuentos) sobre los precios base. |
| **Materia prima** | Articulo que se usa como ingrediente en recetas pero que tipicamente no se vende directamente. |
| **Modo stock** | Como el sistema controla el stock de un articulo: Ninguno, Unitario o Por Receta. |
| **Nota de Credito** | Comprobante fiscal que anula total o parcialmente una factura. |
| **NxM** | Tipo de promocion: "Lleva N, paga M" (o bonifica la diferencia). |
| **Opcional** | Opcion dentro de un grupo opcional que puede tener precio adicional y receta propia. |
| **Produccion** | Proceso de fabricar un articulo a partir de sus ingredientes (segun receta). |
| **Punto de venta** | Numero habilitado ante ARCA para emitir comprobantes fiscales. |
| **Receta** | Composicion de un articulo en terminos de ingredientes y cantidades. |
| **Rendicion** | Proceso por el cual una caja entrega el efectivo del turno cerrado a tesoreria. |
| **Saldo a favor** | Monto que el comercio le debe al cliente (por ejemplo, si pago de mas). |
| **Sucursal** | Cada ubicacion fisica del comercio. |
| **Tesoreria** | Caja fuerte central de la sucursal donde se resguarda el efectivo no asignado a cajas operativas. |
| **Ticket** | Comprobante no fiscal de una venta. |
| **Tipo de cambio** | Cotizacion de una moneda respecto a otra (tasa de compra y tasa de venta). |
| **Turno** | Periodo operativo de una caja, desde su apertura hasta su cierre con arqueo. |
| **Wizard** | Asistente paso a paso que guia al usuario en un proceso complejo. |
