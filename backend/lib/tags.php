<?php
// ============================================================
//  ETIQUETAS (tags) de artículos
//  Archivo: backend/lib/tags.php
//
//  Sincroniza el texto "tag1, tag2" del formulario con las
//  tablas `tags` y `articulo_tags`. Antes vivía declarada dentro
//  de admin/articulo_form.php, lo que impedía reutilizarla.
// ============================================================

/**
 * Reemplaza por completo las etiquetas de un artículo por las
 * indicadas en $textoTags (separadas por comas).
 *
 * Los slugs se generan con slugify(), de modo que "Motor 2D" y
 * "motor-2d" apuntan al mismo registro y no se duplican.
 */
function sincronizarTags(int $articuloId, string $textoTags): void {
    $db = getDB();

    $nombres = array_filter(
        array_map('trim', explode(',', $textoTags)),
        static fn(string $n): bool => $n !== ''
    );

    $tagIds = [];

    foreach ($nombres as $nombre) {
        $slug = slugify($nombre);
        if ($slug === '') {
            continue;
        }

        $existe = dbUnica('SELECT id FROM tags WHERE slug = ? LIMIT 1', [$slug]);

        if ($existe) {
            $tagIds[] = (int) $existe['id'];
        } else {
            $db->prepare('INSERT IGNORE INTO tags (nombre, slug) VALUES (?, ?)')
               ->execute([mb_substr($nombre, 0, 100), mb_substr($slug, 0, 120)]);
            $tagIds[] = (int) $db->lastInsertId();
        }
    }

    // Resincronizar: fuera las que ya no estén, dentro las nuevas
    $db->prepare('DELETE FROM articulo_tags WHERE articulo_id = ?')
       ->execute([$articuloId]);

    $ins = $db->prepare(
        'INSERT IGNORE INTO articulo_tags (articulo_id, tag_id) VALUES (?, ?)'
    );
    foreach (array_unique($tagIds) as $tagId) {
        $ins->execute([$articuloId, $tagId]);
    }
}

/** Texto "tag1, tag2" con las etiquetas actuales del artículo. */
function tagsDeArticulo(int $articuloId): string {
    $st = getDB()->prepare(
        'SELECT t.nombre
           FROM tags t
           JOIN articulo_tags atg ON atg.tag_id = t.id
          WHERE atg.articulo_id = ?
       ORDER BY t.nombre'
    );
    $st->execute([$articuloId]);
    return implode(', ', $st->fetchAll(PDO::FETCH_COLUMN));
}
