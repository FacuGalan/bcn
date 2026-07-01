# Changelog

## [0.1.8](https://github.com/FacuGalan/bcn/compare/v0.1.7...v0.1.8) (2026-07-01)


### Funcionalidades

* **conciliacion:** conciliación de CuentaEmpresa contra el proveedor de pago (Paso 3) ([#132](https://github.com/FacuGalan/bcn/issues/132)) ([3ef2888](https://github.com/FacuGalan/bcn/commit/3ef2888963dbc7665079f7b94c1d9085dd2a1bbf))
* **domicilio:** picker de Google Maps + uploader de logo modernizado ([#140](https://github.com/FacuGalan/bcn/issues/140)) ([5a37ea7](https://github.com/FacuGalan/bcn/commit/5a37ea7aba6924664b690a6e668343198d312d4a))
* **fiscal:** alta manual de movimientos fiscales (RF-08) ([#137](https://github.com/FacuGalan/bcn/issues/137)) ([09826b0](https://github.com/FacuGalan/bcn/commit/09826b008e1f38bb3c1f4df78e973a0e801a479a))
* **fiscal:** comando arca:tipos-tributos para definir codigo_arca (Fase 5b) ([#135](https://github.com/FacuGalan/bcn/issues/135)) ([8b369bb](https://github.com/FacuGalan/bcn/commit/8b369bbcd70515e3f9bd0b206077a08946b7b730))
* **fiscal:** importador de padrón ARBA/AGIP — percepción IIBB por padrón (Fase 10b) ([#139](https://github.com/FacuGalan/bcn/issues/139)) ([59145b6](https://github.com/FacuGalan/bcn/commit/59145b67bdf1a47417220129ad26cb50428e236d))
* **fiscal:** percepciones aplicadas en ventas (Fase 5b) ([#136](https://github.com/FacuGalan/bcn/issues/136)) ([387f4ec](https://github.com/FacuGalan/bcn/commit/387f4ecb050b6231d23d4119ea1cecbfea66167f))
* **fiscal:** perfil fiscal del cliente — percepción IIBB por sujeto (Fase 10a) + fix AFIP 10051 ([#138](https://github.com/FacuGalan/bcn/issues/138)) ([5b59121](https://github.com/FacuGalan/bcn/commit/5b591216e2b3c739bceecadf708f1e24767086bc))
* **fiscal:** revisión Fable del sistema impositivo — 2 fixes + 4 profundizaciones ([#151](https://github.com/FacuGalan/bcn/issues/151)) ([e3b1416](https://github.com/FacuGalan/bcn/commit/e3b14160e93400ee9455dc8c3ee8d6ec027d23b1))
* **fiscal:** sistema impositivo — motor fiscal (Fases 1-9) ([#133](https://github.com/FacuGalan/bcn/issues/133)) ([8f00537](https://github.com/FacuGalan/bcn/commit/8f00537853b9c2cce357a733bc0b2f95625521ae))
* **integraciones-pago:** ayuda contextual para configurar el webhook de MP ([#130](https://github.com/FacuGalan/bcn/issues/130)) ([b594a6a](https://github.com/FacuGalan/bcn/commit/b594a6a23399e28c51fed244ddf06faa9aa0edec))
* **integraciones-pago:** modo Point (posnet físico Mercado Pago) ([#128](https://github.com/FacuGalan/bcn/issues/128)) ([9ac0b7e](https://github.com/FacuGalan/bcn/commit/9ac0b7ea0e33a42d40b69df0c3e21a37f9d2ca4d))
* **integraciones-pago:** picker de Google Maps para la dirección de la sucursal ([#141](https://github.com/FacuGalan/bcn/issues/141)) ([3ec95fb](https://github.com/FacuGalan/bcn/commit/3ec95fbde6dca1daec53c151065629d62d67d1d3))
* **integraciones-pago:** QR de monto libre (qr_libre) ([#129](https://github.com/FacuGalan/bcn/issues/129)) ([a13e191](https://github.com/FacuGalan/bcn/commit/a13e191df671c5ed1a2c7def3a5b75b2824ae585))
* **integraciones-pago:** vínculo CuentaEmpresa ↔ integraciones de pago (Paso 2) ([#131](https://github.com/FacuGalan/bcn/issues/131)) ([dcdcab4](https://github.com/FacuGalan/bcn/commit/dcdcab4a731034833b04521032f9b06653b7982e))
* **pantalla-cliente:** botón instalar en perfil + cartel de instalación ([#127](https://github.com/FacuGalan/bcn/issues/127)) ([0a0ddd3](https://github.com/FacuGalan/bcn/commit/0a0ddd3c0bfcb6f22508b3bdddaf95850e517230))
* **pantalla-cliente:** personalización por sucursal de la 2da pantalla ([#123](https://github.com/FacuGalan/bcn/issues/123)) ([ba179a8](https://github.com/FacuGalan/bcn/commit/ba179a80d0022a3d91ec476d480b8e8217f907bb))
* **pantallas-clase-b:** cartel de desactivado + español por defecto ([#149](https://github.com/FacuGalan/bcn/issues/149)) ([4c5acf4](https://github.com/FacuGalan/bcn/commit/4c5acf4b4ed8591ea0f2d9915d4b7fc17b64edc5))
* **pantallas-clase-b:** iconos de marca, footer más alto y consultor con fullscreen + sonido ([#144](https://github.com/FacuGalan/bcn/issues/144)) ([db88820](https://github.com/FacuGalan/bcn/commit/db88820b14cd9876737be6dce389506a1976290b))
* **pantallas-clase-b:** Multi-PWA Clase B — llamador de pedidos, consultor de precios y numeración de turno ([#143](https://github.com/FacuGalan/bcn/issues/143)) ([f6327d8](https://github.com/FacuGalan/bcn/commit/f6327d833660010f68a06de9fe231cf44312ee98))
* **pedidos-mostrador:** filtros visibles, orden por columna, cliente opcional y atajos ([#150](https://github.com/FacuGalan/bcn/issues/150)) ([3252920](https://github.com/FacuGalan/bcn/commit/3252920688ec2de967d2ff2fa5eb26bcd7247138))
* **ventas:** reporte de cortesías + fix cero negativo en checksum Livewire ([#142](https://github.com/FacuGalan/bcn/issues/142)) ([62060e2](https://github.com/FacuGalan/bcn/commit/62060e292c83ced0a30ef68e6865c1fb14d1c7c5))


### Correcciones

* **deploy:** hook de composer usa optimize:clear (no config:cache) ([#146](https://github.com/FacuGalan/bcn/issues/146)) ([d950ed6](https://github.com/FacuGalan/bcn/commit/d950ed6bdadfdeeed746c0e9cf09fc3d7eba16b7))
* **fiscal:** rechazar certificado/clave de CUIT vacíos al subir ([#134](https://github.com/FacuGalan/bcn/issues/134)) ([075b76c](https://github.com/FacuGalan/bcn/commit/075b76c11e30c5308c858fe404b1e7bf1fdffbea))
* **pedidos-mostrador:** vista en blanco — mover x-data al root del componente ([#148](https://github.com/FacuGalan/bcn/issues/148)) ([6070091](https://github.com/FacuGalan/bcn/commit/60700915fa3f0d82d0d2900ea4f8ed1f1986f738))


### Rendimiento

* **deploy:** comando deploy:warm con icons:cache — fix lentitud sistémica (~600ms/request) ([#147](https://github.com/FacuGalan/bcn/issues/147)) ([ed898b3](https://github.com/FacuGalan/bcn/commit/ed898b33052ff6545ea4c7ef2b57010b0fd089d9))
* **volt+pwa:** montar solo views/volt y versionar el service worker por build ([#145](https://github.com/FacuGalan/bcn/issues/145)) ([76acc57](https://github.com/FacuGalan/bcn/commit/76acc576be81af06c4e7e5627f5c0d4a42d97613))


### Refactoring

* **pantalla-cliente:** limpieza post-repaso del botón conectar ([#126](https://github.com/FacuGalan/bcn/issues/126)) ([37dcdee](https://github.com/FacuGalan/bcn/commit/37dcdee4edef885a3f9e1459b66e7af14d37e074))
* **pwa:** scope /app para app principal + multi-PWA pantalla cliente ([#125](https://github.com/FacuGalan/bcn/issues/125)) ([3e9192f](https://github.com/FacuGalan/bcn/commit/3e9192f90075f63383710d8da7ff1ce25782a841))

## [0.1.7](https://github.com/FacuGalan/bcn/compare/v0.1.6...v0.1.7) (2026-06-02)


### Funcionalidades

* **integraciones-pago:** cobro QR en todos los flujos de cobro de pedidos ([#115](https://github.com/FacuGalan/bcn/issues/115)) ([476281d](https://github.com/FacuGalan/bcn/commit/476281dfa312cf5ef20363b9ae0b15c61fb395c7))
* **integraciones-pago:** Fase 1 - esqueleto BD, modelos y catalogo (MVP MP) ([#107](https://github.com/FacuGalan/bcn/issues/107)) ([b231925](https://github.com/FacuGalan/bcn/commit/b231925b6c25357edd6c42ade00d1bef74d93921))
* **integraciones-pago:** Fase 2 - UI configuracion + service sucursal ([#109](https://github.com/FacuGalan/bcn/issues/109)) ([64a44ab](https://github.com/FacuGalan/bcn/commit/64a44abc31c76987c127679ef8be0a1350eda6e4))
* **integraciones-pago:** Fase 3 - MercadoPagoGateway + probar conexion ([#110](https://github.com/FacuGalan/bcn/issues/110)) ([d055ca6](https://github.com/FacuGalan/bcn/commit/d055ca6502d246aa2139fc629818317e7db7c5ba))
* **integraciones-pago:** Fase 3.5 - sincronizacion Sucursal-&gt;Store y Caja-&gt;POS de MP ([#111](https://github.com/FacuGalan/bcn/issues/111)) ([ea8e276](https://github.com/FacuGalan/bcn/commit/ea8e276feedbedc63a8d943f6a8a60010dc05a5a))
* **integraciones-pago:** Fase 4 - FormaPago con integraciones (N:M) + modos ([#112](https://github.com/FacuGalan/bcn/issues/112)) ([b63941a](https://github.com/FacuGalan/bcn/commit/b63941a46f8effabbe5a53f09d5542e8b10e8d5d))
* **integraciones-pago:** Fase 5 - cobro QR dinámico + pantalla cliente ([#114](https://github.com/FacuGalan/bcn/issues/114)) ([a315f80](https://github.com/FacuGalan/bcn/commit/a315f807dc769650b73f50b132f72aab4d7830a5))
* **integraciones-pago:** Fase 6 - webhook MP + confirmación en tiempo real (Reverb) ([#116](https://github.com/FacuGalan/bcn/issues/116)) ([5a63bc9](https://github.com/FacuGalan/bcn/commit/5a63bc92feed0fdfbf41f26fd820f0deab8e4c75))
* **integraciones-pago:** Fase 7 - cobro QR estático + modo único por forma de pago ([#119](https://github.com/FacuGalan/bcn/issues/119)) ([11eacd9](https://github.com/FacuGalan/bcn/commit/11eacd945c1b9dca6ee43f053f29dbc09af7d099))
* **integraciones-pago:** Fase 8 - confirmación manual + job de expiración ([#120](https://github.com/FacuGalan/bcn/issues/120)) ([39fc740](https://github.com/FacuGalan/bcn/commit/39fc740af09eb2d402ca4b42fdc4fb12d76fbdf7))
* **integraciones-pago:** Fase 9 + 10 - pagos mixtos, trazabilidad y cierre del MVP ([#121](https://github.com/FacuGalan/bcn/issues/121)) ([fbe1e51](https://github.com/FacuGalan/bcn/commit/fbe1e516157e86cdb2fb29b023165b0a661aba2d))


### Correcciones

* **facturación:** marcar el pago como facturado al asociar el comprobante ([#118](https://github.com/FacuGalan/bcn/issues/118)) ([651f871](https://github.com/FacuGalan/bcn/commit/651f8716e83e6d47f43bf4ff6133ce2832dd617b))
* **integraciones-pago:** auditoria fases 1-10 - bloqueo en pedidos, concurrencia y cuenta MP unica ([#122](https://github.com/FacuGalan/bcn/issues/122)) ([ae97e45](https://github.com/FacuGalan/bcn/commit/ae97e453ec7faacb3fdef838371596a5967d28d8))
* **integraciones-pago:** sincronización idempotente de Store/POS de MP ([#117](https://github.com/FacuGalan/bcn/issues/117)) ([b28ae3c](https://github.com/FacuGalan/bcn/commit/b28ae3c81a1777b13b7693fbd3b5b0265f4bba51))


### Rendimiento

* **tests:** optimizar suite quitando reset de AUTO_INCREMENT (-62%) ([#113](https://github.com/FacuGalan/bcn/issues/113)) ([3d073a7](https://github.com/FacuGalan/bcn/commit/3d073a7beb9141847f72f7614e845cf032614066))

## [0.1.6](https://github.com/FacuGalan/bcn/compare/v0.1.5...v0.1.6) (2026-05-26)


### Funcionalidades

* **articulos:** imagen del articulo con upload seguro y render en panel tactil ([#94](https://github.com/FacuGalan/bcn/issues/94)) ([c338212](https://github.com/FacuGalan/bcn/commit/c3382122d297842a4a463105e7a4c8dffece3ed5))
* **articulos:** import/export con lógica de precio y historial ([#42](https://github.com/FacuGalan/bcn/issues/42)) ([98cb8ef](https://github.com/FacuGalan/bcn/commit/98cb8ef37996f92d1eb9ae58be465b6a3cea809f))
* **broadcasting:** infra Reverb con aislamiento multi-tenant ([#83](https://github.com/FacuGalan/bcn/issues/83)) ([3aad3f3](https://github.com/FacuGalan/bcn/commit/3aad3f3a4ac153b28132cbb59bfb8b22651559e5))
* **cajas:** cierre de turno separa canje de puntos sin contaminar el cobrado real (PR E) ([#63](https://github.com/FacuGalan/bcn/issues/63)) ([cf4de76](https://github.com/FacuGalan/bcn/commit/cf4de76513d6e105e8168a3d80130c64a66ff511))
* **cajas:** patron append-only en MovimientoCaja + factory crearContraasiento (PR H+I) ([#66](https://github.com/FacuGalan/bcn/issues/66)) ([190eff0](https://github.com/FacuGalan/bcn/commit/190eff0faf6483352eb63e5b8cecbbedea0e3adc))
* **categorias:** import/export con plantilla Excel ([#41](https://github.com/FacuGalan/bcn/issues/41)) ([83516c6](https://github.com/FacuGalan/bcn/commit/83516c68110529966c85152e4ce31b2b130ac43a))
* **cc:** snapshot id+tasa de moneda en MovimientoCuentaCorriente (PR L Repaso 3) ([#69](https://github.com/FacuGalan/bcn/issues/69)) ([0152a93](https://github.com/FacuGalan/bcn/commit/0152a9352a470f7b949012aad5bc8d4a9f19a6bd))
* **invitaciones:** cortesia en pedidos mostrador + refactor touch-friendly del carrito ([#101](https://github.com/FacuGalan/bcn/issues/101)) ([6a435b8](https://github.com/FacuGalan/bcn/commit/6a435b83f8b23009cd86296a8205b7e201e8912b))
* **invitaciones:** fase 7 - cortesia en NuevaVenta (POS) ([#102](https://github.com/FacuGalan/bcn/issues/102)) ([732e634](https://github.com/FacuGalan/bcn/commit/732e6342a3a77d1f812bc3e442726c9d6f0cb2b6))
* **invitaciones:** fases 8-10 - conversion pedido→venta + badges + tests CA-13/14/15 ([#103](https://github.com/FacuGalan/bcn/issues/103)) ([73af664](https://github.com/FacuGalan/bcn/commit/73af6640c295407d0de9238a887e3c628edfc048))
* listas de precios estáticas + fix bug categorías paso 5 ([#37](https://github.com/FacuGalan/bcn/issues/37)) ([f5e3798](https://github.com/FacuGalan/bcn/commit/f5e37982e9ec4875ffe6372c7cb11299d4a7d0e9))
* **moneda:** cierre Repaso 3 — cobranzas + cobertura transferencias y cambio FP (PR N) ([#71](https://github.com/FacuGalan/bcn/issues/71)) ([85f4a64](https://github.com/FacuGalan/bcn/commit/85f4a64036741b68a73b8aeddf343ea7ae0d3c29))
* **moneda:** completar snapshot id+tasa en CobroPago, MovimientoTesoreria, ProvisionFondo (PR M) ([#70](https://github.com/FacuGalan/bcn/issues/70)) ([53eb430](https://github.com/FacuGalan/bcn/commit/53eb43035fe7c41e4de7088405d24a13ad811ac4))
* **navbar:** menú scrolleable horizontal con indicadores y auto-scroll ([#43](https://github.com/FacuGalan/bcn/issues/43)) ([46fc6f3](https://github.com/FacuGalan/bcn/commit/46fc6f305f923541ce2c74631763ebf90ffd42fe))
* **pedidos-mostrador:** alta/edicion como modal full-screen (PR2.C.2.A v2) ([#79](https://github.com/FacuGalan/bcn/issues/79)) ([228e8ab](https://github.com/FacuGalan/bcn/commit/228e8abff9da4f51e61e442e0e8a817437a6ff93))
* **pedidos-mostrador:** base de datos + modelos (PR2.A) ([#72](https://github.com/FacuGalan/bcn/issues/72)) ([b0e1a5e](https://github.com/FacuGalan/bcn/commit/b0e1a5e09745c610e377a3bac09ee8037e6daf00))
* **pedidos-mostrador:** bloquear conversion sin pagos suficientes (PR2.B.2) ([#75](https://github.com/FacuGalan/bcn/issues/75)) ([ee07cba](https://github.com/FacuGalan/bcn/commit/ee07cbae6b6ca9da95cb0adef1395c0503d828f7))
* **pedidos-mostrador:** comanda por detalle + desacople de cobro y entrega ([#106](https://github.com/FacuGalan/bcn/issues/106)) ([e406731](https://github.com/FacuGalan/bcn/commit/e406731d0a5357275c5ee2a6fb290cb9737075f5))
* **pedidos-mostrador:** flujo cobro + comanda automatica + edicion ampliada ([#105](https://github.com/FacuGalan/bcn/issues/105)) ([7535b02](https://github.com/FacuGalan/bcn/commit/7535b02d4e43f0308ad7b776858ee7f9a8a9c59a))
* **pedidos-mostrador:** hidratar desglose mixto al editar y modal Ver completo ([#91](https://github.com/FacuGalan/bcn/issues/91)) ([7de1f9f](https://github.com/FacuGalan/bcn/commit/7de1f9fc85d0e92f936f9417f6161bb1bedd34f5))
* **pedidos-mostrador:** highlight en vivo + toOthers + CajaAware + orden Kanban ([#98](https://github.com/FacuGalan/bcn/issues/98)) ([215b2cf](https://github.com/FacuGalan/bcn/commit/215b2cfcc3ee676408955a26000e5e7e159250f6))
* **pedidos-mostrador:** layout compacto + cobro rapido con desglose ([#100](https://github.com/FacuGalan/bcn/issues/100)) ([9100286](https://github.com/FacuGalan/bcn/commit/9100286a0e3aac8d4c8073edac4bec1f1deda661))
* **pedidos-mostrador:** lista Livewire con acciones (PR2.C.1) ([#76](https://github.com/FacuGalan/bcn/issues/76)) ([e573f54](https://github.com/FacuGalan/bcn/commit/e573f54c80973e5d967b2c4e80efd7a690e08cf2))
* **pedidos-mostrador:** logica autoritativa de paridad con venta ([#90](https://github.com/FacuGalan/bcn/issues/90)) ([06d131b](https://github.com/FacuGalan/bcn/commit/06d131bdeacbce700fde3dca9fd674ab7f901240))
* **pedidos-mostrador:** modal de pago 1:1 con NuevaVenta + Confirmar sin cobrar ([#81](https://github.com/FacuGalan/bcn/issues/81)) ([97916a8](https://github.com/FacuGalan/bcn/commit/97916a81c36db1cd33354a7f3d7d7f8cd6bcd208))
* **pedidos-mostrador:** modal de pagos con desglose mixto (PR2.C.2.B.1) ([#80](https://github.com/FacuGalan/bcn/issues/80)) ([e7a843b](https://github.com/FacuGalan/bcn/commit/e7a843be76a31826d58ba1f644099031ebd04d6f))
* **pedidos-mostrador:** pagos planificados sin cobrar (PR2.B.1) ([#74](https://github.com/FacuGalan/bcn/issues/74)) ([1741a68](https://github.com/FacuGalan/bcn/commit/1741a68051edd6917c5d6a77130064381f58cbd5))
* **pedidos-mostrador:** panel tactil RF-11 + promociones aplicadas en detalle ([#82](https://github.com/FacuGalan/bcn/issues/82)) ([85381cd](https://github.com/FacuGalan/bcn/commit/85381cd125a998b005427103d36f195dff40cc11))
* **pedidos-mostrador:** rediseno panel tactil + scroll auto + body lock ([#93](https://github.com/FacuGalan/bcn/issues/93)) ([ed75bd1](https://github.com/FacuGalan/bcn/commit/ed75bd17dbe9e91aa486de5a2b85899294863a24))
* **pedidos-mostrador:** service + eventos + plantillas (PR2.B) ([#73](https://github.com/FacuGalan/bcn/issues/73)) ([62a9a9f](https://github.com/FacuGalan/bcn/commit/62a9a9f61507abe456e28a74ca5737778d386bfd))
* **pedidos-mostrador:** tiempo real + acciones rapidas en la lista ([#84](https://github.com/FacuGalan/bcn/issues/84)) ([984f95e](https://github.com/FacuGalan/bcn/commit/984f95e0ea4b53a1aaa56913636709742f26a961))
* **pedidos-mostrador:** UI config conversion auto + canje puntos en convertirEnVenta ([#92](https://github.com/FacuGalan/bcn/issues/92)) ([17877b3](https://github.com/FacuGalan/bcn/commit/17877b37e28a617bccb61f068834e98cc5b0002c))
* **pedidos-mostrador:** vista Kanban con drag&drop ([#85](https://github.com/FacuGalan/bcn/issues/85)) ([bfcb342](https://github.com/FacuGalan/bcn/commit/bfcb3425928626ebf96210b77a17b40fef24de62))
* pesables, decimales stock, multi-select promos y scanner buffer ([#34](https://github.com/FacuGalan/bcn/issues/34)) ([68aa9bd](https://github.com/FacuGalan/bcn/commit/68aa9bd281f3129f42d41a24b518e5d8f5012db2))
* promociones especiales óptimas + mejoras UI ([#36](https://github.com/FacuGalan/bcn/issues/36)) ([e580c31](https://github.com/FacuGalan/bcn/commit/e580c311cc0e57b5bf67352b5ac1e6a856a21f3e))
* **testing:** smoke tests para 45 componentes Livewire + fix bug usuarios ([#50](https://github.com/FacuGalan/bcn/issues/50)) ([331b75f](https://github.com/FacuGalan/bcn/commit/331b75fbdd12f0f4da8e7e69842dcd4a667a0621))
* **ventas:** auditoria usuario_id en descuentos manuales y generales (PR G) ([#65](https://github.com/FacuGalan/bcn/issues/65)) ([fffb913](https://github.com/FacuGalan/bcn/commit/fffb9133fb460a43d3ba8777d6e139e1e4d447de))
* **ventas:** cambio de forma de pago registrada + fiscal automático ([#38](https://github.com/FacuGalan/bcn/issues/38)) ([de5660a](https://github.com/FacuGalan/bcn/commit/de5660ad6adaad82917b23fd702db28b0b04a725))
* **ventas:** persistencia completa para reconstruir cualquier venta sin ambiguedad ([#58](https://github.com/FacuGalan/bcn/issues/58)) ([e148875](https://github.com/FacuGalan/bcn/commit/e1488754ba616cfc76ea22b8351545abcd3cafed))
* **ventas:** popular descuento_lista con aporte de la lista por linea (PR F) ([#64](https://github.com/FacuGalan/bcn/issues/64)) ([fe3689f](https://github.com/FacuGalan/bcn/commit/fe3689f25cff2817f94448ded6ccb0965414e690))
* **ventas:** snapshots completos de cliente/cupon + origen de ajuste manual ([#54](https://github.com/FacuGalan/bcn/issues/54)) ([27b9406](https://github.com/FacuGalan/bcn/commit/27b9406b66887f4d7a910749405959607b262fc3))
* **ventas:** trazabilidad completa de canje de puntos via FP/concepto solo_sistema (PR C) ([#61](https://github.com/FacuGalan/bcn/issues/61)) ([f251352](https://github.com/FacuGalan/bcn/commit/f25135259dd642b17d81fc165e293f0719740cef))
* **ventas:** trazabilidad completa de cotizacion en pagos ME (PR D — repaso 1) ([#62](https://github.com/FacuGalan/bcn/issues/62)) ([82bab0d](https://github.com/FacuGalan/bcn/commit/82bab0daa41df7ad551f38441ed82a153ba600a5))


### Correcciones

* 3 bugs reportados + soporte completo de concepto libre en ventas ([#39](https://github.com/FacuGalan/bcn/issues/39)) ([2060150](https://github.com/FacuGalan/bcn/commit/206015037470ca53f80911e08dc94e2f1da4e593))
* **awareness:** evitar race condition en primer load del SPA ([#48](https://github.com/FacuGalan/bcn/issues/48)) ([2944293](https://github.com/FacuGalan/bcn/commit/294429346c9e55127d60cb7f80abe0b1e187ed05))
* **cajas:** pagos pendientes de facturar - modales y filtro default ([#40](https://github.com/FacuGalan/bcn/issues/40)) ([b438bf1](https://github.com/FacuGalan/bcn/commit/b438bf12a6bd747a649958ddd8d1b44e1397cc34))
* **invitaciones:** venta cortesía sin modal vuelto + sin forma_pago + info en detalle ([#104](https://github.com/FacuGalan/bcn/issues/104)) ([36d371c](https://github.com/FacuGalan/bcn/commit/36d371ccd92097b3aacdd52415ad1055bc008afa))
* **pedidos-mostrador:** aislar scope Alpine del x-data raíz ([#88](https://github.com/FacuGalan/bcn/issues/88)) ([f3066c6](https://github.com/FacuGalan/bcn/commit/f3066c6469de3b5b5a6982d04552f13589ce5aa9))
* **pedidos-mostrador:** broadcast inmediato + Alpine kanban registrado correctamente ([#86](https://github.com/FacuGalan/bcn/issues/86)) ([3c337e0](https://github.com/FacuGalan/bcn/commit/3c337e0aed6ac40e6ff741d029dc67c600e09802))
* **pedidos-mostrador:** hot-fix total_final con FP simple + base de paridad con venta ([#89](https://github.com/FacuGalan/bcn/issues/89)) ([814c9c3](https://github.com/FacuGalan/bcn/commit/814c9c3ada107d7e9eb4b5fc0de0c3269c4f6181))
* **pedidos-mostrador:** repaso completo — guard turno cerrado + promos por linea ([#95](https://github.com/FacuGalan/bcn/issues/95)) ([45df9f3](https://github.com/FacuGalan/bcn/commit/45df9f31d679e9c2a88cae50207686de11bd48c1))
* **provision:** corregir errores que impedían crear nuevos comercios ([#33](https://github.com/FacuGalan/bcn/issues/33)) ([241ab50](https://github.com/FacuGalan/bcn/commit/241ab509f3f06d182021cfc1f8c185212110be23))
* **testing:** aislar tests de BDs reales con cuatro defensas redundantes ([#47](https://github.com/FacuGalan/bcn/issues/47)) ([5d0308a](https://github.com/FacuGalan/bcn/commit/5d0308a729c45543b9d9af7ed9eed6e865607cf0))
* **testing:** cleanup automatico de users factory en TestCase ([#52](https://github.com/FacuGalan/bcn/issues/52)) ([7fbd8bc](https://github.com/FacuGalan/bcn/commit/7fbd8bcb8fc8b9953447035da622983ca528a005))
* **testing:** eliminar RefreshDatabase y endurecer guarda anti-BD-real ([#49](https://github.com/FacuGalan/bcn/issues/49)) ([680e59e](https://github.com/FacuGalan/bcn/commit/680e59ef4ea95b46a600b7397ecce318e43682f5))
* **testing:** prefijar FK constraints y unificar conexión de cleanTestData ([#45](https://github.com/FacuGalan/bcn/issues/45)) ([d0abed2](https://github.com/FacuGalan/bcn/commit/d0abed231db42094c6210cce40d5349584807881))
* **ui:** mejoras UX selectores, artículos, ventas y tesorería ([#31](https://github.com/FacuGalan/bcn/issues/31)) ([169756c](https://github.com/FacuGalan/bcn/commit/169756cc07c531608ace5c58d8e784435ead991a))
* **ventas:** cupon excluye promos comunes y especiales del item bonificado + warning si rinde menos ([#57](https://github.com/FacuGalan/bcn/issues/57)) ([cb819f5](https://github.com/FacuGalan/bcn/commit/cb819f5e970f74700834c7c09145bb094cddc02e))
* **ventas:** cupon tiene prioridad sobre descuento general % en items bonificados ([#56](https://github.com/FacuGalan/bcn/issues/56)) ([c5c4aff](https://github.com/FacuGalan/bcn/commit/c5c4affeffc4bb474dfaa74caf362bee0f95f5bd))
* **ventas:** guard contra doble emision de NC al anular fiscalmente (PR K) ([#68](https://github.com/FacuGalan/bcn/issues/68)) ([cce3c7f](https://github.com/FacuGalan/bcn/commit/cce3c7f7071bc264c06777963724d3a690b1910a))
* **ventas:** mostrar precio efectivo de sucursal en busqueda y consulta ([#51](https://github.com/FacuGalan/bcn/issues/51)) ([77bc466](https://github.com/FacuGalan/bcn/commit/77bc4664bb4a5453ffe55b1f6846c2da1628f1df))
* **ventas:** repaso 1 — validaciones defensivas, revalidaciones al cobrar y criterios documentados ([#59](https://github.com/FacuGalan/bcn/issues/59)) ([9f399cf](https://github.com/FacuGalan/bcn/commit/9f399cf26085c45d5832ee83f42b674ef5c9eb78))
* **ventas:** venta_detalle_promociones replicaba descuento total en cada unidad ([#55](https://github.com/FacuGalan/bcn/issues/55)) ([8bdfeb7](https://github.com/FacuGalan/bcn/commit/8bdfeb7d8dd0e36835bf225a30dbf9fc8f85e984))


### Refactoring

* **carrito:** extraer bloques compartidos de NuevaVenta a parciales ([#78](https://github.com/FacuGalan/bcn/issues/78)) ([3b69bad](https://github.com/FacuGalan/bcn/commit/3b69bad2290fc55ee57c43b61bfd10894cba1914))
* **pedidos-mostrador:** layout fullscreen + header compacto + atajos teclado ([#99](https://github.com/FacuGalan/bcn/issues/99)) ([13b7e36](https://github.com/FacuGalan/bcn/commit/13b7e361559100defb4592de2a1d2701578091de))
* **ventas,cobros:** eliminar SoftDeletes muerto, patron unico estado-enum (PR J) ([#67](https://github.com/FacuGalan/bcn/issues/67)) ([29b791f](https://github.com/FacuGalan/bcn/commit/29b791fa47577960ede940104eb443402acacb47))
* **ventas:** extraer 11 traits de NuevaVenta (PR1 — cero cambios funcionales) ([#53](https://github.com/FacuGalan/bcn/issues/53)) ([015d737](https://github.com/FacuGalan/bcn/commit/015d7370deb838a131cf197739f23dd42352a291))


### Documentación

* agente docs-sync + actualización de manuales ([#35](https://github.com/FacuGalan/bcn/issues/35)) ([a08444a](https://github.com/FacuGalan/bcn/commit/a08444a822194896f46d121efd539664c1ef0dcf))
* **reverb:** playbook de deploy con ruta real del server ([#87](https://github.com/FacuGalan/bcn/issues/87)) ([24c6bb5](https://github.com/FacuGalan/bcn/commit/24c6bb5f91c9aadc2567da718c4ab8d4a0974f62))
* **specs:** rescatar diseño historico de recetas/opcionales ([#97](https://github.com/FacuGalan/bcn/issues/97)) ([b22e498](https://github.com/FacuGalan/bcn/commit/b22e498782039acfedd35b66e4582009bf5178ab))


### Mantenimiento

* **pedidos-mostrador:** cleanup post-repaso de code quality ([#96](https://github.com/FacuGalan/bcn/issues/96)) ([f146f7b](https://github.com/FacuGalan/bcn/commit/f146f7b13eadfa8e31659d9c959b79447fb3b685))
* spec pedidos-mostrador + fix de helpers de tests para baseline limpio ([#46](https://github.com/FacuGalan/bcn/issues/46)) ([d263ca3](https://github.com/FacuGalan/bcn/commit/d263ca30fa4aaff011757af368a9ddf2096b6096))

## [0.1.5](https://github.com/FacuGalan/bcn/compare/v0.1.4...v0.1.5) (2026-04-08)


### Funcionalidades

* **puntos-cupones:** sistema completo de puntos y cupones ([#29](https://github.com/FacuGalan/bcn/issues/29)) ([e745971](https://github.com/FacuGalan/bcn/commit/e745971f654daf6497765f9f655628c0c8914184))


### Correcciones

* **migrations:** compatibilidad MariaDB para columnas json ([#24](https://github.com/FacuGalan/bcn/issues/24)) ([1312ed0](https://github.com/FacuGalan/bcn/commit/1312ed02704c3e4616084874263fc5eca950e40a))
* **ui:** filtrar cuentas y clientes por sucursal activa ([#28](https://github.com/FacuGalan/bcn/issues/28)) ([84c09e4](https://github.com/FacuGalan/bcn/commit/84c09e43413d6aa7179c06d59fb6a0cc68574440))


### Rendimiento

* **cache:** optimizar caches de config, rutas y store ([#26](https://github.com/FacuGalan/bcn/issues/26)) ([dd3f1c2](https://github.com/FacuGalan/bcn/commit/dd3f1c22474780ec3af2e7495b7be69ce60dec3e))
* **tenant:** eliminar query por request en middleware ([#23](https://github.com/FacuGalan/bcn/issues/23)) ([7bdc795](https://github.com/FacuGalan/bcn/commit/7bdc795394e68a8d226d0b5393341420a35ec813))


### Documentación

* guía de configuración de servidores ([#27](https://github.com/FacuGalan/bcn/issues/27)) ([5f2ab6d](https://github.com/FacuGalan/bcn/commit/5f2ab6d0e08be0a5fa17c349d060bb3011d263fb))

## [0.1.4](https://github.com/FacuGalan/bcn/compare/v0.1.3...v0.1.4) (2026-03-30)


### Funcionalidades

* **articulos:** refactorizar listado sucursal-aware + multi-sucursal detection ([#18](https://github.com/FacuGalan/bcn/issues/18)) ([741680b](https://github.com/FacuGalan/bcn/commit/741680b32e701841598b10494f0eaeb169fecd3c))
* **ci:** agregar Claude Code Review con OAuth token ([#11](https://github.com/FacuGalan/bcn/issues/11)) ([182774e](https://github.com/FacuGalan/bcn/commit/182774edbbba8c7ca91041e9a905301fa4397a15))
* **formas-pago:** agregar campo orden y reordenamiento drag-and-drop ([#19](https://github.com/FacuGalan/bcn/issues/19)) ([ca8a345](https://github.com/FacuGalan/bcn/commit/ca8a3453b2aac87731fbddf0a5357abe7d2925fe))
* **ui:** lazy loading con skeletons + fix transacciones tenant ([#21](https://github.com/FacuGalan/bcn/issues/21)) ([a30c782](https://github.com/FacuGalan/bcn/commit/a30c78259e3a92addbce31713da89537dda2da11))


### Correcciones

* **ci:** fallar CI si las migraciones fallan ([#8](https://github.com/FacuGalan/bcn/issues/8)) ([41d3e10](https://github.com/FacuGalan/bcn/commit/41d3e10d9a23ac983c19028dc3dbfe6ab30043b2))
* **precios:** corregir bugs en cálculo de precios y promociones ([#13](https://github.com/FacuGalan/bcn/issues/13)) ([2440353](https://github.com/FacuGalan/bcn/commit/2440353d4252240d34bd86e3d205a22a25bd2af5))
* **test:** corregir infraestructura de tests y warnings PHPUnit 11 ([#12](https://github.com/FacuGalan/bcn/issues/12)) ([22bd1f6](https://github.com/FacuGalan/bcn/commit/22bd1f60f6bc1ee6404f87728c7997dab341af1e))
* **ui:** corregir UX de modales en móvil ([#14](https://github.com/FacuGalan/bcn/issues/14)) ([4577f1d](https://github.com/FacuGalan/bcn/commit/4577f1daf10892ddbaf8b631bc7fab34b8c6b6bb))
* **ui:** evitar que drag-and-drop cierre el modal en móvil ([fa496f6](https://github.com/FacuGalan/bcn/commit/fa496f6c634b935be1a3f2ac9b2fbfaa430780bd))
* **ui:** revertir visualViewport y corregir bloqueo de clicks en modal ([#15](https://github.com/FacuGalan/bcn/issues/15)) ([29697b4](https://github.com/FacuGalan/bcn/commit/29697b465b5463f9e2a6c23a742d9cde56652e98))
* **ui:** simplificar UI single-sucursal y selectores dinámicos ([#20](https://github.com/FacuGalan/bcn/issues/20)) ([ceb5d82](https://github.com/FacuGalan/bcn/commit/ceb5d822fdc73d87a270d7ec6f3f77fcaee01deb))


### Rendimiento

* optimizar middleware tenant y reducir queries por request ([#16](https://github.com/FacuGalan/bcn/issues/16)) ([fa9d5a9](https://github.com/FacuGalan/bcn/commit/fa9d5a9684f4efda9736e1a60ca43224b1fdad53))
* optimizar middleware, queries y cache de catálogos ([#17](https://github.com/FacuGalan/bcn/issues/17)) ([e92e756](https://github.com/FacuGalan/bcn/commit/e92e756d56218c6926bde75310a49f95157bf20c))


### Refactoring

* **ui:** crear componente bcn-modal y migrar modales batch 0+1 ([1cb0e4b](https://github.com/FacuGalan/bcn/commit/1cb0e4b68ad8c863098c9a0d61154ea28f72f5f8))
* **ui:** migrar modales con $set a bcn-modal + crear métodos cancel (batch 3) ([8a587b3](https://github.com/FacuGalan/bcn/commit/8a587b3066b2fd8df9bb6a8af869613b738b0882))
* **ui:** migrar modales delete/confirmación a bcn-modal (batch 2) ([37ba433](https://github.com/FacuGalan/bcn/commit/37ba433b5e8e3010af62489fd70289c23486fa98))
* **ui:** migrar modales solo lectura y clientes/cobranzas (batch 4+5) ([583083f](https://github.com/FacuGalan/bcn/commit/583083ff4abbd52bc1e729a3c6edb32b7437c5af))
* **ui:** migrar parciales, ventas y turno actual a bcn-modal (batch 8+9+10) ([de73bf4](https://github.com/FacuGalan/bcn/commit/de73bf48c10f67a4edc33d289a5cdd2e0cc824c4))
* **ui:** migrar submodales z-index y tesorería/config (batch 6+7) ([3c10223](https://github.com/FacuGalan/bcn/commit/3c10223824321b51f26bbf83717cccf1aba5dd9e))


### Documentación

* **ci:** guidelines Release Please y fix migraciones CI ([#10](https://github.com/FacuGalan/bcn/issues/10)) ([a478ae8](https://github.com/FacuGalan/bcn/commit/a478ae8d175d0b9ce164bb36cd6745fe97b40356))
* manuales del sistema + skills auto-mantenimiento ([#22](https://github.com/FacuGalan/bcn/issues/22)) ([4b2c7a8](https://github.com/FacuGalan/bcn/commit/4b2c7a8acdb08067e23c24f82dd4b251a98c2616))

## [0.1.3](https://github.com/FacuGalan/bcn/compare/v0.1.2...v0.1.3) (2026-03-18)


### Rendimiento

* **ci:** saltar lint y tests en PRs de Release Please ([#6](https://github.com/FacuGalan/bcn/issues/6)) ([a871c9e](https://github.com/FacuGalan/bcn/commit/a871c9ee6320cac30415f8fb70f15deeddddc73c))

## [0.1.2](https://github.com/FacuGalan/bcn/compare/v0.1.1...v0.1.2) (2026-03-18)


### Correcciones

* **ci:** usar PAT en Release Please para disparar checks ([#4](https://github.com/FacuGalan/bcn/issues/4)) ([37eb544](https://github.com/FacuGalan/bcn/commit/37eb544432ee87a344141cc9f43255fcf35a4c1c))

## [0.1.1](https://github.com/FacuGalan/bcn/compare/v0.1.0...v0.1.1) (2026-03-18)


### Funcionalidades

* CI/CD, testing infra y modal detalle artículos por sucursal ([#1](https://github.com/FacuGalan/bcn/issues/1)) ([3ed4f92](https://github.com/FacuGalan/bcn/commit/3ed4f921ed4e67e58fd2b162d1dc50aa52c5978a))


### Correcciones

* corregir llamada estática a TenantService en migración ([956b1ad](https://github.com/FacuGalan/bcn/commit/956b1ad9f9b556fd4a7316d71b0ef68f2a0877f7))

## Changelog

All notable changes to this project will be documented in this file.

This project uses [Conventional Commits](https://www.conventionalcommits.org/)
and [Release Please](https://github.com/googleapis/release-please) for automatic versioning.
