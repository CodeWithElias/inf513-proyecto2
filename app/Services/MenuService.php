<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Usuario;

/** Construye el menú dinámico (desde la BD) según los privilegios del rol. */
class MenuService
{
    public function para(Usuario $usuario): array
    {
        $privilegios = array_flip($usuario->privilegios());
        $items = MenuItem::where('activo', true)->orderBy('orden')->orderBy('id_menu')->get();

        $visible = fn (MenuItem $m) => $m->codigo_privilegio === null || isset($privilegios[$m->codigo_privilegio]);
        $nodo = fn (MenuItem $m) => [
            'id' => $m->id_menu,
            'titulo' => $m->titulo,
            'ruta' => $m->ruta,
            'icono' => $m->icono,
            'privilegio' => $m->codigo_privilegio,
        ];

        $menu = [];
        foreach ($items->whereNull('id_padre') as $raiz) {
            if (! $visible($raiz)) {
                continue;
            }
            $hijos = $items->where('id_padre', $raiz->id_menu)->filter($visible)->map($nodo)->values()->all();
            // Un grupo (sin ruta) solo aparece si tiene al menos un hijo visible.
            if ($raiz->ruta === null && $hijos === []) {
                continue;
            }
            $menu[] = $nodo($raiz) + ['hijos' => $hijos];
        }

        return $menu;
    }
}
