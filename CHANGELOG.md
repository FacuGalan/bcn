# Changelog

## [0.1.4](https://github.com/FacuGalan/bcn/compare/v0.1.3...v0.1.4) (2026-03-20)


### Funcionalidades

* **ci:** agregar Claude Code Review con OAuth token ([#11](https://github.com/FacuGalan/bcn/issues/11)) ([182774e](https://github.com/FacuGalan/bcn/commit/182774edbbba8c7ca91041e9a905301fa4397a15))


### Correcciones

* **ci:** fallar CI si las migraciones fallan ([#8](https://github.com/FacuGalan/bcn/issues/8)) ([41d3e10](https://github.com/FacuGalan/bcn/commit/41d3e10d9a23ac983c19028dc3dbfe6ab30043b2))
* **precios:** corregir bugs en cálculo de precios y promociones ([#13](https://github.com/FacuGalan/bcn/issues/13)) ([2440353](https://github.com/FacuGalan/bcn/commit/2440353d4252240d34bd86e3d205a22a25bd2af5))
* **test:** corregir infraestructura de tests y warnings PHPUnit 11 ([#12](https://github.com/FacuGalan/bcn/issues/12)) ([22bd1f6](https://github.com/FacuGalan/bcn/commit/22bd1f60f6bc1ee6404f87728c7997dab341af1e))
* **ui:** corregir UX de modales en móvil ([#14](https://github.com/FacuGalan/bcn/issues/14)) ([4577f1d](https://github.com/FacuGalan/bcn/commit/4577f1daf10892ddbaf8b631bc7fab34b8c6b6bb))
* **ui:** revertir visualViewport y corregir bloqueo de clicks en modal ([#15](https://github.com/FacuGalan/bcn/issues/15)) ([29697b4](https://github.com/FacuGalan/bcn/commit/29697b465b5463f9e2a6c23a742d9cde56652e98))


### Rendimiento

* optimizar middleware tenant y reducir queries por request ([#16](https://github.com/FacuGalan/bcn/issues/16)) ([fa9d5a9](https://github.com/FacuGalan/bcn/commit/fa9d5a9684f4efda9736e1a60ca43224b1fdad53))


### Refactoring

* **ui:** crear componente bcn-modal y migrar modales batch 0+1 ([1cb0e4b](https://github.com/FacuGalan/bcn/commit/1cb0e4b68ad8c863098c9a0d61154ea28f72f5f8))
* **ui:** migrar modales con $set a bcn-modal + crear métodos cancel (batch 3) ([8a587b3](https://github.com/FacuGalan/bcn/commit/8a587b3066b2fd8df9bb6a8af869613b738b0882))
* **ui:** migrar modales delete/confirmación a bcn-modal (batch 2) ([37ba433](https://github.com/FacuGalan/bcn/commit/37ba433b5e8e3010af62489fd70289c23486fa98))
* **ui:** migrar modales solo lectura y clientes/cobranzas (batch 4+5) ([583083f](https://github.com/FacuGalan/bcn/commit/583083ff4abbd52bc1e729a3c6edb32b7437c5af))
* **ui:** migrar parciales, ventas y turno actual a bcn-modal (batch 8+9+10) ([de73bf4](https://github.com/FacuGalan/bcn/commit/de73bf48c10f67a4edc33d289a5cdd2e0cc824c4))
* **ui:** migrar submodales z-index y tesorería/config (batch 6+7) ([3c10223](https://github.com/FacuGalan/bcn/commit/3c10223824321b51f26bbf83717cccf1aba5dd9e))


### Documentación

* **ci:** guidelines Release Please y fix migraciones CI ([#10](https://github.com/FacuGalan/bcn/issues/10)) ([a478ae8](https://github.com/FacuGalan/bcn/commit/a478ae8d175d0b9ce164bb36cd6745fe97b40356))

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
