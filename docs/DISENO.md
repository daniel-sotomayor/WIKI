# Sistema de diseño

TelecomWiki usa un sistema de diseño propio en CSS plano (sin
preprocesador): tokens en variables CSS, una hoja por capa y
componentes reutilizables generados por helpers PHP. El look & feel
es institucional: azul marino, acentos dorados, tipografía del
sistema.

## Hojas de estilo y orden de carga

`partials/header.php` carga en este orden (sin `defer` para los
críticos):

| Archivo | Contenido |
|---|---|
| `frontend/css/base.css` | Reset, variables (tokens), tipografía, utilidades, formularios base |
| `frontend/css/layout.css` | Cabecera fija, footer, rejilla, breadcrumbs, paginación |
| `frontend/css/components.css` | Tarjetas, badges, alertas/flash, tablas, modal, botones |
| `frontend/css/pages.css` | Estilos específicos de cada página pública y mantenimiento |
| `frontend/css/admin.css` | Layout del panel (sidebar), tablas densas, diagnóstico |

El CSP exige `style-src 'self'`: **nunca** se usan atributos `style=`
ni `<style>` en línea; toda clase usada en el HTML debe existir en
estas hojas (lo valida `estructura_test.php`, sección 3).

## Tokens (`base.css`)

- **Color**: `--navy` `#0B1F3A` (base), `--navy-mid`, `--blue`
  `#1B4F8A`, `--blue-lt` `#2563EB` (enlaces/acciones), dorados
  `--gold`/`--gold-lt` (acento), grises `--grey-100…700`, texto
  `--text`/`--text-muted`, estados `--ok-*`, `--warn-*`, `--danger-*`,
  `--info-*` (fondo+texto).
- **Radio**: `--radius` 6px, `--radius-md` 9px, `--radius-lg` 12px.
- **Sombras**: `--shadow-sm/md/lg` (tinte navy translúcido).
- **Tipografía**: `--font-body` (system-ui…), `--font-display`
  (para títulos), `--font-mono` (códigos técnicos).
- **Transición**: `--transition` 180ms ease.

Las páginas deben consumir tokens, nunca colores literales, salvo
justificación documentada.

## Componentes (resumen)

- **Botones**: `.btn`, variantes `.btn--primario`, `.btn--peligro`,
  `.btn--fantasma`, `.btn--chico`. Las acciones destructivas usan
  confirmación JS (`confirmarAccion` en `js/app.js`).
- **Tarjetas**: `.tarjeta` para grid de proyectos/artículos/noticias;
  helpers `tarjetaProyecto()`, `tarjetaArticulo()`, `tarjetaNoticia()`
  emiten el marcado.
- **Badges**: `badgeEstado()`, `badgeRol()`, `badgeTipo()` →
  `.badge` + variante de color.
- **Alertas/flash**: `obtenerFlash()` imprime `.alerta alerta--ok|info|error`.
- **Tablas**: `.tabla` con cabecera sticky en el panel.
- **Formularios**: helper `campo()` genera label+input+errores con la
  clase `.campo`; errores con `pintarErrores()`.
- **Galería**: `galeríaMedia()` en `.galeria` con miniaturas.
- **Iconos**: `icono()` devuelve SVG inline con `aria-hidden="true"`
  (sin librerías externas).
- **Header/footer**: `partials/header.php` (SVG en línea del logo,
  navegación responsive con checkbox hack, sin JS obligatorio) y
  `partials/footer.php` (créditos académicos).

## Responsive

- Breakpoints usados: **700px** (móvil/tablet) y **~1000px** (panel).
- La navegación pública colapsa a menú desplegable en móvil
  (checkbox + label, accesible por teclado).
- El panel usa sidebar colapsable en pantallas chicas.
- Rejillas (`grid`) de tarjetas pasan de 3 columnas → 2 → 1.
- Tablas del panel: contenedor con `overflow-x:auto` para no romper
  el layout en pantallas angostas.

## Accesibilidad

- Un solo `<h1>` por página (lo valida la sección 6 de
  `estructura_test.php`); jerarquía de títulos sin saltos.
- Todo `<img>` lleva `alt` (vacío explícito si es decorativa; los
  SVG inline de iconos van con `aria-hidden="true"`).
- Contrast tokens: texto `--text` sobre `--off-white` supera 4.5:1;
  badges con par fondo/texto definidos por token.
- Foco visible (`:focus-visible`) en enlaces, botones e inputs.
- Formularios con `<label for>` real (helper `campo()`).
- `prefers-reduced-motion` respetado en transiciones.

## JavaScript

JS puro en `frontend/js/app.js`: menú móvil, confirmaciones de borrado,
pequeñas mejoras de formulario. Sin handlers inline (CSP); los datos
necesarios para JS se pasan en `<input type="hidden">` y se leen con
`dataset`. La página funciona sin JS para los flujos esenciales
(navegación, CRUD, subidas).
