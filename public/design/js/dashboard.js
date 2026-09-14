/* financiaí — comportamento do dashboard.
   Sem dependências além de theme.js. */

(function () {

  /* ---------- Barra lateral compacta ---------- */
  var sidebar = document.querySelector('[data-sidebar]');
  if (sidebar) {
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (b) {
      b.addEventListener('click', function () {
        var compacta = sidebar.getAttribute('data-collapsed') === 'true';
        sidebar.setAttribute('data-collapsed', String(!compacta));
      });
    });
  }

  /* ---------- Navegação por abas ---------- */
  var META = {
    visao: ['Visão geral', 'Seu panorama financeiro, sem ruído.'],
    config: ['Configurações', 'Perfil, preferências e segurança da sua conta.'],
    novaConta: ['Nova conta manual', 'Uma conta que você controla por aqui, sem extrato.'],
    transacoes: ['Transações', 'Tudo o que entrou e saiu, com busca e filtros.'],
    assinaturas: ['Assinaturas', 'O que se repete todo mês, mesmo sem você lembrar.'],
    planejamento: ['Planejamento', 'O que você espera receber e pagar adiante.'],
    contas: ['Contas', 'Saldos calculados a partir das suas movimentações.'],
    cartoes: ['Cartões', 'Faturas e limite, em um lugar só.'],
    chat: ['Capí', 'Seu assistente financeiro.'],
    agentes: ['Agentes', 'Personagens temáticos para cada área da sua vida.'],
    dividas: ['Dívidas', 'Parcelas e faturas que ainda vão pesar no bolso.'],
    investimentos: ['Investimentos', 'Onde o seu dinheiro está rendendo.'],
    orcamentos: ['Orçamentos', 'Quanto você planejou gastar em cada categoria.'],
    metas: ['Metas', 'O que você está guardando para conquistar.'],
    relatorios: ['Relatórios', 'Seus números, mês a mês.'],
    categorias: ['Categorias', 'Como você organiza receitas e despesas.'],
    previsao: ['Previsão', 'Os próximos meses, projetados a partir do que você já sabe.']
  };

  var COM_PERIODO = ['visao', 'transacoes', 'assinaturas'];

  var titulo = document.querySelector('[data-page-title]');
  var subtitulo = document.querySelector('[data-page-sub]');
  var periodo = document.querySelector('[data-period]');

  function abrir(aba) {
    document.querySelectorAll('[data-page]').forEach(function (p) {
      p.hidden = p.dataset.page !== aba;
    });
    document.querySelectorAll('[data-goto]').forEach(function (b) {
      if (b.dataset.goto === aba) b.setAttribute('aria-current', 'page');
      else b.removeAttribute('aria-current');
    });
    var meta = META[aba] || ['', ''];
    if (titulo) titulo.textContent = meta[0];
    if (subtitulo) subtitulo.textContent = meta[1];
    if (periodo) periodo.hidden = COM_PERIODO.indexOf(aba) === -1;
    animarEntrada();
  }

  document.querySelectorAll('[data-goto]').forEach(function (b) {
    b.addEventListener('click', function () { abrir(b.dataset.goto); });
  });

  /* ---------- Período ---------- */
  var grupoPeriodo = document.querySelector('[data-period-group]');
  if (grupoPeriodo) {
    var intervalo = document.querySelector('[data-date-range]');
    var resumo = document.querySelector('[data-period-summary]');
    var RESUMOS = { '30d': '5 jul – 4 ago 2026', '12m': 'set 2025 – ago 2026' };

    grupoPeriodo.querySelectorAll('[data-period-value]').forEach(function (b) {
      b.addEventListener('click', function () {
        var v = b.dataset.periodValue;
        grupoPeriodo.querySelectorAll('[data-period-value]').forEach(function (o) {
          o.setAttribute('aria-pressed', String(o === b));
        });
        if (intervalo) intervalo.hidden = v !== 'intervalo';
        if (resumo) {
          resumo.hidden = v === 'intervalo';
          if (RESUMOS[v]) resumo.textContent = RESUMOS[v];
        }
      });
    });
  }

  /* ---------- Ocultar valores ---------- */
  var btnOlho = document.querySelector('[data-toggle-money]');
  if (btnOlho) {
    /* Parte de aria-pressed (renderizado pelo servidor a partir da
       preferência salva) em vez de sempre false — senão, para quem já
       salvou "ocultar por padrão", o primeiro clique capturava o texto
       já mascarado como "original" e a tela nunca mais revelava os valores
       reais. Quando já carrega oculto, quem mascara/revela de verdade é o
       reload feito em dashboard-settings-sync.js (só ele tem o valor real). */
    var oculto = btnOlho.getAttribute('aria-pressed') === 'true';
    if (!btnOlho.hasAttribute('data-money-reload-on-reveal')) {
      btnOlho.addEventListener('click', function () {
        oculto = !oculto;
        document.querySelectorAll('[data-money]').forEach(function (el) {
          if (!el.dataset.moneyOriginal) el.dataset.moneyOriginal = el.textContent;
          el.textContent = oculto ? '••••••' : el.dataset.moneyOriginal;
        });
        var i = btnOlho.querySelector('i');
        if (i) i.className = oculto ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
        btnOlho.setAttribute('aria-pressed', String(oculto));
      });
    }
  }

  /* ---------- Notificações ---------- */
  var sino = document.querySelector('[data-notif-toggle]');
  var painel = document.querySelector('[data-notif-panel]');
  if (sino && painel) {
    sino.addEventListener('click', function (e) {
      e.stopPropagation();
      painel.hidden = !painel.hidden;
      sino.setAttribute('aria-expanded', String(!painel.hidden));
    });
    document.addEventListener('click', function (e) {
      if (painel.hidden) return;
      if (painel.contains(e.target)) return;
      painel.hidden = true;
      sino.setAttribute('aria-expanded', 'false');
    });
    var marcar = document.querySelector('[data-notif-read]');
    if (marcar) {
      marcar.addEventListener('click', function () {
        painel.hidden = true;
        sino.setAttribute('aria-expanded', 'false');
        var ponto = sino.querySelector('.icon-btn__dot');
        if (ponto) ponto.hidden = true;
      });
    }
  }


  /* ---------- Dropdowns do sistema ---------- */
  function fecharDropdowns(exceto) {
    document.querySelectorAll('[data-dropdown]').forEach(function (d) {
      if (d === exceto) return;
      d.setAttribute('data-open', 'false');
      var menu = d.querySelector('.dropdown__menu');
      var btn = d.querySelector('[data-dropdown-btn]');
      if (menu) menu.hidden = true;
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }

  /* Delegado (em vez de attachar por elemento no load) porque alguns
     dropdowns — ex.: categoria de cada linha na revisão de importação —
     são clonados dinamicamente por JS depois do carregamento da página. */
  document.addEventListener('click', function (e) {
    var opt = e.target.closest('.dropdown__opt');
    if (opt) {
      var dropOpt = opt.closest('[data-dropdown]');
      var menuOpt = dropOpt && dropOpt.querySelector('.dropdown__menu');
      if (!dropOpt || !menuOpt) return;
      var btnOpt = dropOpt.querySelector('[data-dropdown-btn]');
      var rotuloOpt = dropOpt.querySelector('[data-dropdown-label]');
      menuOpt.querySelectorAll('.dropdown__opt').forEach(function (o) {
        o.classList.toggle('is-selected', o === opt);
      });
      if (rotuloOpt) rotuloOpt.textContent = opt.textContent.trim();
      dropOpt.setAttribute('data-open', 'false');
      menuOpt.hidden = true;
      if (btnOpt) btnOpt.setAttribute('aria-expanded', 'false');
      return;
    }

    var btn = e.target.closest('[data-dropdown-btn]');
    if (btn) {
      var drop = btn.closest('[data-dropdown]');
      var menu = drop && drop.querySelector('.dropdown__menu');
      if (drop && menu) {
        var aberto = drop.getAttribute('data-open') === 'true';
        fecharDropdowns(drop);
        drop.setAttribute('data-open', String(!aberto));
        menu.hidden = aberto;
        btn.setAttribute('aria-expanded', String(!aberto));
        // Dropdowns dentro de um container com overflow:hidden (ex.: a lista
        // de linhas na revisão de importação) teriam o menu cortado — nesses
        // casos ele é posicionado em coordenadas fixas, fora do fluxo do card.
        if (!aberto && drop.hasAttribute('data-dropdown-fixed')) {
          var rect = btn.getBoundingClientRect();
          var alturaMenu = menu.getBoundingClientRect().height;
          var espacoAbaixo = window.innerHeight - rect.bottom;
          menu.style.position = 'fixed';
          menu.style.right = (window.innerWidth - rect.right) + 'px';
          menu.style.left = 'auto';
          // Linhas perto do fim da tela (comum em listas longas, ex.: revisão
          // de importação) não têm espaço embaixo — sem isso o menu abria fora
          // da viewport e ficava efetivamente invisível até rolar a página.
          if (espacoAbaixo < alturaMenu + 6 && rect.top > alturaMenu + 6) {
            menu.style.top = 'auto';
            menu.style.bottom = (window.innerHeight - rect.top + 6) + 'px';
          } else {
            menu.style.bottom = 'auto';
            menu.style.top = (rect.bottom + 6) + 'px';
          }
        }
      }
      return;
    }

    fecharDropdowns(null);
  });

  /* Dropdowns com data-dropdown-fixed são posicionados em coordenadas fixas
     no momento em que abrem (ver acima) e não acompanham scroll/resize —
     sem fechar aqui, o menu fica "flutuando" sobre outra linha enquanto a
     página rola, com o risco real de escolher a categoria errada. Escuta em
     fase de captura porque scroll não borbulha (importa se algum dia esse
     dropdown viver dentro de um container com scroll próprio). */
  function fecharDropdownsFixos() {
    document.querySelectorAll('[data-dropdown-fixed][data-open="true"]').forEach(function (d) {
      d.setAttribute('data-open', 'false');
      var menu = d.querySelector('.dropdown__menu');
      var btn = d.querySelector('[data-dropdown-btn]');
      if (menu) menu.hidden = true;
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }
  window.addEventListener('scroll', fecharDropdownsFixos, { capture: true, passive: true });
  window.addEventListener('resize', fecharDropdownsFixos);

  /* ---------- Modais (cartão / assinatura) ---------- */
  function abrirModal(nome) {
    var modal = document.querySelector('[data-modal="' + nome + '"]');
    if (!modal) return;
    modal.hidden = false;
    var foco = modal.querySelector('input, button');
    if (foco) foco.focus();
  }

  function fecharModais() {
    document.querySelectorAll('[data-modal]').forEach(function (m) { m.hidden = true; });
  }

  document.querySelectorAll('[data-modal-open]').forEach(function (b) {
    b.addEventListener('click', function () { abrirModal(b.dataset.modalOpen); });
  });

  document.querySelectorAll('[data-modal-close]').forEach(function (b) {
    b.addEventListener('click', fecharModais);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fecharModais();
  });

  /* ---------- Grupos de chips (bandeira, ciclo) ---------- */
  document.querySelectorAll('[data-chip-group]').forEach(function (grupo) {
    grupo.querySelectorAll('.chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        grupo.querySelectorAll('.chip').forEach(function (c) {
          c.classList.toggle('is-selected', c === chip);
        });
      });
    });
  });

  /* ---------- Nova conta manual: seleções + prévia ---------- */
  var previaIcone = document.querySelector('[data-preview-icon]');
  var previaNome = document.querySelector('[data-preview-name]');
  var previaTipo = document.querySelector('[data-preview-type]');
  var previaSaldo = document.querySelector('[data-preview-balance]');
  var previaNota = document.querySelector('[data-preview-note]');
  var campoNome = document.querySelector('[data-account-name]');
  var campoBanco = document.querySelector('[data-account-bank]');
  var campoSaldo = document.querySelector('[data-account-balance]');
  var campoData = document.querySelector('[data-account-date]');
  var grupoTipo = document.querySelector('[data-account-type]');
  var grupoCores = document.querySelector('[data-account-colors]');
  var switchTotal = document.querySelector('[data-account-total]');

  function atualizarPrevia() {
    if (!previaNome) return;
    var tipoSel = grupoTipo && grupoTipo.querySelector('.option-tile.is-selected');
    var tipoNome = tipoSel ? tipoSel.textContent.trim() : 'Conta corrente';
    var banco = campoBanco && campoBanco.value.trim();
    var num = parseFloat(String(campoSaldo ? campoSaldo.value : '').replace(/\./g, '').replace(',', '.'));
    var partes = String(campoData ? campoData.value : '').split('-');
    var somaTotal = !switchTotal || switchTotal.classList.contains('is-on');

    previaNome.textContent = (campoNome && campoNome.value.trim()) || 'Nome da conta';
    previaTipo.textContent = banco ? tipoNome + ' · ' + banco : tipoNome;
    previaSaldo.textContent = 'R$ ' + (isNaN(num) ? 0 : num).toLocaleString('pt-BR', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
    if (partes.length === 3) {
      previaNota.textContent = 'Saldo informado em ' + partes[2] + '/' + partes[1] + '/' + partes[0] +
        (somaTotal ? '' : ' · fora do saldo total');
    }
  }

  [campoNome, campoBanco, campoSaldo, campoData].forEach(function (el) {
    if (el) el.addEventListener('input', atualizarPrevia);
  });

  if (grupoTipo) {
    grupoTipo.querySelectorAll('.option-tile').forEach(function (tile) {
      tile.addEventListener('click', function () {
        grupoTipo.querySelectorAll('.option-tile').forEach(function (t) {
          t.classList.toggle('is-selected', t === tile);
        });
        if (previaIcone) previaIcone.innerHTML = '<i class="' + tile.dataset.icon + '"></i>';
        atualizarPrevia();
      });
    });
  }

  if (grupoCores) {
    grupoCores.querySelectorAll('.swatch').forEach(function (sw) {
      sw.addEventListener('click', function () {
        grupoCores.querySelectorAll('.swatch').forEach(function (o) {
          o.classList.toggle('is-selected', o === sw);
        });
        if (previaIcone) previaIcone.style.background = sw.dataset.value;
      });
    });
  }

  if (switchTotal) {
    switchTotal.addEventListener('click', function () {
      var ligado = switchTotal.classList.toggle('is-on');
      switchTotal.setAttribute('aria-checked', String(ligado));
      atualizarPrevia();
    });
  }

  atualizarPrevia();


  /* ---------- Date picker do sistema ---------- */
  var MESES_DP = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho',
    'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
  var SEMANA_DP = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

  function fecharDatepickers(exceto) {
    document.querySelectorAll('[data-datepicker]').forEach(function (dp) {
      if (dp === exceto) return;
      dp.setAttribute('data-open', 'false');
      var painel = dp.querySelector('[data-datepicker-panel]');
      var btn = dp.querySelector('[data-datepicker-btn]');
      if (painel) painel.hidden = true;
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }

  document.querySelectorAll('[data-datepicker]').forEach(function (dp) {
    var btn = dp.querySelector('[data-datepicker-btn]');
    var painel = dp.querySelector('[data-datepicker-panel]');
    var rotulo = dp.querySelector('[data-datepicker-label]');
    var oculto = dp.parentNode && dp.parentNode.querySelector('input[type="hidden"]');
    if (!btn || !painel) return;

    var partes = (dp.dataset.value || '').split('-');
    var sel = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));
    var visto = new Date(sel.getFullYear(), sel.getMonth(), 1);

    function iso(d) {
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') +
        '-' + String(d.getDate()).padStart(2, '0');
    }

    function br(d) {
      return String(d.getDate()).padStart(2, '0') + '/' +
        String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
    }

    function desenhar() {
      var inicio = visto.getDay();
      var total = new Date(visto.getFullYear(), visto.getMonth() + 1, 0).getDate();
      var html = '<div class="datepicker__head">' +
        '<button class="datepicker__nav" type="button" data-dp-prev aria-label="Mês anterior"><i class="fa-solid fa-chevron-left"></i></button>' +
        '<span class="datepicker__month">' + MESES_DP[visto.getMonth()] + ' ' + visto.getFullYear() + '</span>' +
        '<button class="datepicker__nav" type="button" data-dp-next aria-label="Próximo mês"><i class="fa-solid fa-chevron-right"></i></button>' +
        '</div><div class="datepicker__week">';
      SEMANA_DP.forEach(function (s) { html += '<span>' + s + '</span>'; });
      html += '</div><div class="datepicker__grid">';
      for (var i = 0; i < 42; i++) {
        var n = i - inicio + 1;
        if (n < 1 || n > total) {
          html += '<button class="datepicker__day datepicker__day--empty" type="button" tabindex="-1"></button>';
          continue;
        }
        var escolhido = n === sel.getDate() && visto.getMonth() === sel.getMonth() &&
          visto.getFullYear() === sel.getFullYear();
        html += '<button class="datepicker__day' + (escolhido ? ' is-selected' : '') +
          '" type="button" data-dp-day="' + n + '">' + n + '</button>';
      }
      painel.innerHTML = html + '</div>';
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var aberto = dp.getAttribute('data-open') === 'true';
      fecharDropdowns(null);
      fecharDatepickers(dp);
      if (!aberto) desenhar();
      dp.setAttribute('data-open', String(!aberto));
      painel.hidden = aberto;
      btn.setAttribute('aria-expanded', String(!aberto));
    });

    /* Reprograma um datepicker já montado com um novo valor — usado ao
       reabrir modais (ex.: editar fatura) preenchidos com dado de outra
       fatura/registro. Sem isso o clique num dia continuaria partindo do
       "sel"/"visto" da primeira renderização da página. */
    dp.__definir = function (isoValue) {
      var p = (isoValue || '').split('-');
      if (p.length === 3) {
        sel = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
        visto = new Date(sel.getFullYear(), sel.getMonth(), 1);
        dp.dataset.value = isoValue;
        if (rotulo) rotulo.textContent = br(sel);
      } else {
        dp.dataset.value = '';
        if (rotulo) rotulo.textContent = '';
      }
      if (oculto) oculto.value = isoValue || '';
      if (!painel.hidden) desenhar();
    };

    painel.addEventListener('click', function (e) {
      e.stopPropagation();
      var alvo = e.target.closest('button');
      if (!alvo) return;
      if (alvo.hasAttribute('data-dp-prev')) {
        visto = new Date(visto.getFullYear(), visto.getMonth() - 1, 1);
        desenhar();
      } else if (alvo.hasAttribute('data-dp-next')) {
        visto = new Date(visto.getFullYear(), visto.getMonth() + 1, 1);
        desenhar();
      } else if (alvo.hasAttribute('data-dp-day')) {
        sel = new Date(visto.getFullYear(), visto.getMonth(), Number(alvo.dataset.dpDay));
        dp.dataset.value = iso(sel);
        if (rotulo) rotulo.textContent = br(sel);
        if (oculto) {
          oculto.value = iso(sel);
          oculto.dispatchEvent(new Event('input', { bubbles: true }));
        }
        dp.setAttribute('data-open', 'false');
        painel.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  });

  document.addEventListener('click', function () { fecharDatepickers(null); });


  /* ---------- Cartão do usuário ---------- */
  var userMenu = document.querySelector('[data-user-menu]');
  if (userMenu) {
    var userBtn = userMenu.querySelector('[data-user-toggle]');
    var userCard = userMenu.querySelector('[data-user-card]');

    userBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var aberto = userMenu.getAttribute('data-open') === 'true';
      userMenu.setAttribute('data-open', String(!aberto));
      userCard.hidden = aberto;
      userBtn.setAttribute('aria-expanded', String(!aberto));
    });

    userCard.addEventListener('click', function (e) { e.stopPropagation(); });

    document.addEventListener('click', function () {
      userMenu.setAttribute('data-open', 'false');
      userCard.hidden = true;
      userBtn.setAttribute('aria-expanded', 'false');
    });
  }

  /* ---------- Tema pelas preferências ---------- */
  document.querySelectorAll('[data-theme-set]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.documentElement.setAttribute('data-theme', b.dataset.themeSet);
      try { localStorage.setItem('financiai-theme', b.dataset.themeSet); } catch (err) {}
    });
  });

  /* ---------- Animação de entrada dos cartões ---------- */
  var reduz = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function animarEntrada() {
    if (reduz) return;
    var pagina = document.querySelector('[data-page]:not([hidden])');
    if (!pagina) return;

    pagina.querySelectorAll('[data-enter]').forEach(function (el, i) {
      el.animate(
        [{ opacity: 0, transform: 'translateY(14px)' }, { opacity: 1, transform: 'none' }],
        { duration: 520, delay: i * 45, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'backwards' }
      );
    });

    pagina.querySelectorAll('.bar').forEach(function (el, i) {
      el.animate([{ transform: 'scaleY(0)' }, { transform: 'scaleY(1)' }],
        { duration: 700, delay: i * 40, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'backwards' });
    });

    pagina.querySelectorAll('.track__fill').forEach(function (el, i) {
      el.animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }],
        { duration: 640, delay: i * 55, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'backwards' });
    });
  }

  animarEntrada();
})();


/* ---- Upload de foto de perfil (Configurações › Perfil) ---- */
(function () {
  var zona = document.getElementById('avatarUpload');
  if (!zona) return;
  var input = document.getElementById('avatarInput');
  var img = document.getElementById('avatarPreview');
  var iniciais = zona.querySelector('.avatar-upload__initials');
  var nome = document.getElementById('avatarName');
  var erro = document.getElementById('avatarError');
  var remover = document.getElementById('avatarRemove');

  function falhar(msg) { erro.textContent = msg; erro.hidden = false; }

  function ler(arquivo) {
    if (!arquivo) return;
    if (!/^image\/(png|jpeg)$/.test(arquivo.type)) return falhar('Use um arquivo JPG ou PNG.');
    if (arquivo.size > 5 * 1024 * 1024) return falhar('A imagem passa de 5 MB. Escolha uma menor.');
    var leitor = new FileReader();
    leitor.onload = function () {
      img.src = leitor.result; img.hidden = false;
      iniciais.hidden = true; remover.hidden = false;
      nome.textContent = arquivo.name; erro.hidden = true;
    };
    leitor.readAsDataURL(arquivo);
  }

  document.getElementById('avatarPick').addEventListener('click', function () { input.click(); });
  input.addEventListener('change', function (e) { ler(e.target.files && e.target.files[0]); });
  remover.addEventListener('click', function () {
    input.value = ''; img.removeAttribute('src'); img.hidden = true;
    iniciais.hidden = false; remover.hidden = true;
    nome.textContent = 'Nenhuma foto enviada'; erro.hidden = true;
  });
  zona.addEventListener('dragover', function (e) { e.preventDefault(); zona.classList.add('is-dragging'); });
  zona.addEventListener('dragleave', function () { zona.classList.remove('is-dragging'); });
  zona.addEventListener('drop', function (e) {
    e.preventDefault(); zona.classList.remove('is-dragging');
    ler(e.dataTransfer.files && e.dataTransfer.files[0]);
  });
})();

/* ======================================================================
   Menus de três pontos (contas, cartões, transações...) — genérico.
   ====================================================================== */
(function () {
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-menu-btn]');
    document.querySelectorAll('[data-menu]').forEach(function (m) {
      var aberto = btn && m.contains(btn) && m.querySelector('[data-menu-list]').hidden;
      m.querySelector('[data-menu-list]').hidden = !aberto;
      m.querySelector('[data-menu-btn]').setAttribute('aria-expanded', String(!!aberto));
      m.style.zIndex = aberto ? '30' : '';
    });
  });
})();

/* ======================================================================
   Contas — histórico e lista de arquivadas.
   Diferente do protótipo estático: os dados já vêm renderizados pelo
   servidor (um painel por conta, o histórico já calculado). O JS só
   decide qual painel mostrar — não busca nem monta HTML.
   ====================================================================== */
(function () {
  document.addEventListener('click', function (ev) {
    var ver = ev.target.closest('[data-account-history]');
    if (ver) {
      var id = ver.dataset.accountHistory;
      document.querySelectorAll('[data-account-panel]').forEach(function (p) {
        p.hidden = p.dataset.accountPanel !== id;
      });
      document.querySelectorAll('[data-account]').forEach(function (card) {
        card.classList.toggle('is-selected', card.dataset.account === id);
      });
      var painel = document.querySelector('[data-account-panel="' + id + '"]');
      if (painel) painel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    if (ev.target.closest('[data-account-close]')) {
      document.querySelectorAll('[data-account-panel]').forEach(function (p) { p.hidden = true; });
      document.querySelectorAll('[data-account]').forEach(function (card) { card.classList.remove('is-selected'); });
    }
  });

  var alternar = document.querySelector('[data-archived-toggle]');
  var lista = document.querySelector('[data-archived-list]');
  if (alternar && lista) {
    alternar.addEventListener('click', function () {
      lista.hidden = !lista.hidden;
      alternar.textContent = lista.hidden ? 'Mostrar' : 'Ocultar';
    });
  }
})();

/* ======================================================================
   Cartões — faturas mensais e pagamento.
   Cada fatura já vem renderizada como um "slide" oculto dentro do painel
   do cartão (data-bill-slide); navegar entre meses é só trocar qual
   slide está visível e copiar os dados dele para o cabeçalho comum.
   ====================================================================== */
(function () {
  var paineis = document.querySelectorAll('[data-invoices-panel]');
  if (!paineis.length) return;

  function painelDoCartao(id) {
    return document.querySelector('[data-invoices-panel="' + id + '"]');
  }

  function slideVisivel(painel) {
    return painel.querySelector('[data-bill-slide]:not([hidden])');
  }

  function moeda(v) {
    return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function sincronizarCabecalho(painel) {
    var slide = slideVisivel(painel);
    var todos = painel.querySelectorAll('[data-bill-slide]');
    var indice = slide ? Array.prototype.indexOf.call(todos, slide) : -1;

    var mes = painel.querySelector('[data-invoice-month]');
    var due = painel.querySelector('[data-invoice-due]');
    var count = painel.querySelector('[data-invoice-count]');
    var total = painel.querySelector('[data-invoice-total]');
    var estado = painel.querySelector('[data-invoice-state]');
    var pagar = painel.querySelector('[data-invoice-pay]');
    var prev = painel.querySelector('[data-invoice-prev]');
    var next = painel.querySelector('[data-invoice-next]');

    if (!slide) {
      if (mes) mes.textContent = '—';
      return;
    }

    if (mes) mes.textContent = slide.dataset.month;
    if (due) due.textContent = slide.dataset.due;
    if (count) count.textContent = slide.dataset.count;
    var paga = slide.dataset.paid === '1';
    if (total) {
      total.dataset.moneyValor = moeda(slide.dataset.total);
      total.textContent = window.__valoresOcultos ? '••••••' : moeda(slide.dataset.total);
    }
    if (estado) {
      estado.classList.toggle('is-paid', paga);
      estado.innerHTML = paga ? '<i class="fa-solid fa-check" aria-hidden="true"></i>Paga' : '<i class="fa-regular fa-clock" aria-hidden="true"></i>Em aberto';
    }
    if (pagar) pagar.hidden = paga;
    var editar = painel.querySelector('[data-invoice-edit]');
    if (editar) editar.hidden = slide.dataset.canEdit !== '1';
    if (prev) prev.style.opacity = indice >= todos.length - 1 ? '0.4' : '';
    if (next) next.style.opacity = indice <= 0 ? '0.4' : '';

    if (window.aplicarOcultarValores) window.aplicarOcultarValores();
  }

  function abrirPainel(cardId) {
    paineis.forEach(function (p) { p.hidden = p.dataset.invoicesPanel !== cardId; });
    var painel = painelDoCartao(cardId);
    if (!painel) return null;
    var slides = painel.querySelectorAll('[data-bill-slide]');
    slides.forEach(function (s, i) { s.hidden = i !== 0; });
    document.querySelectorAll('[data-card]').forEach(function (card) {
      card.classList.toggle('is-selected', card.dataset.card === cardId);
    });
    sincronizarCabecalho(painel);
    return painel;
  }

  function abrirPagamento(painel) {
    var slide = slideVisivel(painel);
    var modal = document.querySelector('[data-modal="pagamento"]');
    if (!slide || !modal) return;
    var tituloEl = painel.querySelector('.invoices__title');
    var titulo = tituloEl ? tituloEl.textContent : '';
    var form = modal.querySelector('[data-pay-form]');
    if (form) form.action = slide.dataset.payUrl;
    var card = modal.querySelector('[data-pay-card]');
    if (card) card.textContent = titulo.replace(/^Faturas · /, '') + ' · ' + slide.dataset.month;
    var due = modal.querySelector('[data-pay-due]');
    if (due) due.textContent = slide.dataset.due;
    var valor = modal.querySelector('[data-pay-amount]');
    if (valor) valor.value = Number(slide.dataset.total).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    modal.hidden = false;
  }

  function abrirEdicaoFatura(painel) {
    var slide = slideVisivel(painel);
    var modal = document.querySelector('[data-modal="fatura"]');
    if (!slide || !modal) return;
    var tituloEl = painel.querySelector('.invoices__title');
    var titulo = tituloEl ? tituloEl.textContent : '';
    var form = modal.querySelector('[data-fatura-form]');
    if (form) form.action = slide.dataset.editUrl;
    var card = modal.querySelector('[data-fatura-card]');
    if (card) card.textContent = titulo.replace(/^Faturas · /, '') + ' · ' + slide.dataset.month;

    var due = modal.querySelector('[data-fatura-due]');
    if (due) {
      var dp = due.closest('[data-datepicker]') || (due.parentNode && due.parentNode.querySelector('[data-datepicker]'));
      if (dp && dp.__definir) dp.__definir(slide.dataset.dueDate);
    }

    var tipo = slide.dataset.adjustmentType || 'acrescimo';
    var tipoInput = modal.querySelector('[data-fatura-adjustment-type]');
    if (tipoInput) {
      tipoInput.value = tipo;
      var grupo = tipoInput.closest('[data-chip-group]') || modal.querySelector('[data-chip-group]');
      if (grupo) {
        grupo.querySelectorAll('.chip').forEach(function (chip) {
          chip.classList.toggle('is-selected', chip.dataset.value === tipo);
        });
      }
    }
    var amount = modal.querySelector('[data-fatura-adjustment-amount]');
    if (amount) {
      var valor = Number(slide.dataset.adjustmentAmount || '0');
      amount.value = valor ? valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
    }
    var motivo = modal.querySelector('[data-fatura-reason]');
    if (motivo) motivo.value = slide.dataset.adjustmentReason || '';

    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  document.addEventListener('click', function (ev) {
    var editarFatura = ev.target.closest('[data-invoice-edit]');
    if (editarFatura) {
      var painelEdicao = editarFatura.closest('[data-invoices-panel]');
      if (painelEdicao) abrirEdicaoFatura(painelEdicao);
    }

    var ver = ev.target.closest('[data-card-invoices]');
    if (ver) abrirPainel(ver.dataset.cardInvoices);

    var pagarCartao = ev.target.closest('[data-card-pay]');
    if (pagarCartao) {
      var painelAberto = abrirPainel(pagarCartao.dataset.cardPay);
      if (painelAberto) abrirPagamento(painelAberto);
    }

    var fechar = ev.target.closest('[data-invoice-close]');
    if (fechar) {
      var painelAtual = fechar.closest('[data-invoices-panel]');
      if (painelAtual) painelAtual.hidden = true;
      document.querySelectorAll('[data-card]').forEach(function (card) { card.classList.remove('is-selected'); });
    }

    var pagarFatura = ev.target.closest('[data-invoice-pay]');
    if (pagarFatura) {
      var painelDaFatura = pagarFatura.closest('[data-invoices-panel]');
      if (painelDaFatura) abrirPagamento(painelDaFatura);
    }

    var anterior = ev.target.closest('[data-invoice-prev]');
    var proxima = ev.target.closest('[data-invoice-next]');
    if (anterior || proxima) {
      var painelNav = (anterior || proxima).closest('[data-invoices-panel]');
      if (!painelNav) return;
      var slides = painelNav.querySelectorAll('[data-bill-slide]');
      var atualIdx = Array.prototype.indexOf.call(slides, slideVisivel(painelNav));
      if (atualIdx === -1) return;
      var novoIdx = anterior ? Math.min(slides.length - 1, atualIdx + 1) : Math.max(0, atualIdx - 1);
      if (novoIdx === atualIdx) return;
      slides[atualIdx].hidden = true;
      slides[novoIdx].hidden = false;
      sincronizarCabecalho(painelNav);
    }
  });
})();

/* ======================================================================
   Configurações › Seções — trava de exatamente 5 escolhidas.
   As caixas já são inputs reais de um <form> (data-sections-form); o envio
   em si é um POST normal. O JS só cuida da trava visual: desabilita as
   não marcadas ao atingir 5 e libera o botão Salvar só quando há
   exatamente 5 marcadas e a seleção mudou em relação à salva no servidor.
   ====================================================================== */
(function () {
  var form = document.querySelector('[data-sections-form]');
  if (!form) return;

  var MAX = 5;
  var caixas = Array.prototype.slice.call(form.querySelectorAll('[data-section]'));
  var iniciais = caixas.filter(function (c) { return c.checked; }).map(function (c) { return c.value; }).sort().join('|');

  var contagem = form.querySelector('[data-sections-count]');
  var dica = form.querySelector('[data-sections-hint]');
  var salvar = form.querySelector('[data-sections-save]');
  var desfazer = form.querySelector('[data-sections-undo]');

  function atualizar() {
    var marcadas = caixas.filter(function (c) { return c.checked; });
    var cheio = marcadas.length >= MAX;

    caixas.forEach(function (c) {
      var opt = c.closest('[data-section-opt]');
      if (!c.checked) c.disabled = cheio;
      if (!opt) return;
      opt.classList.toggle('is-on', c.checked);
      opt.classList.toggle('is-locked', !c.checked && cheio);
      var texto = opt.querySelector('[data-section-text]');
      if (texto) {
        texto.textContent = c.checked
          ? 'Aparece na barra de seções.'
          : cheio ? 'Barra cheia — desmarque outra para liberar.' : 'Fica em "Mais opções".';
      }
    });

    if (contagem) contagem.textContent = marcadas.length + ' de ' + MAX + ' escolhidas';
    if (contagem) contagem.classList.toggle('is-full', cheio);
    if (dica) {
      dica.textContent = cheio
        ? 'Barra completa. Desmarque uma para trocar.'
        : 'Escolha ' + (MAX - marcadas.length) + ' seção(ões) para poder salvar.';
    }

    var atual = marcadas.map(function (c) { return c.value; }).sort().join('|');
    var mudou = atual !== iniciais;
    if (salvar) salvar.disabled = !(marcadas.length === MAX && mudou);
    if (desfazer) desfazer.hidden = !mudou;
  }

  form.addEventListener('change', function (ev) {
    if (ev.target.closest('[data-section]')) atualizar();
  });
  form.addEventListener('reset', function () {
    setTimeout(atualizar, 0);
  });

  atualizar();
})();

/* ======================================================================
   Transações — filtros submetem de verdade (GET), sem array de exemplo:
   trocar um dropdown já reenvia o formulário; a busca envia no Enter.
   ====================================================================== */
(function () {
  var form = document.querySelector('[data-tx-filters]');
  if (!form) return;

  form.querySelectorAll('[data-dropdown] [data-dropdown-input]').forEach(function (hidden) {
    hidden.addEventListener('change', function () { form.submit(); });
  });

  var busca = form.querySelector('[data-tx-search]');
  if (busca) {
    busca.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); form.submit(); }
    });
  }
})();

/* ======================================================================
   Fase 4 — painéis (parcelas de dívida, histórico de investimento,
   aportes de meta) e seletores de cor/ícone/segmento. Genérico e
   orientado a DOM — sem os dados de exemplo do protótipo.
   ====================================================================== */
(function () {
  var PAINEIS = [
    ['data-debt-open', 'data-debt-panel', 'data-debt-close', 'data-debt'],
    ['data-invest-open', 'data-invest-panel', 'data-invest-close', 'data-invest'],
    ['data-goal-open', 'data-goal-panel', 'data-goal-close', 'data-goal'],
  ];

  document.addEventListener('click', function (ev) {
    PAINEIS.forEach(function (p) {
      var abrir = ev.target.closest('[' + p[0] + ']');
      if (abrir) {
        var id = abrir.getAttribute(p[0]);
        document.querySelectorAll('[' + p[1] + ']').forEach(function (painel) {
          painel.hidden = painel.getAttribute(p[1]) !== id;
        });
        document.querySelectorAll('[' + p[3] + ']').forEach(function (card) {
          card.classList.toggle('is-selected', card.getAttribute(p[3]) === id);
        });
      }
      if (ev.target.closest('[' + p[2] + ']')) {
        document.querySelectorAll('[' + p[1] + ']').forEach(function (painel) { painel.hidden = true; });
        document.querySelectorAll('[' + p[3] + ']').forEach(function (card) { card.classList.remove('is-selected'); });
      }
    });
  });

  /* Seletores de cor, ícone e segmentos (modais de categoria e investimento) */
  document.addEventListener('click', function (ev) {
    var cor = ev.target.closest('[data-color]');
    if (cor) {
      cor.parentElement.querySelectorAll('[data-color]').forEach(function (b) { b.classList.toggle('is-on', b === cor); });
      var chip = document.querySelector('[data-cat-preview]');
      if (chip) chip.style.background = cor.dataset.color;
      var input = document.querySelector('[data-modal="categoria"] input[name="color"]');
      if (input) input.value = cor.dataset.color;
    }
    var icone = ev.target.closest('[data-icon]');
    if (icone) {
      icone.parentElement.querySelectorAll('[data-icon]').forEach(function (b) { b.classList.toggle('is-on', b === icone); });
      var prev = document.querySelector('[data-cat-preview-icon]');
      if (prev) prev.className = icone.dataset.icon;
      var iconInput = document.querySelector('[data-modal="categoria"] input[name="icon"]');
      if (iconInput) iconInput.value = icone.dataset.icon;
    }
    var seg = ev.target.closest('[data-seg-opt]');
    if (seg) {
      seg.parentElement.querySelectorAll('[data-seg-opt]').forEach(function (b) { b.classList.toggle('is-on', b === seg); });
      var campo = seg.closest('.field');
      var segInput = campo ? campo.querySelector('input[type="hidden"]') : null;
      if (segInput && seg.dataset.value) segInput.value = seg.dataset.value;
    }
  });

  var nome = document.querySelector('[data-modal="categoria"] input[name="name"]');
  if (nome) {
    nome.addEventListener('input', function () {
      var preview = document.querySelector('[data-cat-preview-name]');
      if (preview) preview.textContent = nome.value.trim() || 'Nome da categoria';
    });
  }
})();

/* ======================================================================
   Modais de aporte/resgate (investimento e meta) — o botão que abre
   carrega para onde o form deve postar e o nome da aplicação/meta; o JS
   só copia isso para dentro do modal compartilhado.
   ====================================================================== */
(function () {
  document.addEventListener('click', function (ev) {
    var gatilhoAporte = ev.target.closest('[data-aporte-url]');
    if (gatilhoAporte) {
      var form = document.querySelector('[data-aporte-form]');
      var sub = document.querySelector('[data-aporte-sub]');
      var tipoInput = document.querySelector('[data-aporte-tipo-input]');
      if (form) form.action = gatilhoAporte.getAttribute('data-aporte-url');
      if (sub) sub.textContent = gatilhoAporte.getAttribute('data-aporte-nome') || '—';
      var tipo = gatilhoAporte.getAttribute('data-aporte-tipo') || 'contribution';
      if (tipoInput) tipoInput.value = tipo;
      document.querySelectorAll('[data-modal="aporte"] [data-seg-opt]').forEach(function (b) {
        b.classList.toggle('is-on', b.dataset.value === tipo);
      });
    }

    var gatilhoMeta = ev.target.closest('[data-meta-url]');
    if (gatilhoMeta) {
      var formMeta = document.querySelector('[data-meta-form]');
      var subMeta = document.querySelector('[data-meta-sub]');
      if (formMeta) formMeta.action = gatilhoMeta.getAttribute('data-meta-url');
      if (subMeta) subMeta.textContent = gatilhoMeta.getAttribute('data-meta-nome') || '—';
    }
  });
})();
