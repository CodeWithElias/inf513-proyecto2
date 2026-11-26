<?php

/*
| CU6 - Monitoreo de páginas no autorizadas (scraping de YouTube).
| Equivale a scraping.* y alertas.rol del config.properties del Proyecto 1.
*/
return [
    // Páginas de resultados de YouTube por búsqueda (unos 20 resultados por página).
    // Cada término hace 2 búsquedas (canales y videos): términos × 2 × páginas peticiones a YouTube.
    'paginas' => (int) env('SCRAPING_PAGINAS', 2),

    // Rol cuyos usuarios activos reciben las alertas de páginas nuevas.
    'alertas_rol' => env('SCRAPING_ALERTAS_ROL', 'Administrador'),

    // Una ejecución "en_proceso" más antigua que esto se considera interrumpida.
    'minutos_en_curso' => 30,

    // Plataformas con scraping implementado (nombre en minúsculas).
    'soportadas' => ['youtube'],

    // Certificados raíz para HTTPS. En el servidor (Linux) no hace falta. En Windows, PHP suele no traerlos:
    // indicar un archivo, por ejemplo el de Git: C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt
    'ca_bundle' => env('SCRAPING_CA_BUNDLE'),
];
