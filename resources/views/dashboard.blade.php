@php
    use App\Enums\DashboardSection;
    use App\Support\Money;
    use Illuminate\Support\Str;

    $user = auth()->user();
    $userSettings = $user->settings()->firstOrCreate();
    $hide = (bool) $userSettings->hide_values;
    $summary = $dashboard['summary'];
    $charts = $dashboard['charts'];
    $creditCardService = app(\App\Services\CreditCardService::class);

    $activeSectionKeys = count($userSettings->sections ?? []) === 5 ? $userSettings->sections : DashboardSection::defaults();
    $activeSections = collect($activeSectionKeys)->map(fn ($key) => DashboardSection::tryFrom($key))->filter()->values();
    $moreSections = collect(DashboardSection::cases())->reject(fn ($section) => $activeSections->contains($section))->values();

    $categoryIcons = [
        'Alimentação' => 'fa-utensils', 'Moradia' => 'fa-house', 'Transporte' => 'fa-car',
        'Saúde' => 'fa-heart-pulse', 'Educação' => 'fa-graduation-cap', 'Lazer' => 'fa-gamepad',
        'Assinaturas' => 'fa-rotate', 'Compras' => 'fa-bag-shopping', 'Impostos' => 'fa-file-invoice-dollar',
        'Dívidas' => 'fa-hand-holding-dollar', 'Salário' => 'fa-briefcase', 'Renda extra' => 'fa-money-bill-wave',
        'Freelance' => 'fa-laptop', 'Rendimentos' => 'fa-chart-line', 'Reembolso' => 'fa-rotate-left',
        'Presente' => 'fa-gift',
    ];

    $maxBar = max(1, ...array_map('floatval', array_merge($charts['income'], $charts['expense'])));
    $bars = collect($charts['labels'])->map(fn ($label, $i) => [
        'label' => $label,
        'month_full' => $charts['labels_full'][$i],
        'rec' => max(4, round(((float) $charts['income'][$i]) / $maxBar * 100)),
        'desp' => max(4, round(((float) $charts['expense'][$i]) / $maxBar * 100)),
        'income' => $charts['income'][$i],
        'expense' => $charts['expense'][$i],
        'result' => bcsub($charts['income'][$i], $charts['expense'][$i], 2),
    ]);

    $topCategories = collect($charts['expense_categories']['labels'])
        ->map(fn ($label, $i) => ['label' => $label, 'value' => (float) $charts['expense_categories']['values'][$i]])
        ->sortByDesc('value')->values()->take(4);
    $maxCategory = max(1, $topCategories->max('value') ?? 1);

    $subscriptionsTotal = $dashboard['subscriptions']->reduce(fn ($t, $s) => bcadd($t, $s->amount, 2), '0.00');
    $forecastIncomeTotal = collect($dashboard['forecast_months'])->reduce(fn ($t, $m) => bcadd($t, $m['income'], 2), '0.00');
    $forecastExpenseTotal = collect($dashboard['forecast_months'])->reduce(fn ($t, $m) => bcadd($t, $m['expense'], 2), '0.00');
    $forecastResultTotal = bcsub($forecastIncomeTotal, $forecastExpenseTotal, 2);
    $maxForecast = max(1, ...array_map(fn ($m) => abs((float) $m['result']), $dashboard['forecast_months']));

    $cardsOutstandingTotal = collect($dashboard['credit_cards'])->reduce(fn ($t, $c) => bcadd($t, $c['outstanding'], 2), '0.00');
    $cardsLimitTotal = collect($dashboard['credit_cards'])->reduce(fn ($t, $c) => bcadd($t, $c['available_limit'], 2), '0.00');

    $upcomingTotal = $dashboard['upcoming']->reduce(fn ($t, $u) => bcadd($t, $u->amount, 2), '0.00');

    $initials = collect(explode(' ', trim($user->name)))->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
    $transactionsInPeriod = $user->transactions()->whereBetween('competence_date', [$dashboard['period']['start'], $dashboard['period']['end']])->count();
    $activeSessionsCount = \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->count();

    $monthStartDayOptions = collect([1, 5, 10, 15, 20]);
    if (! $monthStartDayOptions->contains($userSettings->financial_month_start_day)) {
        $monthStartDayOptions = $monthStartDayOptions->push($userSettings->financial_month_start_day)->sort()->values();
    }
@endphp
<x-dashboard-layout title="Visão geral">

<div class="app">

  <aside class="sidebar" data-sidebar data-collapsed="false">

    <div class="sidebar__brand-row">
      <div class="brand-swap">
        <img class="brand-swap__logo" src="{{ asset('design/assets/capi/capi-rosto.png') }}" alt="Capí">
        <button class="brand-swap__btn" type="button" data-sidebar-toggle aria-label="Abrir barra lateral"><i class="fa-solid fa-table-columns" aria-hidden="true"></i></button>
      </div>
      <span class="sidebar__brand-name sidebar__label">financi<span class="brand__name-accent">aí</span></span>
      <button class="sidebar__collapse" type="button" data-sidebar-toggle aria-label="Recolher barra lateral"><i class="fa-solid fa-table-columns" aria-hidden="true"></i></button>
    </div>

    <button class="sidebar__cta" type="button" data-goto="chat" title="Bater papo com o Capí">
      <i class="fa-solid fa-comment-dots sidebar__icon" aria-hidden="true"></i>
      <span class="sidebar__label">Bater papo com o Capí</span>
    </button>

    <nav class="sidebar__nav">
      <button class="nav-item" type="button" data-goto="agentes" title="Agentes">
        <i class="fa-solid fa-user-group sidebar__icon" aria-hidden="true"></i>
        <span class="sidebar__label">Agentes</span>
      </button>
      <button class="nav-item" type="button" data-goto="visao" title="Painel" aria-current="page">
        <i class="fa-solid fa-chart-pie sidebar__icon" aria-hidden="true"></i>
        <span class="sidebar__label">Painel</span>
      </button>
    </nav>

    <div class="sidebar__history">
      <div class="history__head">
        <span class="history__title">Conversas</span>
        <span class="tag-soon">em breve</span>
      </div>
      <div class="history__list history__list--empty">
        <p class="history__empty-text">O histórico de conversas com o Capí chega junto com os agentes.</p>
      </div>
    </div>

    <div class="sidebar__user" data-user-menu>
      <div class="sidebar__user-row">
        <button class="sidebar__user-btn" type="button" aria-label="Sua conta" aria-expanded="false" data-user-toggle>
          <span class="avatar">@if($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="">@else{{ $initials }}@endif</span>
          <span class="sidebar__user-info sidebar__label">
            <span class="sidebar__user-name">{{ $user->name }}</span>
            <span class="sidebar__user-plan">Beta aberto</span>
          </span>
        </button>
        <button class="sidebar__settings" type="button" aria-label="Configurações" data-goto="config"><i class="fa-solid fa-gear" aria-hidden="true"></i></button>
      </div>

      <div class="user-card" hidden data-user-card>
        <div class="user-card__head">
          <span class="avatar avatar--lg">@if($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="">@else{{ $initials }}@endif</span>
          <span class="user-card__ident">
            <span class="user-card__name">{{ $user->name }}</span>
            <span class="user-card__mail">{{ $user->email }}</span>
          </span>
        </div>

        <div class="user-card__plan">
          <span class="user-card__plan-info">
            <span class="user-card__plan-name">Beta aberto</span>
          </span>
        </div>

        <div class="user-card__stats">
          <span class="user-card__stat"><strong>{{ $dashboard['accounts']->count() }}</strong>{{ $dashboard['accounts']->count() === 1 ? 'conta conectada' : 'contas conectadas' }}</span>
          <span class="user-card__stat"><strong>{{ $transactionsInPeriod }}</strong>lançamentos no período</span>
        </div>

        <div class="user-card__actions">
          <button class="user-card__action" type="button" data-goto="config"><i class="fa-solid fa-gear" aria-hidden="true"></i>Configurações</button>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="user-card__action user-card__action--out" type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>Sair da conta</button>
          </form>
        </div>
      </div>
    </div>
  </aside>

  <main class="main">

    <header class="topbar">
      <div class="topbar__inner">

        <nav class="tabs-nav" data-tabs-nav>
          <button class="tab-btn" type="button" data-goto="visao" aria-current="page"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i>Visão geral</button>
          <span data-tabs-slot>
            @foreach($activeSections as $section)
              <button class="tab-btn" type="button" data-goto="{{ $section->value }}"><i class="{{ $section->icon() }}" aria-hidden="true"></i>{{ $section->label() }}</button>
            @endforeach
          </span>
          @if(count($moreSections) > 0)
            <div class="dropdown tabs-more" data-dropdown>
              <button class="tab-btn tab-btn--more" type="button" aria-haspopup="menu" aria-expanded="false" data-dropdown-btn>
                <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>Mais opções<i class="fa-solid fa-chevron-down dropdown__chevron" aria-hidden="true"></i>
              </button>
              <div class="dropdown__menu dropdown__menu--right" role="menu" hidden>
                @foreach($moreSections as $section)
                  <button class="dropdown__opt" type="button" role="menuitem" data-goto="{{ $section->value }}"><i class="{{ $section->icon() }}" aria-hidden="true"></i>{{ $section->label() }}</button>
                @endforeach
              </div>
            </div>
          @endif
        </nav>

        <div class="topbar__actions">
          <button class="icon-btn" type="button" data-notif-toggle aria-label="Notificações" aria-expanded="false">
            <i class="fa-regular fa-bell" aria-hidden="true"></i>
            @if($summary['overdue_count'] + $summary['overdue_bill_count'] + $summary['upcoming_count'] + $unreadNotificationCount > 0)
              <span class="icon-btn__dot"></span>
            @endif
          </button>
          <button class="icon-btn" type="button" data-theme-toggle data-toggle-url="{{ route('settings.toggle-theme') }}" aria-label="Alternar tema"><i class="fa-solid {{ $userSettings->theme === 'dark' ? 'fa-sun' : 'fa-moon' }}" aria-hidden="true"></i></button>
          <button class="icon-btn" type="button" data-toggle-money data-toggle-url="{{ route('settings.toggle-values') }}" @if($hide) data-money-reload-on-reveal @endif aria-label="Mostrar ou ocultar valores" aria-pressed="{{ $hide ? 'true' : 'false' }}"><i class="fa-regular {{ $hide ? 'fa-eye-slash' : 'fa-eye' }}" aria-hidden="true"></i></button>
          <button class="btn-add" type="button" data-modal-open="transacao"><i class="fa-solid fa-plus" aria-hidden="true"></i>Nova transação</button>

          <div class="notif-panel" data-notif-panel hidden>
            <div class="notif-panel__head">
              <span class="notif-panel__title">Notificações</span>
              {{-- Único ponto de entrada de /notifications no app. --}}
              <a class="link-sm" href="{{ route('notifications.index') }}">Ver todas</a>
              <span class="notif-panel__count">{{ $summary['overdue_count'] + $summary['overdue_bill_count'] + ($summary['upcoming_count'] > 0 ? 1 : 0) + $unreadNotificationCount }} pendências</span>
            </div>
            <div>
              {{-- Lembretes individuais gerados pelo comando agendado
                   finance:send-reminders. Antes só existiam em /notifications,
                   que nenhuma tela do app alcançava. --}}
              @foreach($unreadNotifications as $notification)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                  @csrf
                  @method('PATCH')
                  <button class="notif" type="submit">
                    <span class="notif__icon"><i class="fa-regular fa-bell" aria-hidden="true"></i></span>
                    <span class="notif__body">
                      <span class="notif__title">{{ $notification->data['title'] ?? 'Lembrete financeiro' }}</span>
                      <span class="notif__text">{{ $notification->data['message'] ?? $notification->created_at->diffForHumans() }}</span>
                    </span>
                  </button>
                </form>
              @endforeach
              @if($summary['overdue_bill_count'] > 0)
                <button class="notif" type="button">
                  <span class="notif__icon notif__icon--fg"><i class="fa-regular fa-credit-card" aria-hidden="true"></i></span>
                  <span class="notif__body">
                    <span class="notif__title">{{ $summary['overdue_bill_count'] }} fatura(s) de cartão vencida(s)</span>
                    <span class="notif__text">Revise em Cartões.</span>
                  </span>
                </button>
              @endif
              @if($summary['overdue_count'] > 0)
                <button class="notif" type="button">
                  <span class="notif__icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                  <span class="notif__body">
                    <span class="notif__title">{{ $summary['overdue_count'] }} transação(ões) vencida(s)</span>
                    <span class="notif__text">Revise em Transações.</span>
                  </span>
                </button>
              @endif
              @if($summary['upcoming_count'] > 0)
                <button class="notif" type="button">
                  <span class="notif__icon notif__icon--accent"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                  <span class="notif__body">
                    <span class="notif__title">{{ $summary['upcoming_count'] }} compromisso(s) nos próximos 15 dias</span>
                    <span class="notif__text">Veja a lista na Visão geral.</span>
                  </span>
                </button>
              @endif
              @if($summary['overdue_count'] === 0 && $summary['overdue_bill_count'] === 0 && $summary['upcoming_count'] === 0 && $unreadNotifications->isEmpty())
                <p style="padding: 16px; font-size: 14px; color: var(--muted);">Nenhuma pendência por aqui.</p>
              @endif
            </div>
            {{-- Antes era um type="button" que só escondia o painel e apagava o
                 pontinho: as notificações continuavam não lidas no banco e
                 voltavam no próximo carregamento. Agora é um envio de verdade. --}}
            <form method="POST" action="{{ route('notifications.read-all') }}">
              @csrf
              @method('PATCH')
              <button class="notif-panel__foot" type="submit" data-notif-read>Marcar todas como lidas</button>
            </form>
          </div>
        </div>
      </div>
    </header>

    <div class="stage">
      <div class="stage__inner">

        <div class="page-head">
          <div>
            <h1 class="page-head__title" data-page-title>Visão geral</h1>
            <p class="page-head__sub" data-page-sub>Seu panorama financeiro, sem ruído.</p>
          </div>

          <div class="period" data-period>
            <div class="period__group" data-period-group>
              <button class="period__btn" type="button" data-period-value="30d" aria-pressed="true">30 dias</button>
              <button class="period__btn" type="button" data-period-value="12m" aria-pressed="false">12 meses</button>
              <button class="period__btn" type="button" data-period-value="intervalo" aria-pressed="false">Intervalo</button>
            </div>
            <div class="date-range" data-date-range hidden>
              <i class="fa-regular fa-calendar" aria-hidden="true"></i>
              <x-datepicker name="period_start" :value="$dashboard['period']['start']->toDateString()" label="Data inicial" inline bare />
              <span>até</span>
              <x-datepicker name="period_end" :value="$dashboard['period']['end']->toDateString()" label="Data final" inline right bare />
            </div>
            <span class="period__summary" data-period-summary>{{ $dashboard['period']['start']->translatedFormat('j \d\e M') }} – {{ $dashboard['period']['end']->translatedFormat('j \d\e M \d\e Y') }}</span>
          </div>
        </div>

        <!-- ============ Visão geral ============ -->
        <section class="page stack" data-page="visao">

          <div class="grid-3">
            <article class="kpi kpi--hover" data-enter>
              <div class="kpi__head">
                <span class="kpi__label"><i class="fa-solid fa-wallet" aria-hidden="true"></i>Saldo atual</span>
                <span class="kpi__delta kpi__delta--neutral">{{ $dashboard['accounts']->count() }} {{ Str::plural('conta', $dashboard['accounts']->count()) }}</span>
              </div>
              <div class="kpi__value" data-money>{{ Money::format($summary['balance_current'], $hide) }}</div>
              <div class="kpi__note">Somente movimentações efetivadas</div>
            </article>
            <article class="kpi kpi--hover" data-enter>
              <div class="kpi__head">
                <span class="kpi__label"><i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i>Resultado do mês</span>
                <span class="kpi__delta {{ bccomp($summary['result'], '0', 2) < 0 ? 'kpi__delta--neutral' : '' }}">{{ bccomp($summary['result'], '0', 2) >= 0 ? 'Positivo' : 'Negativo' }}</span>
              </div>
              <div class="kpi__value kpi__value--accent" data-money>{{ Money::format($summary['result'], $hide) }}</div>
              <div class="kpi__note">Receitas menos despesas</div>
            </article>
            <article class="kpi kpi--hover" data-enter>
              <div class="kpi__head">
                <span class="kpi__label"><i class="fa-regular fa-clock" aria-hidden="true"></i>Compromissos futuros</span>
                <span class="kpi__delta kpi__delta--neutral">{{ $summary['upcoming_count'] }} {{ Str::plural('item', $summary['upcoming_count']) }}</span>
              </div>
              <div class="kpi__value" data-money>{{ Money::format($upcomingTotal, $hide) }}</div>
              <div class="kpi__note">Próximos 15 dias</div>
            </article>
          </div>

          <div class="grid-wide">
            <section class="panel panel--span2" data-enter>
              <div class="panel__head">
                <div>
                  <h2 class="panel__title">Entradas e saídas</h2>
                  <p class="panel__sub">Últimos 6 meses</p>
                </div>
                <div class="legend">
                  <span><span class="legend__swatch legend__swatch--rec"></span>Receitas</span>
                  <span><span class="legend__swatch legend__swatch--desp"></span>Despesas</span>
                </div>
              </div>
              <div class="bars">
                @foreach($bars as $bar)
                  <div class="bars__col chart-col" data-chart-col data-hover="false">
                    <div class="bars__pair">
                      <span class="bar bar--rec chart-bar" data-kind="rec" data-dim="false" style="height:{{ $bar['rec'] }}%"></span>
                      <span class="bar bar--desp chart-bar" data-kind="desp" data-dim="false" style="height:{{ $bar['desp'] }}%"></span>
                      <span class="chart-tip" role="tooltip">
                        <span class="chart-tip__month">{{ $bar['month_full'] }}</span>
                        <span class="chart-tip__row" data-kind="rec"><span class="chart-tip__key"><span class="chart-tip__dot"></span>Receitas</span><span class="chart-tip__val" data-money>{{ Money::format($bar['income'], $hide) }}</span></span>
                        <span class="chart-tip__row" data-kind="desp"><span class="chart-tip__key"><span class="chart-tip__dot chart-tip__dot--exp"></span>Despesas</span><span class="chart-tip__val" data-money>{{ Money::format($bar['expense'], $hide) }}</span></span>
                        <span class="chart-tip__row chart-tip__row--total"><span class="chart-tip__key">Sobrou</span><span class="chart-tip__val" data-money>{{ Money::format($bar['result'], $hide) }}</span></span>
                      </span>
                    </div>
                    <span class="bars__label">{{ $bar['label'] }}</span>
                  </div>
                @endforeach
              </div>
            </section>

            <section class="panel" data-enter>
              <h2 class="panel__title">Próximos compromissos</h2>
              <p class="panel__sub panel__sub--gap">15 dias</p>
              <div class="due-list">
                @forelse($dashboard['upcoming'] as $item)
                  <div class="due">
                    <span class="due__date"><span class="due__day">{{ $item->due_date->format('d') }}</span><span class="due__month">{{ $item->due_date->translatedFormat('M') }}</span></span>
                    <span class="due__body">
                      <span class="due__name">{{ $item->description }}</span>
                      <span class="due__status {{ $item->type->value === 'income' ? 'due__status--accent' : '' }}">
                        @if($item->installment_total)
                          {{ $item->installment_number }} de {{ $item->installment_total }}
                        @elseif($item->type->value === 'income')
                          Entrada prevista
                        @else
                          {{ $item->category?->name ?? 'Despesa' }}
                        @endif
                      </span>
                    </span>
                    <span class="due__value {{ $item->type->value === 'income' ? 'due__value--accent' : '' }}" data-money>{{ Money::format($item->amount, $hide) }}</span>
                  </div>
                @empty
                  <p style="font-size: 14px; color: var(--muted);">Nenhum compromisso nos próximos 15 dias.</p>
                @endforelse
              </div>
            </section>
          </div>

          <div class="grid-2">
            <section class="panel" data-enter>
              <h2 class="panel__title">Para onde foi o dinheiro</h2>
              <p class="panel__sub panel__sub--gap-lg">{{ $dashboard['period']['start']->translatedFormat('F') }} · principais categorias</p>
              <div class="cat-list">
                @forelse($topCategories as $cat)
                  <div class="cat">
                    <div class="cat__row">
                      <span class="cat__name"><i class="fa-solid {{ $categoryIcons[$cat['label']] ?? 'fa-tag' }}" aria-hidden="true"></i>{{ $cat['label'] }}</span>
                      <span class="cat__value" data-money>{{ Money::format((string) $cat['value'], $hide) }}</span>
                    </div>
                    <div class="track"><span class="track__fill" style="width:{{ max(4, round($cat['value'] / $maxCategory * 100)) }}%"></span></div>
                  </div>
                @empty
                  <p style="font-size: 14px; color: var(--muted);">Nenhuma despesa categorizada neste período.</p>
                @endforelse
              </div>
            </section>

            <section class="capi-card" data-enter>
              <span class="capi-card__ring" aria-hidden="true"></span>
              <div class="capi-card__body">
                <span class="capi-card__eyebrow">Capí sugere</span>
                <p class="capi-card__text">O assistente do Capí ainda está em desenvolvimento. Em breve ele vai analisar seus lançamentos e trazer sugestões por aqui.</p>
              </div>
              <div class="capi-card__foot">
                <button class="btn-capi" type="button" data-goto="chat"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i>Conversar com o Capí</button>
                <img class="capi-card__art" src="{{ asset('design/assets/capi/capi-apontando.png') }}" alt="">
              </div>
            </section>
          </div>
        </section>

        <!-- ============ Transações ============ -->
        <section class="page stack" data-page="transacoes" hidden>
          @php
              $txFiltrosAtivos = collect($filters)->only(['tx_search', 'tx_type', 'tx_category', 'tx_account', 'tx_status'])->filter()->isNotEmpty();
              $txPersistFilters = collect($filters)->only(['month', 'year', 'account_id', 'start_date', 'end_date'])->all();
          @endphp

          <form method="GET" action="{{ route('dashboard') }}#transacoes" data-tx-filters>
            @foreach($txPersistFilters as $key => $value)
              <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <div class="filters" data-enter>
              <label class="search-field">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="tx_search" value="{{ $filters['tx_search'] ?? '' }}" placeholder="Buscar por descrição ou categoria" aria-label="Buscar por descrição ou categoria" data-tx-search>
              </label>
              <x-dropdown name="tx_type" icon="fa-solid fa-arrow-right-arrow-left" :block="false" :selected="$filters['tx_type'] ?? ''" :options="[
                  ['value' => '', 'label' => 'Entradas e saídas'],
                  ['value' => 'income', 'label' => 'Somente entradas'],
                  ['value' => 'expense', 'label' => 'Somente saídas'],
                  ['value' => 'pending', 'label' => 'Pendentes'],
              ]" />
              <x-dropdown name="tx_category" icon="fa-solid fa-tag" :block="false" :selected="$filters['tx_category'] ?? ''" :options="collect([['value' => '', 'label' => 'Todas as categorias']])->concat($categories->map(fn ($category) => ['value' => $category->id, 'label' => $category->name]))" />
            </div>

            <div class="filters filters--second" data-enter>
              <x-dropdown name="tx_account" icon="fa-solid fa-building-columns" :block="false" :selected="$filters['tx_account'] ?? ''" :options="collect([['value' => '', 'label' => 'Todas as contas']])->concat($dashboard['accounts']->map(fn ($row) => ['value' => $row['account']->id, 'label' => $row['account']->name]))" />
              <x-dropdown name="tx_status" icon="fa-regular fa-circle-check" :block="false" :selected="$filters['tx_status'] ?? ''" :options="[
                  ['value' => '', 'label' => 'Todos os status'],
                  ['value' => 'completed', 'label' => 'Efetivada'],
                  ['value' => 'pending', 'label' => 'Pendente'],
                  ['value' => 'cancelled', 'label' => 'Cancelada'],
              ]" />
              @if($txFiltrosAtivos)
                <a class="link-clear" href="{{ route('dashboard', array_merge($txPersistFilters, [])) }}#transacoes"><i class="fa-solid fa-xmark" aria-hidden="true"></i>Limpar filtros</a>
              @endif
              <span class="filters__spacer"></span>
              <a class="btn-outline-hard btn-outline--sm" href="{{ route('transactions.export', array_filter(['start_date' => $dashboard['period']['start']->toDateString(), 'end_date' => $dashboard['period']['end']->toDateString()])) }}"><i class="fa-solid fa-file-csv" aria-hidden="true"></i>Exportar CSV</a>
              <button class="btn-primary btn-primary--sm" type="button" data-modal-open="transacao"><i class="fa-solid fa-plus" aria-hidden="true"></i>Nova transação</button>
            </div>
          </form>

          <section class="tx-table" data-enter>
            <div class="tx-row tx-row--head">
              <span>Data</span>
              <span>Descrição</span>
              <span>Status</span>
              <span class="is-right">Valor</span>
              <span></span>
            </div>

            <div>
              @foreach($transactionsPage as $t)
                <div class="tx-row">
                  <span class="tx-date"><span class="tx-date__day">{{ $t->competence_date->format('d') }}</span><span class="tx-date__month">{{ $t->competence_date->translatedFormat('M') }}</span></span>
                  <span class="tx-desc">
                    <span class="row__icon"><i class="fa-solid {{ $categoryIcons[$t->category?->name] ?? ($t->type->value === 'income' ? 'fa-briefcase' : 'fa-receipt') }}" aria-hidden="true"></i></span>
                    <span class="tx-desc__body">
                      <span class="tx-desc__name {{ $t->status->value === 'cancelled' ? 'is-void' : '' }}">{{ $t->description }}</span>
                      <span class="tx-desc__detail">{{ $t->category?->name ?? $t->type->label() }} · {{ $t->account?->name ?? $t->creditCard?->name }}</span>
                    </span>
                  </span>
                  <span>
                    @if($t->status->value === 'cancelled')
                      <span class="tx-badge is-void"><i class="fa-solid fa-ban" aria-hidden="true"></i>Cancelada</span>
                    @elseif($t->status->value === 'completed')
                      <span class="tx-badge is-done"><i class="fa-solid fa-check" aria-hidden="true"></i>Efetivada</span>
                    @else
                      <span class="tx-badge is-pending"><i class="fa-regular fa-clock" aria-hidden="true"></i>Pendente</span>
                    @endif
                  </span>
                  <span class="tx-value {{ $t->type->value === 'income' ? 'is-in' : '' }}" data-money>{{ $t->type->value === 'income' ? '+ ' : '− ' }}{{ Money::format($t->amount, $hide) }}</span>
                  <span class="menu" data-menu>
                    <button class="btn-icon btn-icon--sm" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Ações da transação" data-menu-btn><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                    <div class="menu__list" role="menu" hidden data-menu-list>
                      <a class="menu__item" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['edit_transaction' => $t->id])) }}#transacoes"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar</a>
                      <form method="POST" action="{{ route('transactions.duplicate', $t) }}">
                        @csrf
                        <button class="menu__item" type="submit" role="menuitem"><i class="fa-regular fa-copy" aria-hidden="true"></i>Duplicar</button>
                      </form>
                      <a class="menu__item" role="menuitem" href="{{ route('transactions.export', ['id' => $t->id]) }}"><i class="fa-solid fa-file-csv" aria-hidden="true"></i>Exportar CSV</a>
                      @if($t->status->value !== 'cancelled')
                        <form method="POST" action="{{ route('transactions.cancel', $t) }}">
                          @csrf @method('PATCH')
                          <button class="menu__item menu__item--danger menu__item--sep" type="submit" role="menuitem"><i class="fa-solid fa-ban" aria-hidden="true"></i>Cancelar</button>
                        </form>
                      @endif
                    </div>
                  </span>
                </div>
              @endforeach
            </div>

            <div class="tx-empty" @unless($transactionsPage->isEmpty()) hidden @endunless>
              <p>Nenhuma transação encontrada com esses filtros.</p>
              @if($txFiltrosAtivos)
                <a class="btn-outline-hard btn-outline--sm" href="{{ route('dashboard', array_merge($txPersistFilters, [])) }}#transacoes">Limpar filtros</a>
              @endif
            </div>

            @if($transactionsPage->total() > 0)
              <div class="tx-foot">
                <span class="tx-foot__summary">Mostrando {{ $transactionsPage->firstItem() }}–{{ $transactionsPage->lastItem() }} de {{ $transactionsPage->total() }} {{ $transactionsPage->total() === 1 ? 'transação' : 'transações' }}</span>
                <span class="tx-pager">
                  <a class="btn-icon btn-icon--sm" aria-label="Página anterior" href="{{ $transactionsPage->previousPageUrl() ?? '#transacoes' }}#transacoes" style="{{ $transactionsPage->onFirstPage() ? 'opacity:.4;pointer-events:none;' : '' }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
                  <span>
                    @for($p = 1; $p <= $transactionsPage->lastPage(); $p++)
                      <a class="tx-page {{ $p === $transactionsPage->currentPage() ? 'is-current' : '' }}" href="{{ $transactionsPage->url($p) }}#transacoes">{{ $p }}</a>
                    @endfor
                  </span>
                  <a class="btn-icon btn-icon--sm" aria-label="Próxima página" href="{{ $transactionsPage->nextPageUrl() ?? '#transacoes' }}#transacoes" style="{{ $transactionsPage->hasMorePages() ? '' : 'opacity:.4;pointer-events:none;' }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                </span>
              </div>
            @endif
          </section>
        </section>

        <!-- ============ Assinaturas ============ -->
        <section class="page stack" data-page="assinaturas" hidden>

          <div class="grid-3">
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-rotate" aria-hidden="true"></i>Total por mês</div>
              <div class="kpi__value kpi__value--sm" data-money>{{ Money::format($subscriptionsTotal, $hide) }}</div>
              <div class="kpi__note">{{ $dashboard['subscriptions']->count() }} {{ Str::plural('assinatura ativa', $dashboard['subscriptions']->count()) }}</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i>Peso no orçamento</div>
              <div class="kpi__value kpi__value--sm">{{ str_replace('.', ',', Money::percentage($subscriptionsTotal, $summary['expense'])) }}%</div>
              <div class="kpi__note">Das suas despesas do período</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-regular fa-clock" aria-hidden="true"></i>Próxima cobrança</div>
              @php
                $next = $dashboard['subscriptions']->sortBy('due_date')->first();
              @endphp
              <div class="kpi__value kpi__value--sm kpi__value--accent">{{ $next?->due_date?->format('d/m') ?? '—' }}</div>
              <div class="kpi__note">{{ $next?->description ?? 'Nenhuma cadastrada' }}</div>
            </article>
          </div>

          <section class="list-card" data-enter>
            <div class="list-card__head list-card__head--tall">
              <span class="list-card__title">Assinaturas identificadas</span>
              <span class="list-card__note">A partir de lançamentos recorrentes</span>
            </div>

            @forelse($dashboard['subscriptions'] as $sub)
              <div class="row row--tall">
                <span class="row__icon row__icon--lg"><i class="fa-solid {{ $categoryIcons[$sub->category?->name] ?? 'fa-rotate' }}" aria-hidden="true"></i></span>
                <span class="row__body"><span class="row__name row__name--bold">{{ $sub->description }}</span><span class="row__detail">{{ $sub->category?->name ?? 'Assinatura' }} · {{ $sub->account?->name }}</span></span>
                <span class="row__right"><span class="row__value" data-money>{{ Money::format($sub->amount, $hide) }}</span><span class="row__next">{{ $sub->due_date?->format('d/m') }}</span></span>
              </div>
            @empty
              <div class="row row--tall">
                <span class="row__body"><span class="row__name row__name--bold">Nenhuma assinatura identificada ainda.</span><span class="row__detail">Lançamentos recorrentes cadastrados como despesa aparecem aqui automaticamente.</span></span>
              </div>
            @endforelse

            <div class="list-card__foot">
              <span>Cancelar uma assinatura pouco usada é o ajuste mais rápido no seu mês.</span>
              <button class="btn-outline" type="button" data-modal-open="assinatura"><i class="fa-solid fa-plus" aria-hidden="true"></i>Adicionar assinatura</button>
            </div>
          </section>
        </section>

        <!-- ============ Planejamento ============ -->
        <section class="page stack" data-page="planejamento" hidden>

          <div class="grid-3">
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-arrow-down-long" aria-hidden="true"></i>Receitas previstas</div>
              <div class="kpi__value kpi__value--sm kpi__value--accent" data-money>{{ Money::format($forecastIncomeTotal, $hide) }}</div>
              <div class="kpi__note">Próximos 6 meses</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-arrow-up-long" aria-hidden="true"></i>Despesas previstas</div>
              <div class="kpi__value kpi__value--sm" data-money>{{ Money::format($forecastExpenseTotal, $hide) }}</div>
              <div class="kpi__note">Próximos 6 meses</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>Resultado projetado</div>
              <div class="kpi__value kpi__value--sm kpi__value--accent" data-money>{{ Money::format($forecastResultTotal, $hide) }}</div>
              <div class="kpi__note">Se o planejado se confirmar</div>
            </article>
          </div>

          <section class="panel" data-enter>
            <h2 class="panel__title">Próximos 6 meses</h2>
            <p class="panel__sub panel__sub--gap-lg">Resultado previsto por mês, a partir de lançamentos planejados</p>
            <div class="month-list">
              @foreach($dashboard['forecast_months'] as $m)
                <div class="month-row">
                  <span class="month-row__name">{{ $m['month']->translatedFormat('M') }}</span>
                  <span class="track track--tall"><span class="track__fill {{ bccomp($m['result'], '0', 2) < 0 ? 'track__fill--danger' : '' }}" style="width:{{ max(4, round(abs((float) $m['result']) / $maxForecast * 100)) }}%"></span></span>
                  <span class="month-row__value {{ bccomp($m['result'], '0', 2) < 0 ? 'month-row__value--danger' : '' }}" data-money>{{ bccomp($m['result'], '0', 2) >= 0 ? '+ ' : '− ' }}{{ Money::format(ltrim($m['result'], '-'), $hide) }}</span>
                </div>
              @endforeach
            </div>
          </section>
        </section>

        <!-- ============ Contas ============ -->
        <section class="page" data-page="contas" hidden>

          <section class="import-card" data-enter>
            <div class="import-card__head">
              <div>
                <h2 class="panel__title">Trazer seus lançamentos</h2>
                <p class="import-card__text">Baixe o extrato no seu banco e importe aqui. O financiaí lê o arquivo, identifica as categorias e mostra tudo para você conferir antes de salvar.</p>
              </div>
              <span class="chip-shield"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Sem acesso ao seu banco</span>
            </div>

            <div class="import-card__body">
              <a class="import-drop" href="{{ route('transactions.import.create') }}">
                <i class="fa-solid fa-file-arrow-up import-drop__icon" aria-hidden="true"></i>
                <span class="import-drop__title">Importar extrato</span>
                <span class="import-drop__hint">OFX ou CSV · até 12 meses por arquivo</span>
                <span class="import-drop__cta">Começar<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
              </a>

              <div class="import-steps">
                <div class="step-mini">
                  <span class="step-mini__num">1</span>
                  <span class="step-mini__body">
                    <span class="step-mini__title">Baixe o extrato no seu banco</span>
                    <span class="step-mini__text">No app do banco, exporte o período em OFX ou CSV.</span>
                  </span>
                </div>
                <div class="step-mini">
                  <span class="step-mini__num">2</span>
                  <span class="step-mini__body">
                    <span class="step-mini__title">Confira o que foi lido</span>
                    <span class="step-mini__text">Datas, valores e categorias sugeridas aparecem para revisão.</span>
                  </span>
                </div>
                <div class="step-mini">
                  <span class="step-mini__num">3</span>
                  <span class="step-mini__body">
                    <span class="step-mini__title">Salve na conta certa</span>
                    <span class="step-mini__text">Lançamentos duplicados são identificados automaticamente.</span>
                  </span>
                </div>
              </div>
            </div>
          </section>

          <div class="grid-accounts">
            @foreach($dashboard['accounts'] as $row)
              @php $variacao = bcsub($row['current'], (string) $row['account']->initial_balance, 2); @endphp
              <article class="account-card" data-enter data-account="{{ $row['account']->id }}">
                <div class="account-card__head">
                  <span class="row__icon row__icon--lg"><i class="fa-solid fa-building-columns" aria-hidden="true"></i></span>
                  <span class="account-card__info">
                    <span class="account-card__name">{{ $row['account']->name }}</span>
                    <span class="account-card__type">{{ $row['account']->type->label() }}{{ $row['account']->institution ? ' · '.$row['account']->institution : '' }}</span>
                  </span>
                  <div class="menu" data-menu>
                    <button class="btn-icon btn-icon--sm" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Ações da conta" data-menu-btn><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                    <div class="menu__list" role="menu" hidden data-menu-list>
                      <button class="menu__item" type="button" role="menuitem" data-account-history="{{ $row['account']->id }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Ver histórico</button>
                      <a class="menu__item" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['edit_account' => $row['account']->id])) }}#novaConta"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar conta</a>
                      <form method="POST" action="{{ route('accounts.archive', $row['account']) }}">
                        @csrf @method('PATCH')
                        <button class="menu__item menu__item--danger menu__item--sep" type="submit" role="menuitem"><i class="fa-solid fa-box-archive" aria-hidden="true"></i>Arquivar conta</button>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="account-card__balance" data-money>{{ Money::format($row['current'], $hide) }}</div>
                <div class="account-card__caption">Saldo atual</div>
                <div class="account-card__split">
                  <span class="account-card__figure">
                    <span class="account-card__figure-label">Saldo inicial</span>
                    <span class="account-card__figure-value" data-money>{{ Money::format($row['account']->initial_balance, $hide) }}</span>
                  </span>
                  <span class="account-card__figure account-card__figure--right">
                    <span class="account-card__figure-label">Variação</span>
                    <span class="account-card__figure-value {{ bccomp($variacao, '0', 2) >= 0 ? 'is-positive' : 'is-negative' }}" data-money>{{ bccomp($variacao, '0', 2) >= 0 ? '+' : '' }}{{ Money::format($variacao, $hide) }}</span>
                  </span>
                </div>
                <div class="account-card__note">Saldo calculado a partir das suas movimentações</div>
              </article>
            @endforeach

            <button class="add-card" type="button" data-goto="novaConta">
              <i class="fa-solid fa-plus" aria-hidden="true"></i>
              <span>Adicionar conta manual</span>
            </button>
          </div>

          @foreach($dashboard['accounts'] as $row)
            <section class="panel history" data-enter hidden data-account-panel="{{ $row['account']->id }}">
              <div class="history__head">
                <span class="history__title-wrap">
                  <span class="history__title">Histórico · {{ $row['account']->name }}</span>
                  <span class="history__sub">{{ $row['account']->type->label() }} · {{ count($row['history']) }} {{ count($row['history']) === 1 ? 'movimentação' : 'movimentações' }}</span>
                </span>
                <span class="history__figures">
                  <span class="account-card__figure">
                    <span class="account-card__figure-label">Inicial</span>
                    <span class="account-card__figure-value" data-money>{{ Money::format($row['account']->initial_balance, $hide) }}</span>
                  </span>
                  <span class="account-card__figure">
                    <span class="account-card__figure-label">Atual</span>
                    <span class="account-card__figure-value" data-money>{{ Money::format($row['current'], $hide) }}</span>
                  </span>
                  {{-- Porta de entrada do extrato completo (paginado, com busca
                       e filtro por tipo). Sem este link a página existia mas era
                       inalcançável: nada no app apontava para accounts.show. --}}
                  <a class="btn-outline-hard btn-outline--sm" href="{{ route('accounts.show', $row['account']) }}">
                    <i class="fa-solid fa-list" aria-hidden="true"></i>Extrato completo
                  </a>
                  <button class="btn-icon btn-icon--sm" type="button" aria-label="Fechar histórico" data-account-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </span>
              </div>
              <div class="history__row history__row--head">
                <span>Data</span><span>Movimentação</span><span class="is-right">Valor</span><span class="is-right">Saldo após</span>
              </div>
              @forelse($row['history'] as $movimento)
                <div class="history__row">
                  <span class="history__date">{{ $movimento['date']->format('d/m') }}</span>
                  <span class="history__desc">{{ $movimento['description'] }}</span>
                  <span class="history__value {{ bccomp($movimento['amount'], '0', 2) > 0 ? 'is-in' : '' }}" data-money>{{ Money::format($movimento['amount'], $hide) }}</span>
                  <span class="history__after" data-money>{{ Money::format($movimento['balance_after'], $hide) }}</span>
                </div>
              @empty
                <p class="history__empty">Nenhuma movimentação ainda.</p>
              @endforelse
            </section>
          @endforeach

          @if(count($dashboard['archived_accounts']) > 0)
            <section class="archived" data-enter data-archived>
              <div class="archived__bar">
                <span class="archived__label"><i class="fa-solid fa-box-archive" aria-hidden="true"></i><span data-archived-count>{{ count($dashboard['archived_accounts']) }} {{ Str::plural('conta arquivada', count($dashboard['archived_accounts'])) }}</span></span>
                <button class="btn-outline-hard btn-outline--sm" type="button" data-archived-toggle>Mostrar</button>
              </div>
              <div class="grid-accounts" hidden data-archived-list>
                @foreach($dashboard['archived_accounts'] as $row)
                  <article class="account-card is-archived" data-enter data-account="{{ $row['account']->id }}">
                    <div class="account-card__head">
                      <span class="row__icon row__icon--lg"><i class="fa-solid fa-building-columns" aria-hidden="true"></i></span>
                      <span class="account-card__info">
                        <span class="account-card__name">{{ $row['account']->name }}</span>
                        <span class="account-card__type">{{ $row['account']->type->label() }}</span>
                      </span>
                      <div class="menu" data-menu>
                        <button class="btn-icon btn-icon--sm" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Ações da conta" data-menu-btn><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                        <div class="menu__list" role="menu" hidden data-menu-list>
                          <form method="POST" action="{{ route('accounts.restore', $row['account']) }}">
                            @csrf @method('PATCH')
                            <button class="menu__item" type="submit" role="menuitem"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i>Reativar conta</button>
                          </form>
                        </div>
                      </div>
                    </div>
                    <div class="account-card__balance" data-money>{{ Money::format($row['current'], $hide) }}</div>
                    <div class="account-card__note">Arquivada · fora do saldo total</div>
                  </article>
                @endforeach
              </div>
            </section>
          @endif
        </section>

        <!-- ============ Cartões ============ -->
        <section class="page stack" data-page="cartoes" hidden>

          @if(count($dashboard['credit_cards']) > 0)
              <div class="grid-3">
                <article class="kpi" data-enter>
                  <div class="kpi__label"><i class="fa-regular fa-credit-card" aria-hidden="true"></i>Faturas em aberto</div>
                  <div class="kpi__value kpi__value--sm" data-money>{{ Money::format($cardsOutstandingTotal, $hide) }}</div>
                  <div class="kpi__note">{{ count($dashboard['credit_cards']) }} {{ Str::plural('cartão ativo', count($dashboard['credit_cards'])) }}</div>
                </article>
                <article class="kpi" data-enter>
                  <div class="kpi__label"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i>Limite disponível</div>
                  <div class="kpi__value kpi__value--sm kpi__value--accent" data-money>{{ Money::format($cardsLimitTotal, $hide) }}</div>
                  <div class="kpi__note">Somado entre os cartões ativos</div>
                </article>
              </div>

              <div class="grid-cards">
                @foreach($dashboard['credit_cards'] as $row)
                  <article class="credit-card" data-enter data-card="{{ $row['card']->id }}">
                    <div class="credit-card__top">
                      <div class="credit-card__brand-row">
                        <span class="credit-card__brand"><i class="fa-regular fa-credit-card" aria-hidden="true"></i>{{ $row['card']->issuer }}</span>
                        <span class="credit-card__actions">
                          <span class="credit-card__state" data-card-state="{{ $row['card']->id }}">Ativo</span>
                          <div class="menu menu--on-dark" data-menu>
                            <button class="btn-icon btn-icon--sm btn-icon--on-dark" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Ações do cartão" data-menu-btn><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                            <div class="menu__list" role="menu" hidden data-menu-list>
                              <button class="menu__item" type="button" role="menuitem" data-card-invoices="{{ $row['card']->id }}"><i class="fa-solid fa-receipt" aria-hidden="true"></i>Ver faturas</button>
                              <button class="menu__item" type="button" role="menuitem" data-card-pay="{{ $row['card']->id }}"><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i>Registrar pagamento</button>
                              {{-- Página do cartão: é para onde o lembrete de
                                   fatura aponta e de onde sai o formulário de
                                   fatura em tela cheia. Nada no app linkava
                                   para cá, então essa jornada ficava morta. --}}
                              <a class="menu__item" role="menuitem" href="{{ route('credit-cards.show', $row['card']) }}"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>Abrir página do cartão</a>
                              <a class="menu__item menu__item--sep" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['edit_card' => $row['card']->id])) }}#cartoes"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar cartão</a>
                            </div>
                          </div>
                        </span>
                      </div>
                      <div class="credit-card__name">{{ $row['card']->name }}</div>
                    </div>
                    <div class="credit-card__body">
                      <div class="credit-card__figures">
                        <span class="credit-card__figure">
                          <span class="credit-card__figure-label">Fatura atual</span>
                          <span class="credit-card__figure-value" data-money>{{ Money::format($row['outstanding'], $hide) }}</span>
                        </span>
                        <span class="credit-card__figure credit-card__figure--right">
                          <span class="credit-card__figure-label">Vence</span>
                          <span class="credit-card__figure-value credit-card__figure-value--sm">{{ $row['next_bill']?->due_date?->format('d/m') ?? '—' }}</span>
                        </span>
                      </div>
                      <div class="credit-card__limit">
                        <div class="credit-card__limit-row"><span>Limite usado</span><span>{{ str_replace('.', ',', $row['limit_used_pct']) }}%</span></div>
                        <div class="track"><span class="track__fill" style="width:{{ min(100, $row['limit_used_pct']) }}%"></span></div>
                      </div>
                    </div>
                  </article>
                @endforeach

                <button class="add-card add-card--tall" type="button" data-modal-open="cartao">
                  <i class="fa-solid fa-plus" aria-hidden="true"></i>
                  <span>Adicionar cartão</span>
                </button>
              </div>

              @foreach($dashboard['credit_cards'] as $row)
                <section class="panel invoices" data-enter hidden data-invoices-panel="{{ $row['card']->id }}">
                  <div class="invoices__head">
                    <span class="invoices__title-wrap">
                      <span class="invoices__title">Faturas · {{ $row['card']->name }} · {{ $row['card']->issuer }}</span>
                      <span class="invoices__sub"><span data-invoice-count>—</span> · <span data-invoice-due>—</span></span>
                    </span>
                    <span class="invoices__nav">
                      <button class="btn-icon btn-icon--sm" type="button" aria-label="Fatura anterior" data-invoice-prev><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                      <span class="invoices__month" data-invoice-month>—</span>
                      <button class="btn-icon btn-icon--sm" type="button" aria-label="Fatura seguinte" data-invoice-next><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                      <button class="btn-icon btn-icon--sm invoices__close" type="button" aria-label="Fechar faturas" data-invoice-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </span>
                  </div>
                  <div class="invoices__summary">
                    <span class="invoices__figures">
                      <span class="invoices__total" data-money data-invoice-total>R$ 0,00</span>
                      <span class="badge-state" data-invoice-state><i class="fa-regular fa-clock" aria-hidden="true"></i>Em aberto</span>
                    </span>
                    <button class="btn-outline btn-outline--sm" type="button" data-invoice-edit><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar fatura</button>
                    <button class="btn-primary btn-primary--sm" type="button" data-invoice-pay><i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i>Registrar pagamento</button>
                  </div>
                  @forelse($row['bills'] as $i => $bill)
                    <div class="invoices__slide" {{ $i === 0 ? '' : 'hidden' }}
                         data-bill-slide
                         data-bill-id="{{ $bill->id }}"
                         data-month="{{ ucfirst($bill->reference_month->translatedFormat('F Y')) }}"
                         data-due="Vence em {{ $bill->due_date->format('d/m/Y') }}"
                         data-due-date="{{ $bill->due_date->toDateString() }}"
                         data-count="{{ $bill->purchases->count() }} {{ Str::plural('lançamento', $bill->purchases->count()) }}"
                         data-total="{{ $bill->total_amount }}"
                         data-paid="{{ $bill->status->value === 'paid' ? '1' : '0' }}"
                         data-can-edit="{{ $creditCardService->canEdit($bill) ? '1' : '0' }}"
                         data-adjustment-type="{{ bccomp($bill->adjustment_amount, '0', 2) < 0 ? 'desconto' : 'acrescimo' }}"
                         data-adjustment-amount="{{ ltrim($bill->adjustment_amount, '-') }}"
                         data-adjustment-reason="{{ $bill->adjustment_reason }}"
                         data-pay-url="{{ route('credit-card-bills.pay', $bill) }}"
                         data-edit-url="{{ route('credit-card-bills.update', $bill) }}">
                      <div data-invoice-rows>
                        @forelse($bill->purchases as $purchase)
                          <div class="invoices__row">
                            <span class="history__date">{{ $purchase->competence_date->format('d/m') }}</span>
                            <span class="history__desc">{{ $purchase->description }}</span>
                            <span class="is-right" data-money>{{ Money::format($purchase->amount, $hide) }}</span>
                          </div>
                        @empty
                          <p class="history__empty">Nenhum lançamento nesta fatura.</p>
                        @endforelse
                      </div>
                    </div>
                  @empty
                    <p class="history__empty" style="padding: 20px 22px;">Nenhuma fatura registrada ainda.</p>
                  @endforelse
                </section>
              @endforeach
          @else
              <section class="empty-state" data-enter data-cards-empty>
                <p>Nenhum cartão cadastrado. <button class="link-btn" type="button" data-modal-open="cartao">Adicionar cartão.</button></p>
              </section>
          @endif
        </section>

        <!-- ============ Categorias ============ -->
        <section class="page stack" data-page="categorias" hidden>
          @php
              $catIncomeCount = $dashboard['categories_overview']->where('type', \App\Enums\CategoryType::Income)->count();
          @endphp
          <div class="page-bar" data-enter>
            <span class="page-bar__note">{{ $dashboard['categories_overview']->count() }} {{ $dashboard['categories_overview']->count() === 1 ? 'categoria' : 'categorias' }} · {{ $catIncomeCount }} {{ $catIncomeCount === 1 ? 'de receita' : 'de receita' }}</span>
            <button class="btn-primary btn-primary--sm" type="button" data-modal-open="categoria"><i class="fa-solid fa-plus" aria-hidden="true"></i>Nova categoria</button>
          </div>

          <div class="grid-cards">
            @foreach($dashboard['categories_overview'] as $category)
              <article class="cat-card" data-enter>
                <div class="cat-card__head">
                  <span class="cat-chip" style="background: {{ $category->color }}"><i class="{{ $category->icon }}" aria-hidden="true"></i></span>
                  <span class="cat-card__body">
                    <span class="cat-card__name">{{ $category->name }}</span>
                    <span class="tx-badge {{ $category->type === \App\Enums\CategoryType::Income ? 'is-done' : 'is-void' }}"><i class="fa-solid {{ $category->type === \App\Enums\CategoryType::Income ? 'fa-arrow-down' : 'fa-arrow-up' }}" aria-hidden="true"></i>{{ $category->type->label() }}</span>
                  </span>
                  <div class="menu" data-menu>
                    <button class="btn-icon btn-icon--sm" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Ações" data-menu-btn><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                    <div class="menu__list" role="menu" hidden data-menu-list>
                      <a class="menu__item" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['edit_category' => $category->id])) }}#categorias"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar categoria</a>
                      <a class="menu__item" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['tx_category' => $category->id])) }}#transacoes"><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>Ver lançamentos</a>
                      <form method="POST" action="{{ route('categories.destroy', $category) }}">
                        @csrf @method('DELETE')
                        <button class="menu__item menu__item--danger menu__item--sep" type="submit" role="menuitem"><i class="fa-regular fa-trash-can" aria-hidden="true"></i>Excluir categoria</button>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="cat-card__foot">{{ $category->transactions_count }} {{ $category->transactions_count === 1 ? 'lançamento' : 'lançamentos' }}</div>
              </article>
            @endforeach
            <button class="add-card" type="button" data-enter data-modal-open="categoria">
              <i class="fa-solid fa-plus" aria-hidden="true"></i>
              <span>Nova categoria</span>
            </button>
          </div>
        </section>

        <!-- ============ Orçamentos ============ -->
        <section class="page stack" data-page="orcamentos" hidden>
          @php
              $budMonthDate = \Illuminate\Support\Carbon::create($budgetYear, $budgetMonth)->startOfMonth();
              $budOrcado = $budgetsPage->reduce(fn ($t, $b) => bcadd($t, $b['budget']->limit_amount, 2), '0.00');
              $budGasto = $budgetsPage->reduce(fn ($t, $b) => bcadd($t, $b['metrics']['used'], 2), '0.00');
              $budDisponivel = bcsub($budOrcado, $budGasto, 2);
          @endphp
          <div class="page-bar" data-enter>
            <span class="month-nav">
              <a class="btn-icon btn-icon--sm" aria-label="Mês anterior" href="{{ route('dashboard', array_merge($filters, ['bud_month' => $budMonthDate->copy()->subMonth()->month, 'bud_year' => $budMonthDate->copy()->subMonth()->year])) }}#orcamentos"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
              <span class="month-nav__label">{{ ucfirst($budMonthDate->translatedFormat('F Y')) }}</span>
              <a class="btn-icon btn-icon--sm" aria-label="Próximo mês" href="{{ route('dashboard', array_merge($filters, ['bud_month' => $budMonthDate->copy()->addMonth()->month, 'bud_year' => $budMonthDate->copy()->addMonth()->year])) }}#orcamentos"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
            </span>
            <span class="page-bar__actions">
              <form method="POST" action="{{ route('budgets.copy') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $budgetMonth }}">
                <input type="hidden" name="year" value="{{ $budgetYear }}">
                <button class="btn-outline-hard btn-outline--sm" type="submit"><i class="fa-regular fa-copy" aria-hidden="true"></i>Copiar mês anterior</button>
              </form>
              <button class="btn-primary btn-primary--sm" type="button" data-modal-open="orcamento"><i class="fa-solid fa-plus" aria-hidden="true"></i>Novo orçamento</button>
            </span>
          </div>

          <div class="grid-3">
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-sliders" aria-hidden="true"></i>Orçado no mês</div>
              <div class="kpi__value" data-money>{{ Money::format($budOrcado, $hide) }}</div>
              <div class="kpi__note">{{ $budgetsPage->count() }} {{ $budgetsPage->count() === 1 ? 'categoria com limite' : 'categorias com limite' }}</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-receipt" aria-hidden="true"></i>Gasto até agora</div>
              <div class="kpi__value" data-money>{{ Money::format($budGasto, $hide) }}</div>
              <div class="kpi__note">{{ Money::percentage($budGasto, $budOrcado) }}% do orçamento</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-wallet" aria-hidden="true"></i>Ainda disponível</div>
              <div class="kpi__value {{ bccomp($budDisponivel, '0', 2) >= 0 ? 'is-positive' : 'is-negative' }}" data-money>{{ Money::format($budDisponivel, $hide) }}</div>
              <div class="kpi__note">Se o ritmo continuar, {{ bccomp($budDisponivel, '0', 2) >= 0 ? 'sobra' : 'falta' }} no mês</div>
            </article>
          </div>

          @if($budgetsPage->isEmpty())
            <section class="empty-state" data-enter>
              <p>Nenhum orçamento neste mês. <button class="link-btn" type="button" data-modal-open="orcamento">Criar o primeiro.</button></p>
            </section>
          @else
            <section class="panel budget" data-enter>
              @foreach($budgetsPage as $row)
                @php $level = $row['metrics']['level']; @endphp
                <div class="budget__row">
                  <div class="budget__head">
                    <span class="cat-chip cat-chip--sm" style="background: {{ $row['budget']->category->color }}"><i class="{{ $row['budget']->category->icon }}" aria-hidden="true"></i></span>
                    <span class="budget__name">{{ $row['budget']->category->name }}</span>
                    @if($level === 'danger')
                      <span class="tx-badge is-over"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>Estourou o limite</span>
                    @elseif($level === 'warning')
                      <span class="tx-badge is-pending"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Perto do limite</span>
                    @endif
                    <span class="budget__figures">
                      <span class="budget__spent" data-money>{{ Money::format($row['metrics']['used'], $hide) }}</span>
                      <span class="budget__limit">de <span data-money>{{ Money::format($row['budget']->limit_amount, $hide) }}</span></span>
                    </span>
                    <div class="menu" data-menu>
                      <button class="btn-icon btn-icon--sm" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Ações" data-menu-btn><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                      <div class="menu__list" role="menu" hidden data-menu-list>
                        <a class="menu__item" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['edit_budget' => $row['budget']->id])) }}#orcamentos"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar limite</a>
                        <a class="menu__item" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['tx_category' => $row['budget']->category_id])) }}#transacoes"><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>Ver lançamentos</a>
                        <form method="POST" action="{{ route('budgets.destroy', $row['budget']) }}">
                          @csrf @method('DELETE')
                          <button class="menu__item menu__item--danger menu__item--sep" type="submit" role="menuitem"><i class="fa-regular fa-trash-can" aria-hidden="true"></i>Remover limite</button>
                        </form>
                      </div>
                    </div>
                  </div>
                  <div class="budget__bar-row">
                    <span class="track"><span class="track__fill {{ $level === 'danger' ? 'is-over' : ($level === 'warning' || $level === 'attention' ? 'is-near' : '') }}" style="width: {{ min(100, $row['metrics']['percentage']) }}%"></span></span>
                    <span class="budget__pct {{ $level === 'danger' ? 'is-over' : ($level === 'warning' || $level === 'attention' ? 'is-near' : '') }}">{{ str_replace('.', ',', $row['metrics']['percentage']) }}%</span>
                    <span class="budget__left">{{ bccomp($row['metrics']['remaining'], '0', 2) >= 0 ? 'Faltam' : 'Excedeu' }} <span data-money>{{ Money::format($row['metrics']['remaining'] < 0 ? bcmul($row['metrics']['remaining'], '-1', 2) : $row['metrics']['remaining'], $hide) }}</span></span>
                  </div>
                </div>
              @endforeach
            </section>
          @endif
        </section>

        <!-- ============ Dívidas ============ -->
        <section class="page stack" data-page="dividas" hidden>
          @php
              $debtInstallmentsThisMonth = $dashboard['debts_overview']->flatMap(fn ($row) => $row['debt']->installments)
                  ->whereIn('status', [\App\Enums\DebtInstallmentStatus::Pending, \App\Enums\DebtInstallmentStatus::Overdue])
                  ->filter(fn ($installment) => $installment->due_date->isSameMonth(now()))
                  ->reduce(fn ($t, $installment) => bcadd($t, $installment->amount, 2), '0.00');
              $defaultPayAccount = $dashboard['accounts']->first()['account'] ?? null;
          @endphp
          <div class="grid-3">
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>Total em aberto</div>
              <div class="kpi__value" data-money>{{ Money::format($dashboard['debt_summary']['total'], $hide) }}</div>
              <div class="kpi__note">Parcelas restantes + faturas em aberto</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i>Parcelas do mês</div>
              <div class="kpi__value" data-money>{{ Money::format($debtInstallmentsThisMonth, $hide) }}</div>
              <div class="kpi__note">{{ $dashboard['debts_overview']->count() }} {{ $dashboard['debts_overview']->count() === 1 ? 'dívida ativa' : 'dívidas ativas' }}</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-regular fa-credit-card" aria-hidden="true"></i>Faturas de cartão</div>
              <div class="kpi__value" data-money>{{ Money::format($dashboard['debt_summary']['cards'], $hide) }}</div>
              <div class="kpi__note">Cartões em aberto</div>
            </article>
          </div>

          <div class="page-bar" data-enter>
            <span class="page-bar__note">Parcelas são geradas automaticamente na criação da dívida.</span>
            <button class="btn-primary btn-primary--sm" type="button" data-modal-open="divida"><i class="fa-solid fa-plus" aria-hidden="true"></i>Nova dívida</button>
          </div>

          @if($dashboard['debts_overview']->isEmpty())
            <section class="empty-state" data-enter>
              <p>Nenhuma dívida cadastrada. <button class="link-btn" type="button" data-modal-open="divida">Cadastrar a primeira.</button></p>
            </section>
          @else
            <div class="grid-cards">
              @foreach($dashboard['debts_overview'] as $row)
                @php $nextInstallment = $row['summary']['next']; @endphp
                <article class="goal-card" data-enter data-debt="{{ $row['debt']->id }}">
                  <div class="goal-card__head">
                    <span class="row__icon row__icon--lg"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i></span>
                    <span class="goal-card__body">
                      <span class="goal-card__name">{{ $row['debt']->name }}</span>
                      <span class="goal-card__sub">{{ $row['debt']->creditor }} · {{ $row['debt']->installment_count }}x</span>
                    </span>
                    <div class="menu" data-menu>
                      <button class="btn-icon btn-icon--sm" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Ações" data-menu-btn><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                      <div class="menu__list" role="menu" hidden data-menu-list>
                        <button class="menu__item" type="button" role="menuitem" data-debt-open="{{ $row['debt']->id }}"><i class="fa-solid fa-list-ol" aria-hidden="true"></i>Ver parcelas</button>
                        <a class="menu__item" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['edit_debt' => $row['debt']->id])) }}#dividas"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar dívida</a>
                        <form method="POST" action="{{ route('debts.destroy', $row['debt']) }}">
                          @csrf @method('DELETE')
                          <button class="menu__item menu__item--danger menu__item--sep" type="submit" role="menuitem"><i class="fa-regular fa-circle-check" aria-hidden="true"></i>Encerrar dívida</button>
                        </form>
                      </div>
                    </div>
                  </div>
                  <div class="goal-card__value" data-money>{{ Money::format($row['summary']['remaining'], $hide) }}</div>
                  <div class="goal-card__caption">Restante · parcela de <span data-money>{{ Money::format($nextInstallment->amount ?? '0.00', $hide) }}</span></div>
                  <div class="goal-card__bar">
                    <span class="track"><span class="track__fill" style="width: {{ min(100, $row['summary']['percentage']) }}%"></span></span>
                    <span class="goal-card__pct">{{ str_replace('.', ',', $row['summary']['percentage']) }}%</span>
                  </div>
                  <div class="goal-card__foot"><span>{{ $row['summary']['paid_count'] }} de {{ $row['debt']->installment_count }} parcelas</span><span>{{ $nextInstallment ? 'Próxima em '.$nextInstallment->due_date->format('d/m/Y') : 'Quitada' }}</span></div>
                </article>
              @endforeach
            </div>

            @foreach($dashboard['debts_overview'] as $row)
              <section class="panel history" data-enter hidden data-debt-panel="{{ $row['debt']->id }}">
                <div class="history__head">
                  <span class="history__title-wrap">
                    <span class="history__title">Parcelas · {{ $row['debt']->name }}</span>
                    <span class="history__sub">{{ $row['debt']->creditor }} · {{ $row['debt']->installment_count }}x · total de <span data-money>{{ Money::format($row['debt']->expected_total_amount, $hide) }}</span></span>
                  </span>
                  <button class="btn-icon btn-icon--sm" type="button" aria-label="Fechar parcelas" data-debt-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                @foreach($row['debt']->installments as $installment)
                  <div class="inst__row">
                    <span class="inst__num">{{ $installment->number }}/{{ $row['debt']->installment_count }}</span>
                    <span class="inst__link">
                      @if($installment->transaction_id)
                        <i class="fa-solid fa-link" aria-hidden="true"></i>Despesa vinculada em {{ $installment->transaction?->account?->name ?? 'conta' }}
                      @endif
                    </span>
                    <span class="inst__due">{{ $installment->due_date->format('d/m/Y') }}</span>
                    <span class="inst__value" data-money>{{ Money::format($installment->amount, $hide) }}</span>
                    <span class="inst__state">
                      @if($installment->status === \App\Enums\DebtInstallmentStatus::Paid)
                        <span class="tx-badge is-done"><i class="fa-solid fa-check" aria-hidden="true"></i>Paga</span>
                      @elseif($installment->status === \App\Enums\DebtInstallmentStatus::Cancelled)
                        <span class="tx-badge is-void"><i class="fa-solid fa-ban" aria-hidden="true"></i>Cancelada</span>
                      @else
                        <span class="tx-badge is-pending"><i class="fa-regular fa-clock" aria-hidden="true"></i>{{ $installment->status->label() }}</span>
                        @if($defaultPayAccount)
                          <form method="POST" action="{{ route('debts.installments.pay', $installment) }}">
                            @csrf
                            <input type="hidden" name="account_id" value="{{ $defaultPayAccount->id }}">
                            <button class="btn-outline-hard btn-outline--xs" type="submit">Baixar</button>
                          </form>
                        @else
                          <a class="btn-outline-hard btn-outline--xs" href="{{ route('dashboard') }}#contas" title="Pagar uma parcela registra uma despesa numa conta — cadastre uma conta ativa primeiro">Cadastrar conta</a>
                        @endif
                      @endif
                    </span>
                  </div>
                @endforeach
              </section>
            @endforeach
          @endif
        </section>

        <!-- ============ Investimentos ============ -->
        <section class="page stack" data-page="investimentos" hidden>
          @php
              $investAplicado = $dashboard['investments_overview']->reduce(fn ($t, $r) => bcadd($t, $r['investment']->invested_amount, 2), '0.00');
              $investAtual = $dashboard['investments_overview']->reduce(fn ($t, $r) => bcadd($t, $r['investment']->current_amount, 2), '0.00');
              $investRendimento = bcsub($investAtual, $investAplicado, 2);
          @endphp
          <div class="grid-3">
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-seedling" aria-hidden="true"></i>Valor atual</div>
              <div class="kpi__value" data-money>{{ Money::format($investAtual, $hide) }}</div>
              <div class="kpi__note">{{ $dashboard['investments_overview']->count() }} {{ $dashboard['investments_overview']->count() === 1 ? 'aplicação na carteira' : 'aplicações na carteira' }}</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>Total aplicado</div>
              <div class="kpi__value" data-money>{{ Money::format($investAplicado, $hide) }}</div>
              <div class="kpi__note">Soma dos aportes menos resgates</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-chart-line" aria-hidden="true"></i>Rendimento</div>
              <div class="kpi__value {{ bccomp($investRendimento, '0', 2) >= 0 ? 'is-positive' : 'is-negative' }}" data-money>{{ Money::format($investRendimento, $hide) }}</div>
              <div class="kpi__note">{{ Money::percentage($investRendimento, $investAplicado) }}% sobre o aplicado</div>
            </article>
          </div>

          <div class="page-bar" data-enter>
            <span class="page-bar__note">Rendimentos entram no histórico e não contam como aporte.</span>
            <button class="btn-primary btn-primary--sm" type="button" data-modal-open="investimento"><i class="fa-solid fa-plus" aria-hidden="true"></i>Nova aplicação</button>
          </div>

          @if($dashboard['investments_overview']->isEmpty())
            <section class="empty-state" data-enter>
              <p>Nenhum investimento cadastrado. <button class="link-btn" type="button" data-modal-open="investimento">Cadastrar o primeiro.</button></p>
            </section>
          @else
            <div class="grid-cards">
              @foreach($dashboard['investments_overview'] as $row)
                <article class="account-card" data-enter data-invest="{{ $row['investment']->id }}">
                  <div class="account-card__head">
                    <span class="row__icon row__icon--lg"><i class="fa-solid fa-seedling" aria-hidden="true"></i></span>
                    <span class="account-card__info">
                      <span class="account-card__name">{{ $row['investment']->name }}</span>
                      <span class="account-card__type">{{ $row['investment']->type->label() }} · {{ $row['investment']->institution }}</span>
                    </span>
                    <div class="menu" data-menu>
                      <button class="btn-icon btn-icon--sm" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Ações" data-menu-btn><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                      <div class="menu__list" role="menu" hidden data-menu-list>
                        <button class="menu__item" type="button" role="menuitem" data-invest-open="{{ $row['investment']->id }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Ver histórico</button>
                        {{-- Porta de entrada do histórico completo (paginado, com
                             filtro por tipo de operação). Sem este link a página
                             existia mas nada no app apontava para investments.show. --}}
                        <a class="menu__item" role="menuitem" href="{{ route('investments.show', $row['investment']) }}"><i class="fa-solid fa-list" aria-hidden="true"></i>Histórico completo</a>
                        <button class="menu__item" type="button" role="menuitem" data-modal-open="aporte" data-aporte-nome="{{ $row['investment']->name }}" data-aporte-url="{{ route('investments.operations.store', $row['investment']) }}" data-aporte-tipo="contribution"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>Registrar aporte</button>
                        <button class="menu__item" type="button" role="menuitem" data-modal-open="aporte" data-aporte-nome="{{ $row['investment']->name }}" data-aporte-url="{{ route('investments.operations.store', $row['investment']) }}" data-aporte-tipo="withdrawal"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>Registrar resgate</button>
                        <a class="menu__item menu__item--sep" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['edit_investment' => $row['investment']->id])) }}#investimentos"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar aplicação</a>
                      </div>
                    </div>
                  </div>
                  <div class="account-card__balance" data-money>{{ Money::format($row['investment']->current_amount, $hide) }}</div>
                  <div class="account-card__caption">Valor atual</div>
                  <div class="account-card__split">
                    <span class="account-card__figure">
                      <span class="account-card__figure-label">Aplicado</span>
                      <span class="account-card__figure-value" data-money>{{ Money::format($row['investment']->invested_amount, $hide) }}</span>
                    </span>
                    <span class="account-card__figure account-card__figure--right">
                      <span class="account-card__figure-label">Rendimento</span>
                      <span class="account-card__figure-value {{ bccomp($row['metrics']['profit'], '0', 2) >= 0 ? 'is-positive' : 'is-negative' }}" data-money>{{ bccomp($row['metrics']['profit'], '0', 2) >= 0 ? '+' : '' }}{{ Money::format($row['metrics']['profit'], $hide) }} · {{ $row['metrics']['return_percentage'] }}%</span>
                    </span>
                  </div>
                </article>
              @endforeach
            </div>

            @foreach($dashboard['investments_overview'] as $row)
              <section class="panel history" data-enter hidden data-invest-panel="{{ $row['investment']->id }}">
                <div class="history__head">
                  <span class="history__title-wrap">
                    <span class="history__title">Histórico · {{ $row['investment']->name }}</span>
                    <span class="history__sub">{{ $row['investment']->type->label() }} · {{ $row['investment']->institution }} · {{ $row['investment']->operations->count() }} {{ $row['investment']->operations->count() === 1 ? 'movimentação' : 'movimentações' }}</span>
                  </span>
                  <span class="history__figures">
                    <span class="account-card__figure">
                      <span class="account-card__figure-label">Aplicado</span>
                      <span class="account-card__figure-value" data-money>{{ Money::format($row['investment']->invested_amount, $hide) }}</span>
                    </span>
                    <span class="account-card__figure">
                      <span class="account-card__figure-label">Atual</span>
                      <span class="account-card__figure-value" data-money>{{ Money::format($row['investment']->current_amount, $hide) }}</span>
                    </span>
                    <button class="btn-icon btn-icon--sm" type="button" aria-label="Fechar histórico" data-invest-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                  </span>
                </div>
                @forelse($row['investment']->operations as $operation)
                  <div class="inv__row">
                    <span class="history__date">{{ $operation->operation_date->format('d/m') }}</span>
                    <span class="history__desc">{{ $operation->type->label() }}</span>
                    <span><span class="tx-badge {{ in_array($operation->type, [\App\Enums\InvestmentOperationType::Yield, \App\Enums\InvestmentOperationType::Dividend], true) ? 'is-done' : 'is-void' }}">{{ $operation->type->label() }}</span></span>
                    <span class="history__value {{ in_array($operation->type, [\App\Enums\InvestmentOperationType::Contribution, \App\Enums\InvestmentOperationType::Yield, \App\Enums\InvestmentOperationType::Dividend, \App\Enums\InvestmentOperationType::Buy], true) ? 'is-in' : '' }}" data-money>{{ in_array($operation->type, [\App\Enums\InvestmentOperationType::Withdrawal, \App\Enums\InvestmentOperationType::Sell], true) ? '− ' : '+ ' }}{{ Money::format($operation->amount, $hide) }}</span>
                  </div>
                @empty
                  <p class="history__empty">Nenhuma movimentação ainda.</p>
                @endforelse
              </section>
            @endforeach
          @endif
        </section>

        <!-- ============ Metas ============ -->
        <section class="page stack" data-page="metas" hidden>
          @php
              $goalsAtivas = $dashboard['goals_overview']->filter(fn ($r) => $r['goal']->status->value === 'active');
              $metaGuardado = $goalsAtivas->reduce(fn ($t, $r) => bcadd($t, $r['current'], 2), '0.00');
              $metaAlvo = $goalsAtivas->reduce(fn ($t, $r) => bcadd($t, $r['goal']->target_amount, 2), '0.00');
              $metaFalta = bcsub($metaAlvo, $metaGuardado, 2);
          @endphp
          <div class="grid-3">
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-bullseye" aria-hidden="true"></i>Guardado nas metas</div>
              <div class="kpi__value" data-money>{{ Money::format($metaGuardado, $hide) }}</div>
              <div class="kpi__note">{{ $goalsAtivas->count() }} {{ $goalsAtivas->count() === 1 ? 'meta ativa' : 'metas ativas' }}</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>Somatório dos alvos</div>
              <div class="kpi__value" data-money>{{ Money::format($metaAlvo, $hide) }}</div>
              <div class="kpi__note">{{ Money::percentage($metaGuardado, $metaAlvo) }}% do total planejado</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-piggy-bank" aria-hidden="true"></i>Falta guardar</div>
              <div class="kpi__value" data-money>{{ Money::format($metaFalta, $hide) }}</div>
              <div class="kpi__note">Considerando todas as metas em aberto</div>
            </article>
          </div>

          <div class="page-bar" data-enter>
            <span class="page-bar__note">O aporte sai de uma conta e entra no progresso da meta.</span>
            <button class="btn-primary btn-primary--sm" type="button" data-modal-open="meta"><i class="fa-solid fa-plus" aria-hidden="true"></i>Nova meta</button>
          </div>

          @if($dashboard['goals_overview']->isEmpty())
            <section class="empty-state" data-enter>
              <p>Nenhuma meta cadastrada. <button class="link-btn" type="button" data-modal-open="meta">Criar a primeira.</button></p>
            </section>
          @else
            <div class="grid-cards">
              @foreach($dashboard['goals_overview'] as $row)
                @php
                    $goalState = $row['goal']->status->value === 'completed' ? 'Concluída' : (bccomp($row['percentage'], '80', 2) >= 0 ? 'Quase lá' : 'Em andamento');
                @endphp
                <article class="goal-card" data-enter data-goal="{{ $row['goal']->id }}">
                  <div class="goal-card__head">
                    <span class="row__icon row__icon--lg" style="background: {{ $row['goal']->color }}"><i class="fa-solid fa-bullseye" aria-hidden="true"></i></span>
                    <span class="goal-card__body">
                      <span class="goal-card__name">{{ $row['goal']->name }}</span>
                      <span class="tx-badge {{ $goalState === 'Concluída' ? 'is-done' : 'is-void' }}">{{ $goalState }}</span>
                    </span>
                    <div class="menu" data-menu>
                      <button class="btn-icon btn-icon--sm" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Ações" data-menu-btn><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                      <div class="menu__list" role="menu" hidden data-menu-list>
                        <button class="menu__item" type="button" role="menuitem" data-goal-open="{{ $row['goal']->id }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Ver aportes</button>
                        <button class="menu__item" type="button" role="menuitem" data-modal-open="aporte-meta" data-meta-goal="{{ $row['goal']->id }}" data-meta-nome="{{ $row['goal']->name }}" data-meta-url="{{ route('goals.contribute', $row['goal']) }}"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>Registrar aporte</button>
                        <a class="menu__item menu__item--sep" role="menuitem" href="{{ route('dashboard', array_merge($filters, ['edit_goal' => $row['goal']->id])) }}#metas"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Editar meta</a>
                      </div>
                    </div>
                  </div>
                  <div class="goal-card__value" data-money>{{ Money::format($row['current'], $hide) }}</div>
                  <div class="goal-card__caption">de <span data-money>{{ Money::format($row['goal']->target_amount, $hide) }}</span>{{ $row['goal']->deadline ? ' · '.ucfirst($row['goal']->deadline->translatedFormat('F Y')) : '' }}</div>
                  <div class="goal-card__bar">
                    <span class="track"><span class="track__fill" style="width: {{ min(100, $row['percentage']) }}%"></span></span>
                    <span class="goal-card__pct">{{ str_replace('.', ',', $row['percentage']) }}%</span>
                  </div>
                  <div class="goal-card__foot"><span>{{ bccomp($row['remaining'], '0', 2) > 0 ? 'Faltam '.Money::format($row['remaining'], $hide) : 'Meta atingida' }}</span></div>
                </article>
              @endforeach
            </div>

            @foreach($dashboard['goals_overview'] as $row)
              <section class="panel history" data-enter hidden data-goal-panel="{{ $row['goal']->id }}">
                <div class="history__head">
                  <span class="history__title-wrap">
                    <span class="history__title">Aportes · {{ $row['goal']->name }}</span>
                    <span class="history__sub">Guardado até agora: <span data-money>{{ Money::format($row['current'], $hide) }}</span></span>
                  </span>
                  <button class="btn-icon btn-icon--sm" type="button" aria-label="Fechar aportes" data-goal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                @forelse($row['goal']->contributions as $contribution)
                  <div class="inst__row">
                    <span class="inst__num">{{ $loop->count - $loop->iteration + 1 }}º aporte</span>
                    <span class="inst__link"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>Somado ao progresso</span>
                    <span class="inst__due">{{ $contribution->contributed_at->format('d/m/Y') }}</span>
                    <span class="inst__value" data-money>{{ Money::format($contribution->amount, $hide) }}</span>
                    <span class="inst__state"></span>
                  </div>
                @empty
                  <p class="card__text card__text--flush">Nenhum aporte registrado ainda além do valor inicial da meta.</p>
                @endforelse
              </section>
            @endforeach
          @endif
        </section>

        <!-- ============ Relatórios ============ -->
        <section class="page stack" data-page="relatorios" hidden>
          @php
              $repIncomeTotal = collect($dashboard['charts']['income'])->reduce(fn ($t, $v) => bcadd($t, $v, 2), '0.00');
              $repExpenseTotal = collect($dashboard['charts']['expense'])->reduce(fn ($t, $v) => bcadd($t, $v, 2), '0.00');
              $repResult = bcsub($repIncomeTotal, $repExpenseTotal, 2);
              $repMaxBar = max(1, ...array_map('floatval', array_merge($dashboard['charts']['income'], $dashboard['charts']['expense'])));
              $repCategoryLabels = $dashboard['charts']['expense_categories']['labels'];
              $repCategoryValues = $dashboard['charts']['expense_categories']['values'];
              $repMaxCategory = max(1, ...array_map('floatval', $repCategoryValues->all() ?: [0]));
              $repCategoryColors = $dashboard['categories_overview']->pluck('color', 'name');
              $repCategoryIcons = $dashboard['categories_overview']->pluck('icon', 'name');
              $repDeltas = $dashboard['report_deltas'];
              $repDeltaIcon = fn ($state) => match ($state) {
                  'alta' => 'fa-solid fa-arrow-trend-up',
                  'baixa' => 'fa-solid fa-arrow-trend-down',
                  default => 'fa-solid fa-equals',
              };
              $repDeltaLabel = function ($delta) {
                  if ($delta['state'] === 'estavel') return 'Estável vs. mês anterior';
                  $sign = $delta['state'] === 'alta' ? '+' : '−';
                  $pct = $delta['percentage'] === null ? '' : $sign.number_format(abs($delta['percentage']), 1, ',', '.').'% ';
                  return $pct.'vs. mês anterior';
              };
              $repDetail = $dashboard['report_detail'];
              $repPaginator = $repDetail['paginator'];
              $repSortUrl = fn ($column) => route('dashboard', array_merge($filters, [
                  'rep_sort' => $column,
                  'rep_dir' => ($repDetail['sort'] === $column && $repDetail['dir'] === 'asc') ? 'desc' : 'asc',
              ])).'#relatorios';
              $repSortIcon = fn ($column) => $repDetail['sort'] !== $column ? 'fa-solid fa-sort' : ($repDetail['dir'] === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down');
              $repOpen = request()->hasAny(['rep_sort', 'rep_page']);
          @endphp
          <div class="filters" data-enter>
            <span class="page-bar__note">Últimos 6 meses · {{ $dashboard['period']['start']->translatedFormat('M/Y') }} a {{ $dashboard['period']['end']->translatedFormat('M/Y') }}</span>
            <span class="filters__spacer"></span>
            <a class="btn-outline-hard btn-outline--sm" href="{{ route('reports.export', ['start_date' => $dashboard['period']['start']->toDateString(), 'end_date' => $dashboard['period']['end']->toDateString()]) }}"><i class="fa-solid fa-file-csv" aria-hidden="true"></i>Exportar CSV</a>
          </div>

          <div class="grid-3">
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>Receitas no período</div>
              <div class="kpi__value is-positive" data-money>{{ Money::format($repIncomeTotal, $hide) }}</div>
              <div class="kpi__note">6 meses considerados</div>
              <div class="kpi__delta"><i class="{{ $repDeltaIcon($repDeltas['income']['state']) }}" aria-hidden="true"></i>{{ $repDeltaLabel($repDeltas['income']) }}</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>Despesas no período</div>
              <div class="kpi__value" data-money>{{ Money::format($repExpenseTotal, $hide) }}</div>
              <div class="kpi__note">Média de {{ Money::format(bcdiv($repExpenseTotal, '6', 2), $hide) }} por mês</div>
              <div class="kpi__delta"><i class="{{ $repDeltaIcon($repDeltas['expense']['state']) }}" aria-hidden="true"></i>{{ $repDeltaLabel($repDeltas['expense']) }}</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>Resultado</div>
              <div class="kpi__value {{ bccomp($repResult, '0', 2) >= 0 ? 'is-positive' : 'is-negative' }}" data-money>{{ Money::format($repResult, $hide) }}</div>
              <div class="kpi__note">{{ Money::percentage($repResult, $repIncomeTotal) }}% das receitas sobraram</div>
              <div class="kpi__delta"><i class="{{ $repDeltaIcon($repDeltas['result']['state']) }}" aria-hidden="true"></i>{{ $repDeltaLabel($repDeltas['result']) }}</div>
            </article>
          </div>

          <section class="panel chart" data-enter>
            <div class="chart__head">
              <span class="chart__title">Receitas e despesas por mês</span>
              <span class="chart__legend">
                <span><span class="chart__key chart__key--in" aria-hidden="true"></span>Receitas</span>
                <span><span class="chart__key chart__key--out" aria-hidden="true"></span>Despesas</span>
              </span>
            </div>
            <div class="chart__plot">
              @foreach($dashboard['charts']['labels'] as $i => $label)
                <span class="chart__col">
                  <span class="chart__bars">
                    <span class="chart__bar chart__bar--in bar" style="height: {{ max(4, (float) $dashboard['charts']['income'][$i] / $repMaxBar * 100) }}%" title="Receitas {{ Money::format($dashboard['charts']['income'][$i], false) }}"></span>
                    <span class="chart__bar chart__bar--out bar" style="height: {{ max(4, (float) $dashboard['charts']['expense'][$i] / $repMaxBar * 100) }}%" title="Despesas {{ Money::format($dashboard['charts']['expense'][$i], false) }}"></span>
                  </span>
                  <span class="chart__label">{{ ucfirst($label) }}</span>
                </span>
              @endforeach
            </div>
          </section>

          <section class="panel chart" data-enter>
            <div class="chart__title">Gastos por categoria no mês</div>
            @if($repCategoryLabels->isEmpty())
              <p class="history__empty">Nenhuma despesa categorizada neste período.</p>
            @else
              <div class="rank">
                @foreach($repCategoryLabels as $i => $label)
                  <span class="rank__row">
                    <span class="cat-chip cat-chip--sm" style="background: {{ $repCategoryColors[$label] ?? '#5B5A54' }}"><i class="{{ $repCategoryIcons[$label] ?? 'fa-solid fa-tag' }}" aria-hidden="true"></i></span>
                    <span class="rank__name">{{ $label }}</span>
                    <span class="track"><span class="track__fill" style="width: {{ max(4, (float) $repCategoryValues[$i] / $repMaxCategory * 100) }}%; background: {{ $repCategoryColors[$label] ?? '#5B5A54' }}"></span></span>
                    <span class="rank__value" data-money>{{ Money::format($repCategoryValues[$i], $hide) }}</span>
                  </span>
                @endforeach
              </div>
            @endif
          </section>

          <section class="report-detail" data-enter>
            <div class="report-detail__head">
              <span>
                <span class="report-detail__title">Lançamentos do recorte</span>
                <span class="report-detail__sub">{{ $repPaginator->total() }} {{ $repPaginator->total() === 1 ? 'lançamento' : 'lançamentos' }} nos últimos 6 meses</span>
              </span>
              <a class="btn-outline btn-outline--sm" href="{{ $repOpen ? route('dashboard', array_diff_key($filters, ['rep_sort' => '', 'rep_dir' => '', 'rep_page' => ''])).'#relatorios' : route('dashboard', array_merge($filters, ['rep_sort' => $repDetail['sort'], 'rep_dir' => $repDetail['dir']])).'#relatorios' }}">
                <i class="fa-solid {{ $repOpen ? 'fa-chevron-up' : 'fa-chevron-down' }}" aria-hidden="true"></i>{{ $repOpen ? 'Ocultar lançamentos' : 'Ver lançamentos' }}
              </a>
            </div>
            <div class="report-detail__body" @unless($repOpen) hidden @endunless>
              <div class="report-detail__row report-detail__row--head">
                <a href="{{ $repSortUrl('data') }}"><span>Data</span><i class="{{ $repSortIcon('data') }}" aria-hidden="true"></i></a>
                <a href="{{ $repSortUrl('desc') }}"><span>Descrição</span><i class="{{ $repSortIcon('desc') }}" aria-hidden="true"></i></a>
                <a href="{{ $repSortUrl('categoria') }}"><span>Categoria</span><i class="{{ $repSortIcon('categoria') }}" aria-hidden="true"></i></a>
                <a href="{{ $repSortUrl('conta') }}"><span>Conta</span><i class="{{ $repSortIcon('conta') }}" aria-hidden="true"></i></a>
                <a class="is-right" href="{{ $repSortUrl('valor') }}"><span>Valor</span><i class="{{ $repSortIcon('valor') }}" aria-hidden="true"></i></a>
              </div>
              <div>
                @foreach($repPaginator as $t)
                  <div class="report-detail__row">
                    <span class="report-detail__date">{{ $t->competence_date->format('d/m/Y') }}</span>
                    <span class="report-detail__desc">{{ $t->description }}</span>
                    <span>{{ $t->category?->name ?? '—' }}</span>
                    <span>{{ $t->account?->name ?? $t->creditCard?->name ?? '—' }}</span>
                    <span class="report-detail__value {{ $t->type->value === 'income' ? 'is-in' : '' }}" data-money>{{ $t->type->value === 'income' ? '+ ' : '− ' }}{{ Money::format($t->amount, $hide) }}</span>
                  </div>
                @endforeach
              </div>
              <div class="history__empty" @unless($repPaginator->isEmpty()) hidden @endunless>Nenhum lançamento nesse recorte.</div>
              @if($repPaginator->total() > 0)
                <div class="tx-foot">
                  <span class="tx-foot__summary">Mostrando {{ $repPaginator->firstItem() }}–{{ $repPaginator->lastItem() }} de {{ $repPaginator->total() }} lançamentos</span>
                  <span class="tx-pager">
                    <a class="btn-icon btn-icon--sm" aria-label="Página anterior" href="{{ $repPaginator->previousPageUrl() ?? '#relatorios' }}#relatorios" style="{{ $repPaginator->onFirstPage() ? 'opacity:.4;pointer-events:none;' : '' }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
                    <span>
                      @for($p = 1; $p <= $repPaginator->lastPage(); $p++)
                        <a class="tx-page {{ $p === $repPaginator->currentPage() ? 'is-current' : '' }}" href="{{ $repPaginator->url($p) }}#relatorios">{{ $p }}</a>
                      @endfor
                    </span>
                    <a class="btn-icon btn-icon--sm" aria-label="Próxima página" href="{{ $repPaginator->nextPageUrl() ?? '#relatorios' }}#relatorios" style="{{ $repPaginator->hasMorePages() ? '' : 'opacity:.4;pointer-events:none;' }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                  </span>
                </div>
              @endif
            </div>
          </section>
        </section>

        <!-- ============ Previsão ============ -->
        <section class="page stack" data-page="previsao" hidden>
          @php
              $prevReceitaPlanejada = auth()->user()->transactions()
                  ->where('type', 'income')
                  ->whereNotNull('recurrence_group_id')
                  ->whereIn('status', ['completed', 'planned', 'overdue'])
                  ->get()->unique('recurrence_group_id')
                  ->reduce(fn ($t, $i) => bcadd($t, $i->amount, 2), '0.00');
              $prevReceitaPrevista = collect($dashboard['forecast_detailed'])->take(3)->reduce(fn ($t, $m) => bcadd($t, $m['income'], 2), '0.00');
              $prevDespesaPrevista = collect($dashboard['forecast_detailed'])->take(3)->reduce(fn ($t, $m) => bcadd($t, $m['expense'], 2), '0.00');
              $prevResultado = bcsub($prevReceitaPrevista, $prevDespesaPrevista, 2);
          @endphp
          <div class="grid-3">
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i>Receita planejada</div>
              <div class="kpi__value" data-money>{{ Money::format($prevReceitaPlanejada, $hide) }}</div>
              <div class="kpi__note">Lançamentos recorrentes já confirmados</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-chart-line" aria-hidden="true"></i>Receita prevista</div>
              <div class="kpi__value" data-money>{{ Money::format($prevReceitaPrevista, $hide) }}</div>
              <div class="kpi__note">Inclui ganhos futuros informados por você</div>
            </article>
            <article class="kpi" data-enter>
              <div class="kpi__label"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>Resultado previsto</div>
              <div class="kpi__value {{ bccomp($prevResultado, '0', 2) >= 0 ? 'is-positive' : 'is-negative' }}" data-money>{{ Money::format($prevResultado, $hide) }}</div>
              <div class="kpi__note">Sobra estimada nos próximos 3 meses</div>
            </article>
          </div>

          <div class="page-bar" data-enter>
            <span class="page-bar__note">A projeção usa lançamentos recorrentes e ganhos futuros que você informar.</span>
            <button class="btn-primary btn-primary--sm" type="button" data-modal-open="ganho"><i class="fa-solid fa-plus" aria-hidden="true"></i>Adicionar ganho futuro</button>
          </div>

          @foreach($dashboard['forecast_detailed'] as $mes)
            <section class="panel history" data-enter>
              <div class="history__head">
                <span class="history__title">{{ ucfirst($mes['month']->translatedFormat('F Y')) }}</span>
                <span class="history__figures">
                  <span class="account-card__figure">
                    <span class="account-card__figure-label">Receitas</span>
                    <span class="account-card__figure-value" data-money>{{ Money::format($mes['income'], $hide) }}</span>
                  </span>
                  <span class="account-card__figure">
                    <span class="account-card__figure-label">Despesas</span>
                    <span class="account-card__figure-value" data-money>{{ Money::format($mes['expense'], $hide) }}</span>
                  </span>
                  <span class="account-card__figure">
                    <span class="account-card__figure-label">Resultado</span>
                    <span class="account-card__figure-value {{ bccomp($mes['result'], '0', 2) >= 0 ? 'is-positive' : 'is-negative' }}" data-money>{{ Money::format($mes['result'], $hide) }}</span>
                  </span>
                </span>
              </div>
              @forelse($mes['transactions'] as $t)
                <div class="forecast__row">
                  <span class="history__date">{{ $t->competence_date->format('d/m') }}</span>
                  <span class="history__desc">{{ $t->description }}</span>
                  <span><span class="tx-badge {{ $t->type->value === 'income' ? 'is-done' : 'is-void' }}">{{ $t->type->label() }}</span></span>
                  <span class="history__value {{ $t->type->value === 'income' ? 'is-in' : '' }}" data-money>{{ $t->type->value === 'income' ? '+ ' : '− ' }}{{ Money::format($t->amount, $hide) }}</span>
                </div>
              @empty
                <p class="history__empty">Nenhum lançamento planejado neste mês.</p>
              @endforelse
            </section>
          @endforeach
        </section>

        <!-- ============ Nova conta manual ============ -->
        <section class="page" data-page="novaConta" hidden>
          @php
              $accountTypeKey = fn ($type) => collect($accountTypeTiles)->firstWhere('type', $type)['key'] ?? $accountTypeTiles[0]['key'];
              $accountTypeIcon = fn ($type) => collect($accountTypeTiles)->firstWhere('type', $type)['iconClass'] ?? $accountTypeTiles[0]['iconClass'];
              $selectedTypeKey = old('type') ? $accountTypeKey(old('type')) : ($editAccount ? $accountTypeKey($editAccount->type->value) : $accountTypeTiles[0]['key']);
          @endphp
          <form class="new-account" data-enter method="POST" action="{{ $editAccount ? route('accounts.update', $editAccount) : route('accounts.store') }}">
            @csrf
            @if($editAccount) @method('PATCH') @endif

            <section class="panel new-account__form">
              <div>
                <h2 class="panel__title new-account__title">{{ $editAccount ? 'Editar conta' : 'Dados da conta' }}</h2>
                <p class="panel__sub">Contas manuais servem para dinheiro que não vem de extrato: carteira, cofrinho, conta de outro banco. Você registra o saldo de partida e segue lançando por aqui.</p>
              </div>

              <div class="field">
                <span class="field__label">Tipo de conta</span>
                <div class="option-grid" data-account-type>
                  @foreach($accountTypeTiles as $tile)
                    <button class="option-tile {{ $tile['key'] === $selectedTypeKey ? 'is-selected' : '' }}" type="button" data-value="{{ $tile['key'] }}" data-type="{{ $tile['type'] }}" data-icon-key="{{ $tile['icon'] }}" data-icon="{{ $tile['iconClass'] }}"><i class="{{ $tile['iconClass'] }}"></i>{{ $tile['label'] }}</button>
                  @endforeach
                </div>
                <input type="hidden" name="type" value="{{ old('type', $editAccount?->type?->value ?? $accountTypeTiles[0]['type']) }}" id="novaConta-type">
                <input type="hidden" name="icon" value="{{ $accountTypeIcon(old('type', $editAccount?->type?->value ?? $accountTypeTiles[0]['type'])) }}" id="novaConta-icon">
                @error('type')<span class="field-error">{{ $message }}</span>@enderror
              </div>

              <div class="field-row">
                <label class="field">
                  <span class="field__label">Nome da conta</span>
                  <input class="input" type="text" name="name" placeholder="Ex.: Carteira do dia a dia" value="{{ old('name', $editAccount?->name ?? '') }}" data-account-name required>
                  @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <label class="field">
                  <span class="field__label">Instituição <span class="field__hint">· opcional</span></span>
                  <input class="input" type="text" name="institution" placeholder="Ex.: Nubank, Caixa, dinheiro em espécie" value="{{ old('institution', $editAccount?->institution ?? '') }}" data-account-bank>
                </label>
              </div>

              <div class="field-row">
                <label class="field">
                  <span class="field__label">Saldo de partida</span>
                  <span class="input-group">
                    <span class="input-group__prefix">R$</span>
                    <input class="input-group__input input-group__input--num" type="text" inputmode="decimal" name="initial_balance" placeholder="0,00" value="{{ old('initial_balance', $editAccount ? number_format((float) $editAccount->initial_balance, 2, ',', '.') : '') }}" data-account-balance>
                  </span>
                  @error('initial_balance')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <div class="field">
                  <span class="field__label">Saldo nesta data</span>
                  <x-datepicker name="initial_balance_date" :value="old('initial_balance_date', $editAccount?->initial_balance_date?->toDateString() ?? now()->toDateString())" data-account-date />
                  @error('initial_balance_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
              </div>

              <div class="field">
                <span class="field__label">Cor da conta</span>
                <div class="swatches" data-account-colors>
                  @foreach(['#137A4A' => 'Verde escuro', '#38C172' => 'Verde', '#2F6FEB' => 'Azul', '#E0A21C' => 'Amarelo', '#B3261E' => 'Vermelho', '#6C4BD6' => 'Roxo'] as $hex => $label)
                    <button class="swatch {{ strtoupper(old('color', $editAccount?->color ?? '#137A4A')) === $hex ? 'is-selected' : '' }}" type="button" style="--swatch:{{ $hex }}" data-value="{{ $hex }}" aria-label="{{ $label }}"><i class="fa-solid fa-check" aria-hidden="true"></i></button>
                  @endforeach
                </div>
                <input type="hidden" name="color" value="{{ old('color', $editAccount?->color ?? '#137A4A') }}" id="novaConta-color">
                @error('color')<span class="field-error">{{ $message }}</span>@enderror
              </div>

              <div class="switch-row">
                <button class="switch {{ old('active', $editAccount?->active ?? true) ? 'is-on' : '' }}" type="button" role="switch" aria-checked="{{ old('active', $editAccount?->active ?? true) ? 'true' : 'false' }}" data-account-total><span class="switch__pin"></span></button>
                <span class="switch-row__body">
                  <span class="switch-row__title">Somar no saldo total</span>
                  <span class="switch-row__text">Deixe desligado para acompanhar a conta separadamente, sem afetar os números do painel.</span>
                </span>
              </div>
              <input type="hidden" name="active" value="{{ old('active', $editAccount?->active ?? true) ? '1' : '0' }}" id="novaConta-active">
              <input type="hidden" name="currency" value="BRL">

              <label class="field">
                <span class="field__label">Observação <span class="field__hint">· opcional</span></span>
                <textarea class="input input--area" rows="2" name="notes" placeholder="Para que serve essa conta">{{ old('notes', $editAccount?->notes ?? '') }}</textarea>
              </label>

              <div class="form-foot">
                <button class="btn-ghost" type="button" data-goto="contas">Cancelar</button>
                <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editAccount ? 'Salvar alterações' : 'Salvar conta' }}</button>
              </div>
            </section>

            <div class="new-account__aside">
              <article class="panel preview-card">
                <span class="preview-card__label">Prévia</span>
                <div class="account-card__head preview-card__head">
                  <span class="row__icon row__icon--lg preview-card__icon" data-preview-icon><i class="{{ $accountTypeIcon(old('type', $editAccount?->type?->value ?? $accountTypeTiles[0]['type'])) }}"></i></span>
                  <span class="account-card__info">
                    <span class="account-card__name" data-preview-name>{{ $editAccount?->name ?? 'Nome da conta' }}</span>
                    <span class="account-card__type" data-preview-type>{{ collect($accountTypeTiles)->firstWhere('key', $selectedTypeKey)['label'] ?? $accountTypeTiles[0]['label'] }}</span>
                  </span>
                </div>
                <div class="account-card__balance" data-preview-balance>{{ $editAccount ? Money::format($editAccount->initial_balance, false) : 'R$ 0,00' }}</div>
                <div class="account-card__note" data-preview-note>Saldo informado em {{ $editAccount?->initial_balance_date?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
              </article>

              <article class="tip-card">
                <span class="tip-card__title"><img src="{{ asset('design/assets/capi/capi-rosto.png') }}" alt="">Dica do Capí</span>
                <p class="tip-card__text">Use o saldo que está na conta hoje, não o do começo do mês. A partir dessa data eu passo a somar e subtrair só o que você lançar.</p>
                <a class="tip-card__link" href="{{ route('transactions.import.create') }}">Prefere importar um extrato?<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </article>
            </div>
          </form>
        </section>

        <!-- ============ Capí (chat) ============ -->
        <section class="page" data-page="chat" hidden>
          <div class="chat" data-enter>
            <img class="chat__capi" src="{{ asset('design/assets/capi/capi-parado.png') }}" alt="Capí">
            <h2 class="chat__title">No que posso ajudar hoje?</h2>
            <p class="chat__text">Pergunte sobre seus gastos, metas ou os próximos meses. O Capí responde com base nos seus próprios registros.</p>

            <div class="composer">
              <textarea rows="1" placeholder="Pergunte ao Capí…"></textarea>
              <button class="composer__send" type="button" aria-label="Enviar"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
            </div>

            <div class="chat__chips">
              <button class="chip" type="button">Quanto gastei este mês?</button>
              <button class="chip" type="button">Onde posso economizar?</button>
              <button class="chip" type="button">Consigo viajar em dezembro?</button>
              <button class="chip" type="button">Como está minha reserva?</button>
            </div>

            <p class="chat__disclaimer">O assistente ainda está em desenvolvimento. Nenhuma resposta é gerada hoje.</p>
          </div>
        </section>

        <!-- ============ Agentes ============ -->
        <section class="page" data-page="agentes" hidden>

          <section class="agents-hero" data-enter>
            <span class="agents-hero__ring" aria-hidden="true"></span>
            <div class="agents-hero__body">
              <span class="agents-hero__badge"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i>Bízu do Capí</span>
              <h2 class="agents-hero__title">Esses são os agentes recomendados pelo próprio Capí.</h2>
              <p class="agents-hero__text">Cada um cuida de uma área específica da sua vida financeira. Comece pelo que mais pesa no seu mês — o Capí te avisa quando fizer sentido acionar os outros.</p>
            </div>
            <img class="agents-hero__art" src="{{ asset('design/assets/capi/capi-apontando.png') }}" alt="">
          </section>

          <div class="grid-3">
            <article class="agent-card agent-card--dumont" data-enter>
              <div class="agent-card__art"><img src="{{ asset('design/assets/agentes/dumont.png') }}" alt="Dumont"></div>
              <div class="agent-card__body">
                <div class="agent-card__head">
                  <span class="agent-card__accent"></span>
                  <h3 class="agent-card__name">Dumont</h3>
                  <span class="agent-card__soon">em breve</span>
                </div>
                <div class="agent-card__role">Viagens e planejamento</div>
                <p class="agent-card__text">Viagens e planos que cabem no seu orçamento.</p>
              </div>
            </article>

            <article class="agent-card agent-card--dinho" data-enter>
              <div class="agent-card__art"><img src="{{ asset('design/assets/agentes/dinho.png') }}" alt="Dinho"></div>
              <div class="agent-card__body">
                <div class="agent-card__head">
                  <span class="agent-card__accent"></span>
                  <h3 class="agent-card__name">Dinho</h3>
                  <span class="agent-card__soon">em breve</span>
                </div>
                <div class="agent-card__role">Economia do dia a dia</div>
                <p class="agent-card__text">Rolês e gastos do dia a dia equilibrados até o fim do mês.</p>
              </div>
            </article>

            <article class="agent-card agent-card--poatan" data-enter>
              <div class="agent-card__art"><img src="{{ asset('design/assets/agentes/saude.png') }}" alt="Poatan"></div>
              <div class="agent-card__body">
                <div class="agent-card__head">
                  <span class="agent-card__accent"></span>
                  <h3 class="agent-card__name">Poatan</h3>
                  <span class="agent-card__soon">em breve</span>
                </div>
                <div class="agent-card__role">Rotina e disciplina</div>
                <p class="agent-card__text">Rotina que cabe na semana e no bolso. Sem cobrança.</p>
              </div>
            </article>
          </div>
        </section>

        <!-- ============ Configurações ============ -->
        <section class="page" data-page="config" hidden>
          <div class="settings" data-enter>

            <nav class="settings__nav">
              <a href="#cfg-perfil"><i class="fa-regular fa-user" aria-hidden="true"></i>Perfil</a>
              <a href="#cfg-secoes"><i class="fa-solid fa-table-columns" aria-hidden="true"></i>Seções</a>
              <a href="#cfg-preferencias"><i class="fa-solid fa-sliders" aria-hidden="true"></i>Preferências</a>
              <a href="#cfg-notificacoes"><i class="fa-regular fa-bell" aria-hidden="true"></i>Notificações</a>
              <a href="#cfg-seguranca"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Segurança</a>
              <a href="#cfg-conta"><i class="fa-solid fa-gear" aria-hidden="true"></i>Conta</a>
            </nav>

            <div class="settings__body">

              <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <section class="panel settings__block" id="cfg-perfil">
                  <div>
                    <h2 class="settings__title">Perfil</h2>
                    <p class="settings__sub">Como você aparece no financiaí e para onde mandamos os avisos.</p>
                  </div>
                  <div class="field">
                    <span class="field__label">Foto de perfil</span>
                    <div class="avatar-upload" id="avatarUpload">
                      <span class="avatar avatar--xl avatar-upload__preview">
                        <span class="avatar-upload__initials" @if($user->avatarUrl()) hidden @endif>{{ $initials }}</span>
                        <img class="avatar-upload__img" id="avatarPreview" alt="Foto de perfil" src="{{ $user->avatarUrl() }}" @unless($user->avatarUrl()) hidden @endunless>
                      </span>
                      <div class="avatar-upload__text">
                        <span class="avatar-upload__name" id="avatarName">{{ $user->avatarUrl() ? 'Foto atual' : 'Nenhuma foto enviada' }}</span>
                        <span class="avatar-upload__hint">Arraste um arquivo aqui ou escolha do computador. JPG ou PNG, até 5 MB.</span>
                      </div>
                      <div class="avatar-upload__actions">
                        <button class="btn-outline" type="button" id="avatarPick">Escolher arquivo</button>
                        <button class="avatar-upload__remove" type="button" id="avatarRemove" @unless($user->avatarUrl()) hidden @endunless>Remover</button>
                      </div>
                      <input class="avatar-upload__input" type="file" id="avatarInput" name="avatar" accept="image/png, image/jpeg">
                    </div>
                    <span class="avatar-upload__error" id="avatarError" hidden></span>
                    <input type="hidden" name="remove_avatar" id="removeAvatarFlag" value="0">
                    @error('avatar')<span class="field-error">{{ $message }}</span>@enderror
                  </div>
                  <div class="field-row">
                    <label class="field">
                      <span class="field__label">Nome</span>
                      <input class="input" type="text" name="name" value="{{ old('name', $user->name) }}">
                      @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="field">
                      <span class="field__label">E-mail</span>
                      <input class="input" type="email" name="email" value="{{ old('email', $user->email) }}">
                      @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                  </div>
                  <label class="field">
                    <span class="field__label">Senha atual</span>
                    <input class="input" type="password" name="current_password" autocomplete="current-password" placeholder="Necessária apenas se você alterar o e-mail">
                    @error('current_password')<span class="field-error">{{ $message }}</span>@enderror
                  </label>
                  <div class="form-foot form-foot--bare">
                    <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Salvar perfil</button>
                  </div>
                </section>
              </form>

              <form method="POST" action="{{ route('settings.sections') }}" data-sections-form>
                @csrf
                @method('PATCH')
                <section class="panel settings__block" id="cfg-secoes">
                  <div>
                    <h2 class="settings__title">Seções da barra</h2>
                    <p class="settings__sub">Escolha as 5 seções que ficam visíveis na barra do painel. As demais continuam acessíveis em "Mais opções".</p>
                  </div>

                  <div class="sections-head">
                    <span class="sections-count" data-sections-count>{{ $activeSections->count() }} de 5 escolhidas</span>
                    <span class="sections-hint" data-sections-hint>Barra completa. Desmarque uma para trocar.</span>
                  </div>

                  <div class="sections-grid" data-sections-grid>
                    <label class="section-opt is-on is-locked">
                      <i class="fa-solid fa-gauge-high section-opt__icon" aria-hidden="true"></i>
                      <span class="section-opt__body">
                        <span class="section-opt__title">Visão geral</span>
                        <span class="section-opt__text">Sempre visível — é a tela inicial do painel.</span>
                      </span>
                      <input class="checkbox" type="checkbox" checked disabled>
                    </label>
                    @foreach(\App\Enums\DashboardSection::cases() as $section)
                      @php $ligado = in_array($section->value, $activeSectionKeys, true); @endphp
                      <label class="section-opt {{ $ligado ? 'is-on' : '' }}" data-section-opt>
                        <i class="{{ $section->icon() }} section-opt__icon" aria-hidden="true"></i>
                        <span class="section-opt__body">
                          <span class="section-opt__title">{{ $section->label() }}</span>
                          <span class="section-opt__text" data-section-text>{{ $ligado ? 'Aparece na barra de seções.' : 'Fica em "Mais opções".' }}</span>
                        </span>
                        <input class="checkbox" type="checkbox" name="sections[]" value="{{ $section->value }}" {{ $ligado ? 'checked' : '' }} data-section>
                      </label>
                    @endforeach
                  </div>
                  @error('sections')<span class="field-error">{{ $message }}</span>@enderror

                  <div class="form-foot">
                    <button class="btn-ghost" type="reset" hidden data-sections-undo>Desfazer</button>
                    <button class="btn-primary btn-primary--sm" type="submit" data-sections-save><i class="fa-solid fa-check" aria-hidden="true"></i>Salvar seções</button>
                  </div>
                </section>
              </form>

              <form method="POST" action="{{ route('settings.update') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="timezone" value="{{ $userSettings->timezone }}">
                <input type="hidden" name="view_preference" value="{{ $userSettings->view_preference }}">
                <input type="hidden" name="hide_values" value="{{ $userSettings->hide_values ? '1' : '0' }}">
                <input type="hidden" name="confirm_deletion" value="{{ $userSettings->confirm_deletion ? '1' : '0' }}">
                <section class="panel settings__block" id="cfg-preferencias">
                  <div>
                    <h2 class="settings__title">Preferências</h2>
                    <p class="settings__sub">Ajustes que mudam o jeito que o painel se comporta.</p>
                  </div>
                  <div class="field">
                    <span class="field__label">Tema</span>
                    <div class="chip-row" data-chip-group>
                      <button class="chip {{ $userSettings->theme === 'light' ? 'is-selected' : '' }}" type="button" data-theme-set="light"><i class="fa-solid fa-sun" aria-hidden="true"></i>Claro</button>
                      <button class="chip {{ $userSettings->theme === 'dark' ? 'is-selected' : '' }}" type="button" data-theme-set="dark"><i class="fa-solid fa-moon" aria-hidden="true"></i>Escuro</button>
                    </div>
                    <input type="hidden" name="theme" value="{{ $userSettings->theme }}" data-theme-input>
                  </div>
                  <div class="field-row">
                    <div class="field">
                      <span class="field__label">Moeda</span>
                      <x-dropdown name="currency" icon="fa-solid fa-coins" :selected="$userSettings->currency" :options="[
                          ['value' => 'BRL', 'label' => 'Real (R$)'],
                          ['value' => 'USD', 'label' => 'Dólar (US$)'],
                          ['value' => 'EUR', 'label' => 'Euro (€)'],
                      ]" />
                    </div>
                    <div class="field">
                      <span class="field__label">Início do mês financeiro</span>
                      <x-dropdown name="financial_month_start_day" icon="fa-regular fa-calendar" :selected="$userSettings->financial_month_start_day" :options="$monthStartDayOptions->map(fn ($day) => ['value' => $day, 'label' => 'Dia '.$day])" />
                    </div>
                  </div>

                  <div class="pref-row">
                    <div class="pref-row__body">
                      <button class="switch {{ $userSettings->hide_values ? 'is-on' : '' }}" type="button" role="switch" aria-checked="{{ $userSettings->hide_values ? 'true' : 'false' }}" data-pref-hide data-toggle-url="{{ route('settings.toggle-values') }}"><span class="switch__pin"></span></button>
                      <span class="pref-row__text">
                        <span class="pref-row__title">Ocultar valores por padrão</span>
                        <span class="pref-row__sub" data-hide-hint>{{ $userSettings->hide_values ? 'Valores ocultos ao abrir o painel.' : 'Valores visíveis ao abrir o painel.' }}</span>
                      </span>
                    </div>
                  </div>

                  <div class="form-foot form-foot--bare">
                    <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Salvar preferências</button>
                  </div>
                </section>
              </form>

              <section class="panel settings__block" id="cfg-notificacoes">
                <div>
                  <h2 class="settings__title">Notificações<span class="tag-soon">em breve</span></h2>
                  <p class="settings__sub">Escolha o que vale um aviso e o que pode esperar você abrir o painel.</p>
                </div>
                <div class="settings__list">
                  <div class="switch-row">
                    <button class="switch is-on" type="button" role="switch" aria-checked="true"><span class="switch__pin"></span></button>
                    <span class="switch-row__body">
                      <span class="switch-row__title">Fatura fechando</span>
                      <span class="switch-row__text">Três dias antes do fechamento de cada cartão.</span>
                    </span>
                  </div>
                  <div class="switch-row">
                    <button class="switch" type="button" role="switch" aria-checked="false"><span class="switch__pin"></span></button>
                    <span class="switch-row__body">
                      <span class="switch-row__title">Saldo baixo</span>
                      <span class="switch-row__text">Quando alguma conta fica abaixo do limite que você definiu.</span>
                    </span>
                  </div>
                  <div class="switch-row">
                    <button class="switch is-on" type="button" role="switch" aria-checked="true"><span class="switch__pin"></span></button>
                    <span class="switch-row__body">
                      <span class="switch-row__title">Assinatura renovando</span>
                      <span class="switch-row__text">Aviso na véspera da cobrança recorrente.</span>
                    </span>
                  </div>
                  <div class="switch-row">
                    <button class="switch" type="button" role="switch" aria-checked="false"><span class="switch__pin"></span></button>
                    <span class="switch-row__body">
                      <span class="switch-row__title">Resumo semanal</span>
                      <span class="switch-row__text">Um panorama do que entrou e saiu, toda segunda.</span>
                    </span>
                  </div>
                </div>
              </section>

              <section class="panel settings__block" id="cfg-seguranca">
                <div>
                  <h2 class="settings__title">Segurança</h2>
                  <p class="settings__sub">Sua senha e os dispositivos com acesso à conta.</p>
                </div>
                <div class="settings__list">
                  <div class="settings__row">
                    <span class="settings__row-body">
                      <span class="settings__row-title">Senha</span>
                    </span>
                    <button class="btn-outline btn-outline--sm" type="button" data-modal-open="senha">Alterar senha</button>
                  </div>
                  <div class="settings__row">
                    <span class="settings__row-body">
                      <span class="settings__row-title">Sessões ativas</span>
                      <span class="settings__row-text">{{ $activeSessionsCount }} {{ $activeSessionsCount === 1 ? 'dispositivo conectado' : 'dispositivos conectados' }}</span>
                    </span>
                    <button class="btn-ghost" type="button" data-modal-open="encerrarSessoes">Encerrar as outras</button>
                  </div>

                  <div class="mfa-card" data-mfa-card data-active="{{ $user->hasTwoFactorEnabled() ? 'true' : 'false' }}"
                       data-mfa-has-password="{{ $user->hasUsablePassword() ? 'true' : 'false' }}"
                       data-mfa-url-reauth="{{ route('reauthenticate') }}"
                       {{-- Oferecido sempre que houver provedor vinculado, não só
                            para quem tem has_usable_password = false: contas de
                            login social criadas ANTES dessa coluna existir ficaram
                            marcadas como "tem senha" e só conhecem uma senha
                            aleatória — sem esta saída elas travariam em "senha
                            incorreta" para sempre. --}}
                       @if($reauthProvider)
                           data-mfa-url-reauth-social="{{ route('social.reauthenticate', $reauthProvider) }}"
                           data-mfa-reauth-provider-label="{{ $reauthProvider === 'github' ? 'GitHub' : 'Google' }}"
                       @endif>
                    <div class="mfa-card__head">
                      <span class="mfa-card__icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
                      <div class="mfa-card__body">
                        <span class="mfa-card__title-row">
                          <span class="mfa-card__title">Autenticação de dois fatores</span>
                          <span class="mfa-card__badge" data-mfa-badge>{{ $user->hasTwoFactorEnabled() ? 'Ativa' : 'Desativada' }}</span>
                        </span>
                        <span class="mfa-card__text" data-mfa-text>{{ $user->hasTwoFactorEnabled() ? 'Além da senha, o financiaí pede um código de 6 dígitos do seu app autenticador a cada novo login.' : 'Pede um código de 6 dígitos do seu app autenticador junto com a senha. Se alguém descobrir sua senha, ainda não entra.' }}</span>
                      </div>
                    </div>

                    <div class="mfa-card__on" data-mfa-on @unless($user->hasTwoFactorEnabled()) hidden @endunless>
                      <p class="mfa-card__since"><i class="fa-solid fa-mobile-screen"></i>App autenticador ativado em <strong data-mfa-date>{{ $user->two_factor_confirmed_at?->translatedFormat('d \d\e F \d\e Y') }}</strong></p>
                      <div class="mfa-card__actions">
                        <button class="btn-outline btn-outline--sm" type="button" data-mfa-regen><i class="fa-solid fa-rotate"></i>Gerar novos códigos de recuperação</button>
                        <button class="btn-danger" type="button" data-mfa-open-off>Desativar</button>
                      </div>
                    </div>

                    <div class="mfa-card__off" data-mfa-off @if($user->hasTwoFactorEnabled()) hidden @endif>
                      <button class="btn-primary btn-primary--sm" type="button" data-mfa-open-on><i class="fa-solid fa-shield-halved"></i>Ativar</button>
                      <span class="mfa-card__hint">Leva menos de um minuto.</span>
                    </div>
                  </div>

                  @php
                      $activityIcons = ['acesso' => 'fa-solid fa-right-to-bracket', 'alteracao' => 'fa-regular fa-pen-to-square', 'alerta' => 'fa-solid fa-triangle-exclamation'];
                      $activityFilterUrl = fn ($type) => route('dashboard', array_merge($filters, ['sec_type' => $type === 'todos' ? null : $type])).'#cfg-seguranca';
                  @endphp
                  <div class="activity" data-activity>
                    <div class="activity__head">
                      <span class="activity__head-body">
                        <span class="activity__title">Atividade da conta</span>
                        <span class="activity__sub">Acessos, alterações sensíveis e tentativas bloqueadas.</span>
                      </span>
                      <span class="activity__filters">
                        @foreach(['todos' => 'Tudo', 'acesso' => 'Acessos', 'alteracao' => 'Alterações', 'alerta' => 'Bloqueios'] as $key => $label)
                          <a class="chip {{ $activityType === $key ? 'is-selected' : '' }}" href="{{ $activityFilterUrl($key) }}">{{ $label }} ({{ $activityCounts[$key] }})</a>
                        @endforeach
                      </span>
                    </div>
                    <div class="activity__list">
                      @forelse($activityPage as $log)
                        <div class="activity__row" data-kind="{{ $log->category }}">
                          <span class="activity__icon" aria-hidden="true"><i class="{{ $activityIcons[$log->category] ?? 'fa-solid fa-circle-info' }}"></i></span>
                          <span class="activity__body">
                            <span class="activity__desc">{{ $log->describe() }}</span>
                            <span class="activity__where">{{ \App\Support\UserAgentSummary::describe($log->user_agent) }} · {{ $log->ip_address }}</span>
                          </span>
                          <span class="activity__when">
                            <span class="activity__date">{{ $log->created_at->format('d/m/Y') }}</span>
                            <span class="activity__hour">{{ $log->created_at->format('H:i') }}</span>
                          </span>
                        </div>
                      @empty
                        <p class="history__empty">Nenhum registro nesse recorte.</p>
                      @endforelse
                    </div>
                    @if($activityPage->total() > 0)
                      <div class="tx-foot">
                        <span class="tx-foot__summary">Mostrando {{ $activityPage->firstItem() }}–{{ $activityPage->lastItem() }} de {{ $activityPage->total() }} registros</span>
                        <span class="tx-pager">
                          <a class="btn-icon btn-icon--sm" aria-label="Página anterior" href="{{ $activityPage->previousPageUrl() ?? '#cfg-seguranca' }}#cfg-seguranca" style="{{ $activityPage->onFirstPage() ? 'opacity:.4;pointer-events:none;' : '' }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
                          <span>
                            @for($p = 1; $p <= $activityPage->lastPage(); $p++)
                              <a class="tx-page {{ $p === $activityPage->currentPage() ? 'is-current' : '' }}" href="{{ $activityPage->url($p) }}#cfg-seguranca">{{ $p }}</a>
                            @endfor
                          </span>
                          <a class="btn-icon btn-icon--sm" aria-label="Próxima página" href="{{ $activityPage->nextPageUrl() ?? '#cfg-seguranca' }}#cfg-seguranca" style="{{ $activityPage->hasMorePages() ? '' : 'opacity:.4;pointer-events:none;' }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                        </span>
                      </div>
                    @endif
                  </div>
                </div>
              </section>

              <section class="panel settings__block" id="cfg-conta">
                <div>
                  <h2 class="settings__title">Conta</h2>
                  <p class="settings__sub">Exportar o que é seu ou encerrar de vez.</p>
                </div>
                <div class="settings__list">
                  <div class="settings__row">
                    <span class="settings__row-body">
                      <span class="settings__row-title">Exportar dados</span>
                      <span class="settings__row-text">Todos os lançamentos em CSV</span>
                    </span>
                    <a class="btn-outline btn-outline--sm" href="{{ route('transactions.export') }}"><i class="fa-solid fa-download" aria-hidden="true"></i>Baixar CSV</a>
                  </div>
                  <div class="settings__row settings__row--danger">
                    <span class="settings__row-body">
                      <span class="settings__row-title">Encerrar conta</span>
                      <span class="settings__row-text">Apaga tudo. Não dá para desfazer.</span>
                    </span>
                    <button class="btn-danger" type="button" data-modal-open="encerrarConta">Encerrar</button>
                  </div>
                </div>
              </section>

            </div>
          </div>
        </section>

      </div>
    </div>
  </main>
</div>

@php
    $defaultAccountId = $dashboard['accounts']->first()['account']->id ?? null;
@endphp

<!-- ============ Modal: nova transação ============ -->
<div class="modal" data-modal="transacao" @unless($editTransaction) hidden @endunless>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-transacao-titulo">
    @php
      $txTipoAtual = old('type', $editTransaction->type->value ?? 'expense');
      $txContaAtual = $editTransaction?->payment_channel === 'credit_card' ? null : ($editTransaction?->account_id ?? $dashboard['accounts']->first()['account']->id ?? null);
      $txCartaoAtual = $editTransaction?->payment_channel === 'credit_card' ? $editTransaction->credit_card_id : null;
      $txContaLabel = $editTransaction
        ? ($editTransaction->payment_channel === 'credit_card' ? $editTransaction->creditCard?->name.' (cartão)' : $editTransaction->account?->name)
        : ($dashboard['accounts']->first()['account']->name ?? 'Selecione');
    @endphp
    <form method="POST" action="{{ $editTransaction ? route('transactions.update', $editTransaction) : route('transactions.store') }}">
      @csrf
      @if($editTransaction) @method('PATCH') @endif
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-transacao-titulo">{{ $editTransaction ? 'Editar transação' : 'Nova transação' }}</h2>
          <p class="modal__sub">Um lançamento avulso, que não veio de extrato.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <span class="field__label">Tipo</span>
          <div class="chip-row" data-chip-group>
            <button class="chip {{ $txTipoAtual === 'income' ? 'is-selected' : '' }}" type="button" data-value="income"><i class="fa-solid fa-arrow-down-long" aria-hidden="true"></i>Entrada</button>
            <button class="chip {{ $txTipoAtual === 'expense' ? 'is-selected' : '' }}" type="button" data-value="expense"><i class="fa-solid fa-arrow-up-long" aria-hidden="true"></i>Saída</button>
            <button class="chip {{ $txTipoAtual === 'transfer' ? 'is-selected' : '' }}" type="button" data-value="transfer"><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>Transferência</button>
          </div>
          <input type="hidden" name="type" value="{{ $txTipoAtual }}" data-chip-input>
        </div>

        <div class="field-row">
          <label class="field">
            <span class="field__label">Valor</span>
            <span class="input-group">
              <span class="input-group__prefix">R$</span>
              <input class="input-group__input input-group__input--num" type="text" inputmode="decimal" name="amount" placeholder="0,00" value="{{ old('amount', $editTransaction ? number_format((float) $editTransaction->amount, 2, ',', '.') : '') }}" required>
            </span>
            @error('amount')<span class="field-error">{{ $message }}</span>@enderror
          </label>
          <div class="field">
            <span class="field__label">Data</span>
            <x-datepicker name="competence_date" :value="old('competence_date', $editTransaction?->competence_date?->toDateString() ?? now()->toDateString())" />
          </div>
        </div>

        <label class="field">
          <span class="field__label">Descrição</span>
          <input class="input" type="text" name="description" placeholder="Ex.: Mercado da esquina" value="{{ old('description', $editTransaction?->description ?? '') }}" required>
          @error('description')<span class="field-error">{{ $message }}</span>@enderror
        </label>

        <div class="field-row">
          <div class="field">
            <span class="field__label">Categoria</span>
            <x-dropdown name="category_id" icon="fa-solid fa-tag" up :selected="old('category_id', $editTransaction?->category_id)"
                :options="$categories->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])" />
            @error('category_id')<span class="field-error">{{ $message }}</span>@enderror
          </div>
          <div class="field">
            <span class="field__label">Conta ou cartão</span>
            <div class="dropdown dropdown--block dropdown--up" data-dropdown data-account-or-card>
              <button class="dropdown__btn" type="button" aria-haspopup="listbox" aria-expanded="false" data-dropdown-btn>
                <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                <span data-dropdown-label>{{ $txContaLabel }}</span>
                <i class="fa-solid fa-chevron-down dropdown__chevron" aria-hidden="true"></i>
              </button>
              <div class="dropdown__menu" role="listbox" hidden>
                @foreach($dashboard['accounts'] as $row)
                  <button class="dropdown__opt {{ $txContaAtual === $row['account']->id ? 'is-selected' : '' }}" type="button" role="option" data-account-id="{{ $row['account']->id }}">{{ $row['account']->name }}<i class="fa-solid fa-check" aria-hidden="true"></i></button>
                @endforeach
                @foreach($dashboard['credit_cards'] as $row)
                  <button class="dropdown__opt {{ $txCartaoAtual === $row['card']->id ? 'is-selected' : '' }}" type="button" role="option" data-credit-card-id="{{ $row['card']->id }}">{{ $row['card']->name }} (cartão)<i class="fa-solid fa-check" aria-hidden="true"></i></button>
                @endforeach
              </div>
            </div>
            <input type="hidden" name="payment_channel" value="{{ $editTransaction->payment_channel ?? 'account' }}" data-payment-channel>
            <input type="hidden" name="account_id" value="{{ $txContaAtual }}" data-account-id-field>
            <input type="hidden" name="credit_card_id" value="{{ $txCartaoAtual }}" data-credit-card-id-field>
            @error('account_id')<span class="field-error">{{ $message }}</span>@enderror
            @error('credit_card_id')<span class="field-error">{{ $message }}</span>@enderror
          </div>
        </div>
        <input type="hidden" name="status" value="{{ $editTransaction->status->value ?? 'completed' }}">
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editTransaction ? 'Salvar alterações' : 'Salvar transação' }}</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: novo cartão ============ -->
<div class="modal" data-modal="cartao" @unless($editCard) hidden @endunless>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-cartao-titulo">
    <form method="POST" action="{{ $editCard ? route('credit-cards.update', $editCard) : route('credit-cards.store') }}">
      @csrf
      @if($editCard) @method('PATCH') @endif
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-cartao-titulo">{{ $editCard ? 'Editar cartão' : 'Novo cartão' }}</h2>
          <p class="modal__sub">Só o essencial para calcular fatura e limite. Os lançamentos entram depois.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        @php $bandeiras = ['Visa', 'Mastercard', 'Elo', 'Amex', 'Outra']; $issuerAtual = old('issuer', $editCard->issuer ?? 'Visa'); @endphp
        <div class="field">
          <span class="field__label">Bandeira</span>
          <div class="chip-row" data-chip-group>
            @foreach($bandeiras as $bandeira)
              <button class="chip {{ $issuerAtual === $bandeira || (! in_array($issuerAtual, $bandeiras, true) && $bandeira === 'Outra') ? 'is-selected' : '' }}" type="button" data-value="{{ $bandeira }}">{{ $bandeira }}</button>
            @endforeach
          </div>
          <input type="hidden" name="issuer" value="{{ $issuerAtual }}" data-chip-input>
          @error('issuer')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <label class="field">
          <span class="field__label">Apelido do cartão</span>
          <input class="input" type="text" name="name" placeholder="Ex.: Cartão do mercado" value="{{ old('name', $editCard->name ?? '') }}" required>
          @error('name')<span class="field-error">{{ $message }}</span>@enderror
        </label>

        <div class="field-row field-row--3">
          <label class="field">
            <span class="field__label">Limite</span>
            <span class="input-group">
              <span class="input-group__prefix">R$</span>
              <input class="input-group__input input-group__input--num" type="text" inputmode="decimal" name="credit_limit" placeholder="0,00" value="{{ old('credit_limit', $editCard ? number_format((float) $editCard->credit_limit, 2, ',', '.') : '') }}" required>
            </span>
            @error('credit_limit')<span class="field-error">{{ $message }}</span>@enderror
          </label>
          <label class="field">
            <span class="field__label">Fecha no dia</span>
            <input class="input input--num" type="number" min="1" max="31" name="closing_day" value="{{ old('closing_day', $editCard->closing_day ?? 28) }}">
          </label>
          <label class="field">
            <span class="field__label">Vence no dia</span>
            <input class="input input--num" type="number" min="1" max="31" name="due_day" value="{{ old('due_day', $editCard->due_day ?? 5) }}">
          </label>
        </div>

        <input type="hidden" name="color" value="{{ old('color', $editCard->color ?? '#137A4A') }}">
        <input type="hidden" name="active" value="1">

        <div class="field">
          <span class="field__label">Conta que paga a fatura <span class="field__hint">· opcional</span></span>
          <x-dropdown name="_payer_account_display" icon="fa-solid fa-building-columns" up
              :options="$dashboard['accounts']->map(fn ($row) => ['value' => $row['account']->id, 'label' => $row['account']->name])" />
        </div>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editCard ? 'Salvar alterações' : 'Salvar cartão' }}</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: registrar pagamento de fatura ============ -->
<div class="modal" data-modal="pagamento" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-pagamento-titulo">
    <form method="POST" action="" data-pay-form>
      @csrf
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-pagamento-titulo">Registrar pagamento</h2>
          <p class="modal__sub">A fatura é marcada como paga e a despesa entra na conta escolhida.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <div class="pay-summary">
          <span class="row__icon row__icon--lg"><i class="fa-regular fa-credit-card" aria-hidden="true"></i></span>
          <span class="pay-summary__body">
            <span class="pay-summary__title" data-pay-card>—</span>
            <span class="pay-summary__text" data-pay-due>—</span>
          </span>
        </div>

        <label class="field">
          <span class="field__label">Valor da fatura</span>
          <span class="input-money">
            <span class="input-money__prefix">R$</span>
            <input class="input-money__input" type="text" inputmode="decimal" data-pay-amount disabled>
          </span>
        </label>

        <div class="field-row">
          <div class="field">
            <span class="field__label">Data do pagamento</span>
            <x-datepicker name="paid_at" :value="now()->toDateString()" />
          </div>
          <div class="field">
            <span class="field__label">Conta de origem</span>
            <x-dropdown name="account_id" icon="fa-solid fa-building-columns"
                :options="$dashboard['accounts']->map(fn ($row) => ['value' => $row['account']->id, 'label' => $row['account']->name])" />
            @error('account_id')<span class="field-error">{{ $message }}</span>@enderror
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Confirmar pagamento</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: editar fatura aberta ============ -->
<div class="modal" data-modal="fatura" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-fatura-titulo">
    <form method="POST" action="" data-fatura-form>
      @csrf
      @method('PATCH')
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-fatura-titulo">Editar fatura aberta</h2>
          <p class="modal__sub">Vale só para a fatura em aberto deste mês. O cartão continua com as regras padrão.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>
      <div class="modal__body">
        <p class="field__hint"><i class="fa-regular fa-credit-card" aria-hidden="true"></i> <span data-fatura-card>—</span></p>

        <div class="field">
          <span class="field__label">Vencimento desta fatura</span>
          <x-datepicker name="due_date" :value="null" data-fatura-due />
        </div>

        <div class="field-row">
          <label class="field">
            <span class="field__label">Ajuste manual</span>
            <span class="chip-row" data-chip-group>
              <button class="chip is-selected" type="button" data-value="acrescimo">Acréscimo</button>
              <button class="chip" type="button" data-value="desconto">Desconto</button>
            </span>
            <input type="hidden" name="adjustment_type" value="acrescimo" data-chip-input data-fatura-adjustment-type>
          </label>
          <label class="field">
            <span class="field__label">Valor do ajuste</span>
            <span class="input-money">
              <span class="input-money__prefix">R$</span>
              <input class="input-money__input" type="text" inputmode="decimal" name="adjustment_amount" placeholder="0,00" data-fatura-adjustment-amount>
            </span>
          </label>
        </div>

        <label class="field">
          <span class="field__label">Motivo</span>
          <input class="input" type="text" name="adjustment_reason" placeholder="Ex.: estorno de compra cancelada" data-fatura-reason>
          @error('adjustment_reason')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <p class="field__hint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> O ajuste substitui o anterior — não é somado a ele.</p>
      </div>
      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Salvar fatura</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: categoria ============ -->
<div class="modal" data-modal="categoria" @unless($editCategory) hidden @endunless>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-categoria-titulo">
    @php
        $catCores = \App\Support\CategoryPalette::COLORS;
        $catIcones = \App\Support\CategoryPalette::ICONS;
        $catCorAtual = old('color', $editCategory?->color ?? $catCores[0]);
        $catIconeAtual = old('icon', $editCategory?->icon ?? $catIcones[0]);
        $catTipoAtual = old('type', $editCategory?->type->value ?? 'expense');
    @endphp
    <form method="POST" action="{{ $editCategory ? route('categories.update', $editCategory) : route('categories.store') }}">
      @csrf
      @if($editCategory) @method('PATCH') @endif
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-categoria-titulo">{{ $editCategory ? 'Editar categoria' : 'Nova categoria' }}</h2>
          <p class="modal__sub">Cor e ícone identificam a categoria nas listas e nos gráficos.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <div class="cat-preview">
          <span class="cat-chip" data-cat-preview style="background: {{ $catCorAtual }}"><i class="{{ $catIconeAtual }}" data-cat-preview-icon aria-hidden="true"></i></span>
          <span class="cat-preview__body">
            <span class="cat-preview__name" data-cat-preview-name>{{ $editCategory?->name ?? 'Nome da categoria' }}</span>
            <span class="cat-preview__text">Prévia de como a categoria aparece nas listas.</span>
          </span>
        </div>

        <label class="field">
          <span class="field__label">Nome</span>
          <input class="input" type="text" name="name" value="{{ old('name', $editCategory?->name ?? '') }}" placeholder="Ex.: Educação">
          @error('name')<span class="field-error">{{ $message }}</span>@enderror
        </label>

        <div class="field">
          <span class="field__label">Tipo</span>
          <div class="seg" data-seg>
            <button class="seg__opt {{ $catTipoAtual === 'expense' ? 'is-on' : '' }}" type="button" data-seg-opt data-value="expense">Despesa</button>
            <button class="seg__opt {{ $catTipoAtual === 'income' ? 'is-on' : '' }}" type="button" data-seg-opt data-value="income">Receita</button>
          </div>
          <input type="hidden" name="type" value="{{ $catTipoAtual }}">
        </div>

        <div class="field">
          <span class="field__label">Cor</span>
          <div class="color-picks" data-swatches>
            @foreach($catCores as $cor)
              <button class="color-pick {{ $catCorAtual === $cor ? 'is-on' : '' }}" type="button" aria-label="Escolher cor" data-color="{{ $cor }}"><span class="color-pick__dot" style="background: {{ $cor }}"></span></button>
            @endforeach
          </div>
          <input type="hidden" name="color" value="{{ $catCorAtual }}">
          @error('color')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
          <span class="field__label">Ícone</span>
          <div class="icon-grid" data-icons>
            @foreach($catIcones as $icone)
              <button class="icon-opt {{ $catIconeAtual === $icone ? 'is-on' : '' }}" type="button" aria-label="Escolher ícone" data-icon="{{ $icone }}"><i class="{{ $icone }}" aria-hidden="true"></i></button>
            @endforeach
          </div>
          <input type="hidden" name="icon" value="{{ $catIconeAtual }}">
          @error('icon')<span class="field-error">{{ $message }}</span>@enderror
        </div>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editCategory ? 'Salvar alterações' : 'Criar categoria' }}</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: orçamento ============ -->
<div class="modal" data-modal="orcamento" @unless($editBudget) hidden @endunless>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-orcamento-titulo">
    <form method="POST" action="{{ $editBudget ? route('budgets.update', $editBudget) : route('budgets.store') }}">
      @csrf
      @if($editBudget) @method('PATCH') @endif
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-orcamento-titulo">{{ $editBudget ? 'Editar orçamento' : 'Novo orçamento' }}</h2>
          <p class="modal__sub">Defina quanto essa categoria pode consumir por mês.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <span class="field__label">Categoria</span>
          <x-dropdown name="category_id" icon="fa-solid fa-tag" :selected="old('category_id', $editBudget?->category_id ?? null)"
              :options="$categories->where('type', \App\Enums\CategoryType::Expense)->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])" />
          @error('category_id')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <label class="field">
          <span class="field__label">Limite mensal</span>
          <span class="input-money">
            <span class="input-money__prefix">R$</span>
            <input class="input-money__input" type="text" inputmode="decimal" name="limit_amount" value="{{ old('limit_amount', $editBudget ? number_format((float) $editBudget?->limit_amount, 2, ',', '.') : '') }}" placeholder="0,00">
          </span>
          @error('limit_amount')<span class="field-error">{{ $message }}</span>@enderror
        </label>

        <input type="hidden" name="month" value="{{ old('month', $editBudget?->month ?? $budgetMonth) }}">
        <input type="hidden" name="year" value="{{ old('year', $editBudget?->year ?? $budgetYear) }}">
        <p class="field__hint">O aviso de consumo aparece a partir de 80% do limite, e o estouro fica marcado em vermelho na lista.</p>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editBudget ? 'Salvar alterações' : 'Criar orçamento' }}</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: dívida ============ -->
<div class="modal" data-modal="divida" @unless($editDebt) hidden @endunless>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-divida-titulo">
    <form method="POST" action="{{ $editDebt ? route('debts.update', $editDebt) : route('debts.store') }}">
      @csrf
      @if($editDebt) @method('PATCH') @endif
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-divida-titulo">{{ $editDebt ? 'Editar dívida' : 'Nova dívida' }}</h2>
          <p class="modal__sub">As parcelas são geradas automaticamente e cada baixa vira uma despesa vinculada.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <label class="field">
          <span class="field__label">Nome da dívida</span>
          <input class="input" type="text" name="name" value="{{ old('name', $editDebt?->name ?? '') }}" placeholder="Ex.: Empréstimo pessoal">
          @error('name')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label class="field">
          <span class="field__label">Credor</span>
          <input class="input" type="text" name="creditor" value="{{ old('creditor', $editDebt?->creditor ?? '') }}" placeholder="Ex.: Banco, cartão ou pessoa">
          @error('creditor')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <div class="field-row">
          <label class="field">
            <span class="field__label">Valor total</span>
            <span class="input-money">
              <span class="input-money__prefix">R$</span>
              <input class="input-money__input" type="text" inputmode="decimal" name="original_amount" value="{{ old('original_amount', $editDebt ? number_format((float) $editDebt?->original_amount, 2, ',', '.') : '') }}" placeholder="0,00">
            </span>
            @error('original_amount')<span class="field-error">{{ $message }}</span>@enderror
          </label>
          <label class="field">
            <span class="field__label">Número de parcelas</span>
            <input class="input" type="text" inputmode="numeric" name="installment_count" value="{{ old('installment_count', $editDebt?->installment_count ?? 12) }}">
            @error('installment_count')<span class="field-error">{{ $message }}</span>@enderror
          </label>
        </div>
        <input type="hidden" name="expected_total_amount" value="{{ old('original_amount', $editDebt ? number_format((float) $editDebt?->original_amount, 2, ',', '.') : '') }}">
        <input type="hidden" name="kind" value="{{ old('kind', $editDebt?->kind ?? 'other') }}">
        <input type="hidden" name="status" value="{{ old('status', $editDebt?->status->value ?? 'active') }}">
        <input type="hidden" name="started_at" value="{{ old('started_at', $editDebt?->started_at?->toDateString() ?? now()->toDateString()) }}">
        <input type="hidden" name="first_due_date" value="{{ old('first_due_date', $editDebt?->due_date?->toDateString() ?? now()->addMonthNoOverflow()->toDateString()) }}">
        <p class="field__hint"><i class="fa-solid fa-list-ol" aria-hidden="true"></i> As parcelas são criadas a partir do próximo mês, com vencimento no mesmo dia.</p>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editDebt ? 'Salvar alterações' : 'Criar dívida' }}</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: investimento ============ -->
<div class="modal" data-modal="investimento" @unless($editInvestment) hidden @endunless>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-investimento-titulo">
    <form method="POST" action="{{ $editInvestment ? route('investments.update', $editInvestment) : route('investments.store') }}">
      @csrf
      @if($editInvestment) @method('PATCH') @endif
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-investimento-titulo">{{ $editInvestment ? 'Editar aplicação' : 'Nova aplicação' }}</h2>
          <p class="modal__sub">Informe onde o dinheiro está aplicado e quanto já foi investido.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <label class="field">
          <span class="field__label">Nome da aplicação</span>
          <input class="input" type="text" name="name" value="{{ old('name', $editInvestment?->name ?? '') }}" placeholder="Ex.: Tesouro Selic 2029">
          @error('name')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <div class="field-row">
          <div class="field">
            <span class="field__label">Tipo</span>
            <x-dropdown name="type" icon="fa-solid fa-seedling" :selected="old('type', $editInvestment?->type->value ?? 'fixed_income')" :options="\App\Enums\InvestmentType::options()" />
          </div>
          <label class="field">
            <span class="field__label">Instituição</span>
            <input class="input" type="text" name="institution" value="{{ old('institution', $editInvestment?->institution ?? '') }}" placeholder="Ex.: Tesouro Direto, corretora">
            @error('institution')<span class="field-error">{{ $message }}</span>@enderror
          </label>
        </div>
        <label class="field">
          <span class="field__label">Valor aplicado</span>
          <span class="input-money">
            <span class="input-money__prefix">R$</span>
            <input class="input-money__input" type="text" inputmode="decimal" name="invested_amount" value="{{ old('invested_amount', $editInvestment ? number_format((float) $editInvestment?->invested_amount, 2, ',', '.') : '') }}" placeholder="0,00">
          </span>
          @error('invested_amount')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <input type="hidden" name="current_amount" value="{{ old('current_amount', $editInvestment ? number_format((float) $editInvestment?->current_amount, 2, ',', '.') : old('invested_amount')) }}" data-investimento-current>
        <input type="hidden" name="last_updated_at" value="{{ old('last_updated_at', $editInvestment?->last_updated_at?->toDateString() ?? now()->toDateString()) }}">
        <input type="hidden" name="status" value="{{ old('status', $editInvestment?->status->value ?? 'active') }}">
        <p class="field__hint">Os rendimentos entram no histórico e não contam como aporte no total aplicado.</p>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editInvestment ? 'Salvar alterações' : 'Salvar aplicação' }}</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: aporte/resgate de investimento ============ -->
<div class="modal" data-modal="aporte" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-aporte-titulo">
    <form method="POST" action="" data-aporte-form>
      @csrf
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-aporte-titulo">Registrar movimentação</h2>
          <p class="modal__sub" data-aporte-sub>—</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <div class="field">
          <span class="field__label">Tipo de movimentação</span>
          <div class="seg" data-seg>
            <button class="seg__opt is-on" type="button" data-seg-opt data-value="contribution">Aporte</button>
            <button class="seg__opt" type="button" data-seg-opt data-value="withdrawal">Resgate</button>
          </div>
          <input type="hidden" name="type" value="contribution" data-aporte-tipo-input>
        </div>
        <label class="field">
          <span class="field__label">Valor</span>
          <span class="input-money">
            <span class="input-money__prefix">R$</span>
            <input class="input-money__input" type="text" inputmode="decimal" name="amount" placeholder="0,00">
          </span>
          @error('amount')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <div class="field-row">
          <div class="field">
            <span class="field__label">Data</span>
            <x-datepicker name="operation_date" :value="now()->toDateString()" />
          </div>
          <div class="field">
            <span class="field__label">Conta <span class="field__hint">· opcional</span></span>
            <x-dropdown name="account_id" icon="fa-solid fa-building-columns"
                :options="collect([['value' => '', 'label' => 'Nenhuma']])->concat($dashboard['accounts']->map(fn ($row) => ['value' => $row['account']->id, 'label' => $row['account']->name]))" />
          </div>
        </div>
        <p class="field__hint">Os rendimentos entram no histórico e não contam como aporte no total aplicado.</p>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Salvar movimentação</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: meta ============ -->
<div class="modal" data-modal="meta" @unless($editGoal) hidden @endunless>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-meta-titulo">
    <form method="POST" action="{{ $editGoal ? route('goals.update', $editGoal) : route('goals.store') }}">
      @csrf
      @if($editGoal) @method('PATCH') @endif
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-meta-titulo">{{ $editGoal ? 'Editar meta' : 'Nova meta' }}</h2>
          <p class="modal__sub">Defina o valor alvo e, se quiser, um prazo para acompanhar o ritmo.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <label class="field">
          <span class="field__label">Nome da meta</span>
          <input class="input" type="text" name="name" value="{{ old('name', $editGoal?->name ?? '') }}" placeholder="Ex.: Viagem ao Chile">
          @error('name')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <div class="field-row">
          <label class="field">
            <span class="field__label">Valor alvo</span>
            <span class="input-money">
              <span class="input-money__prefix">R$</span>
              <input class="input-money__input" type="text" inputmode="decimal" name="target_amount" value="{{ old('target_amount', $editGoal ? number_format((float) $editGoal?->target_amount, 2, ',', '.') : '') }}" placeholder="0,00">
            </span>
            @error('target_amount')<span class="field-error">{{ $message }}</span>@enderror
          </label>
          <div class="field">
            <span class="field__label">Prazo <span class="field__hint">· opcional</span></span>
            <x-datepicker name="deadline" :value="old('deadline', $editGoal?->deadline?->toDateString())" up />
          </div>
        </div>
        <input type="hidden" name="current_amount" value="{{ old('current_amount', $editGoal?->current_amount ?? '0.00') }}">
        <input type="hidden" name="color" value="{{ old('color', $editGoal?->color ?? '#137A4A') }}">
        <input type="hidden" name="status" value="{{ old('status', $editGoal?->status->value ?? 'active') }}">
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editGoal ? 'Salvar alterações' : 'Criar meta' }}</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: aporte de meta ============ -->
<div class="modal" data-modal="aporte-meta" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-aporte-meta-titulo">
    <form method="POST" action="" data-meta-form>
      @csrf
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-aporte-meta-titulo">Registrar aporte</h2>
          <p class="modal__sub" data-meta-sub>—</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <label class="field">
          <span class="field__label">Valor do aporte</span>
          <span class="input-money">
            <span class="input-money__prefix">R$</span>
            <input class="input-money__input" type="text" inputmode="decimal" name="amount" placeholder="0,00">
          </span>
          @error('amount')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <p class="field__hint">O valor entra direto no progresso da meta.</p>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Registrar aporte</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: ganho futuro ============ -->
<div class="modal" data-modal="ganho" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-ganho-titulo">
    <form method="POST" action="{{ route('transactions.store') }}">
      @csrf
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-ganho-titulo">Adicionar ganho futuro</h2>
          <p class="modal__sub">Entra na previsão dos próximos meses, sem afetar o saldo atual.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <label class="field">
          <span class="field__label">Descrição</span>
          <input class="input" type="text" name="description" placeholder="Ex.: Bônus, 13º, freelance combinado" required>
          @error('description')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <div class="field-row">
          <label class="field">
            <span class="field__label">Valor</span>
            <span class="input-group">
              <span class="input-group__prefix">R$</span>
              <input class="input-group__input input-group__input--num" type="text" inputmode="decimal" name="amount" placeholder="0,00" required>
            </span>
            @error('amount')<span class="field-error">{{ $message }}</span>@enderror
          </label>
          <div class="field">
            <span class="field__label">Data prevista</span>
            <x-datepicker name="competence_date" :value="now()->addMonthNoOverflow()->toDateString()" up />
          </div>
        </div>
        <input type="hidden" name="type" value="income">
        <input type="hidden" name="status" value="planned">
        <input type="hidden" name="payment_channel" value="account">
        <input type="hidden" name="account_id" value="{{ $dashboard['accounts']->first()['account']->id ?? '' }}">
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Adicionar ganho</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: nova assinatura ============ -->
<div class="modal" data-modal="assinatura" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-assinatura-titulo">
    <form method="POST" action="{{ route('transactions.store') }}">
      @csrf
      <input type="hidden" name="type" value="expense">
      <input type="hidden" name="status" value="planned">
      <input type="hidden" name="payment_channel" value="account">
      <input type="hidden" name="account_id" value="{{ $defaultAccountId }}">
      <input type="hidden" name="recurrence_count" value="12">

      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-assinatura-titulo">Nova assinatura</h2>
          <p class="modal__sub">Cadastre uma cobrança que se repete, para ela aparecer nos próximos meses.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <label class="field">
          <span class="field__label">Nome do serviço</span>
          <input class="input" type="text" name="description" placeholder="Ex.: Streaming, academia, nuvem" required>
          @error('description')<span class="field-error">{{ $message }}</span>@enderror
        </label>

        <div class="field-row">
          <label class="field">
            <span class="field__label">Valor</span>
            <span class="input-group">
              <span class="input-group__prefix">R$</span>
              <input class="input-group__input input-group__input--num" type="text" inputmode="decimal" name="amount" placeholder="0,00" required>
            </span>
            @error('amount')<span class="field-error">{{ $message }}</span>@enderror
          </label>
          <div class="field">
            <span class="field__label">Próxima cobrança</span>
            <x-datepicker name="competence_date" :value="now()->addMonth()->startOfMonth()->toDateString()" />
          </div>
        </div>

        <div class="field">
          <span class="field__label">Repete a cada</span>
          <div class="chip-row" data-chip-group>
            <button class="chip is-selected" type="button" data-value="1">Mês</button>
            <button class="chip" type="button" data-value="2">Dois meses</button>
            <button class="chip" type="button" data-value="12">Ano</button>
          </div>
          <input type="hidden" name="recurrence_interval_months" value="1" data-chip-input>
        </div>

        <div class="field">
          <span class="field__label">Categoria</span>
          @php
            $assinaturasCategoria = $categories->firstWhere('name', 'Assinaturas');
          @endphp
          <x-dropdown name="category_id" icon="fa-regular fa-credit-card" up
              :selected="$assinaturasCategoria?->id"
              :options="$categories->where('type', \App\Enums\CategoryType::Expense)->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])" />
        </div>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Salvar assinatura</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: alterar senha ============ -->
<div class="modal" data-modal="senha" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-senha-titulo">
    <form method="POST" action="{{ route('password.update') }}">
      @csrf
      @method('PUT')
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-senha-titulo">Alterar senha</h2>
          <p class="modal__sub">Use uma senha que você ainda não usou em outro lugar.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <label class="field">
          <span class="field__label">Senha atual</span>
          <input class="input" type="password" name="current_password" required>
          @error('current_password', 'updatePassword')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label class="field">
          <span class="field__label">Nova senha</span>
          <input class="input" type="password" name="password" required>
          @error('password', 'updatePassword')<span class="field-error">{{ $message }}</span>@enderror
        </label>
        <label class="field">
          <span class="field__label">Confirmar nova senha</span>
          <input class="input" type="password" name="password_confirmation" required>
        </label>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Salvar senha</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: encerrar outras sessões ============ -->
<div class="modal" data-modal="encerrarSessoes" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-encerrar-sessoes-titulo">
    <form method="POST" action="{{ route('profile.logout-other-sessions') }}">
      @csrf
      @method('PATCH')
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-encerrar-sessoes-titulo">Encerrar as outras sessões</h2>
          <p class="modal__sub">Todos os outros dispositivos conectados à sua conta vão precisar entrar de novo.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <label class="field">
          <span class="field__label">Confirme sua senha</span>
          <input class="input" type="password" name="password" required>
          @error('password')<span class="field-error">{{ $message }}</span>@enderror
        </label>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i>Encerrar as outras</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: encerrar conta ============ -->
<div class="modal" data-modal="encerrarConta" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-encerrar-conta-titulo">
    <form method="POST" action="{{ route('profile.destroy') }}">
      @csrf
      @method('DELETE')
      <div class="modal__head">
        <div>
          <h2 class="modal__title" id="modal-encerrar-conta-titulo">Encerrar conta</h2>
          <p class="modal__sub">Isso apaga sua conta e todos os seus dados. Não dá para desfazer.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>

      <div class="modal__body">
        <label class="field">
          <span class="field__label">Confirme sua senha</span>
          <input class="input" type="password" name="password" required>
          @error('password', 'userDeletion')<span class="field-error">{{ $message }}</span>@enderror
        </label>
      </div>

      <div class="modal__foot">
        <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
        <button class="btn-danger" type="submit">Encerrar conta</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modais de dois fatores ============ -->
{{-- Confirmação de identidade antes de INICIAR a ativação do MFA. O modal de
     senha do handoff (mfa-senha) atende só desativar/regerar, que já nascem com
     campo de senha; ativar não tinha nenhum, e passou a exigir reautenticação
     (achado A1). Vive fora do mfa.js, que é cópia do handoff e não se edita. --}}
<div class="modal" data-modal="mfa-reautenticar" hidden>
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-mfa-reauth-titulo">
    <div class="modal__head">
      <div>
        <h2 class="modal__title" id="modal-mfa-reauth-titulo">Confirme que é você</h2>
        <p class="modal__sub">Ativar a verificação em duas etapas exige confirmar sua identidade.</p>
      </div>
      <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>

    <div class="modal__body">
      <label class="field">
        <span class="field__label">Sua senha atual</span>
        <input class="input" type="password" autocomplete="current-password" placeholder="Sua senha" data-mfa-reauth-password>
      </label>
      <p class="field__error" data-mfa-reauth-error hidden>Senha incorreta. Tente de novo.</p>
      <p class="mfa-links" data-mfa-reauth-social-wrap hidden>
        <a class="link-sm" href="#" data-mfa-reauth-social-link>Não sei minha senha — confirmar pelo provedor</a>
      </p>
    </div>

    <div class="modal__foot">
      <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
      <button class="btn-primary btn-primary--sm" type="button" data-mfa-reauth-confirm>Confirmar</button>
    </div>
  </div>
</div>

<div class="modal" data-modal="mfa-ativar" hidden
     data-mfa-url-start="{{ route('two-factor.enable') }}"
     data-mfa-url-confirm="{{ route('two-factor.confirm') }}">
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="modal-mfa-titulo">
    <div class="modal__head modal__head--stack">
      <div class="modal__head-row">
        <div>
          <h2 class="modal__title" id="modal-mfa-titulo" data-mfa-modal-title>Ativar dois fatores</h2>
          <p class="modal__sub" data-mfa-modal-sub>Três etapas rápidas: escanear, confirmar e guardar os códigos de recuperação.</p>
        </div>
        <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>
      <div class="steps" data-mfa-steps>
        <span class="steps__item" data-step="1"><span class="steps__bar"></span><span class="steps__label">1. Escanear</span></span>
        <span class="steps__item" data-step="2"><span class="steps__bar"></span><span class="steps__label">2. Confirmar</span></span>
        <span class="steps__item" data-step="3"><span class="steps__bar"></span><span class="steps__label">3. Recuperação</span></span>
      </div>
    </div>

    <div class="modal__body" data-mfa-step="1">
      <p class="mfa-step__text">Abra seu app autenticador (Google Authenticator, Authy, 1Password) e escaneie o código abaixo.</p>
      <div class="mfa-scan">
        <span class="mfa-scan__qr-wrap">
          <span class="mfa-qr" aria-label="QR code de ativação" data-mfa-qr></span>
          <span class="mfa-scan__caption">Escaneie com o app</span>
        </span>
        <div class="mfa-scan__manual">
          <span class="mfa-scan__label">Não consegue escanear? Digite a chave:</span>
          <span class="mfa-key">
            <code class="mfa-key__code" data-mfa-key>••••</code>
            <button class="mfa-key__copy" type="button" aria-label="Copiar chave" data-mfa-copy-key><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
          </span>
          <span class="mfa-scan__note" data-mfa-key-note>A chave vale só para esta ativação.</span>
        </div>
      </div>
    </div>

    <div class="modal__body" data-mfa-step="2" hidden>
      <p class="mfa-step__text">Digite o código de 6 dígitos que aparece no app agora. Ele muda a cada 30 segundos.</p>
      <label class="field">
        <span class="field__label">Código de verificação</span>
        <input class="input input--code" type="text" inputmode="numeric" maxlength="6" placeholder="000000" data-mfa-code>
      </label>
      <div class="alert alert--danger" data-mfa-code-error hidden>
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span class="alert__body">
          <span class="alert__title" data-mfa-code-error-text>Código inválido ou já expirado.</span>
          <span class="alert__text">Confira se o relógio do celular está no automático e tente com o código atual.</span>
        </span>
      </div>
    </div>

    <div class="modal__body" data-mfa-step="3" hidden>
      <div class="alert alert--neutral">
        <i class="fa-solid fa-key" aria-hidden="true"></i>
        <span class="alert__text">Cada código serve uma vez só. São a <strong>única forma de entrar</strong> se você perder o celular — guarde num lugar seguro.</span>
      </div>
      <div class="mfa-codes" data-mfa-codes></div>
      <div class="mfa-card__actions">
        <button class="btn-outline btn-outline--sm" type="button" data-mfa-copy-codes><i class="fa-regular fa-copy" aria-hidden="true"></i>Copiar todos</button>
        <button class="btn-outline btn-outline--sm" type="button" data-mfa-download><i class="fa-solid fa-download" aria-hidden="true"></i>Baixar .txt</button>
      </div>
      <label class="mfa-confirm" data-mfa-confirm-box>
        <input class="checkbox" type="checkbox" data-mfa-saved>
        <span>Eu salvei meus códigos de recuperação</span>
      </label>
    </div>

    <div class="modal__foot modal__foot--split">
      <button class="btn-ghost" type="button" data-mfa-back hidden><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Voltar</button>
      <div class="modal__foot-right">
        <button class="btn-ghost" type="button" data-modal-close data-mfa-cancel>Cancelar</button>
        <button class="btn-primary btn-primary--sm" type="button" data-mfa-next><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span data-mfa-next-label>Já escaneei</span></button>
      </div>
    </div>
  </div>
</div>

<div class="modal" data-modal="mfa-senha" hidden
     data-mfa-url-disable="{{ route('two-factor.disable') }}"
     data-mfa-url-regen="{{ route('two-factor.recovery-codes') }}">
  <div class="modal__veil" data-modal-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-mfa-off-titulo">
    <div class="modal__head">
      <div>
        <h2 class="modal__title" id="modal-mfa-off-titulo" data-mfa-off-title>Desativar dois fatores?</h2>
        <p class="modal__sub" data-mfa-off-sub>Sua conta volta a ser protegida só pela senha.</p>
      </div>
      <button class="modal__close" type="button" aria-label="Fechar" data-modal-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="modal__body">
      <div class="alert alert--danger" data-mfa-off-alert>
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span class="alert__text" data-mfa-off-alert-text>Seus códigos de recuperação atuais serão invalidados. Se ativar de novo depois, o app terá que ser reconfigurado.</span>
      </div>
      <label class="field">
        <span class="field__label">Confirme sua senha atual</span>
        <input class="input" type="password" autocomplete="current-password" placeholder="Sua senha" data-mfa-password>
      </label>
      <p class="field__error" data-mfa-password-error hidden>Senha incorreta. Tente de novo.</p>
    </div>
    <div class="modal__foot">
      <button class="btn-ghost" type="button" data-modal-close>Cancelar</button>
      <button class="btn-danger" type="button" data-mfa-confirm-off><i class="fa-solid fa-ban" aria-hidden="true"></i><span data-mfa-off-confirm-label>Desativar</span></button>
    </div>
  </div>
</div>

</x-dashboard-layout>
