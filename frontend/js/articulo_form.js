// ============================================================
//  Formulario de artículo — guía de plantillas por tipo
//  Archivo: frontend/js/articulo_form.js
//
//  Toma la definición de plantillas desde un <input type="hidden">
//  en lugar de inyectar <script> con datos dinámicos, así no hay
//  que romper la política de seguridad de contenidos (CSP).
// ============================================================

(function () {
  'use strict';

  var dato = document.getElementById('plantillas-data');
  var sel = document.getElementById('tipo');
  var titulo = document.getElementById('plantilla-titulo');
  var lista = document.getElementById('plantilla-lista');
  var boton = document.getElementById('btn-plantilla');
  var contenido = document.getElementById('contenido');

  if (!dato || !sel || !titulo || !lista || !boton || !contenido) return;

  var plantillas = {};
  try {
    plantillas = JSON.parse(dato.value || '{}');
  } catch (e) {
    plantillas = {};
  }

  function plantillaActual() {
    return plantillas[sel.value] || plantillas.otro || { label: '', secciones: [] };
  }

  /** Pinta la lista de secciones recomendadas para el tipo elegido. */
  function actualizarGuia() {
    var t = plantillaActual();
    titulo.textContent = t.label
      ? 'Estructura sugerida — ' + t.label
      : 'Estructura sugerida';

    lista.textContent = '';
    (t.secciones || []).forEach(function (s) {
      var li = document.createElement('li');
      var fuerte = document.createElement('strong');
      fuerte.textContent = s.titulo;
      li.appendChild(fuerte);
      li.appendChild(document.createTextNode(' — ' + s.ayuda));
      lista.appendChild(li);
    });
  }

  /** Genera el esqueleto HTML a partir de las secciones. */
  function generarEsqueleto(t) {
    return (t.secciones || [])
      .map(function (s) {
        return '<h2>' + s.titulo + '</h2>\n<p>[' + s.ayuda + ']</p>';
      })
      .join('\n\n');
  }

  sel.addEventListener('change', actualizarGuia);
  boton.addEventListener('click', function () {
    var t = plantillaActual();
    if (contenido.value.trim() !== '') {
      var ok = window.confirm(
        'Esto reemplaza el contenido actual por la plantilla de «' +
          (t.label || sel.value) +
          '». ¿Continuar?'
      );
      if (!ok) return;
    }
    contenido.value = generarEsqueleto(t);
    contenido.focus();
  });

  actualizarGuia();
})();
