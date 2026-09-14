@php
    $userSettings = auth()->user()->settings()->firstOrCreate();
    // A maioria das páginas que este layout servia virou aba do dashboard novo
    // (Prompt 6) — só sobram aqui detalhes/edições sem equivalente na aba
    // ainda (conta, investimento, cartão, fatura aberta, relatórios
    // detalhados, notificações), acessados por link direto, nunca por essa
    // barra. Por isso a navegação encolheu pra só isto.
    //
    // Os links de entrada vivem no dashboard: "Extrato completo" no histórico
    // da conta, "Histórico completo" no menu do investimento, "Abrir página do
    // cartão" no menu do cartão e "Ver todas" no sino de notificações. Se algum
    // deles sair, a página correspondente volta a ficar inalcançável — foi
    // exatamente o que uma auditoria de consistência pegou aqui.
    $nav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Visão geral', 'icon' => 'fa-chart-pie'],
        ['route' => 'reports.index', 'match' => 'reports.*', 'label' => 'Relatórios', 'icon' => 'fa-chart-column'],
    ];
    $primaryNav = array_slice($nav, 0, 6);
    $secondaryNav = array_slice($nav, 6);
    $secondaryNavIsActive = collect($secondaryNav)->contains(
        fn (array $item): bool => request()->routeIs($item['match'])
    );
    $userInitials = mb_strtoupper(mb_substr(auth()->user()->name, 0, 2));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $userSettings->theme === 'dark' ? 'dark' : '' }}" data-theme="{{ $userSettings->theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FFF6E6">
    <meta name="application-name" content="financi.ai">
    <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('design/assets/capi/capi-rosto.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <script>
        try {
            localStorage.setItem('financi-theme', @json($userSettings->theme));
            if (localStorage.getItem('financi-sidebar-collapsed') === 'true') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (error) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell-body">
<div class="min-h-screen">
    <aside id="desktop-sidebar" class="app-sidebar fixed inset-y-0 left-0 z-40 hidden lg:flex lg:flex-col">
        <div class="sidebar-brand-row">
            <a href="{{ route('dashboard') }}" data-sidebar-full-brand aria-label="Ir para a visão geral">
                <x-application-logo size="sidebar" />
            </a>
            <a href="{{ route('dashboard') }}" data-sidebar-compact-brand class="hidden" aria-label="Ir para a visão geral">
                <x-application-logo compact />
            </a>
            <button type="button" data-desktop-sidebar-toggle class="app-icon-button shrink-0" aria-label="Recolher menu lateral" aria-expanded="true" title="Recolher menu">
                <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
            </button>
        </div>

        <section class="sidebar-conversations" aria-labelledby="conversation-history-title">
            <div class="sidebar-section-heading" data-sidebar-label>
                <div>
                    <p class="sidebar-eyebrow">Assistente financeiro</p>
                    <h2 id="conversation-history-title">Conversas recentes</h2>
                </div>
                <span class="sidebar-status">Em breve</span>
            </div>

            <div class="sidebar-conversation-empty">
                <span class="sidebar-conversation-icon" aria-hidden="true">
                    <i class="fa-regular fa-message"></i>
                </span>
                <div data-sidebar-label>
                    <p class="font-semibold">Nenhuma conversa ainda</p>
                    <p class="mt-1 text-xs leading-relaxed text-foreground-tertiary">
                        Seu histórico com a financi.ai aparecerá aqui quando o assistente for ativado.
                    </p>
                </div>
            </div>
        </section>

        <div class="sidebar-user-area">
            <a href="{{ route('dashboard').'#config' }}" title="Configurações" class="sidebar-user-profile">
                <span class="sidebar-avatar">{{ $userInitials }}</span>
                <span data-sidebar-label class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs text-foreground-tertiary">{{ auth()->user()->email }}</span>
                </span>
                <span class="app-icon-button size-9" data-sidebar-label aria-hidden="true">
                    <i class="fa-solid fa-gear"></i>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
                @csrf
                <button class="app-icon-button w-full" aria-label="Sair" data-tooltip="Sair">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    <span data-sidebar-label>Sair</span>
                </button>
            </form>
        </div>
    </aside>

    <div id="app-content">
        <header class="app-topbar sticky top-0 z-30">
            <div class="flex items-center gap-2 lg:hidden">
                <button type="button" data-sidebar-open class="app-icon-button" aria-label="Abrir conversas">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <a href="{{ route('dashboard') }}" aria-label="Ir para a visão geral">
                    <x-application-logo compact />
                </a>
            </div>

            <div class="app-action-cluster">
                <a href="{{ route('notifications.index') }}" class="app-icon-button relative" aria-label="Notificações" data-tooltip="Notificações">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                    @if(auth()->user()->unreadNotifications()->exists())
                        <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-red-500" aria-label="Há notificações não lidas"></span>
                    @endif
                </a>
                <button type="button" id="toggle-theme" data-url="{{ route('settings.toggle-theme') }}" class="app-icon-button cursor-pointer" aria-label="{{ $userSettings->theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo noturno' }}" data-tooltip="{{ $userSettings->theme === 'dark' ? 'Modo claro' : 'Modo noturno' }}">
                    <i class="fa-solid {{ $userSettings->theme === 'dark' ? 'fa-sun' : 'fa-moon' }}" aria-hidden="true"></i>
                </button>
                <button type="button" id="toggle-values" data-url="{{ route('settings.toggle-values') }}" data-hidden="{{ $userSettings->hide_values ? 'true' : 'false' }}" class="app-icon-button cursor-pointer" aria-label="{{ $userSettings->hide_values ? 'Exibir valores' : 'Ocultar valores' }}" data-tooltip="{{ $userSettings->hide_values ? 'Exibir valores' : 'Ocultar valores' }}">
                    <i class="fa-solid {{ $userSettings->hide_values ? 'fa-eye-slash' : 'fa-eye' }}" aria-hidden="true"></i>
                </button>
                <a href="{{ route('dashboard') }}" class="btn-primary">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span class="hidden sm:inline">Nova transação</span>
                </a>
            </div>
        </header>

        <div class="app-tabs-stage">
            <nav class="floating-tabs hidden lg:flex" aria-label="Navegação principal">
                @foreach($primaryNav as $item)
                    <a href="{{ route($item['route']) }}" class="floating-tab {{ request()->routeIs($item['match']) ? 'is-active' : '' }}" @if(request()->routeIs($item['match'])) aria-current="page" @endif>
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach

                <details class="floating-more">
                    <summary class="floating-tab {{ $secondaryNavIsActive ? 'is-active' : '' }}">
                        <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                        <span>Mais</span>
                        <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                    </summary>
                    <div class="floating-more-menu">
                        @foreach($secondaryNav as $item)
                            <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['match']) ? 'is-active' : '' }}" @if(request()->routeIs($item['match'])) aria-current="page" @endif>
                                <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            </nav>

            <nav class="floating-tabs flex lg:hidden" aria-label="Navegação principal">
                @foreach($nav as $item)
                    <a href="{{ route($item['route']) }}" class="floating-tab {{ request()->routeIs($item['match']) ? 'is-active' : '' }}" @if(request()->routeIs($item['match'])) aria-current="page" @endif>
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        <main class="app-main-content">
            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-semibold">Revise os campos informados.</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>

    <div id="mobile-sidebar" class="fixed inset-0 z-50 hidden lg:hidden">
        <button class="absolute inset-0 bg-slate-950/60" data-sidebar-close aria-label="Fechar conversas"></button>
        <aside class="app-sidebar-mobile relative flex h-full w-[min(21rem,88vw)] flex-col">
            <div class="sidebar-brand-row">
                <x-application-logo size="sidebar" />
                <button data-sidebar-close class="app-icon-button" aria-label="Fechar conversas">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <section class="sidebar-conversations flex-1">
                <div class="sidebar-section-heading">
                    <div>
                        <p class="sidebar-eyebrow">Assistente financeiro</p>
                        <h2>Conversas recentes</h2>
                    </div>
                    <span class="sidebar-status">Em breve</span>
                </div>
                <div class="sidebar-conversation-empty">
                    <span class="sidebar-conversation-icon" aria-hidden="true"><i class="fa-regular fa-message"></i></span>
                    <div>
                        <p class="font-semibold">Nenhuma conversa ainda</p>
                        <p class="mt-1 text-xs leading-relaxed text-foreground-tertiary">
                            Seu histórico com a financi.ai aparecerá aqui quando o assistente for ativado.
                        </p>
                    </div>
                </div>
            </section>
            <div class="sidebar-user-area">
                <a href="{{ route('dashboard').'#config' }}" class="sidebar-user-profile">
                    <span class="sidebar-avatar">{{ $userInitials }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-foreground-tertiary">Configurações</span>
                    </span>
                    <span class="app-icon-button size-9" aria-hidden="true"><i class="fa-solid fa-gear"></i></span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
                    @csrf
                    <button class="app-icon-button w-full" aria-label="Sair" data-tooltip="Sair">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        <span>Sair</span>
                    </button>
                </form>
            </div>
        </aside>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-border bg-surface px-2 pb-[max(.5rem,env(safe-area-inset-bottom))] pt-2 lg:hidden" aria-label="Navegação móvel">
        <a href="{{ route('dashboard') }}" class="flex min-h-12 flex-col items-center justify-center gap-1 text-[11px] font-semibold {{ request()->routeIs('dashboard') ? 'text-primary-600' : 'text-foreground-tertiary' }}"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Início</span></a>
        <a href="{{ route('dashboard').'#transacoes' }}" class="flex min-h-12 flex-col items-center justify-center gap-1 text-[11px] font-semibold text-foreground-tertiary"><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i><span>Transações</span></a>
        <a href="{{ route('dashboard') }}" class="mx-auto grid size-12 place-items-center rounded-full border border-border-strong bg-primary-500 text-xl text-foreground shadow-[2px_2px_0_var(--text-primary)]" aria-label="Nova transação"><i class="fa-solid fa-plus" aria-hidden="true"></i></a>
        <a href="{{ route('dashboard').'#cartoes' }}" class="flex min-h-12 flex-col items-center justify-center gap-1 text-[11px] font-semibold text-foreground-tertiary"><i class="fa-solid fa-credit-card" aria-hidden="true"></i><span>Cartões</span></a>
        <button type="button" data-sidebar-open class="flex min-h-12 flex-col items-center justify-center gap-1 text-[11px] font-semibold text-foreground-tertiary"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Conversas</span></button>
    </nav>
</div>

<div id="toast-container" class="fixed right-4 top-20 z-[70] space-y-2" aria-live="polite">
    @foreach(['success', 'error'] as $kind)
        @if(session($kind))
            <div class="toast max-w-sm rounded-xl border {{ $kind === 'success' ? 'border-accent-400/30 bg-accent-50 text-accent-800' : 'border-red-200 bg-red-50 text-red-800' }} px-4 py-3 text-sm font-medium shadow-lg">
                {{ session($kind) }}
            </div>
        @endif
    @endforeach
</div>

<x-modal name="confirm-modal" title="Confirmar ação">
    <p id="confirm-message" class="text-sm text-foreground-secondary">Deseja continuar?</p>
    <div class="mt-6 flex justify-end gap-2">
        <x-button variant="secondary" data-modal-close>Cancelar</x-button>
        <x-button variant="danger" id="confirm-submit">Confirmar</x-button>
    </div>
</x-modal>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js').catch(() => {}));
    }
</script>
</body>
</html>
