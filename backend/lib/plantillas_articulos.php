<?php
// ============================================================
//  MÓDULO 6 — PLANTILLAS DE ARTÍCULO POR TIPO
//  Archivo: backend/lib/plantillas_articulos.php
// ============================================================
//
//  Define la estructura sugerida de secciones para cada tipo de
//  artículo de la wiki (guion, arquitectura, mecánica, personaje,
//  nivel, técnico, otro). Se usa desde admin/articulo_form.php
//  para mostrar una guía de secciones recomendadas y para poder
//  insertar automáticamente un esqueleto HTML en el campo
//  "contenido" cuando se crea o edita un artículo.
//
//  Es una estructura SUGERIDA, no obligatoria a nivel de base de
//  datos: el campo articulos.contenido sigue siendo HTML libre.
//  Esto evita romper artículos ya existentes o forzar un formato
//  rígido sobre contenido que un editor ya redactó a su manera,
//  pero sí estandariza la forma en que se documenta contenido
//  nuevo, que es el objetivo del sistema.
// ============================================================

/**
 * Devuelve la definición completa de plantillas: para cada tipo
 * de artículo, una etiqueta legible y la lista de secciones
 * sugeridas (título + texto de ayuda).
 */
function plantillasArticulo(): array {
    return [
        'guion' => [
            'label' => 'Guion',
            'secciones' => [
                ['titulo' => 'Sinopsis',                 'ayuda' => 'Resumen breve de la historia, escena o diálogo en 2-3 líneas.'],
                ['titulo' => 'Personajes involucrados',  'ayuda' => 'Quiénes participan en esta parte del guion y su rol.'],
                ['titulo' => 'Estructura narrativa',     'ayuda' => 'Planteamiento, desarrollo y desenlace de la escena o acto.'],
                ['titulo' => 'Diálogos clave',            'ayuda' => 'Líneas de diálogo relevantes o de referencia.'],
                ['titulo' => 'Notas de producción',       'ayuda' => 'Indicaciones para quien implemente el guion en el juego (timing, tono, referencias).'],
            ],
        ],
        'arquitectura' => [
            'label' => 'Arquitectura',
            'secciones' => [
                ['titulo' => 'Visión general',           'ayuda' => 'Qué resuelve este componente o sistema dentro del proyecto.'],
                ['titulo' => 'Capas / módulos del sistema', 'ayuda' => 'División lógica del sistema (presentación, lógica, datos, etc.).'],
                ['titulo' => 'Tecnologías utilizadas',    'ayuda' => 'Lenguajes, librerías y herramientas involucradas.'],
                ['titulo' => 'Diagrama de componentes',   'ayuda' => 'Descripción textual o imagen del flujo entre componentes.'],
                ['titulo' => 'Decisiones técnicas',       'ayuda' => 'Por qué se eligió este enfoque y qué alternativas se descartaron.'],
            ],
        ],
        'mecanica' => [
            'label' => 'Mecánica de juego',
            'secciones' => [
                ['titulo' => 'Descripción general',      'ayuda' => 'Qué hace esta mecánica y qué experiencia busca generar.'],
                ['titulo' => 'Reglas y controles',        'ayuda' => 'Cómo la activa o utiliza el jugador (input, condiciones).'],
                ['titulo' => 'Sistemas relacionados',     'ayuda' => 'Con qué otras mecánicas o sistemas interactúa.'],
                ['titulo' => 'Balance y dificultad',      'ayuda' => 'Parámetros de balance, curva de dificultad asociada.'],
                ['titulo' => 'Casos límite',              'ayuda' => 'Situaciones especiales o bordes que deben contemplarse.'],
            ],
        ],
        'personaje' => [
            'label' => 'Personaje',
            'secciones' => [
                ['titulo' => 'Datos generales',           'ayuda' => 'Nombre, rol en la historia, bando o facción.'],
                ['titulo' => 'Historia / trasfondo',      'ayuda' => 'Contexto narrativo del personaje.'],
                ['titulo' => 'Habilidades y atributos',   'ayuda' => 'Estadísticas, habilidades especiales, equipamiento.'],
                ['titulo' => 'Relaciones',                'ayuda' => 'Vínculos con otros personajes del proyecto.'],
                ['titulo' => 'Referencias visuales',      'ayuda' => 'Descripción de diseño de arte o enlaces a concept art.'],
            ],
        ],
        'nivel' => [
            'label' => 'Nivel / Escenario',
            'secciones' => [
                ['titulo' => 'Objetivo del nivel',        'ayuda' => 'Qué debe lograr el jugador para completarlo.'],
                ['titulo' => 'Descripción del entorno',   'ayuda' => 'Ambientación, geografía, elementos visuales clave.'],
                ['titulo' => 'Enemigos y obstáculos',     'ayuda' => 'Qué desafíos enfrenta el jugador y dónde.'],
                ['titulo' => 'Progresión y dificultad',   'ayuda' => 'Cómo escala la dificultad a lo largo del nivel.'],
                ['titulo' => 'Puntos de guardado',        'ayuda' => 'Checkpoints, puntos de reinicio o de guardado.'],
            ],
        ],
        'tecnico' => [
            'label' => 'Documento técnico',
            'secciones' => [
                ['titulo' => 'Propósito',                 'ayuda' => 'Qué problema resuelve este documento.'],
                ['titulo' => 'Requisitos previos',        'ayuda' => 'Qué se necesita saber o tener instalado antes.'],
                ['titulo' => 'Procedimiento',             'ayuda' => 'Pasos concretos, en orden, para llevarlo a cabo.'],
                ['titulo' => 'Consideraciones y riesgos',  'ayuda' => 'Errores comunes, advertencias, límites conocidos.'],
                ['titulo' => 'Referencias',                'ayuda' => 'Enlaces o documentos relacionados.'],
            ],
        ],
        'otro' => [
            'label' => 'Otro',
            'secciones' => [
                ['titulo' => 'Resumen',                   'ayuda' => 'De qué trata este artículo.'],
                ['titulo' => 'Contenido',                 'ayuda' => 'Desarrollo principal del tema.'],
                ['titulo' => 'Notas adicionales',         'ayuda' => 'Cualquier información complementaria relevante.'],
            ],
        ],
    ];
}

/**
 * Genera el esqueleto HTML (secciones con <h2> y un <p> de ayuda
 * entre corchetes) para el tipo de artículo indicado. Se usa como
 * contenido inicial sugerido al crear un artículo desde cero.
 */
function plantillaHTML(string $tipo): string {
    $plantillas = plantillasArticulo();
    $t = $plantillas[$tipo] ?? $plantillas['otro'];

    $html = '';
    foreach ($t['secciones'] as $s) {
        $html .= '<h2>' . htmlspecialchars($s['titulo'], ENT_QUOTES, 'UTF-8') . "</h2>\n";
        $html .= '<p>[' . htmlspecialchars($s['ayuda'], ENT_QUOTES, 'UTF-8') . "]</p>\n\n";
    }
    return rtrim($html);
}
