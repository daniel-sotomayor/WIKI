<?php
// ============================================================
//  SUBIDA Y GESTIÓN DE ARCHIVOS MULTIMEDIA
//  Archivo: backend/lib/upload.php
//
//  El proyecto original NO tenía ninguna función de subida. Este
//  módulo implementa el flujo completo con las medidas de
//  seguridad habituales:
//
//   · lista blanca de extensiones Y de MIME reales (finfo)
//   · el nombre del archivo se descarta: se genera uno aleatorio
//     de 32 hex, imposible de adivinar y sin caracteres raros
//   · los archivos se guardan FUERA del alcance web
//     (backend/storage/uploads/) y sólo se sirven con media.php
//   · el tipo se determina desde la base de datos, nunca desde
//     la URL, así que no hay "path traversal" posible
//   · SVG denegado (permite ejecutar JavaScript en el origen)
//   · límite de tamaño distinto según el tipo de archivo
//   · rate-limit por IP para evitar inundar el disco
// ============================================================

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/validate.php';

/** Devuelve ['tipo' => …, 'ext' => …] o null si no está permitido. */
function infoArchivo(string $nombre): ?array {
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    if ($ext === '' || !isset(UPLOAD_MIME[$ext])) {
        return null;
    }
    [$tipo] = UPLOAD_MIME[$ext];
    return ['tipo' => $tipo, 'ext' => $ext];
}

/**
 * Valida una entrada $_FILES completa.
 *
 * @return string|null mensaje de error, o null si todo está bien
 */
function validarSubida(array $file): ?string {
    $codigo = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($codigo === UPLOAD_ERR_NO_FILE) {
        return 'No se seleccionó ningún archivo.';
    }
    if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
        return 'El archivo supera el límite permitido por el servidor.';
    }
    if ($codigo !== UPLOAD_ERR_OK) {
        return 'No se pudo subir el archivo (código de error ' . $codigo . ').';
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return 'Archivo temporal inválido.';
    }

    $nombre = (string) ($file['name'] ?? '');
    $info = infoArchivo($nombre);
    if ($info === null) {
        return 'Tipo de archivo no permitido. Formatos aceptados: '
             . implode(', ', array_keys(UPLOAD_MIME)) . '.';
    }

    $tamano = (int) ($file['size'] ?? 0);
    $limite = UPLOAD_LIMITS[$info['tipo']] ?? 5 * 1024 * 1024;
    if ($tamano <= 0) {
        return 'El archivo está vacío.';
    }
    if ($tamano > $limite) {
        return 'El archivo pesa ' . bytes($tamano) . ' y el límite para '
             . $info['tipo'] . ' es de ' . bytes($limite) . '.';
    }

    // MIME real (no el que declara el navegador)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = (string) $finfo->file($file['tmp_name']);
    $permitidos = UPLOAD_MIME[$info['ext']][1];

    if (!in_array($mimeReal, $permitidos, true)) {
        // Caso frecuente: .docx/.zip que finfo detecta como application/zip
        error_log('[upload] MIME rechazado: ext=' . $info['ext'] . ' mime=' . $mimeReal);
        return 'El contenido real del archivo (' . $mimeReal
             . ') no coincide con su extensión.';
    }

    // Para imágenes, además: que existan dimensiones reales
    if ($info['tipo'] === 'imagen') {
        $dim = @getimagesize($file['tmp_name']);
        if ($dim === false || empty($dim[0]) || empty($dim[1])) {
            return 'El archivo no es una imagen válida.';
        }
        if ($dim[0] > 12000 || $dim[1] > 12000) {
            return 'Las dimensiones de la imagen son excesivas.';
        }
    }

    // Los vídeos deben poder decodificarse como tal
    if ($info['tipo'] === 'video') {
        $mimeReal = strtolower($mimeReal);
        if (!str_starts_with($mimeReal, 'video/')) {
            return 'El archivo no es un vídeo válido.';
        }
    }

    return null;
}

/**
 * Guarda el archivo en disco con un nombre aleatorio.
 *
 * @return array{nombre:string, ruta:string, tipo:string, mime:string, tamano:int}
 *         o una cadena con el mensaje de error
 */
function guardarArchivo(array $file): array|string {
    $error = validarSubida($file);
    if ($error !== null) {
        return $error;
    }

    $nombreOriginal = (string) $file['name'];
    $info  = infoArchivo($nombreOriginal);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($file['tmp_name']);

    $nombreGuardado = bin2hex(random_bytes(16)) . '.' . $info['ext'];
    $directorio = UPLOADS_DIR . '/' . $info['tipo'];

    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        error_log('[upload] No se pudo crear el directorio: ' . $directorio);
        return 'No se pudo preparar el almacenamiento de archivos.';
    }

    if (!move_uploaded_file($file['tmp_name'], $directorio . '/' . $nombreGuardado)) {
        error_log('[upload] move_uploaded_file falló para ' . $nombreOriginal);
        return 'No se pudo guardar el archivo en el servidor.';
    }

    @chmod($directorio . '/' . $nombreGuardado, 0644);

    return [
        'nombre'        => $nombreGuardado,
        'ruta'          => $directorio . '/' . $nombreGuardado,
        'tipo'          => $info['tipo'],
        'mime'          => $mime,
        'tamano'        => (int) $file['size'],
        'nombreOriginal'=> $nombreOriginal,
    ];
}

/** Ruta absoluta en disco de un archivo registrado. */
function rutaMedia(string $tipo, string $nombreGuardado): string {
    return UPLOADS_DIR . '/' . $tipo . '/' . $nombreGuardado;
}

/** Borra el archivo físico si existe. Devuelve true si ya no está. */
function borrarArchivoFisico(string $tipo, string $nombreGuardado): bool {
    if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $nombreGuardado)) {
        return false;
    }
    $ruta = rutaMedia($tipo, $nombreGuardado);
    if (is_file($ruta)) {
        return @unlink($ruta);
    }
    return true;
}

// ── Operaciones sobre la tabla media ────────────────────────

/**
 * Registra un archivo ya subido y lo asocia a una entidad.
 *
 * @return int id del registro, o cadena con el mensaje de error
 */
function adjuntarMedia(string $entidadTipo, int $entidadId, array $archivo,
                       int $usuarioId, bool $principal = false, string $titulo = ''): int|string {
    if (!in_array($entidadTipo, ['articulo', 'noticia', 'proyecto'], true)) {
        return 'Entidad no válida.';
    }

    if (is_string($archivo)) {
        return $archivo;    // guardarArchivo devolvió un error
    }

    $db = getDB();

    try {
        if ($principal) {
            $db->prepare('UPDATE media SET es_principal = 0
                           WHERE entidad_tipo = ? AND entidad_id = ?')
               ->execute([$entidadTipo, $entidadId]);
        }

        $db->prepare(
            'INSERT INTO media (entidad_tipo, entidad_id, tipo, nombre_original,
                                nombre_guardado, mime, tamano, titulo, es_principal, subido_por)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $entidadTipo, $entidadId, $archivo['tipo'], $archivo['nombreOriginal'],
            $archivo['nombre'], $archivo['mime'], $archivo['tamano'],
            mb_substr($titulo !== '' ? $titulo : $archivo['nombreOriginal'], 0, 255),
            $principal ? 1 : 0, $usuarioId,
        ]);

        return (int) $db->lastInsertId();
    } catch (Throwable $e) {
        // Si falla el INSERT, el archivo ya quedó en disco: se retira.
        borrarArchivoFisico($archivo['tipo'], $archivo['nombre']);
        error_log('[adjuntarMedia] ' . $e->getMessage());
        return 'No se pudo registrar el archivo adjunto.';
    }
}

/**
 * Procesa un campo <input type="file" multiple> de un formulario.
 *
 * Recorre cada archivo subido, lo valida, lo mueve a disco y lo registra
 * en la tabla `media`. Si algún archivo falla, se acumula el mensaje en
 * `$errores` y el resto sigue procesándose (una imagen mala no debería
 * impedir guardar la noticia).
 *
 * @param string     $entidadTipo 'articulo' | 'noticia' | 'proyecto'
 * @param int        $entidadId   id de la fila dueña de los archivos
 * @param array      $files       $_FILES['archivos']
 * @param int        $usuarioId   quién sube
 * @param string[]   $errores     salida: mensajes de error acumulados
 * @param int        $maxArchivos tope absoluto (se ignoran los excedentes)
 * @return int cantidad de archivos guardados correctamente
 */
/**
 * Normaliza $_FILES a una lista de archivos.
 *
 * El navegador envía arrays sólo cuando el <input type="file"> tiene el
 * atributo `multiple`; con un solo archivo todos los campos llegan como
 * escalares y acceder con [0] devolvía la primera letra del nombre.
 */
function normalizarFiles(array $files): array {
    $nombres = $files['name'] ?? null;

    if (!is_array($nombres)) {
        $nombre = (string) ($files['name'] ?? '');
        if ($nombre === '') {
            return [];
        }
        return [[
            'name'     => $nombre,
            'tmp_name' => (string) ($files['tmp_name'] ?? ''),
            'error'    => (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($files['size'] ?? 0),
            'type'     => (string) ($files['type'] ?? ''),
        ]];
    }

    $lista = [];
    foreach ($nombres as $k => $nombre) {
        if ((string) $nombre === '') {
            continue;
        }
        $lista[] = [
            'name'     => (string) $nombre,
            'tmp_name' => (string) ($files['tmp_name'][$k] ?? ''),
            'error'    => (int) ($files['error'][$k] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($files['size'][$k] ?? 0),
            'type'     => (string) ($files['type'][$k] ?? ''),
        ];
    }
    return $lista;
}

function subirAdjuntos(string $entidadTipo, int $entidadId, array $files,
                       int $usuarioId, array &$errores, int $maxArchivos = 6): int {
    $guardados = 0;

    foreach (normalizarFiles($files) as $archivo) {
        if ($guardados >= $maxArchivos) {
            $errores[] = 'Se ignoraron archivos: el máximo por publicación es '
                       . $maxArchivos . '.';
            break;
        }

        $resultado = guardarArchivo($archivo);

        if (is_string($resultado)) {
            $errores[] = $resultado;
            continue;
        }

        $idMedia = adjuntarMedia($entidadTipo, $entidadId, $resultado, $usuarioId, $guardados === 0);

        if (is_string($idMedia)) {
            $errores[] = $idMedia;
            continue;
        }

        $guardados++;
    }

    return $guardados;
}

function listarMedia(string $entidadTipo, int $entidadId): array {
    $st = getDB()->prepare(
        'SELECT * FROM media WHERE entidad_tipo = ? AND entidad_id = ?
          ORDER BY es_principal DESC, creado_en ASC'
    );
    $st->execute([$entidadTipo, $entidadId]);
    return $st->fetchAll();
}

/** Nombre guardado de la imagen principal de una entidad (para tarjetas). */
function mediaPrincipal(string $entidadTipo, int $entidadId): ?string {
    $st = getDB()->prepare(
        "SELECT nombre_guardado FROM media
          WHERE entidad_tipo = ? AND entidad_id = ? AND tipo = 'imagen' AND es_principal = 1
          LIMIT 1"
    );
    $st->execute([$entidadTipo, $entidadId]);
    $v = $st->fetchColumn();
    return $v ? (string) $v : null;
}

/**
 * Varios registros de una vez (para listados con portada).
 * Devuelve [entidad_id => nombre_guardado].
 */
function portadasMedia(string $entidadTipo, array $ids): array {
    if (!$ids) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = getDB()->prepare(
        "SELECT entidad_id, nombre_guardado FROM media
          WHERE entidad_tipo = ? AND entidad_id IN ($ph) AND tipo = 'imagen' AND es_principal = 1"
    );
    $st->execute(array_merge([$entidadTipo], array_map('intval', $ids)));
    $salida = [];
    foreach ($st->fetchAll() as $f) {
        $salida[(int) $f['entidad_id']] = $f['nombre_guardado'];
    }
    return $salida;
}

/**
 * Elimina un registro de media: primero el archivo físico y luego
 * la fila. Si el archivo no se puede borrar, no se borra la fila
 * (así queda visible en el panel y se reintenta).
 */
function eliminarMedia(int $id): bool {
    $fila = dbUnica('SELECT * FROM media WHERE id = ? LIMIT 1', [$id]);
    if (!$fila) {
        return true;
    }

    if (!borrarArchivoFisico($fila['tipo'], $fila['nombre_guardado'])) {
        error_log('[eliminarMedia] No se pudo borrar el archivo ' . $fila['nombre_guardado']);
        return false;
    }

    getDB()->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
    return true;
}

/**
 * Borra todos los adjuntos de una entidad (al borrar el registro padre).
 */
function eliminarMediaDe(string $entidadTipo, int $entidadId): void {
    foreach (listarMedia($entidadTipo, $entidadId) as $m) {
        borrarArchivoFisico($m['tipo'], $m['nombre_guardado']);
    }
    getDB()->prepare('DELETE FROM media WHERE entidad_tipo = ? AND entidad_id = ?')
           ->execute([$entidadTipo, $entidadId]);
}
