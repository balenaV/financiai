/* financiaí — extensão do backend sobre o pacote de design.
   Alterna entre código do app autenticador e código de recuperação no
   desafio de login (auth.login, mode=desafio). O pacote de referência é uma
   SPA sem submit real; aqui o campo visível precisa carregar name="code" e
   o flag oculto "recovery" precisa refletir qual modo está ativo antes do
   POST para two-factor.verify. Arquivo novo: não edita mfa.js. */

(function () {
  var form = document.querySelector('[data-mfa-form]');
  if (!form) return;

  var campoApp = form.querySelector('[data-mfa-field="app"]');
  var campoRecuperacao = form.querySelector('[data-mfa-field="recuperacao"]');
  var inputApp = campoApp.querySelector('[data-mfa-input="app"]');
  var inputRecuperacao = campoRecuperacao.querySelector('[data-mfa-input="recuperacao"]');
  var flag = form.querySelector('[data-mfa-recovery-flag]');
  var link = form.querySelector('[data-mfa-switch]');

  function usarRecuperacao(ativo) {
    campoApp.hidden = ativo;
    campoRecuperacao.hidden = !ativo;
    flag.value = ativo ? '1' : '0';
    inputApp.name = ativo ? '' : 'code';
    inputRecuperacao.name = ativo ? 'code' : '';
    link.textContent = ativo ? 'Usar o código do app' : 'Usar um código de recuperação';
    (ativo ? inputRecuperacao : inputApp).focus();
  }

  link.addEventListener('click', function (e) {
    e.preventDefault();
    usarRecuperacao(campoApp.hidden);
  });

  inputApp.addEventListener('input', function () {
    inputApp.value = inputApp.value.replace(/\D/g, '').slice(0, 6);
  });
})();
