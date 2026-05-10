@php
    $panel      = \Filament\Facades\Filament::getCurrentPanel();
    $profileUrl = $panel?->getProfileUrl() ?? url('/admin/profile');
    $logoutUrl  = $panel?->getLogoutUrl() ?? url('/admin/logout');
@endphp

<div class="siard-topbar-actions flex items-center gap-2 pe-3">
    {{-- Alterar Senha --}}
    <a
        href="{{ $profileUrl }}"
        class="siard-topbar-btn siard-topbar-btn--ghost"
        title="Alterar Senha"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
        <span>Alterar Senha</span>
    </a>

    {{-- Sair --}}
    <form method="POST" action="{{ $logoutUrl }}" style="display:contents">
        @csrf
        <button type="submit" class="siard-topbar-btn siard-topbar-btn--danger" title="Sair">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
            <span>Sair</span>
        </button>
    </form>
</div>
