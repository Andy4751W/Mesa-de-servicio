(function () {
  'use strict';

  window.__MESA_CONTROL_SESION_VERSION__ = '2026-08-10.5-session-only';

  var ENDPOINT = 'sesionActividad.php';
  var idleLimit = 300;
  var absoluteLimit = 1800;
  var warningLimit = 60;
  var idleEndsAt = Date.now() + idleLimit * 1000;
  var absoluteEndsAt = Date.now() + absoluteLimit * 1000;
  var lastActivitySent = 0;
  var requestInProgress = false;
  var sessionWarning = null;
  var sessionCountdown = null;

  function logout(reason) {
    window.location.replace('logout.php?motivo=' + encodeURIComponent(reason));
  }

  function addWarningStyles() {
    if (document.getElementById('mesa-session-warning-style')) return;
    var style = document.createElement('style');
    style.id = 'mesa-session-warning-style';
    style.textContent =
      '#mesa-session-warning{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;padding:20px;background:rgba(16,42,67,.58);backdrop-filter:blur(3px)}' +
      '#mesa-session-warning[hidden]{display:none!important}.mesa-session-card{width:min(410px,100%);padding:28px;border:1px solid #dce6f1;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(16,42,67,.28);color:#243b53;text-align:center;font-family:Inter,"Segoe UI",Arial,sans-serif}' +
      '.mesa-session-icon{display:grid;width:50px;height:50px;margin:0 auto 14px;place-items:center;border-radius:14px;background:#fff4e5;color:#b54708;font-size:24px;font-weight:800}.mesa-session-card h2{margin:0 0 8px;color:#102a43;font-size:21px}.mesa-session-card p{margin:0;color:#627d98;font-size:14px;line-height:1.5}' +
      '#mesa-session-countdown{display:block;margin:14px 0;color:#b42318;font-size:28px}#mesa-session-continue{min-height:42px;padding:10px 17px;border:0;border-radius:10px;background:#0f6fec;color:#fff;cursor:pointer;font-weight:750}#mesa-session-continue[hidden]{display:none!important}';
    document.head.appendChild(style);
  }

  function createWarning() {
    if (sessionWarning) return;
    addWarningStyles();
    sessionWarning = document.createElement('div');
    sessionWarning.id = 'mesa-session-warning';
    sessionWarning.hidden = true;
    sessionWarning.setAttribute('role', 'dialog');
    sessionWarning.setAttribute('aria-modal', 'true');
    sessionWarning.setAttribute('aria-labelledby', 'mesa-session-title');
    sessionWarning.innerHTML =
      '<div class="mesa-session-card">' +
        '<div class="mesa-session-icon" aria-hidden="true">!</div>' +
        '<h2 id="mesa-session-title">La sesión está por cerrarse</h2>' +
        '<p id="mesa-session-message"></p>' +
        '<strong id="mesa-session-countdown"></strong>' +
        '<button type="button" id="mesa-session-continue">Continuar trabajando</button>' +
      '</div>';
    document.body.appendChild(sessionWarning);
    sessionCountdown = sessionWarning.querySelector('#mesa-session-countdown');
    sessionWarning.querySelector('#mesa-session-continue').addEventListener('click', function () {
      registerActivity(true);
    });
  }

  function hideWarning() {
    if (sessionWarning) sessionWarning.hidden = true;
  }

  function showWarning(seconds, absolute) {
    createWarning();
    sessionWarning.hidden = false;
    sessionWarning.querySelector('#mesa-session-message').textContent = absolute
      ? 'Por seguridad, toda sesión debe finalizar al cumplir el tiempo máximo.'
      : 'No se ha detectado interacción. Use el botón para mantener la sesión activa.';
    sessionWarning.querySelector('#mesa-session-continue').hidden = absolute;
    sessionCountdown.textContent = Math.max(0, seconds) + ' s';
  }

  function applyState(data) {
    if (!data || !data.ok || !data.tiempos) return false;
    var now = Date.now();
    idleLimit = Number(data.tiempos.inactividad) || 0;
    absoluteLimit = Number(data.tiempos.maximo) || 0;
    warningLimit = Number(data.tiempos.aviso) || 60;
    idleEndsAt = now + idleLimit * 1000;
    absoluteEndsAt = now + absoluteLimit * 1000;
    if (idleLimit > warningLimit && absoluteLimit > warningLimit) hideWarning();
    return true;
  }

  function requestSession(action) {
    if (requestInProgress) return Promise.resolve(false);
    requestInProgress = true;
    var body = new URLSearchParams();
    body.set('accion', action);
    return fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body: body.toString()
    }).then(function (response) {
      return response.json().then(function (data) {
        if (response.status === 401) {
          logout(data.motivo || 'sesion_expirada');
          return false;
        }
        return response.ok && applyState(data);
      });
    }).catch(function () {
      return false;
    }).finally(function () {
      requestInProgress = false;
    });
  }

  function registerActivity(force) {
    var now = Date.now();
    idleEndsAt = now + idleLimit * 1000;
    hideWarning();
    if (!force && now - lastActivitySent < 30000) return;
    lastActivitySent = now;
    requestSession('actividad');
  }

  function start() {
    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (eventName) {
      window.addEventListener(eventName, function () {
        registerActivity(false);
      }, {passive: true});
    });

    window.addEventListener('message', function (event) {
      if (event.origin === window.location.origin && event.data && event.data.type === 'mesa-profile-activity') {
        registerActivity(false);
      }
    });

    window.setInterval(function () {
      var now = Date.now();
      var absoluteRemaining = Math.ceil((absoluteEndsAt - now) / 1000);
      var idleRemaining = Math.ceil((idleEndsAt - now) / 1000);
      if (absoluteRemaining <= 0 || idleRemaining <= 0) {
        if (requestInProgress) return;
        requestSession('estado').then(function (valid) {
          if (!valid) logout(absoluteRemaining <= 0 ? 'tiempo_maximo' : 'inactividad');
        });
      } else if (absoluteRemaining <= warningLimit) {
        showWarning(absoluteRemaining, true);
      } else if (idleRemaining <= warningLimit) {
        showWarning(idleRemaining, false);
      }
    }, 1000);

    requestSession('estado');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, {once: true});
  } else {
    start();
  }
}());
