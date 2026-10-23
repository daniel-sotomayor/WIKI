-- ============================================================
--  TelecomWiki 3.0 — Sistema de Gestión y Estandarización de
--  Documentación Técnica para proyectos de Telecom Venezuela, C.A.
--
--  Motor   : MySQL 5.7+ / MariaDB 10.2+
--  Charset : utf8mb4 (admite tildes, ñ y emojis)
--
--  IMPORTACIÓN EN XAMPP:
--    1. Abre http://localhost/phpmyadmin
--    2. Importar archivo → seleccioná este archivo → Continuar
--    (el script crea la base de datos, las tablas y los datos de ejemplo)
--
--  CUENTAS DE PRUEBA
--    admin       / Admin2026!   (rol administrador)
--    supervisor  / Demo2026!    (rol supervisor)
--    editor      / Demo2026!    (rol editor)
--    lector      / Demo2026!    (rol lector)
--  Cambiá la contraseña de admin inmediatamente después del primer acceso.
-- ============================================================

CREATE DATABASE IF NOT EXISTS telecom_wiki
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE telecom_wiki;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Tabla: usuarios  (4 roles jerárquicos: lector < editor < supervisor < admin)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`        VARCHAR(100)  NOT NULL,
    `apellido`      VARCHAR(100)  NOT NULL,
    `usuario`       VARCHAR(50)   NOT NULL,
    `email`         VARCHAR(150)  NOT NULL,
    `password`      VARCHAR(255)  NOT NULL,
    `rol`           ENUM('lector','editor','supervisor','admin') NOT NULL DEFAULT 'lector',
    `activo`        TINYINT(1)    NOT NULL DEFAULT 1,
    `ultimo_acceso` DATETIME      NULL DEFAULT NULL,
    `creado_en`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
    UNIQUE KEY `uq_usuarios_email`   (`email`),
    KEY `idx_usuarios_rol_activo` (`rol`,`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: categorias  (clasificación de proyectos)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(120) NOT NULL,
    `descripcion` TEXT         NULL,
    `icono`       VARCHAR(50)  NOT NULL DEFAULT 'folder',
    `creado_en`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categorias_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: proyectos  (ficha técnica estandarizada)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `proyectos`;
CREATE TABLE `proyectos` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titulo`              VARCHAR(200)  NOT NULL,
    `slug`                VARCHAR(220)  NOT NULL,
    `categoria_id`        INT UNSIGNED  NOT NULL,
    `descripcion_corta`   VARCHAR(500)  NULL,
    `descripcion`         LONGTEXT      NULL,
    `tecnologias`         VARCHAR(300)  NULL,
    `version_actual`      VARCHAR(20)   NULL,
    `estado`              ENUM('activo','en_desarrollo','pausado','archivado') NOT NULL DEFAULT 'en_desarrollo',
    `responsable_id`      INT UNSIGNED  NULL,
    `fecha_inicio`        DATE          NULL,
    `fecha_actualizacion` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `creado_en`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_proyectos_slug` (`slug`),
    KEY `idx_proyectos_categoria` (`categoria_id`),
    KEY `idx_proyectos_estado` (`estado`,`creado_en`),
    KEY `idx_proyectos_responsable` (`responsable_id`),
    CONSTRAINT `fk_proyectos_categoria`   FOREIGN KEY (`categoria_id`)   REFERENCES `categorias`(`id`)     ON DELETE RESTRICT,
    CONSTRAINT `fk_proyectos_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios`(`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: articulos  (documentación wiki, con control de versiones)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `articulos`;
CREATE TABLE `articulos` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `proyecto_id` INT UNSIGNED  NOT NULL,
    `titulo`      VARCHAR(300)  NOT NULL,
    `slug`        VARCHAR(320)  NOT NULL,
    `resumen`     VARCHAR(500)  NULL,
    `contenido`   LONGTEXT      NULL,
    `tipo`        ENUM('guion','arquitectura','mecanica','personaje','nivel','tecnico','otro') NOT NULL DEFAULT 'tecnico',
    `autor_id`    INT UNSIGNED  NULL,
    `version`     INT UNSIGNED  NOT NULL DEFAULT 1,
    `publicado`   TINYINT(1)    NOT NULL DEFAULT 0,
    `creado_en`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_articulos_slug` (`slug`),
    KEY `idx_articulos_proyecto`  (`proyecto_id`,`publicado`),
    KEY `idx_articulos_publicado` (`publicado`),
    KEY `idx_articulos_tipo`      (`tipo`),
    KEY `idx_articulos_autor`     (`autor_id`),
    CONSTRAINT `fk_articulos_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_articulos_autor`    FOREIGN KEY (`autor_id`)    REFERENCES `usuarios`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: historial_versiones  (cada guardado de un artículo deja copia)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `historial_versiones`;
CREATE TABLE `historial_versiones` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `articulo_id` INT UNSIGNED  NOT NULL,
    `contenido`   LONGTEXT      NULL,
    `version`     INT UNSIGNED  NOT NULL,
    `editado_por` INT UNSIGNED  NULL,
    `editado_en`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_historial_articulo` (`articulo_id`,`version`),
    CONSTRAINT `fk_historial_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `articulos`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_historial_autor`    FOREIGN KEY (`editado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: tags / articulo_tags  (etiquetas de artículos, relación N:M)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `slug`  VARCHAR(120) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `articulo_tags`;
CREATE TABLE `articulo_tags` (
    `articulo_id` INT UNSIGNED NOT NULL,
    `tag_id`      INT UNSIGNED NOT NULL,
    PRIMARY KEY (`articulo_id`,`tag_id`),
    KEY `idx_articulo_tags_tag` (`tag_id`),
    CONSTRAINT `fk_at_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `articulos`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_at_tag`      FOREIGN KEY (`tag_id`)      REFERENCES `tags`(`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: hitos  (seguimiento y porcentaje de avance por proyecto)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `hitos`;
CREATE TABLE `hitos` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `proyecto_id`       INT UNSIGNED  NOT NULL,
    `titulo`            VARCHAR(200)  NOT NULL,
    `descripcion`       TEXT          NULL,
    `porcentaje_avance` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `fecha`             DATE          NOT NULL,
    `creado_por`        INT UNSIGNED  NULL,
    `creado_en`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hitos_proyecto_fecha` (`proyecto_id`,`fecha`),
    CONSTRAINT `fk_hitos_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hitos_autor`    FOREIGN KEY (`creado_por`)  REFERENCES `usuarios`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: noticias  (portal institucional con flujo de moderación)
--   estado es la ÚNICA fuente de verdad de "noticia visible"
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `noticias`;
CREATE TABLE `noticias` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titulo`            VARCHAR(300)  NOT NULL,
    `slug`              VARCHAR(320)  NOT NULL,
    `resumen`           VARCHAR(600)  NULL,
    `contenido`         LONGTEXT      NULL,
    `categoria`         VARCHAR(100)  NULL,
    `autor_id`          INT UNSIGNED  NULL,
    `estado`            ENUM('borrador','pendiente','publicada','rechazada') NOT NULL DEFAULT 'pendiente',
    `destacada`         TINYINT(1)    NOT NULL DEFAULT 0,
    `aprobado_por`      INT UNSIGNED  NULL,
    `fecha_publicacion` DATETIME      NULL,
    `razon_rechazo`     TEXT          NULL,
    `creado_en`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_noticias_slug` (`slug`),
    KEY `idx_noticias_estado` (`estado`,`destacada`),
    KEY `idx_noticias_autor`  (`autor_id`),
    KEY `idx_noticias_fecha`  (`fecha_publicacion`),
    KEY `idx_noticias_creado` (`creado_en`),
    CONSTRAINT `fk_noticias_autor`     FOREIGN KEY (`autor_id`)     REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_noticias_aprobador` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: media  (imágenes, videos y documentos adjuntos)
--   El archivo vive en backend/storage/uploads/ (fuera del alcance web)
--   y se sirve siempre a través de frontend/media.php
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entidad_tipo`   ENUM('articulo','noticia','proyecto') NOT NULL,
    `entidad_id`     INT UNSIGNED  NOT NULL,
    `tipo`           ENUM('imagen','video','documento') NOT NULL DEFAULT 'imagen',
    `nombre_original` VARCHAR(255) NOT NULL,
    `nombre_guardado` VARCHAR(64)  NOT NULL,
    `mime`           VARCHAR(100)  NOT NULL,
    `tamano`         INT UNSIGNED  NOT NULL DEFAULT 0,
    `titulo`         VARCHAR(255)  NULL,
    `es_principal`   TINYINT(1)    NOT NULL DEFAULT 0,
    `subido_por`     INT UNSIGNED  NULL,
    `creado_en`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_media_guardado` (`nombre_guardado`),
    KEY `idx_media_entidad` (`entidad_tipo`,`entidad_id`,`es_principal`),
    KEY `idx_media_tipo` (`tipo`),
    CONSTRAINT `fk_media_subidor` FOREIGN KEY (`subido_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: mensajes_contacto  (formulario "Quiénes somos")
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `mensajes_contacto`;
CREATE TABLE `mensajes_contacto` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(150) NOT NULL,
    `email`       VARCHAR(150) NOT NULL,
    `mensaje`     TEXT         NOT NULL,
    `ip`          VARCHAR(45)  NULL,
    `leido`       TINYINT(1)   NOT NULL DEFAULT 0,
    `enviado_ok`  TINYINT(1)   NOT NULL DEFAULT 0,
    `creado_en`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mensajes_leido` (`leido`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: eventos_seguridad  (bitácora + límite de intentos de acceso)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `eventos_seguridad`;
CREATE TABLE `eventos_seguridad` (
    `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tipo`      VARCHAR(40)  NOT NULL,
    `usuario`   VARCHAR(150) NULL,
    `ip`        VARCHAR(45)  NOT NULL,
    `detalle`   TEXT         NULL,
    `creado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_eventos_tipo_fecha` (`tipo`,`creado_en`),
    KEY `idx_eventos_ip_tipo` (`ip`,`tipo`,`creado_en`),
    KEY `idx_eventos_usuario` (`usuario`,`tipo`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: migraciones  (registro de esquema aplicado)
--   La usa backend/bin/migrar.php. Cada archivo
--   backend/database/migraciones/NNNN_*.sql deja una fila acá.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `migraciones`;
CREATE TABLE `migraciones` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `archivo`     VARCHAR(120) NOT NULL,
    `checksum`    CHAR(64)     NOT NULL,
    `aplicado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_migraciones_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: config_sitio  (ajustes que se cambian desde el panel)
--   Clave/valor en texto; la capa backend/lib/config_sitio.php
--   cachea en memoria por petición.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `config_sitio`;
CREATE TABLE `config_sitio` (
    `clave`      VARCHAR(60)  NOT NULL,
    `valor`      TEXT         NULL,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  VISTAS  (consultas de lectura que reutilizan el panel y el sitio)
-- ============================================================

-- Ficha resumida de proyectos: categoría, responsable y avance medio
-- de sus hitos (NULL si el proyecto no tiene hitos cargados).
CREATE OR REPLACE VIEW `v_proyectos_resumen` AS
SELECT p.`id`, p.`titulo`, p.`slug`, p.`estado`, p.`version_actual`,
       p.`tecnologias`, p.`descripcion_corta`, p.`fecha_inicio`,
       p.`fecha_actualizacion`, p.`creado_en`,
       c.`nombre`     AS categoria_nombre,
       c.`slug`       AS categoria_slug,
       u.`nombre`     AS responsable_nombre,
       u.`apellido`   AS responsable_apellido,
       (SELECT COUNT(*) FROM `articulos` a
         WHERE a.`proyecto_id` = p.`id` AND a.`publicado` = 1) AS articulos_publicados,
       (SELECT COUNT(*) FROM `articulos` a
         WHERE a.`proyecto_id` = p.`id`)                        AS articulos_total,
       (SELECT ROUND(AVG(h.`porcentaje_avance`))
          FROM `hitos` h WHERE h.`proyecto_id` = p.`id`)        AS avance_medio
  FROM `proyectos` p
  LEFT JOIN `categorias` c ON c.`id` = p.`categoria_id`
  LEFT JOIN `usuarios`   u ON u.`id` = p.`responsable_id`;

-- Artículos publicados con los datos del proyecto (listados de la wiki).
CREATE OR REPLACE VIEW `v_articulos_publicados` AS
SELECT a.`id`, a.`titulo`, a.`slug`, a.`resumen`, a.`tipo`, a.`version`,
       a.`actualizado`, a.`creado_en`,
       p.`id`     AS proyecto_id,
       p.`titulo` AS proyecto_titulo,
       p.`slug`   AS proyecto_slug,
       u.`nombre` AS autor_nombre, u.`apellido` AS autor_apellido
  FROM `articulos` a
  JOIN `proyectos` p ON p.`id` = a.`proyecto_id`
  LEFT JOIN `usuarios` u ON u.`id` = a.`autor_id`
 WHERE a.`publicado` = 1;

-- Noticias visibles para el público (ya aprobadas y con fecha de
-- publicación); la portada destaca con `destacada`.
CREATE OR REPLACE VIEW `v_noticias_publicadas` AS
SELECT n.`id`, n.`titulo`, n.`slug`, n.`resumen`, n.`categoria`,
       n.`destacada`, n.`fecha_publicacion`, n.`creado_en`,
       u.`nombre` AS autor_nombre, u.`apellido` AS autor_apellido
  FROM `noticias` n
  LEFT JOIN `usuarios` u ON u.`id` = n.`autor_id`
 WHERE n.`estado` = 'publicada'
   AND n.`fecha_publicacion` IS NOT NULL;

-- Cola de moderación: quién revisó qué y cuándo quedó fuera.
CREATE OR REPLACE VIEW `v_noticias_moderacion` AS
SELECT n.`id`, n.`titulo`, n.`slug`, n.`estado`, n.`razon_rechazo`,
       n.`creado_en`, n.`fecha_publicacion`,
       a.`nombre` AS autor_nombre, a.`apellido` AS autor_apellido,
       r.`nombre` AS revisor_nombre, r.`apellido` AS revisor_apellido
  FROM `noticias` n
  LEFT JOIN `usuarios` a ON a.`id` = n.`autor_id`
  LEFT JOIN `usuarios` r ON r.`id` = n.`aprobado_por`
 WHERE n.`estado` IN ('pendiente','rechazada');

-- ============================================================
--  DATOS INICIALES
-- ============================================================

-- Usuarios de prueba (contraseñas: Admin2026! para admin, Demo2026! para el resto)
-- (hashes generados con password_hash(…, PASSWORD_DEFAULT))
INSERT INTO `usuarios` (`nombre`,`apellido`,`usuario`,`email`,`password`,`rol`) VALUES
('Administrador','Sistema','admin','admin@telecom.gob.ve',
 '$2y$10$PWFXk9J.m.FlfNC4Bt8/N.wapuN0QFKQvFk085DflNT9AuZa7V5hW','admin'),
('Carolina','Díaz','supervisor','supervisor@telecom.gob.ve',
 '$2y$10$sck1l3NC.PLbhV.Mnt4by.xjDtWY5WOvbeVw2c1jfbADdQktYasPa','supervisor'),
('Luis','Herrera','editor','editor@telecom.gob.ve',
 '$2y$10$sck1l3NC.PLbhV.Mnt4by.xjDtWY5WOvbeVw2c1jfbADdQktYasPa','editor'),
('María','Pérez','lector','lector@telecom.gob.ve',
 '$2y$10$sck1l3NC.PLbhV.Mnt4by.xjDtWY5WOvbeVw2c1jfbADdQktYasPa','lector');

-- Categorías base
INSERT INTO `categorias` (`nombre`,`slug`,`descripcion`,`icono`) VALUES
('Videojuegos','videojuegos','Proyectos de software recreativo tipo videojuego','gamepad'),
('Simuladores','simuladores','Aplicaciones de simulación educativa y técnica','cpu'),
('Apps Interactivas','apps-interactivas','Aplicaciones interactivas multiplataforma','mobile'),
('Infraestructura','infraestructura','Documentación de infraestructura tecnológica','server'),
('Talleres de Formación','talleres-formacion','Talleres y diplomados de capacitación tecnológica','users'),
('Telecomunicaciones','telecomunicaciones','Proyectos de mejora de las telecomunicaciones nacionales','signal');

-- Proyecto de ejemplo
INSERT INTO `proyectos`
 (`titulo`,`slug`,`categoria_id`,`descripcion_corta`,`descripcion`,`tecnologias`,`version_actual`,`estado`,`responsable_id`,`fecha_inicio`)
VALUES
('Batalla Bicentenaria','batalla-bicentenaria',1,
 'Videojuego histórico-educativo sobre la independencia venezolana.',
 '<p>Batalla Bicentenaria es un videojuego de estrategia histórica desarrollado por el departamento de desarrollo de Telecom Venezuela, C.A. El proyecto busca difundir el conocimiento sobre las gestas independentistas venezolanas mediante mecánicas de juego innovadoras y una narrativa rigurosa.</p><p>El juego combina elementos de estrategia por turnos, gestión de recursos y narrativa histórica para crear una experiencia educativa y entretenida, orientada al público joven venezolano.</p>',
 'Python, Pygame, MySQL, Tiled Map Editor','0.8.2','en_desarrollo',1,'2025-01-15');

-- Artículo de ejemplo
INSERT INTO `articulos`
 (`proyecto_id`,`titulo`,`slug`,`resumen`,`contenido`,`tipo`,`autor_id`,`publicado`)
VALUES
(1,'Arquitectura General del Sistema','batalla-bicentenaria-arquitectura',
 'Descripción de las capas del motor y las decisiones de diseño del juego.',
 '<h2>Arquitectura del Motor</h2><p>El motor de Batalla Bicentenaria está desarrollado en Python utilizando la librería Pygame. La arquitectura sigue el patrón MVC (Modelo-Vista-Controlador) para separar la lógica de juego de la presentación visual.</p><h3>Capas del Sistema</h3><ul><li><strong>Capa de Presentación:</strong> renderizado gráfico con Pygame y gestión de sprites.</li><li><strong>Capa de Lógica:</strong> motor de reglas del juego, IA de enemigos y gestión de turnos.</li><li><strong>Capa de Datos:</strong> MySQL para persistencia de partidas guardadas y estadísticas.</li></ul>',
 'arquitectura',1,1);

-- Etiquetas de ejemplo
INSERT INTO `tags` (`nombre`,`slug`) VALUES
('arquitectura','arquitectura'),
('python','python'),
('educativo','educativo');

INSERT INTO `articulo_tags` (`articulo_id`,`tag_id`) VALUES (1,1),(1,2),(1,3);

-- Hitos de ejemplo
INSERT INTO `hitos` (`proyecto_id`,`titulo`,`descripcion`,`porcentaje_avance`,`fecha`,`creado_por`) VALUES
(1,'Definición del diseño documental','Documento de diseño aprobado por la dirección.',100,'2025-02-01',1),
(1,'Prototipo jugable vertical','Primer escenario con combate por turnos funcional.',60,'2025-08-15',1),
(1,'Integración del capítulo de Boyacá','Contenido histórico y assets del segundo capítulo.',20,'2026-03-01',1);

-- Noticia de ejemplo
INSERT INTO `noticias`
 (`titulo`,`slug`,`resumen`,`contenido`,`categoria`,`autor_id`,`estado`,`destacada`,`aprobado_por`,`fecha_publicacion`)
VALUES
('Telecom Venezuela avanza en soberanía tecnológica con nuevos proyectos de software recreativo',
 'telecom-avanza-soberania-tecnologica',
 'El departamento de desarrollo presenta sus avances en videojuegos educativos y simuladores nacionales.',
 '<p>El equipo de desarrollo de Telecom Venezuela, C.A. presentó sus avances en el portafolio de software recreativo nacional, destacando el videojuego Batalla Bicentenaria y tres nuevos simuladores educativos en fase de prototipado.</p><p>Estas iniciativas se enmarcan en la política de soberanía tecnológica promovida por el Estado venezolano y buscan reemplazar progresivamente el software recreativo extranjero en los centros educativos nacionales.</p>',
 'Desarrollo',1,'publicada',1,1,NOW());

-- Ajustes iniciales del sitio (se editan desde Panel → Diagnóstico)
INSERT INTO `config_sitio` (`clave`,`valor`) VALUES
('mantenimiento','0'),
('titulo_portada','TelecomWiki — Documentación Técnica'),
('contacto_institucional','desarrollo@telecom.gob.ve');

-- Segundo proyecto de ejemplo
INSERT INTO `proyectos`
 (`titulo`,`slug`,`categoria_id`,`descripcion_corta`,`descripcion`,`tecnologias`,`version_actual`,`estado`,`responsable_id`,`fecha_inicio`)
VALUES
('Simulador de Centrales Virtuales','simulador-de-centrales-virtuales',4,
 'Simulación de operación y mantenimiento de centrales telefónicas.',
 '<p>Simulador interactivo para la formación de técnicos en la operación, diagnóstico y mantenimiento de centrales telefónicas, con escenarios basados en instalaciones reales de Telecom Venezuela.</p>',
 'C#, Unity, MySQL','1.2.0','activo',2,'2024-09-01');

-- Artículos de ejemplo (uno por tipo documental)
INSERT INTO `articulos`
 (`proyecto_id`,`titulo`,`slug`,`resumen`,`contenido`,`tipo`,`autor_id`,`publicado`)
VALUES
(1,'Guion narrativo del capítulo uno','batalla-bicentenaria-guion-capitulo-uno',
 'Estructura de misiones y diálogos del primer capítulo.',
 '<h2>Objetivo del capítulo</h2><p>Introducir al jugador en la campaña de 1810 con tres misiones tutoriales y un jefe de nivel.</p><ul><li>Misión 1: reclutamiento.</li><li>Misión 2: primer combate.</li><li>Misión 3: defensa del puente.</li></ul>',
 'guion',2,1),
(1,'Mecánica de combate por turnos','batalla-bicentenaria-mecanica-combate',
 'Reglas de combate, iniciativa y tipos de unidad.',
 '<h2>Reglas base</h2><p>Cada unidad tiene movimiento, ataque y moral. La iniciativa se resuelve por velocidad acumulada del bando.</p>',
 'mecanica',1,1),
(2,'Instalación y puesta en marcha','simulador-de-centrales-virtuales-instalacion',
 'Requisitos y pasos para instalar el simulador.',
 '<h2>Requisitos</h2><ul><li>Windows 10 o superior.</li><li>4 GB de RAM.</li><li>Resolución 1280×720 o superior.</li></ul>',
 'tecnico',2,1);

-- Noticias de ejemplo: una pendiente de moderación y una rechazada
INSERT INTO `noticias`
 (`titulo`,`slug`,`resumen`,`contenido`,`categoria`,`autor_id`,`estado`,`destacada`,`razon_rechazo`)
VALUES
('Convocatoria: taller de documentación técnica con Git','convocatoria-taller-documentacion-tecnica-git',
 'Taller práctico para el personal del departamento de desarrollo.',
 '<p>Se convoca al personal a un taller práctico sobre control de versiones con Git y buenas prácticas de documentación.</p>',
 'Formación',2,'pendiente',0,NULL),
('Promoción de software recreativo extranjero','promocion-software-recreativo-extranjero',
 'Nota promocional de terceros.',
 '<p>Contenido promocional sin relación con la institución.</p>',
 'Institucional',2,'rechazada',0,
 'La nota es publicidad de terceros y no corresponde al portal institucional.');

-- Mensajes de contacto de ejemplo (para la bandeja del panel)
INSERT INTO `mensajes_contacto` (`nombre`,`email`,`mensaje`,`ip`,`leido`) VALUES
('Ana Rivas','ana.rivas@ejemplo.com',
 'Consulta sobre el uso del simulador de centrales en el taller de formación.','127.0.0.1',0),
('Jorge Márquez','jorge.marquez@ejemplo.com',
 'Solicito acceso a la documentación del proyecto Batalla Bicentenaria para la revisión de la dirección.','127.0.0.1',0);

-- Artículos de ejemplo adicionales y relaciones de etiquetas
INSERT INTO `articulo_tags` (`articulo_id`,`tag_id`) VALUES (2,1),(4,2);
