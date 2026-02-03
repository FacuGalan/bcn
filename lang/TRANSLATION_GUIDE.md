# Guía para generar traducciones

## Método que funciona (probado con pt.json)

Debido a limitaciones de tamaño de output, el archivo JSON de traducciones (~1890 líneas) no se puede escribir de una sola vez. Se debe usar el siguiente método de scripts PHP por partes.

### Pasos:

1. **Crear archivos PHP por partes** (generate_XX_part1.php a generate_XX_part8.php) donde XX es el código de idioma (ej: `pt`, `fr`, `de`, `it`, etc.)

   - **Part 1**: Usa `$translations = [...]` y `file_put_contents` para escribir un archivo temporal. Contiene ~200 traducciones.
   - **Parts 2-8**: Usan `return [...]` para devolver arrays de traducciones. Cada uno contiene ~250 traducciones.

   Estructura de Part 1:
   ```php
   <?php
   $translations = [
       "clave_español" => "traducción_idioma",
       // ...~200 entradas
   ];
   file_put_contents(__DIR__ . '/XX_build.json', json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
   echo "Part 1 done: " . count($translations) . " translations\n";
   ```

   Estructura de Parts 2-8:
   ```php
   <?php
   return [
       "clave_español" => "traducción_idioma",
       // ...~250 entradas
   ];
   ```

2. **Crear script combinador** (generate_XX_combine.php):
   ```php
   <?php
   $all = [];
   include __DIR__ . '/generate_XX_part1.php'; // define $translations
   if (isset($translations) && is_array($translations)) {
       $all = array_merge($translations, $all);
   }
   for ($i = 2; $i <= 8; $i++) {
       $file = __DIR__ . "/generate_XX_part{$i}.php";
       if (file_exists($file)) {
           $data = include $file;
           if (is_array($data)) $all = array_merge($all, $data);
       }
   }
   ksort($all, SORT_STRING | SORT_FLAG_CASE);
   file_put_contents(__DIR__ . '/XX.json', json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
   echo "XX.json generated with " . count($all) . " translations\n";
   // Cleanup
   foreach (glob(__DIR__ . '/generate_XX_part*.php') as $f) unlink($f);
   unlink(__DIR__ . '/generate_XX_combine.php');
   if (file_exists(__DIR__ . '/XX_build.json')) unlink(__DIR__ . '/XX_build.json');
   ```

3. **Ejecutar**: `php lang/generate_XX_combine.php`

4. **Verificar** que la cantidad de claves coincida con en.json:
   ```php
   <?php
   $en = json_decode(file_get_contents(__DIR__ . '/en.json'), true);
   $xx = json_decode(file_get_contents(__DIR__ . '/XX.json'), true);
   echo "en.json: " . count($en) . " keys\n";
   echo "XX.json: " . count($xx) . " keys\n";
   $missing = array_diff_key($en, $xx);
   if (count($missing) > 0) {
       echo "Missing: " . count($missing) . "\n";
       foreach ($missing as $k => $v) echo "  - $k\n";
   } else {
       echo "All keys covered!\n";
   }
   ```

5. **Crear también** `lang/XX/pagination.php`:
   ```php
   <?php
   return [
       'previous' => '&laquo; Anterior',  // traducir
       'next' => 'Próximo &raquo;',       // traducir
   ];
   ```

### Distribución de claves por parte (referencia):

| Parte | Líneas en.json | Cantidad aprox |
|-------|---------------|----------------|
| 1     | 1-200         | ~200           |
| 2     | 200-450       | ~250           |
| 3     | 450-700       | ~250           |
| 4     | 700-950       | ~250           |
| 5     | 950-1200      | ~250           |
| 6     | 1200-1450     | ~250           |
| 7     | 1450-1700     | ~250           |
| 8     | 1700-1891     | ~190           |

### Archivo de referencia

Las claves (keys) son siempre en español. Los valores se traducen al idioma destino. Usar `en.json` como referencia para las claves y para entender el contexto de cada traducción.

### Idiomas existentes

- `es.json` - Español (base)
- `en.json` - Inglés
- `pt.json` - Portugués (Brasileño)
