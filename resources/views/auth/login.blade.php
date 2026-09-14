@php
    $modes = ['login', 'registro', 'recuperar', 'redefinir', 'verificar', 'desafio'];
    $mode = in_array($mode ?? null, $modes, true) ? $mode : 'login';
    $copy = [
        'login' => [
            'titulo' => 'Bem-vindo de volta.',
            'subtitulo' => 'Entre para acompanhar suas contas, metas e compromissos dos próximos meses.',
        ],
        'registro' => [
            'titulo' => 'Comece a organizar hoje.',
            'subtitulo' => 'Crie sua conta gratuita e registre sua realidade financeira em poucos minutos.',
        ],
        'recuperar' => session('status')
            ? ['titulo' => 'Confira seu e-mail.', 'subtitulo' => 'Mandamos um link de redefinição. Ele vale por 60 minutos e só pode ser usado uma vez.']
            : ['titulo' => 'Recuperar acesso.', 'subtitulo' => 'Informe o e-mail da sua conta e enviamos um link para você criar uma senha nova.'],
        'redefinir' => [
            'titulo' => 'Defina uma nova senha.',
            'subtitulo' => 'Escolha uma senha que você não use em outro serviço. Os critérios abaixo precisam estar todos verdes.',
        ],
        'verificar' => [
            'titulo' => 'Falta confirmar seu e-mail.',
            'subtitulo' => 'Enviamos um link de confirmação para o e-mail cadastrado. Depois de confirmar, seu painel fica liberado.',
        ],
        'desafio' => [
            'titulo' => 'Só falta o segundo passo.',
            'subtitulo' => 'Sua conta tem verificação em duas etapas ativa. Confirme o código para concluir o login.',
        ],
    ];
    $titulos = [
        'login' => 'Entrar',
        'registro' => 'Criar conta',
        'recuperar' => 'Recuperar acesso',
        'redefinir' => 'Redefinir senha',
        'verificar' => 'Verificar e-mail',
        'desafio' => 'Verificação em duas etapas',
    ];
@endphp
<x-auth-layout :title="$titulos[$mode]">

@if(config('features.registration'))
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif

<div class="auth">

  <section class="auth__form-side">

    <div class="auth__top">
      <a class="brand" href="{{ url('/') }}">
        <img class="brand__mark" src="{{ asset('design/assets/capi/capi-rosto.png') }}" alt="">
        <span class="brand__name">financi<span class="brand__name-accent">aí</span></span>
      </a>
      <div class="auth__top-actions">
        <button class="btn-icon" type="button" data-theme-toggle aria-label="Alternar tema">☾</button>
        <a class="btn-outline-hard" href="{{ url('/') }}">Voltar ao site</a>
      </div>
    </div>

    <div class="auth__center">
      <div class="auth__card">

        <h1 class="auth__title" data-auth-title>{{ $copy[$mode]['titulo'] }}</h1>
        <p class="auth__subtitle" data-auth-subtitle>{{ $copy[$mode]['subtitulo'] }}</p>

        @if(config('features.registration') && in_array($mode, ['login', 'registro'], true))
            <div class="tabs auth__tabs" role="tablist" data-tabs data-active="{{ $mode }}">
                <span class="tabs__indicator" aria-hidden="true"></span>
                <button class="tabs__btn" type="button" role="tab" data-tab="login" data-url="{{ route('login') }}" aria-selected="{{ $mode === 'login' ? 'true' : 'false' }}">Entrar</button>
                <button class="tabs__btn" type="button" role="tab" data-tab="registro" data-url="{{ route('register') }}" aria-selected="{{ $mode === 'registro' ? 'true' : 'false' }}">Criar conta</button>
            </div>
        @endif

        @if ($mode === 'login' && session('status'))
            <p class="form-status">{{ session('status') }}</p>
        @endif

        <div class="auth__panes">

          <form class="auth-form auth-form--login" data-pane="login" data-state="{{ $mode === 'login' ? 'active' : 'hidden' }}" method="post" action="{{ route('login') }}">
            @csrf

            @if ($errors->has('social'))
                <div class="form-alert" role="alert">{{ $errors->first('social') }}</div>
            @endif

            <div class="auth__social">
                <a class="btn-social" href="{{ route('social.redirect', ['provider' => 'google']) }}">
                    <svg class="btn-social__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.6v3h3.9c2.2-2.1 3.5-5.2 3.5-8.8zM12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3c-1.1.7-2.4 1.1-4 1.1-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1A12 12 0 0 0 12 24zM5.4 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.4a12 12 0 0 0 0 10.8l4-3.1zM12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4C17.9 1.2 15.2 0 12 0 7.3 0 3.3 2.7 1.4 6.6l4 3.1C6.3 6.9 8.9 4.8 12 4.8z"/></svg>
                    Google
                </a>
                <a class="btn-social" href="{{ route('social.redirect', ['provider' => 'github']) }}">
                    <svg class="btn-social__icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38l-.01-1.34c-2.23.48-2.7-1.07-2.7-1.07-.36-.93-.89-1.18-.89-1.18-.73-.5.05-.49.05-.49.81.06 1.23.83 1.23.83.72 1.23 1.88.87 2.34.67.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82a7.6 7.6 0 0 1 4 0c1.53-1.03 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.28.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48l-.01 2.2c0 .21.15.46.55.38A8 8 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>
                    GitHub
                </a>
            </div>

            <div class="divider">
                <span class="divider__line"></span>
                <span class="divider__label">ou continue com e-mail</span>
                <span class="divider__line"></span>
            </div>

            <label class="field">
              <span class="field__label">E-mail</span>
              <input class="input" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="voce@email.com">
              @error('email', 'login') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="field">
              <span class="field__label-row">
                Senha
                <a class="link-sm" href="{{ route('password.request') }}">Esqueci minha senha</a>
              </span>
              <span class="input-group">
                <input class="input-group__input" type="password" name="password" autocomplete="current-password" placeholder="••••••••">
                <button class="input-group__action" type="button" data-password-toggle aria-label="Mostrar ou ocultar senha">Mostrar</button>
              </span>
              @error('password', 'login') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="checkbox-row">
              <input class="checkbox" type="checkbox" name="remember">
              Manter conectado neste dispositivo
            </label>

            <button class="btn-primary" type="submit">Entrar na minha conta</button>
          </form>

          @if(config('features.registration'))
              <form class="auth-form auth-form--registro" data-pane="registro" data-state="{{ $mode === 'registro' ? 'active' : 'hidden' }}" method="post" action="{{ route('register') }}">
                @csrf

                <label class="field">
                  <span class="field__label">Nome completo</span>
                  <input class="input" type="text" name="name" value="{{ old('name') }}" autocomplete="name" placeholder="Como você quer ser chamado">
                  @error('name', 'registro') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="field">
                  <span class="field__label">E-mail</span>
                  <input class="input" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="voce@email.com">
                  @error('email', 'registro') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="field">
                  <span class="field__label">Senha</span>
                  <span class="input-group">
                    <input class="input-group__input" type="password" name="password" id="regPassword" autocomplete="new-password" placeholder="Mínimo de 8 caracteres">
                    <button class="input-group__action" type="button" data-password-toggle aria-label="Mostrar ou ocultar senha">Mostrar</button>
                  </span>
                  <x-auth.password-meter prefix="reg" />
                  @error('password', 'registro') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="field">
                  <span class="field__label">Confirmar senha</span>
                  <input class="input" type="password" name="password_confirmation" id="regConfirm" autocomplete="new-password" placeholder="Repita a senha">
                  <span class="pw-confirm" id="regConfirmMsg" hidden></span>
                </label>

                <label class="checkbox-row checkbox-row--top">
                  <input class="checkbox" type="checkbox" name="terms" required>
                  <span>Li e aceito os <a href="#termos">termos de uso</a> e a <a href="#privacidade">política de privacidade</a>.</span>
                </label>
                @error('terms', 'registro') <span class="field-error">{{ $message }}</span> @enderror

                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                @error('cf-turnstile-response', 'registro') <span class="field-error">{{ $message }}</span> @enderror

                <button class="btn-primary" type="submit" id="regSubmit" disabled aria-disabled="true">Criar minha conta</button>
              </form>
          @endif

          @if($mode === 'recuperar')
              <form class="auth-form auth-form--recuperar" data-pane="recuperar" data-state="active" method="post" action="{{ route('password.email') }}">
                @csrf

                @if(! session('status'))
                    <div class="auth-step" data-step="pedir">
                      <label class="field">
                        <span class="field__label">E-mail da conta</span>
                        <input class="input" type="email" name="email" id="recEmail" value="{{ old('email') }}" autocomplete="email" placeholder="voce@email.com">
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                      </label>
                      <button class="btn-primary" type="submit" id="recSubmit" disabled aria-disabled="true">Enviar link de redefinição</button>
                      <a class="link-back" href="{{ route('login') }}"><span aria-hidden="true">&#8592;</span>Voltar para o login</a>
                    </div>
                @else
                    <div class="auth-step" data-step="enviado">
                      <div class="notice">
                        <span class="notice__icon" aria-hidden="true">&#10003;</span>
                        <span class="notice__body">
                          <span class="notice__title">Link enviado para <span data-email-echo>{{ session('sentEmail') }}</span></span>
                          <span class="notice__text">Não chegou? Confira a caixa de spam antes de pedir outro.</span>
                        </span>
                      </div>
                      <div class="auth-actions">
                        <button class="btn-outline-hard" type="submit" data-resend name="email" value="{{ session('sentEmail') }}">Reenviar e-mail</button>
                      </div>
                      <a class="link-back" href="{{ route('login') }}"><span aria-hidden="true">&#8592;</span>Voltar para o login</a>
                    </div>
                @endif
              </form>
          @endif

          @if($mode === 'redefinir')
              <form class="auth-form auth-form--redefinir" data-pane="redefinir" data-state="active" method="post" action="{{ route('password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">
                <label class="field">
                  <span class="field__label">Nova senha</span>
                  <span class="input-group">
                    <input class="input-group__input" type="password" name="password" id="rstPassword" autocomplete="new-password" placeholder="Mínimo de 8 caracteres">
                    <button class="input-group__action" type="button" data-password-toggle aria-label="Mostrar ou ocultar senha">Mostrar</button>
                  </span>
                  <x-auth.password-meter prefix="rst" />
                  @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">
                  <span class="field__label">Confirmar nova senha</span>
                  <input class="input" type="password" name="password_confirmation" id="rstConfirm" autocomplete="new-password" placeholder="Repita a senha">
                  <span class="pw-confirm" id="rstConfirmMsg" hidden></span>
                </label>
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
                <button class="btn-primary" type="submit" id="rstSubmit" disabled aria-disabled="true">Salvar nova senha</button>
                <a class="link-back" href="{{ route('login') }}"><span aria-hidden="true">&#8592;</span>Voltar para o login</a>
              </form>
          @endif

          @if($mode === 'verificar')
              <div class="auth-form auth-form--verificar" data-pane="verificar" data-state="active">
                <div class="notice">
                  <span class="notice__icon" aria-hidden="true">&#9993;</span>
                  <span class="notice__body">
                    <span class="notice__title">
                      @if(session('registered'))
                        Conta criada com sucesso! Confirmação enviada para <span data-email-echo>{{ auth()->user()->email }}</span>
                      @else
                        Confirmação enviada para <span data-email-echo>{{ auth()->user()->email }}</span>
                      @endif
                    </span>
                    <span class="notice__text">O link expira em 24 horas. Enquanto isso, sua conta fica com acesso limitado.</span>
                  </span>
                </div>
                <ol class="auth-steps">
                  <li class="auth-steps__item"><span class="auth-steps__num" aria-hidden="true">1</span>Abra a mensagem do financiaí na sua caixa de entrada.</li>
                  <li class="auth-steps__item"><span class="auth-steps__num" aria-hidden="true">2</span>Toque em confirmar e-mail. Você volta direto para o painel.</li>
                  <li class="auth-steps__item"><span class="auth-steps__num" aria-hidden="true">3</span>Se não encontrar, confira o spam ou peça o reenvio abaixo.</li>
                </ol>
                <div class="auth-actions">
                  <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button class="btn-primary" type="submit" data-resend>{{ session('status') === 'verification-link-sent' ? 'E-mail reenviado' : 'Reenviar e-mail' }}</button>
                  </form>
                  <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-outline-hard" type="submit">Sair da conta</button>
                  </form>
                </div>
              </div>
          @endif

          @if($mode === 'desafio')
              <div class="auth-form auth-form--mfa" data-pane="mfa" data-state="active">
                <div class="mfa-callout">
                  <span class="mfa-callout__icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1.5a5 5 0 0 0-5 5V9H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-1V6.5a5 5 0 0 0-5-5zm0 2.2a2.8 2.8 0 0 1 2.8 2.8V9H9.2V6.5A2.8 2.8 0 0 1 12 3.7zm0 10.1a1.9 1.9 0 0 1 1 3.5v1.9a1 1 0 0 1-2 0v-1.9a1.9 1.9 0 0 1 1-3.5z"/></svg>
                  </span>
                  <span class="mfa-callout__body">
                    <span class="mfa-callout__title">Verificação em duas etapas</span>
                    <span class="mfa-callout__text" data-mfa-text>Abra seu app autenticador e informe o código de 6 dígitos gerado agora para esta conta.</span>
                  </span>
                </div>

                <form class="mfa-form" method="post" action="{{ route('two-factor.verify') }}" data-mfa-form>
                  @csrf
                  <input type="hidden" name="recovery" value="{{ old('recovery') ? '1' : '0' }}" data-mfa-recovery-flag>

                  <label class="field" data-mfa-field="app" @if(old('recovery')) hidden @endif>
                    <span class="field__label">Código de verificação</span>
                    <input class="input input--code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" name="{{ old('recovery') ? '' : 'code' }}" value="{{ old('recovery') ? '' : old('code') }}" data-mfa-input="app">
                  </label>

                  <label class="field" data-mfa-field="recuperacao" @unless(old('recovery')) hidden @endunless>
                    <span class="field__label">Código de recuperação</span>
                    <input class="input input--recovery" type="text" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX" name="{{ old('recovery') ? 'code' : '' }}" value="{{ old('recovery') ? old('code') : '' }}" data-mfa-input="recuperacao">
                  </label>

                  @error('code', 'mfa')
                      <p class="mfa-error">
                        <span class="mfa-error__mark" aria-hidden="true">!</span>
                        <span role="alert">{{ $message }}</span>
                      </p>
                  @enderror

                  <button class="btn-primary" type="submit" data-mfa-submit>Confirmar e entrar</button>

                  <div class="mfa-links">
                    <a class="link-sm" href="#mfa" data-mfa-switch>{{ old('recovery') ? 'Usar o código do app' : 'Usar um código de recuperação' }}</a>
                    <a class="link-sm link-sm--muted" href="{{ route('login') }}">Cancelar e sair</a>
                  </div>
                </form>
              </div>
          @endif

        </div>

        <p class="auth__disclaimer">O financiaí não movimenta o seu dinheiro e não conecta contas bancárias. Você registra, ele organiza.</p>

      </div>
    </div>

    <div class="auth__footer">
      <span>© {{ now()->year }} financiaí</span>
      <a href="#privacidade">Privacidade</a>
      <a href="#termos">Termos</a>
      <a href="#ajuda">Ajuda</a>
    </div>

  </section>

  <aside class="auth__aside">
    <div class="promo">

      <span class="promo__ring" aria-hidden="true"></span>
      <span class="promo__dot promo__dot--a" aria-hidden="true"></span>
      <span class="promo__dot promo__dot--b" aria-hidden="true"></span>

      <div class="promo__head">
        <span class="badge"><span class="badge__dot"></span>Beta aberto</span>
        <h2 class="promo__title">Sua vida financeira inteira em um só painel.</h2>
        <p class="promo__text">Contas, cartões, metas e compromissos futuros organizados para você decidir com clareza — hoje e nos próximos doze meses.</p>
      </div>

      <div class="promo__proofs">
        <div class="proof">
          <span class="proof__check" aria-hidden="true">✓</span>
          <span class="proof__text">Contas, cartões e faturas no mesmo painel</span>
        </div>
        <div class="proof">
          <span class="proof__check" aria-hidden="true">✓</span>
          <span class="proof__text">Receitas e despesas futuras já projetadas</span>
        </div>
        <div class="proof">
          <span class="proof__check" aria-hidden="true">✓</span>
          <span class="proof__text">Dados isolados por usuário e histórico de segurança</span>
        </div>
        <div class="proof">
          <span class="proof__check" aria-hidden="true">✓</span>
          <span class="proof__text">Exportação dos seus dados quando quiser</span>
        </div>
      </div>

      <div class="promo__bottom">
        <img class="promo__capi" src="{{ asset('design/assets/capi/capi-comemorando.png') }}" alt="Capí comemorando">
        <div class="promo__stats">
          <div class="stat">
            <div class="stat__value">12 meses</div>
            <div class="stat__label">de visão futura</div>
          </div>
          <div class="stat">
            <div class="stat__value">100%</div>
            <div class="stat__label">dados isolados</div>
          </div>
        </div>
      </div>

    </div>
  </aside>

</div>

</x-auth-layout>
