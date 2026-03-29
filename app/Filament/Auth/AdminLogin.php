<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class AdminLogin extends Login
{
    protected string $view = 'filament.auth.admin-login';

    protected array $extraBodyAttributes = [
        'class' => 'fi-panel-admin-auth',
    ];

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();
            $panel = Filament::getCurrentOrDefaultPanel();

            if (($user instanceof FilamentUser) && $user->canAccessPanel($panel)) {
                redirect()->intended(Filament::getUrl());

                return;
            }

            Filament::auth()->logout();
            session()->invalidate();
            session()->regenerateToken();
        }

        $this->form->fill();
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Las credenciales son incorrectas o esta cuenta no pertenece al panel de superadministración.',
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Acceso de superadministración';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Panel de superadministración';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            'Usa una cuenta autorizada como superadministrador. '.
            'Si vas a entrar al panel de tu negocio, usa <a href="/login" style="color:#34d399;font-weight:700;text-decoration:none;">/login</a>.'
        );
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->extraAttributes(['class' => 'biz-auth-input-wrap'])
            ->extraFieldWrapperAttributes(['class' => 'biz-auth-field']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->extraAttributes(['class' => 'biz-auth-input-wrap'])
            ->extraFieldWrapperAttributes(['class' => 'biz-auth-field']);
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->extraFieldWrapperAttributes(['class' => 'biz-auth-remember']);
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->extraAttributes(['class' => 'biz-auth-submit']);
    }
}
