(function () {
  'use strict';

  var endpoint = 'sesionActividad.php';
  var limiteInactividad = 300;
  var limiteMaximo = 1800;
  var aviso = 60;
  var finInactividad = Date.now() + limiteInactividad * 1000;
  var finMaximo = Date.now() + limiteMaximo * 1000;
  var ultimoEnvio = 0;
  var consultando = false;
  var modal = null;
  var contador = null;

  function urlSalida(motivo) {
    return 'logout.php?motivo=' + encodeURIComponent(motivo);
  }

  function salir(motivo) {
    window.location.replace(urlSalida(motivo));
  }

  function crearModal() {
    if (modal) return;

    modal = document.createElement('div');
    modal.id = 'mesa-session-warning';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'mesa-session-title');
    modal.innerHTML =
      '<div class="mesa-session-card">' +
        '<div class="mesa-session-icon" aria-hidden="true">!</div>' +
        '<h2 id="mesa-session-title">La sesión está por cerrarse</h2>' +
        '<p id="mesa-session-message"></p>' +
        '<strong id="mesa-session-countdown"></strong>' +
        '<button type="button" id="mesa-session-continue">Continuar trabajando</button>' +
      '</div>';

    var style = document.createElement('style');
    style.textContent =
      '#mesa-session-warning{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;padding:20px;background:rgba(16,42,67,.58);backdrop-filter:blur(3px)}' +
      '#mesa-session-warning[hidden]{display:none}' +
      '.mesa-session-card{width:min(410px,100%);padding:28px;border:1px solid #dce6f1;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(16,42,67,.28);color:#243b53;text-align:center;font-family:Inter,"Segoe UI",Arial,sans-serif}' +
      '.mesa-session-icon{display:grid;width:50px;height:50px;margin:0 auto 14px;place-items:center;border-radius:14px;background:#fff4e5;color:#b54708;font-size:24px;font-weight:800}' +
      '.mesa-session-card h2{margin:0 0 8px;color:#102a43;font-size:21px}' +
      '.mesa-session-card p{margin:0;color:#627d98;font-size:14px;line-height:1.5}' +
      '#mesa-session-countdown{display:block;margin:14px 0;color:#b42318;font-size:28px}' +
      '#mesa-session-continue{min-height:42px;padding:10px 17px;border:0;border-radius:10px;background:#0f6fec;color:#fff;cursor:pointer;font-weight:750}' +
      '#mesa-session-continue[hidden]{display:none}';

    document.head.appendChild(style);
    document.body.appendChild(modal);
    modal.hidden = true;
    contador = modal.querySelector('#mesa-session-countdown');
    modal.querySelector('#mesa-session-continue').addEventListener('click', function () {
      registrarActividad(true);
    });
  }

  function ocultarAviso() {
    if (modal) modal.hidden = true;
  }

  function mostrarAviso(segundos, absoluto) {
    crearModal();
    modal.hidden = false;
    modal.querySelector('#mesa-session-message').textContent = absoluto
      ? 'Por seguridad, toda sesión debe finalizar al cumplir 30 minutos.'
      : 'No se ha detectado interacción. Use el botón para mantener la sesión activa.';
    modal.querySelector('#mesa-session-continue').hidden = absoluto;
    contador.textContent = Math.max(0, segundos) + ' s';
  }

  function aplicarEstado(datos) {
    if (!datos || !datos.ok || !datos.tiempos) return false;
    var ahora = Date.now();
    limiteInactividad = Number(datos.tiempos.inactividad) || 0;
    limiteMaximo = Number(datos.tiempos.maximo) || 0;
    aviso = Number(datos.tiempos.aviso) || 60;
    finInactividad = ahora + limiteInactividad * 1000;
    finMaximo = ahora + limiteMaximo * 1000;
    if (limiteInactividad > aviso && limiteMaximo > aviso) ocultarAviso();
    return true;
  }

  function consultar(accion) {
    if (consultando) return Promise.resolve(false);
    consultando = true;
    var cuerpo = new URLSearchParams();
    cuerpo.set('accion', accion);

    return fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body: cuerpo.toString()
    }).then(function (respuesta) {
      return respuesta.json().then(function (datos) {
        if (respuesta.status === 401) {
          salir(datos.motivo || 'sesion_expirada');
          return false;
        }
        return respuesta.ok && aplicarEstado(datos);
      });
    }).catch(function () {
      return false;
    }).finally(function () {
      consultando = false;
    });
  }

  function registrarActividad(forzar) {
    var ahora = Date.now();
    finInactividad = ahora + 300000;
    ocultarAviso();

    if (!forzar && ahora - ultimoEnvio < 30000) return;
    ultimoEnvio = ahora;
    consultar('actividad');
  }

  ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (evento) {
    window.addEventListener(evento, function () {
      registrarActividad(false);
    }, {passive: true});
  });

  window.setInterval(function () {
    var ahora = Date.now();
    var restanteMaximo = Math.ceil((finMaximo - ahora) / 1000);
    var restanteInactividad = Math.ceil((finInactividad - ahora) / 1000);

    if (restanteMaximo <= 0 || restanteInactividad <= 0) {
      if (consultando) return;
      consultar('estado').then(function (vigente) {
        if (!vigente) {
          salir(restanteMaximo <= 0 ? 'tiempo_maximo' : 'inactividad');
        }
      });
      return;
    }

    if (restanteMaximo <= aviso) {
      mostrarAviso(restanteMaximo, true);
    } else if (restanteInactividad <= aviso) {
      mostrarAviso(restanteInactividad, false);
    }
  }, 1000);

  consultar('estado');
}());
