# Git & CI/CD

## Conventional Commits (OBLIGATORIO)
Todos los commits DEBEN seguir el formato [Conventional Commits](https://www.conventionalcommits.org/):

```
<tipo>[scope opcional]: <descripción>

[cuerpo opcional]

[footer opcional]
```

| Tipo | Cuándo usar | Bump |
|------|-------------|------|
| `feat:` | Nueva funcionalidad | minor |
| `fix:` | Corrección de bug | patch |
| `refactor:` | Refactoring sin cambio funcional | — |
| `perf:` | Mejora de rendimiento | patch |
| `test:` | Agregar o modificar tests | — |
| `docs:` | Documentación | — |
| `ci:` | Cambios en CI/CD workflows | — |
| `chore:` | Mantenimiento, dependencias | — |
| `feat!:` o `BREAKING CHANGE:` | Cambio incompatible | major |

Ejemplos:
- `feat(ventas): agregar filtro por fecha en listado`
- `fix(stock): corregir cálculo de stock al anular venta`
- `refactor(auth): extraer lógica de permisos a PermissionService`

## Workflow de branches
- **Al iniciar sesión**: verificar en qué rama estamos. Si no estamos trabajando sobre una rama específica en curso, hacer `git checkout master && git pull` antes de crear la nueva rama. Esto evita trabajar sobre código desactualizado.
- **Nunca push directo a master** — todo vía Pull Request
- Cada PR ejecuta automáticamente: Lint (Pint) + Tests (PHPUnit)
- Si algo falla, el PR queda bloqueado
- `master` siempre lista para producción

## GitHub Actions (`.github/workflows/`)
- `ci.yml` — Lint con Pint + Tests con PHPUnit en cada PR
- `release-please.yml` — Versionado automático con Release Please

## Release Please
- Tras merge en master → crea/actualiza un único Release PR con los cambios acumulados
- Al mergear Release PR → crea tag `vX.Y.Z` + GitHub Release + CHANGELOG
- **No mergear el Release PR después de cada PR** — dejar acumular cambios
- Mergear el Release PR cuando hay un conjunto coherente (feature completo, grupo de fixes)
- Config: `release-please-config.json` (tipo PHP)
- Manifest: `.release-please-manifest.json` (versión actual)
- Usa `RELEASE_PLEASE_TOKEN` (PAT) para que los PRs de release disparen CI
- CI skipea automáticamente los PRs de release (no tocan código PHP)

## Semver
- `patch` (0.1.**X**) — fixes y ajustes menores
- `minor` (0.**X**.0) — funcionalidad nueva
- `major` (**X**.0.0) — cambios incompatibles (reservar para producción)
- Proyecto en `0.x.x` hasta estar listo para producción → entonces `1.0.0`
