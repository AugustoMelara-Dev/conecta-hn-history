{{-- Impersonation Banner - Shadow Login Indicator --}}
@if(session('impersonation_grant_id'))
<div style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 9999;
    font-family: 'Segoe UI', sans-serif;
    font-size: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
">
    <div style="display: flex; align-items: center; gap: 10px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
        <span>
            <strong>MODO SOPORTE:</strong> 
            Estas viendo el panel como <strong>{{ Auth::user()->name }}</strong>. 
            (Sesion iniciada por: {{ session('impersonator_name', 'Admin') }})
            @if(session('impersonation_expires_at'))
                · Expira {{ \Illuminate\Support\Carbon::parse(session('impersonation_expires_at'))->diffForHumans() }}
            @endif
        </span>
    </div>
    <form method="POST" action="{{ route('impersonate.leave') }}">
        @csrf
    <button type="submit" style="
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 6px 16px;
        border: 0;
        border-radius: 6px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: background 0.2s;
    " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
        Regresar a mi sesion
    </button>
    </form>
</div>
<div style="height: 50px;"></div> {{-- Spacer to push content down --}}
@endif
