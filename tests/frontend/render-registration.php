<?php
// Validación frontend no destructiva: Blade, referencias ARIA y artefactos de Vite.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (['auth.register', 'auth.login', 'landing'] as $view) {
    $html = view($view)->render();
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($dom);
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $element) {
        $id = $element->getAttribute('id');
        if (isset($ids[$id])) throw new RuntimeException($view.' ID duplicado: '.$id);
        $ids[$id] = true;
    }
    foreach ($xpath->query('//*[@aria-controls or @aria-labelledby or @aria-describedby]') as $element) {
        foreach (['aria-controls', 'aria-labelledby', 'aria-describedby'] as $attribute) {
            foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                if (!isset($ids[$id])) throw new RuntimeException($view.' referencia ARIA rota: '.$id);
            }
        }
    }
    if ($view === 'auth.register') {
        if ($xpath->query('//main')->length !== 1) throw new RuntimeException('Registro: main no único');
        if ($xpath->query('//input[@name]')->length !== 6 || $xpath->query('//select[@name]')->length !== 1) throw new RuntimeException('Registro: campos fuera del contrato');
        if (str_contains($html, 'register/step') || str_contains($html, 'name="_token"')) throw new RuntimeException('Registro legacy activo');
    }
    echo $view.': render OK, '.count($ids).' IDs únicos, ARIA válido'.PHP_EOL;
}
$manifest = json_decode(file_get_contents(dirname(__DIR__, 2).'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($manifest as $entry) {
    foreach (array_merge([$entry['file']], $entry['css'] ?? []) as $file) if (!file_exists(dirname(__DIR__, 2).'/public/build/'.$file)) throw new RuntimeException('Asset inexistente: '.$file);
    foreach ($entry['imports'] ?? [] as $key) if (!isset($manifest[$key])) throw new RuntimeException('Import inexistente: '.$key);
}
foreach (['resources/css/app.css', 'resources/js/modules/registration/wizard.js', 'resources/js/modules/security/auth.js'] as $key) if (!isset($manifest[$key])) throw new RuntimeException('Entrada Vite ausente: '.$key);
echo 'Manifest: '.count($manifest).' entradas válidas'.PHP_EOL;
