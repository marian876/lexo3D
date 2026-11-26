<?php
declare(strict_types=1);

// Si en local se ejecutó `php -S` sin `-t public`, mostramos la instrucción exacta para que carguen CSS, JS y rutas.
if (PHP_SAPI === 'cli-server') {
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $publicDir = realpath(__DIR__ . '/public');
    if ($docRoot !== $publicDir) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>LEXO — Iniciar servidor local</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:4rem auto;padding:1.5rem;line-height:1.5;">'
            . '<h1>Falta <code>-t public</code> al iniciar PHP</h1>'
            . '<p>El servidor integrado de PHP se inició en la raíz del proyecto, pero la carpeta pública de LEXO es <code>public/</code>.</p>'
            . '<p>Detené el servidor actual (<code>Ctrl+C</code>) y ejecutá:</p>'
            . '<pre style="background:#141226;color:#fff;padding:14px;border-radius:10px;overflow:auto;">php -S 127.0.0.1:8000 -t public</pre>'
            . '<p>Luego abrí <a href="http://127.0.0.1:8000">http://127.0.0.1:8000</a>.</p>'
            . '</body></html>';
        exit;
    }
}

require __DIR__ . '/public/index.php';
