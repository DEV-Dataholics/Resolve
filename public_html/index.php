<?php

$minPhpVersion = '8.1';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo sprintf('PHP %s+ required. Current: %s', $minPhpVersion, PHP_VERSION);
    exit(1);
}

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 * La carpeta /api vive al mismo nivel que index.php
 * (dentro de resolve.dataholics.com.mx/)
 * Acceso directo bloqueado por .htaccess
 */
$pathsFile = FCPATH . 'api/app/Config/Paths.php';

if (!file_exists($pathsFile)) {
    header('HTTP/1.1 500 Internal Server Error.', true, 500);
    echo 'No se encontro el core de la aplicacion. Verifica que /api este subido correctamente.';
    exit(1);
}

require $pathsFile;

$paths = new Config\Paths();

require $paths->systemDirectory . '/Boot.php';

exit(CodeIgniter\Boot::bootWeb($paths));
