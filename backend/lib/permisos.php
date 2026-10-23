<?php
// ============================================================
//  PERMISOS — matriz única de autorización
//  Archivo: backend/lib/permisos.php
//
//  Principio de mínimo privilegio (NIST SP 800-53): cada capacidad
//  declara el rol MÍNIMO necesario. Toda pantalla consulta este
//  archivo; ninguna repite su propia copia de la lista de roles.
//
//  Jerarquía: lector(1) < editor(2) < supervisor(3) < admin(4)
//  Un rol superior hereda todo lo de los inferiores.
// ============================================================

const JERARQUIA_ROLES = [
    'lector'     => 1,
    'editor'     => 2,
    'supervisor' => 3,
    'admin'      => 4,
];

// capacidad => rol mínimo requerido
const CAPACIDADES = [
    // Lectura pública: no requieren sesión, sólo se listan por completitud
    'leer'                     => 'lector',

    // Contenido de proyectos y wiki — equipo de desarrollo
    'proyecto.crear'           => 'editor',
    'proyecto.editar'          => 'editor',
    'proyecto.eliminar'        => 'admin',
    'articulo.crear'           => 'editor',
    'articulo.editar'          => 'editor',
    'articulo.publicar'        => 'editor',
    'articulo.historial'       => 'editor',
    'hito.gestionar'           => 'editor',
    'etiqueta.gestionar'       => 'editor',
    'media.subir'              => 'editor',
    'media.eliminar'           => 'editor',

    // Noticias — flujo de moderación (separación de funciones)
    'noticia.crear'            => 'editor',
    'noticia.editar'           => 'editor',     // siempre que sea propia y no publicada
    'noticia.moderar'          => 'supervisor', // aprueba/rechaza
    'noticia.eliminar'         => 'admin',

    // Administración del sistema
    'categoria.gestionar'      => 'admin',
    'usuario.gestionar'        => 'admin',
    'mensaje.gestionar'        => 'admin',
    'panel.acceder'            => 'editor',
    'bitacora.ver'             => 'admin',
];

/**
 * Nivel numérico de un rol (0 si es desconocido).
 */
function nivelRol(?string $rol): int {
    return JERARQUIA_ROLES[$rol] ?? 0;
}

/**
 * ¿Tiene el usuario actual la capacidad pedida?
 * Si requiere sesión y no la hay, devuelve false.
 */
function puede(string $capacidad, ?array $usuario = null): bool {
    $minimo = CAPACIDADES[$capacidad] ?? 'admin';   // ante una capacidad desconocida, exigir admin
    $usuario = $usuario ?? currentUser();

    if ($usuario === null) {
        // Las capacidades de lectura pública no necesitan sesión.
        return $minimo === 'lector';
    }

    return nivelRol($usuario['rol']) >= nivelRol($minimo);
}

/**
 * Exige una capacidad concreta: verifica sesión, nivel de rol y
 * registra el intento denegado en la bitácora de seguridad.
 */
function requierePermiso(string $capacidad): void {
    requireLogin();

    if (!puede($capacidad)) {
        $u = currentUser();
        logSeguridad('acceso_denegado', $u['usuario'],
            "capacidad: $capacidad, rol actual: {$u['rol']}, URI: " . ($_SERVER['REQUEST_URI'] ?? ''));
        accesoDenegado('No tenés permiso para realizar esta acción.');
    }
}

/**
 * Atajo: "puede editar este registro según su autoría".
 * Útil para las reglas propias de las noticias (un editor sólo
 * toca las suyas y todavía no publicadas).
 */
function puedeEditarPropio(array $registro, string $campoAutor = 'autor_id'): bool {
    $u = currentUser();
    if ($u === null) {
        return false;
    }
    if (nivelRol($u['rol']) >= nivelRol('admin')) {
        return true;
    }
    return (int) ($registro[$campoAutor] ?? -1) === (int) $u['id'];
}
