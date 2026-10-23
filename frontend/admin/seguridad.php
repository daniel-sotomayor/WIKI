<?php
// ============================================================
//  Bitácora de seguridad
//  Archivo: frontend/admin/seguridad.php
//  Requiere: capacidad `bitacora.ver` (administrador)
//
//  Lista los eventos registrados por logSeguridad(): accesos,
//  intentos fallidos, CSRF rechazado, denegaciones de permiso,
//  altas/bajas de contenido y subidas.
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

requierePermiso('bitacora.ver');

$yo = currentUser();

// ── Purga de eventos antiguos (acción administrativa) ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'purgar') {
    verifyCsrf();

    $dias = entero($_POST['dias'] ?? '90', 90);
    $dias = max(7, min($dias, 3650));
    $desde = date('Y-m-d H:i:s', time() - ($dias * 86400));

    $st = getDB()->prepare('DELETE FROM eventos_seguridad WHERE creado_en < ?');
    $st->execute([$desde]);

    $eliminados = $st->rowCount();
    logSeguridad('bitacora_purgada', $yo['usuario'], $eliminados . ' eventos anteriores a ' . $dias . ' días');

    redirigir('admin/seguridad.php', 'success', 'Bitácora purgada: ' . $eliminados . ' eventos eliminados.');
}

// ── Filtros ─────────────────────────────────────────────────
$filtroTipo  = saneaGET('tipo', 40);
$filtroTexto = saneaGET('q', 80);

// Lista de tipos existentes: el filtro se adapta a los eventos nuevos
$tipos = getDB()->query('SELECT DISTINCT tipo FROM eventos_seguridad ORDER BY tipo')
                ->fetchAll(PDO::FETCH_COLUMN);

if ($filtroTipo !== '' && !in_array($filtroTipo, $tipos, true)) {
    $filtroTipo = '';
}

// ── Consulta con filtros (preparada, LIKE escapado) ─────────
$condiciones = [];
$params = [];

if ($filtroTipo !== '') {
    $condiciones[] = 'tipo = :tipo';
    $params[':tipo'] = $filtroTipo;
}
if ($filtroTexto !== '') {
    // escaparLike neutraliza % y _ (MySQL usa \ por defecto).
    $busqueda = '%' . escaparLike($filtroTexto) . '%';
    $condiciones[] = '(usuario LIKE :bus1 OR ip LIKE :bus2 OR detalle LIKE :bus3)';
    $params[':bus1'] = $busqueda;
    $params[':bus2'] = $busqueda;
    $params[':bus3'] = $busqueda;
}

$where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';

$sqlCount = 'SELECT COUNT(*) FROM eventos_seguridad' . $where;
$stCount = getDB()->prepare($sqlCount);
$stCount->execute($params);
$total = (int) $stCount->fetchColumn();

$pag = paginar($total, POR_PAGINA_ADMIN);

$sql = 'SELECT * FROM eventos_seguridad' . $where
     . ' ORDER BY creado_en DESC, id DESC'
     // LIMIT/OFFSET se interpolan: paginar() garantiza enteros y así
     // evitamos mezclar parámetros nombrados con enteros nativos.
     . " LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}";
$st = getDB()->prepare($sql);
$st->execute($params);
$eventos = $st->fetchAll();

// ── Resumen de las últimas 24 horas ─────────────────────────
$ayer = date('Y-m-d H:i:s', time() - 86400);

$contar = function (string $sql, array $p = []) use ($ayer): int {
    $s = getDB()->prepare($sql);
    $s->execute(array_merge([$ayer], $p));
    return (int) $s->fetchColumn();
};

$total24 = $contar('SELECT COUNT(*) FROM eventos_seguridad WHERE creado_en > ?');
$fallidos24 = $contar(
    "SELECT COUNT(*) FROM eventos_seguridad
      WHERE creado_en > ? AND tipo IN ('login_fallido','login_bloqueado')"
);
$denegados24 = $contar(
    "SELECT COUNT(*) FROM eventos_seguridad
      WHERE creado_en > ? AND tipo IN ('acceso_denegado','csrf_invalido','entrada_invalida')"
);

// ── Insignia según el tipo de evento ────────────────────────
$claseEvento = [
    'login_exitoso'    => 'status-activo',
    'login_fallido'    => 'status-rechazada',
    'login_bloqueado'  => 'status-rechazada',
    'acceso_denegado'  => 'status-rechazada',
    'csrf_invalido'    => 'status-rechazada',
    'entrada_invalida' => 'status-rechazada',
    'logout'           => 'status-borrador',
    'bitacora_purgada' => 'status-pendiente',
];
$clasePorDefecto = 'status-borrador';

$etiquetas = [
    'login_exitoso'    => 'Login correcto',
    'login_fallido'    => 'Login fallido',
    'login_bloqueado'  => 'Login bloqueado',
    'logout'           => 'Sesión cerrada',
    'acceso_denegado'  => 'Permiso denegado',
    'csrf_invalido'    => 'CSRF inválido',
    'entrada_invalida' => 'Entrada inválida',
    'bitacora_purgada' => 'Bitácora purgada',
];

$pageTitle = 'Bitácora de seguridad';
$cssExtra  = 'admin.css';
abrirPagina();
?>

<div class="container container--panel">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Panel' => 'admin/index.php'],
        ['Bitácora de seguridad' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1>Bitácora de seguridad</h1>
            <p class="muted">Eventos de acceso, permisos y contenido registrados por el sistema.</p>
        </div>
        <div class="seccion-cabecera__acciones">
            <a class="btn btn-ghost" href="<?= e(url('admin/index.php')) ?>">
                <?= icono('arrow', 15) ?> Volver al panel
            </a>
        </div>
    </div>

    <ul class="rejilla rejilla--3 lista-stats">
        <li class="tarjeta-stat">
            <strong><?= $total24 ?></strong>
            <span>Eventos en las últimas 24 horas</span>
        </li>
        <li class="tarjeta-stat<?= $fallidos24 ? ' tarjeta-stat--aviso' : '' ?>">
            <strong><?= $fallidos24 ?></strong>
            <span>Intentos de login fallidos o bloqueados (24 h)</span>
        </li>
        <li class="tarjeta-stat<?= $denegados24 ? ' tarjeta-stat--aviso' : '' ?>">
            <strong><?= $denegados24 ?></strong>
            <span>Permisos denegados o CSRF rechazado (24 h)</span>
        </li>
    </ul>

    <form class="filtros" method="get" action="<?= e(url('admin/seguridad.php')) ?>">
        <div class="filtros__campo">
            <label for="tipo">Tipo de evento</label>
            <select class="form-control form-control--sm" id="tipo" name="tipo">
                <option value="">Todos</option>
                <?php foreach ($tipos as $t): ?>
                    <option value="<?= e($t) ?>"<?= $filtroTipo === $t ? ' selected' : '' ?>>
                        <?= e($etiquetas[$t] ?? $t) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtros__campo filtros__campo--grande">
            <label for="q">Buscar por usuario, IP o detalle</label>
            <input class="form-control form-control--sm" type="search" id="q" name="q"
                   value="<?= e($filtroTexto) ?>" placeholder="ej.: admin, 127.0.0.1, artículo">
        </div>
        <div class="filtros__acciones">
            <button class="btn btn-primary btn-sm" type="submit"><?= icono('search', 14) ?> Filtrar</button>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/seguridad.php')) ?>">Limpiar</a>
        </div>
    </form>

    <?php if (!$eventos): ?>
        <div class="alert alert-info">
            <?= $total ? 'Ningún evento coincide con los filtros aplicados.' : 'Todavía no hay eventos registrados en la bitácora.' ?>
        </div>
    <?php else: ?>

        <div class="tabla-envoltura">
            <table class="tabla-datos">
                <caption class="sr-only">Eventos de seguridad</caption>
                <thead>
                    <tr>
                        <th scope="col">Fecha y hora</th>
                        <th scope="col">Evento</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">IP</th>
                        <th scope="col">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventos as $ev): ?>
                        <?php $tipo = (string) $ev['tipo']; ?>
                        <tr>
                            <td><?= e(fecha($ev['creado_en'], 'd/m/Y H:i:s')) ?></td>
                            <td>
                                <span class="status-badge <?= e($claseEvento[$tipo] ?? $clasePorDefecto) ?>">
                                    <?= e($etiquetas[$tipo] ?? $tipo) ?>
                                </span>
                            </td>
                            <td><?= $ev['usuario'] !== null && $ev['usuario'] !== '' ? e($ev['usuario']) : '<span class="muted">—</span>' ?></td>
                            <td><code><?= e($ev['ip']) ?></code></td>
                            <td><?= $ev['detalle'] !== null && $ev['detalle'] !== '' ? e(resumir($ev['detalle'], 120)) : '<span class="muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="muted"><?= e(resumenPaginacion($pag)) ?></p>
        <?= htmlPaginacion($pag) ?>

    <?php endif; ?>

    <section class="panel panel--peligro">
        <h2 class="panel__titulo">Mantenimiento de la bitácora</h2>
        <p>Los eventos se conservan indefinidamente. Para aligerar la base podés eliminar
           los registros antiguos; esta acción queda registrada en la bitácora.</p>
        <form method="post" action="<?= e(url('admin/seguridad.php')) ?>"
              data-confirm-submit="¿Eliminar los eventos antiguos de la bitácora? Esta acción no se puede deshacer.">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="purgar">
            <div class="form-group">
                <label class="form-label" for="dias">Conservar eventos de los últimos</label>
                <div class="page-head__acciones">
                    <select class="form-control form-control--sm" id="dias" name="dias">
                        <option value="30">30 días</option>
                        <option value="90" selected>90 días</option>
                        <option value="180">180 días</option>
                        <option value="365">1 año</option>
                    </select>
                    <button class="btn btn-danger" type="submit"><?= icono('trash', 15) ?> Purgar eventos antiguos</button>
                </div>
            </div>
        </form>
    </section>

</div>

<?php cerrarPagina(); ?>
