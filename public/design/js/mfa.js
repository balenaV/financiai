/* ============================================================
   financiaí — dois fatores e tooltip do gráfico de entradas/saídas.
   Complementa js/dashboard.js. Carregar depois dele.

   Reescrito a partir do pacote handoff/js/mfa.js: o protótipo de referência
   simulava a ativação inteiramente no cliente (secret e códigos fixos, sem
   chamada nenhuma ao servidor). Aqui os mesmos atributos data- e classes de
   estado são mantidos, mas cada etapa fala com as rotas settings/two-factor —
   QR e códigos de recuperação são sempre gerados no backend.
   ============================================================ */

(function () {
  'use strict';

  var card = document.querySelector('[data-mfa-card]');
  if (!card) return;

  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

  var badge = card.querySelector('[data-mfa-badge]');
  var texto = card.querySelector('[data-mfa-text]');
  var blocoOn = card.querySelector('[data-mfa-on]');
  var blocoOff = card.querySelector('[data-mfa-off]');

  var TEXTO_ON = 'Além da senha, o financiaí pede um código de 6 dígitos do seu app autenticador a cada novo login.';
  var TEXTO_OFF = 'Pede um código de 6 dígitos do seu app autenticador junto com a senha. Se alguém descobrir sua senha, ainda não entra.';

  var modalOn = document.querySelector('[data-modal="mfa-ativar"]');
  var modalSenha = document.querySelector('[data-modal="mfa-senha"]');
  if (!modalOn || !modalSenha) return;

  var urlStart = modalOn.getAttribute('data-mfa-url-start');
  var urlConfirm = modalOn.getAttribute('data-mfa-url-confirm');
  var urlDisable = modalSenha.getAttribute('data-mfa-url-disable');
  var urlRegen = modalSenha.getAttribute('data-mfa-url-regen');

  var passos = modalOn.querySelectorAll('[data-mfa-steps] .steps__item');
  var corpos = modalOn.querySelectorAll('[data-mfa-step]');
  var tituloModal = modalOn.querySelector('[data-mfa-modal-title]');
  var subModal = modalOn.querySelector('[data-mfa-modal-sub]');
  var qrWrap = modalOn.querySelector('[data-mfa-qr]');
  var campoChave = modalOn.querySelector('[data-mfa-key]');
  var notaChave = modalOn.querySelector('[data-mfa-key-note]');
  var campoCodigo = modalOn.querySelector('[data-mfa-code]');
  var erroCodigo = modalOn.querySelector('[data-mfa-code-error]');
  var erroCodigoTexto = modalOn.querySelector('[data-mfa-code-error-text]');
  var listaCodigos = modalOn.querySelector('[data-mfa-codes]');
  var caixaSalvou = modalOn.querySelector('[data-mfa-confirm-box]');
  var checkSalvou = modalOn.querySelector('[data-mfa-saved]');
  var btnVoltar = modalOn.querySelector('[data-mfa-back]');
  var btnCancelar = modalOn.querySelector('[data-mfa-cancel]');
  var btnAvancar = modalOn.querySelector('[data-mfa-next]');
  var rotuloAvancar = modalOn.querySelector('[data-mfa-next-label]');

  var campoSenha = modalSenha.querySelector('[data-mfa-password]');
  var erroSenha = modalSenha.querySelector('[data-mfa-password-error]');
  var tituloSenha = modalSenha.querySelector('[data-mfa-off-title]');
  var subSenha = modalSenha.querySelector('[data-mfa-off-sub]');
  var alertaSenha = modalSenha.querySelector('[data-mfa-off-alert]');
  var alertaTextoSenha = modalSenha.querySelector('[data-mfa-off-alert-text]');
  var btnConfirmarSenha = modalSenha.querySelector('[data-mfa-confirm-off]');
  var rotuloConfirmarSenha = modalSenha.querySelector('[data-mfa-off-confirm-label]');

  var estado = { etapa: 1, regerar: false, codigos: [] };
  var propositoSenha = 'disable';

  function abrir(modal) {
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function fechar(modal) {
    modal.hidden = true;
    document.body.style.overflow = '';
  }

  function post(url, body, isJson) {
    return fetch(url, {
      method: 'POST',
      headers: Object.assign(
        { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        isJson ? { 'Content-Type': 'application/json' } : {},
      ),
      body: isJson ? JSON.stringify(body) : body,
    });
  }

  function del(url, body) {
    return fetch(url, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
  }

  function pintarCard(ativo, dataAtivacao) {
    card.setAttribute('data-active', String(ativo));
    badge.textContent = ativo ? 'Ativa' : 'Desativada';
    texto.textContent = ativo ? TEXTO_ON : TEXTO_OFF;
    blocoOn.hidden = !ativo;
    blocoOff.hidden = ativo;
    if (ativo && dataAtivacao) {
      blocoOn.querySelector('[data-mfa-date]').textContent = dataAtivacao;
    }
  }

  function pintarEtapa() {
    var et = estado.etapa;

    passos.forEach(function (p) {
      var n = Number(p.dataset.step);
      p.classList.toggle('is-current', n === et);
      p.classList.toggle('is-done', n < et);
    });

    corpos.forEach(function (c) {
      c.hidden = Number(c.dataset.mfaStep) !== et;
    });

    tituloModal.textContent = estado.regerar
      ? 'Novos códigos de recuperação'
      : (et === 3 ? 'Guarde seus códigos' : 'Ativar dois fatores');

    subModal.textContent = estado.regerar
      ? 'Os códigos antigos deixam de funcionar assim que você concluir.'
      : (et === 1 ? 'Três etapas rápidas: escanear, confirmar e guardar os códigos de recuperação.'
        : et === 2 ? 'Confirme que o app está gerando os códigos certos.'
          : 'Sem o celular, são eles que devolvem seu acesso.');

    btnVoltar.hidden = et === 1 || estado.regerar;
    btnCancelar.hidden = et === 3;
    rotuloAvancar.textContent = et === 1 ? 'Já escaneei' : (et === 2 ? 'Verificar código' : 'Concluir');
    btnAvancar.querySelector('i').className = et === 3 ? 'fa-solid fa-check' : 'fa-solid fa-arrow-right';
    validarEtapa();
  }

  function validarEtapa() {
    var trava = estado.etapa === 3 && !checkSalvou.checked;
    btnAvancar.disabled = trava;
    btnAvancar.setAttribute('aria-disabled', String(trava));
  }

  function limparErroCodigo() {
    erroCodigo.hidden = true;
    campoCodigo.classList.remove('is-invalid');
  }

  function preencherCodigos(codigos) {
    estado.codigos = codigos;
    listaCodigos.textContent = '';
    codigos.forEach(function (c) {
      var el = document.createElement('code');
      el.textContent = c;
      listaCodigos.appendChild(el);
    });
  }

  card.querySelector('[data-mfa-open-on]').addEventListener('click', function () {
    post(urlStart, {}, false).then(function (r) { return r.json(); }).then(function (data) {
      estado.etapa = 1;
      estado.regerar = false;
      campoChave.textContent = data.key;
      qrWrap.innerHTML = data.qr;
      notaChave.textContent = 'A chave vale só para esta ativação.';
      campoCodigo.value = '';
      checkSalvou.checked = false;
      caixaSalvou.classList.remove('is-pending');
      limparErroCodigo();
      pintarEtapa();
      abrir(modalOn);
    });
  });

  card.querySelector('[data-mfa-regen]').addEventListener('click', function () {
    propositoSenha = 'regenerate';
    tituloSenha.textContent = 'Gerar novos códigos de recuperação?';
    subSenha.textContent = 'Os códigos antigos deixam de funcionar assim que os novos forem gerados.';
    alertaSenha.hidden = true;
    rotuloConfirmarSenha.textContent = 'Gerar códigos';
    campoSenha.value = '';
    campoSenha.classList.remove('is-invalid');
    erroSenha.hidden = true;
    abrir(modalSenha);
  });

  card.querySelector('[data-mfa-open-off]').addEventListener('click', function () {
    propositoSenha = 'disable';
    tituloSenha.textContent = 'Desativar dois fatores?';
    subSenha.textContent = 'Sua conta volta a ser protegida só pela senha.';
    alertaSenha.hidden = false;
    alertaTextoSenha.textContent = 'Seus códigos de recuperação atuais serão invalidados. Se ativar de novo depois, o app terá que ser reconfigurado.';
    rotuloConfirmarSenha.textContent = 'Desativar';
    campoSenha.value = '';
    campoSenha.classList.remove('is-invalid');
    erroSenha.hidden = true;
    abrir(modalSenha);
  });

  campoCodigo.addEventListener('input', function () {
    campoCodigo.value = campoCodigo.value.replace(/\D/g, '').slice(0, 6);
    limparErroCodigo();
  });

  checkSalvou.addEventListener('change', function () {
    caixaSalvou.classList.remove('is-pending');
    validarEtapa();
  });

  btnVoltar.addEventListener('click', function () {
    estado.etapa = 1;
    limparErroCodigo();
    pintarEtapa();
  });

  btnAvancar.addEventListener('click', function () {
    if (estado.etapa === 1) {
      estado.etapa = 2;
      pintarEtapa();
      campoCodigo.focus();
      return;
    }

    if (estado.etapa === 2) {
      var v = campoCodigo.value;
      if (v.length < 6) {
        erroCodigoTexto.textContent = 'Digite os 6 dígitos do código.';
        erroCodigo.hidden = false;
        campoCodigo.classList.add('is-invalid');
        return;
      }

      btnAvancar.disabled = true;
      post(urlConfirm, { code: v }, true).then(function (r) {
        return r.json().then(function (data) { return { ok: r.ok, data: data }; });
      }).then(function (res) {
        btnAvancar.disabled = false;
        if (!res.ok) {
          erroCodigoTexto.textContent = res.data.message || 'Código inválido ou já expirado.';
          erroCodigo.hidden = false;
          campoCodigo.classList.add('is-invalid');
          return;
        }
        limparErroCodigo();
        preencherCodigos(res.data.recovery_codes);
        estado.etapa = 3;
        pintarEtapa();
        pintarCard(true, res.data.confirmed_at);
      });
      return;
    }

    if (!checkSalvou.checked) {
      caixaSalvou.classList.add('is-pending');
      return;
    }

    estado.etapa = 1;
    estado.regerar = false;
    fechar(modalOn);
  });

  modalOn.querySelector('[data-mfa-copy-key]').addEventListener('click', function () {
    if (navigator.clipboard) navigator.clipboard.writeText(campoChave.textContent.trim());
    notaChave.textContent = 'Chave copiada.';
  });

  modalOn.querySelector('[data-mfa-copy-codes]').addEventListener('click', function (e) {
    if (navigator.clipboard) navigator.clipboard.writeText(estado.codigos.join('\n'));
    e.currentTarget.lastChild.textContent = 'Copiado';
  });

  modalOn.querySelector('[data-mfa-download]').addEventListener('click', function () {
    var blob = new Blob([estado.codigos.join('\n') + '\n'], { type: 'text/plain' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'financiai-codigos-recuperacao.txt';
    a.click();
    URL.revokeObjectURL(a.href);
  });

  btnConfirmarSenha.addEventListener('click', function () {
    var senha = campoSenha.value.trim();
    if (!senha) {
      erroSenha.hidden = false;
      campoSenha.classList.add('is-invalid');
      return;
    }

    btnConfirmarSenha.disabled = true;

    var pedido = propositoSenha === 'disable'
      ? del(urlDisable, { current_password: senha })
      : post(urlRegen, { current_password: senha }, true);

    pedido.then(function (r) {
      return r.json().then(function (data) { return { ok: r.ok, data: data }; });
    }).then(function (res) {
      btnConfirmarSenha.disabled = false;

      if (!res.ok) {
        erroSenha.hidden = false;
        erroSenha.textContent = res.data.errors && res.data.errors.current_password
          ? res.data.errors.current_password[0]
          : 'Senha incorreta. Tente de novo.';
        campoSenha.classList.add('is-invalid');
        return;
      }

      fechar(modalSenha);

      if (propositoSenha === 'disable') {
        pintarCard(false, null);
        return;
      }

      estado.regerar = true;
      estado.etapa = 3;
      checkSalvou.checked = false;
      caixaSalvou.classList.remove('is-pending');
      preencherCodigos(res.data.recovery_codes);
      pintarEtapa();
      abrir(modalOn);
    });
  });

  [modalOn, modalSenha].forEach(function (m) {
    m.querySelectorAll('[data-modal-close]').forEach(function (b) {
      b.addEventListener('click', function () { fechar(m); });
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!modalOn.hidden) fechar(modalOn);
    if (!modalSenha.hidden) fechar(modalSenha);
  });

  pintarEtapa();
})();

/* ---------------- Tooltip do gráfico de entradas e saídas ---------------- */

(function () {
  'use strict';

  var colunas = document.querySelectorAll('[data-chart-col]');
  if (!colunas.length) return;

  colunas.forEach(function (col) {
    col.querySelectorAll('.chart-bar').forEach(function (barra) {
      barra.addEventListener('mouseenter', function () {
        col.setAttribute('data-hover', 'true');
        col.querySelectorAll('.chart-bar').forEach(function (b) {
          b.setAttribute('data-dim', String(b !== barra));
        });
        col.querySelectorAll('.chart-tip__row').forEach(function (r) {
          r.classList.toggle('is-strong', r.dataset.kind === barra.dataset.kind);
        });
      });
      barra.addEventListener('mouseleave', function () {
        col.setAttribute('data-hover', 'false');
        col.querySelectorAll('.chart-bar').forEach(function (b) { b.setAttribute('data-dim', 'false'); });
        col.querySelectorAll('.chart-tip__row').forEach(function (r) { r.classList.remove('is-strong'); });
      });
    });
  });
})();
