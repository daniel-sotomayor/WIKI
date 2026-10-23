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
      toggle.addEventListener('click', function () {
        var abierto = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        toggle.setAttribute('aria-label', abierto ? 'Cerrar menú de navegación'
                                                  : 'Abrir menú de navegación');
      });

      // Al salir del foco del menú con Escape se cierra
      nav.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          nav.classList.remove('is-open');
          toggle.setAttribute('aria-expanded', 'false');
          toggle.focus();
        }
      });
    }

    // ── Animación de aparición al hacer scroll ─────────────
    var elementos = document.querySelectorAll('.reveal');

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
  });
})();
