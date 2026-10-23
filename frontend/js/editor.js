// ============================================================
//  TelecomWiki — editor.js
//  Barra de herramientas propia (HTML + JS puro, sin librerías)
//  para los campos con la clase «editor-ric».
//
//  El campo sigue siendo un <textarea> normal: se envía tal cual
//  al servidor, donde lib/sanitize.php aplica la lista blanca de
//  etiquetas. Aquí sólo se insertan etiquetas permitidas.
// ============================================================ */

(function () {
  'use strict';

  // Etiquetas que la barra puede generar (debe coincidir con
  // TAGS_PERMITIDAS en backend/lib/sanitize.php).
  var BOTONES = [
    { accion: 'h2',      etiqueta: 'H2',  titulo: 'Encabezado 2',     tipo: 'bloque', etiquetaHtml: 'h2' },
    { accion: 'h3',      etiqueta: 'H3',  titulo: 'Encabezado 3',     tipo: 'bloque', etiquetaHtml: 'h3' },
    { accion: 'negrita', etiqueta: 'N',   titulo: 'Negrita (Ctrl+B)', tipo: 'linea',  apertura: '<strong>', cierre: '</strong>' },
    { accion: 'cursiva', etiqueta: 'C',   titulo: 'Cursiva (Ctrl+I)', tipo: 'linea',  apertura: '<em>', cierre: '</em>' },
    { accion: 'lista',   etiqueta: '•',   titulo: 'Lista con viñetas', tipo: 'lista', etiquetaHtml: 'ul' },
    { accion: 'numeros', etiqueta: '1.',  titulo: 'Lista numerada',   tipo: 'lista',  etiquetaHtml: 'ol' },
    { accion: 'cita',    etiqueta: '❝',  titulo: 'Cita',             tipo: 'bloque', etiquetaHtml: 'blockquote' },
    { accion: 'codigo',  etiqueta: '<>',  titulo: 'Bloque de código', tipo: 'bloque', etiquetaHtml: 'pre' },
    { accion: 'enlace',  etiqueta: 'Enl', titulo: 'Insertar enlace',  tipo: 'enlace' },
    { accion: 'imagen',  etiqueta: 'Img', titulo: 'Insertar imagen',  tipo: 'imagen' },
    { accion: 'tabla',   etiqueta: 'Tbl', titulo: 'Insertar tabla',   tipo: 'plantilla' },
    { accion: 'linea',   etiqueta: '—',   titulo: 'Línea horizontal', tipo: 'insertar', html: '<hr>' }
  ];

  var TABLA_EJEMPLO =
    '<table>\n  <thead>\n    <tr><th>Columna</th><th>Columna</th></tr>\n  </thead>\n'
    + '  <tbody>\n    <tr><td>Dato</td><td>Dato</td></tr>\n'
    + '    <tr><td>Dato</td><td>Dato</td></tr>\n  </tbody>\n</table>\n';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea.editor-ric').forEach(instalar);
  });

  function instalar(area) {
    if (area.dataset.editorInstalado === '1') return;
    area.dataset.editorInstalado = '1';

    // El envoltorio evita que la barra se desplace con el texto
    // y sirve de contenedor para el foco visible.
    var envoltura = document.createElement('div');
    envoltura.className = 'editor';
    area.parentNode.insertBefore(envoltura, area);
    envoltura.appendChild(area);
    area.classList.add('editor__campo');

    var barra = document.createElement('div');
    barra.className = 'editor-toolbar';
    barra.setAttribute('role', 'toolbar');
    barra.setAttribute('aria-label', 'Herramientas de formato de texto');
    envoltura.insertBefore(barra, area);

    BOTONES.forEach(function (cfg) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'editor-btn';
      btn.textContent = cfg.etiqueta;
      btn.title = cfg.titulo;
      btn.setAttribute('aria-label', cfg.titulo);
      btn.addEventListener('click', function () {
        aplicar(area, cfg);
        area.focus();
      });
      barra.appendChild(btn);
    });

    // Atajos de teclado (el navegador sólo trae Ctrl+B/I por defecto).
    area.addEventListener('keydown', function (e) {
      if (!(e.ctrlKey || e.metaKey)) return;
      var tecla = e.key.toLowerCase();
      if (tecla === 'b') { e.preventDefault(); aplicar(area, buscar('negrita')); }
      else if (tecla === 'i') { e.preventDefault(); aplicar(area, buscar('cursiva')); }
    });
  }

  function buscar(accion) {
    for (var i = 0; i < BOTONES.length; i++) {
      if (BOTONES[i].accion === accion) return BOTONES[i];
    }
    return null;
  }

  function aplicar(area, cfg) {
    if (!cfg) return;

    var ini = area.selectionStart;
    var fin = area.selectionEnd;
    var valor = area.value;
    var seleccion = valor.slice(ini, fin);
    var texto = '';
    var cursorFinal;

    switch (cfg.tipo) {

      case 'linea':
        texto = cfg.apertura + (seleccion || 'texto') + cfg.cierre;
        cursorFinal = ini + texto.length;
        break;

      case 'bloque': {
        // Envuelve cada línea seleccionada en la etiqueta de bloque.
        var fragmentos = (seleccion || 'Título').split('\n');
        texto = fragmentos.map(function (linea) {
          return '<' + cfg.etiquetaHtml + '>' + linea + '</' + cfg.etiquetaHtml + '>';
        }).join('\n');
        cursorFinal = ini + texto.length;
        break;
      }

      case 'lista': {
        var items = (seleccion || 'Elemento').split('\n');
        texto = '<' + cfg.etiquetaHtml + '>\n'
          + items.map(function (linea) { return '  <li>' + linea + '</li>'; }).join('\n')
          + '\n</' + cfg.etiquetaHtml + '>\n';
        cursorFinal = ini + texto.length;
        break;
      }

      case 'enlace': {
        var href = window.prompt('Dirección del enlace (https://…):', 'https://');
        if (!href) return;
        texto = '<a href="' + href + '">' + (seleccion || 'texto del enlace') + '</a>';
        cursorFinal = ini + texto.length;
        break;
      }

      case 'imagen': {
        var src = window.prompt('Ruta o dirección de la imagen:', 'assets/img/');
        if (!src) return;
        var alt = window.prompt('Descripción de la imagen (texto alternativo):', '') || '';
        texto = '<img src="' + src + '" alt="' + alt + '">';
        cursorFinal = ini + texto.length;
        break;
      }

      case 'plantilla':
        texto = seleccion + TABLA_EJEMPLO;
        cursorFinal = ini + texto.length;
        break;

      case 'insertar':
        texto = (ini > 0 && valor[ini - 1] !== '\n' ? '\n' : '') + cfg.html + '\n';
        cursorFinal = ini + texto.length;
        break;

      default:
        return;
    }

    area.value = valor.slice(0, ini) + texto + valor.slice(fin);
    area.focus();
    area.setSelectionRange(cursorFinal, cursorFinal);
    // Notifica a cualquier escucha (contador de caracteres, etc.).
    area.dispatchEvent(new Event('input', { bubbles: true }));
  }
})();
