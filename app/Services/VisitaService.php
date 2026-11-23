<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Contador de visitas por página (footer). Usa la función SQL fn_registrar_visita. */
class VisitaService
{
    public function registrar(string $ruta): int
    {
        return (int) DB::selectOne('select fn_registrar_visita(?) as total', [$this->normalizar($ruta)])->total;
    }

    public function total(string $ruta): int
    {
        return (int) (DB::table('visita_pagina')->where('ruta', $this->normalizar($ruta))->value('total') ?? 0);
    }

    /** "/usuarios/15?x=1" -> "/usuarios/:id" para no crear un contador por cada registro. */
    private function normalizar(string $ruta): string
    {
        $ruta = '/'.trim(parse_url($ruta, PHP_URL_PATH) ?? '/', '/');
        $ruta = preg_replace('#/\d+(?=/|$)#', '/:id', $ruta);

        return mb_substr($ruta, 0, 255);
    }
}
