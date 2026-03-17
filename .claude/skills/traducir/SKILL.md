---
name: traducir
description: Agregar traducciones a los 3 archivos de idioma (es, en, pt) manteniendo orden alfabético.
user-invocable: true
argument-hint: "[claves a traducir]"
---

# Traducir — Agregar Traducciones

Tu trabajo es agregar claves de traducción a los 3 archivos JSON del proyecto manteniendo el orden alfabético.

## Al ejecutar este skill:

### 1. Obtener las traducciones

Si el argumento incluye las claves, usarlas directamente.
Si no, preguntar al usuario:
- Clave(s) en español (que son también la key del JSON)
- Traducción al inglés
- Traducción al portugués

Si el usuario solo da las claves en español, proponer las traducciones en en/pt y confirmar.

### 2. Leer los 3 archivos actuales

- `lang/es.json`
- `lang/en.json`
- `lang/pt.json`

### 3. Verificar que las claves no existan

Si alguna clave ya existe, informar al usuario y preguntar si quiere actualizarla o saltarla.

### 4. Agregar y reordenar

Para cada archivo:
1. Parsear el JSON existente
2. Agregar las nuevas claves con sus valores
3. Reordenar todas las claves alfabéticamente (case-insensitive)
4. Escribir el JSON formateado con `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE`

### 5. Verificar consistencia

- Los 3 archivos deben tener exactamente la misma cantidad de claves
- Si hay diferencia, informar al usuario

### 6. Mostrar resumen

```
Traducciones agregadas: X claves
- "Clave 1": es=valor | en=value | pt=valor
- "Clave 2": es=valor | en=value | pt=valor

Total claves en archivos: Y
```

## Reglas
- SIEMPRE mantener orden alfabético (case-insensitive)
- SIEMPRE agregar a los 3 archivos
- Clave = texto en español (misma convención que el resto del proyecto)
- Usar `__('Clave en español')` en el código PHP/Blade
- NO modificar claves existentes sin confirmación del usuario
