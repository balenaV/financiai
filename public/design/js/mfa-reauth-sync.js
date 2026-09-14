/**
 * Reautenticação antes de iniciar a ativação do MFA.
 *
 * Complementa mfa.js sem editá-lo (ele é cópia do handoff de design, mesma
 * convenção de form-widgets-sync.js e auth-url-sync.js). Faz duas coisas:
 *
 *  1. intercepta o clique em "Ativar" na fase de captura, confirma a
 *     identidade primeiro e só então deixa o fluxo original seguir;
 *  2. observa qualquer resposta 423 das demais chamadas do MFA (desativar,
 *     regerar códigos) e leva ao re-consentimento no provedor social.
 */
(function () {
  'use strict';

  var card = document.querySelector('[data-mfa-card]');
  if (!card) return;

  var modal = document.querySelector('[data-modal="mfa-reautenticar"]');
  var botaoAtivar = card.querySelector('[data-mfa-open-on]');
  var urlReauth = card.getAttribute('data-mfa-url-reauth');
  var urlReauthSocial = card.getAttribute('data-mfa-url-reauth-social');
  var temSenha = card.getAttribute('data-mfa-has-password') === 'true';
  var csrf = document.querySelector('meta[name="csrf-token"]');
  csrf = csrf ? csrf.getAttribute('content') : '';

  // Marcado depois de uma confirmação bem-sucedida, para o clique reenviado
  // atravessar sem cair de novo nesta interceptação.
  var confirmado = false;

  function abrir(el) { el.hidden = false; document.body.style.overflow = 'hidden'; }
  function fechar(el) { el.hidden = true; document.body.style.overflow = ''; }

  /* ---------- 1. intercepta o "Ativar" ---------- */

  if (botaoAtivar && modal) {
    var campo = modal.querySelector('[data-mfa-reauth-password]');
    var erro = modal.querySelector('[data-mfa-reauth-error]');
    var confirmar = modal.querySelector('[data-mfa-reauth-confirm]');
    var socialWrap = modal.querySelector('[data-mfa-reauth-social-wrap]');
    var socialLink = modal.querySelector('[data-mfa-reauth-social-link]');

    // Alternativa à senha para quem tem provedor vinculado — inclusive contas
    // antigas de login social, que carregam uma senha aleatória e nunca
    // conseguiriam passar pelo campo acima.
    if (urlReauthSocial && socialWrap && socialLink) {
      var rotulo = card.getAttribute('data-mfa-reauth-provider-label') || 'provedor';
      socialLink.textContent = 'Não sei minha senha — confirmar com ' + rotulo;
      socialLink.setAttribute('href', urlReauthSocial);
      socialWrap.hidden = false;
    }

    botaoAtivar.addEventListener('click', function (e) {
      if (confirmado) return;

      e.preventDefault();
      e.stopImmediatePropagation();

      // Sem senha utilizável (conta criada por login social): o único jeito de
      // provar identidade é refazer o consentimento no provedor.
      if (!temSenha) {
        if (urlReauthSocial) window.location.href = urlReauthSocial;
        return;
      }

      campo.value = '';
      campo.classList.remove('is-invalid');
      erro.hidden = true;
      abrir(modal);
      campo.focus();
    }, true);

    modal.querySelectorAll('[data-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { fechar(modal); });
    });

    confirmar.addEventListener('click', function () {
      erro.hidden = true;
      campo.classList.remove('is-invalid');
      confirmar.disabled = true;

      fetch(urlReauth, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ current_password: campo.value }),
      })
        .then(function (r) {
          return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
        })
        .then(function (res) {
          confirmar.disabled = false;

          if (res.status === 423 && res.data.reauthentication && res.data.reauthentication.url) {
            window.location.href = res.data.reauthentication.url;
            return;
          }

          if (!res.ok) {
            campo.classList.add('is-invalid');
            erro.textContent = (res.data.errors && res.data.errors.current_password)
              ? res.data.errors.current_password[0]
              : (res.data.message || 'Senha incorreta. Tente de novo.');
            erro.hidden = false;
            return;
          }

          // Identidade confirmada: fecha e reenvia o clique original, que
          // agora atravessa direto para o fluxo do mfa.js.
          fechar(modal);
          confirmado = true;
          botaoAtivar.click();
        })
        .catch(function () {
          confirmar.disabled = false;
          erro.textContent = 'Não foi possível confirmar agora. Tente de novo.';
          erro.hidden = false;
        });
    });

    campo.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); confirmar.click(); }
    });
  }

  /* ---------- 2. 423 em qualquer outra chamada do MFA ---------- */

  var fetchOriginal = window.fetch;

  window.fetch = function () {
    return fetchOriginal.apply(this, arguments).then(function (response) {
      if (response.status !== 423) return response;

      // Clona para não consumir o corpo que o chamador ainda vai ler.
      response.clone().json().then(function (data) {
        if (data && data.reauthentication && data.reauthentication.url) {
          window.location.href = data.reauthentication.url;
        }
      }).catch(function () {});

      return response;
    });
  };
})();
