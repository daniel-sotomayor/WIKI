// ============================================================
//  TelecomWiki — main.js
//  Comportamiento común de todas las páginas.
//
//  Todo está resuelto aquí para no necesitar atributos
//  onclick/onsubmit en línea: la política de seguridad de
//  contenidos (CSP) no permite script-src 'unsafe-inline'.
// ============================================================

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    // ── Menú móvil ─────────────────────────────────────────
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('nav-principal');

    if (toggle && nav) {
      function cerrarMenu() {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Abrir menú de navegación');
      }

      toggle.addEventListener('click', function () {
        var abierto = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        toggle.setAttribute('aria-label', abierto ? 'Cerrar menú de navegación'
                                                  : 'Abrir menú de navegación');
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && nav.classList.contains('is-open')) {
          cerrarMenu();
          toggle.focus();
        }
      });

      document.addEventListener('click', function (e) {
        if (nav.classList.contains('is-open')
            && !nav.contains(e.target)
            && !toggle.contains(e.target)) {
          cerrarMenu();
        }
      });

      nav.querySelectorAll('a').forEach(function (enlace) {
        enlace.addEventListener('click', cerrarMenu);
      });

      var breakpoint = window.matchMedia('(min-width: 961px)');
      var alCambiarBreakpoint = function (e) {
        if (e.matches) cerrarMenu();
      };

      if (breakpoint.addEventListener) {
        breakpoint.addEventListener('change', alCambiarBreakpoint);
      } else if (breakpoint.addListener) {
        breakpoint.addListener(alCambiarBreakpoint);
      }
    }

    // ── Sombra reforzada de la cabecera al hacer scroll ────
    var cabecera = document.querySelector('.site-header');
    if (cabecera) {
      var alHacerScroll = function () {
        cabecera.classList.toggle('is-scrolled', window.scrollY > 8);
      };
      alHacerScroll();
      window.addEventListener('scroll', alHacerScroll, { passive: true });
    }

    // ── Animación de aparición al hacer scroll ─────────────
    var selectoresReveal = [
      '#contenido .page-head',
      '#contenido .seccion-cabecera',
      '#contenido .section-header',
      '#contenido .hero__inner',
      '#contenido .hero-institucional',
      '#contenido .grid > *',
      '#contenido .rejilla > *',
      '#contenido .lista-versiones > *',
      '#contenido .lista-moderacion > *',
      '#contenido .lista-hitos > *',
      '#contenido .lista-mensajes > *',
      '#contenido .lista-roles > *',
      '#contenido .panel'
    ];
    var elementos = document.querySelectorAll(selectoresReveal.join(','));

    document.querySelectorAll(
      '#contenido .grid, #contenido .rejilla, #contenido .lista-versiones, ' +
      '#contenido .lista-moderacion, #contenido .lista-hitos, #contenido .lista-mensajes, ' +
      '#contenido .lista-roles'
    ).forEach(function (grupo) {
      Array.from(grupo.children).forEach(function (el, indice) {
        el.classList.add('reveal', 'reveal--delay-' + (indice % 4 + 1));
      });
    });

    document.querySelectorAll(
      '#contenido .page-head, #contenido .seccion-cabecera, #contenido .section-header, ' +
      '#contenido .hero__inner, #contenido .hero-institucional, #contenido .panel'
    ).forEach(function (el) {
      el.classList.add('reveal');
    });

    if (elementos.length && 'IntersectionObserver' in window) {
      var observador = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (entrada) {
          if (entrada.isIntersecting) {
            entrada.target.classList.add('visible');
            observador.unobserve(entrada.target);
          }
        });
      }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

      elementos.forEach(function (el) { observador.observe(el); });
    } else {
      elementos.forEach(function (el) { el.classList.add('visible'); });
    }

    // ── Contadores de la portada ──────────────────────────
    var contadores = document.querySelectorAll('[data-count]');

    if (contadores.length && 'IntersectionObserver' in window
        && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      var observadorContadores = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (entrada) {
          if (!entrada.isIntersecting) return;

          var elemento = entrada.target;
          var destino = Number(elemento.dataset.count);
          observadorContadores.unobserve(elemento);

          if (!Number.isFinite(destino) || destino <= 0) {
            elemento.textContent = String(Number.isFinite(destino) ? destino : 0);
            return;
          }

          var inicio = performance.now();
          elemento.textContent = '0';

          function actualizarContador(ahora) {
            var avance = Math.min((ahora - inicio) / 900, 1);
            var suavizado = 1 - Math.pow(1 - avance, 3);
            elemento.textContent = String(Math.round(destino * suavizado));

            if (avance < 1) window.requestAnimationFrame(actualizarContador);
          }

          window.requestAnimationFrame(actualizarContador);
        });
      }, { threshold: 0.5 });

      contadores.forEach(function (elemento) {
        observadorContadores.observe(elemento);
      });
    }

    // ── Alertas que se cierran solas ───────────────────────
    document.querySelectorAll('.alert[role="status"], .alert[role="alert"]').forEach(function (el) {
      // Las alertas de error quedan; sólo las informativas desaparecen.
      if (el.classList.contains('alert-danger')) return;

      setTimeout(function () {
        el.style.transition = 'opacity .5s';
        el.style.opacity = '0';
        setTimeout(function () { el.remove(); }, 500);
      }, 5000);
    });

    // ── Confirmación de envío (destructivo) ────────────────
    document.querySelectorAll('form[data-confirm-submit]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.dataset.confirmSubmit || '¿Estás seguro?')) {
          e.preventDefault();
        }
      });
    });

    // ── Enlaces que piden confirmación ─────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (!window.confirm(el.dataset.confirm || '¿Estás seguro?')) {
          e.preventDefault();
        }
      });
    });

    // ── Modo oscuro / claro ────────────────────────────────
    //  <head> ya resolvió la preferencia (script con nonce) y dejó
    //  data-tema ('claro'|'oscuro') y data-tema-pref
    //  ('auto'|'claro'|'oscuro') en <html>. Acá sólo:
    //  · el clic del alternador fuerza el tema opuesto y lo guarda
    //    en localStorage ('tw-tema');
    //  · en 'auto', los cambios del sistema se aplican en vivo;
    //  · si otra pestaña cambia la preferencia, se sincroniza.
    (function tema() {
      var raiz = document.documentElement;
      var boton = document.querySelector('[data-tema-toggle]');
      var sistema = window.matchMedia('(prefers-color-scheme: dark)');
      var ETIQUETAS = { auto: 'seguir el sistema', claro: 'claro', oscuro: 'oscuro' };

      function pref()      { return raiz.getAttribute('data-tema-pref') || 'auto'; }
      function efectivo()  { return raiz.getAttribute('data-tema') === 'oscuro' ? 'oscuro' : 'claro'; }

      function aplicar(nuevaPref) {
        var oscuro = nuevaPref === 'auto'
          ? sistema.matches
          : nuevaPref === 'oscuro';
        raiz.setAttribute('data-tema', oscuro ? 'oscuro' : 'claro');
        raiz.setAttribute('data-tema-pref', nuevaPref);
        try { localStorage.setItem('tw-tema', nuevaPref); } catch (e) { /* modo privado */ }
        pintar();
      }

      function pintar() {
        if (!boton) return;
        var actual = efectivo();
        var siguiente = actual === 'oscuro' ? 'claro' : 'oscuro';
        boton.setAttribute('aria-pressed', actual === 'oscuro' ? 'true' : 'false');
        boton.setAttribute('aria-label',
          'Tema: ' + ETIQUETAS[pref()] + '. Activar modo ' + siguiente);
      }

      if (boton) {
        boton.addEventListener('click', function () {
          aplicar(efectivo() === 'oscuro' ? 'claro' : 'oscuro');
        });
      }

      // El usuario no forzó nada: el sistema manda en caliente.
      sistema.addEventListener('change', function () {
        if (pref() === 'auto') aplicar('auto');
      });

      // Otra pestaña cambió la preferencia.
      window.addEventListener('storage', function (e) {
        if (e.key !== 'tw-tema') return;
        var p = e.newValue || 'auto';
        var oscuro = p === 'auto' ? sistema.matches : p === 'oscuro';
        raiz.setAttribute('data-tema', oscuro ? 'oscuro' : 'claro');
        raiz.setAttribute('data-tema-pref', p);
        pintar();
      });

      pintar();
    })();
  });
})();
