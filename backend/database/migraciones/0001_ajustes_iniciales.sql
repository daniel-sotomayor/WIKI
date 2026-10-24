-- ============================================================
--  Migración 0001 — Ajustes iniciales del esquema
--  Idempotente: se puede aplicar sobre una base recién instalada
--  con database.sql (todo ya existe) o sobre una copia anterior.
--
--  Añade: tabla de configuración del sitio, vistas de lectura y
--  las tablas que usa la utilería de migraciones.
-- ============================================================

CREATE TABLE IF NOT EXISTS `config_sitio` (
    `clave`          VARCHAR(60) NOT NULL,
    `valor`          TEXT        NULL,
    `actualizado_en` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migraciones` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `archivo`     VARCHAR(120) NOT NULL,
    `checksum`    CHAR(64)     NOT NULL,
    `aplicado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_migraciones_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajustes por defecto (sólo si la clave no existe todavía)
INSERT INTO `config_sitio` (`clave`, `valor`)
SELECT 'mantenimiento', '0' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `config_sitio` WHERE `clave` = 'mantenimiento');

INSERT INTO `config_sitio` (`clave`, `valor`)
SELECT 'contacto_institucional', 'desarrollo@telecom.gob.ve' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `config_sitio` WHERE `clave` = 'contacto_institucional');

-- Vistas de lectura (CREATE OR REPLACE es seguro repetir)
CREATE OR REPLACE VIEW `v_proyectos_resumen` AS
SELECT p.`id`, p.`titulo`, p.`slug`, p.`estado`, p.`version_actual`,
       p.`tecnologias`, p.`descripcion_corta`, p.`fecha_inicio`,
       p.`fecha_actualizacion`, p.`creado_en`,
       c.`nombre` AS categoria_nombre, c.`slug` AS categoria_slug,
       u.`nombre` AS responsable_nombre, u.`apellido` AS responsable_apellido,
       (SELECT COUNT(*) FROM `articulos` a
         WHERE a.`proyecto_id` = p.`id` AND a.`publicado` = 1) AS articulos_publicados,
       (SELECT COUNT(*) FROM `articulos` a WHERE a.`proyecto_id` = p.`id`) AS articulos_total,
       (SELECT ROUND(AVG(h.`porcentaje_avance`))
          FROM `hitos` h WHERE h.`proyecto_id` = p.`id`) AS avance_medio
  FROM `proyectos` p
  LEFT JOIN `categorias` c ON c.`id` = p.`categoria_id`
  LEFT JOIN `usuarios`   u ON u.`id` = p.`responsable_id`;

CREATE OR REPLACE VIEW `v_articulos_publicados` AS
SELECT a.`id`, a.`titulo`, a.`slug`, a.`resumen`, a.`tipo`, a.`version`,
       a.`actualizado`, a.`creado_en`,
       p.`id` AS proyecto_id, p.`titulo` AS proyecto_titulo, p.`slug` AS proyecto_slug,
       u.`nombre` AS autor_nombre, u.`apellido` AS autor_apellido
  FROM `articulos` a
  JOIN `proyectos` p ON p.`id` = a.`proyecto_id`
  LEFT JOIN `usuarios` u ON u.`id` = a.`autor_id`
 WHERE a.`publicado` = 1;

CREATE OR REPLACE VIEW `v_noticias_publicadas` AS
SELECT n.`id`, n.`titulo`, n.`slug`, n.`resumen`, n.`categoria`,
       n.`destacada`, n.`fecha_publicacion`, n.`creado_en`,
       u.`nombre` AS autor_nombre, u.`apellido` AS autor_apellido
  FROM `noticias` n
  LEFT JOIN `usuarios` u ON u.`id` = n.`autor_id`
 WHERE n.`estado` = 'publicada'
   AND n.`fecha_publicacion` IS NOT NULL;

CREATE OR REPLACE VIEW `v_noticias_moderacion` AS
SELECT n.`id`, n.`titulo`, n.`slug`, n.`estado`, n.`razon_rechazo`,
       n.`creado_en`, n.`fecha_publicacion`,
       a.`nombre` AS autor_nombre, a.`apellido` AS autor_apellido,
       r.`nombre` AS revisor_nombre, r.`apellido` AS revisor_apellido
  FROM `noticias` n
  LEFT JOIN `usuarios` a ON a.`id` = n.`autor_id`
  LEFT JOIN `usuarios` r ON r.`id` = n.`aprobado_por`
 WHERE n.`estado` IN ('pendiente','rechazada');
