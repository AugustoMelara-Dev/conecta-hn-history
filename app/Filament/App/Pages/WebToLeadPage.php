<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Application\Crm\CanonicalCrmEntityWriter;
use App\Infrastructure\Crm\HttpWebToLeadCaptchaVerifier;
use App\Models\CrmCompany;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final class WebToLeadPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $title = 'Captura de Leads';

    protected static ?string $navigationLabel = 'Web-to-Lead';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.app.pages.web-to-lead';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function canAccess(): bool
    {
        return auth()->check() && Filament::getTenant() !== null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nuevo contacto')->components([
                TextInput::make('first_name')->label('Nombre')->required()->maxLength(100),
                TextInput::make('last_name')->label('Apellido')->required()->maxLength(100),
                TextInput::make('email')->label('Email')->email()->required()->maxLength(255),
                TextInput::make('phone')->label('Teléfono')->tel()->maxLength(50),
                TextInput::make('company_name')->label('Empresa')->maxLength(200),
                TextInput::make('job_title')->label('Cargo')->maxLength(100),
                Textarea::make('message')->label('Mensaje')->rows(4)->maxLength(5000),
                Hidden::make('captcha')->required(),
            ]),
        ])->statePath('data');
    }

    public function submit(
        HttpWebToLeadCaptchaVerifier $captcha,
        CanonicalCrmEntityWriter $writer,
    ): void {
        $state = $this->form->getState();
        $tenant = Filament::getTenant();
        $actorId = auth()->id();
        if ($tenant === null || ! is_string($actorId)) {
            abort(403);
        }
        $tenantId = (string) $tenant->getKey();
        $key = 'web-to-lead:'.$tenantId.':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            Notification::make()->title('Demasiadas solicitudes')->danger()->send();

            return;
        }
        RateLimiter::hit($key, 60);

        if (! $captcha->verify((string) $state['captcha'], request()->ip())) {
            Notification::make()
                ->title('No pudimos verificar la solicitud')
                ->body('Completa la verificación e intenta nuevamente.')
                ->danger()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($state, $actorId, $writer): void {
                $tenantId = (string) Filament::getTenant()?->getKey();
                if ($tenantId === '') {
                    abort(403);
                }
                $companyId = null;
                if (filled($state['company_name'] ?? null)) {
                    $company = CrmCompany::query()
                        // CANONICAL_WRITER_TENANT_FILTER: deduplication is constrained to the active tenant before canonical CRM write.
                        ->withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('name', trim((string) $state['company_name']))
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->first();
                    $company ??= $writer->createCompany($tenantId, $actorId, [
                        'name' => trim((string) $state['company_name']),
                    ]);
                    $companyId = (string) $company->getKey();
                }

                $contact = $writer->createContact($tenantId, $actorId, [
                    'company_id' => $companyId,
                    'first_name' => (string) $state['first_name'],
                    'last_name' => (string) $state['last_name'],
                    'email' => (string) $state['email'],
                    'phone' => $state['phone'] ?? null,
                    'job_title' => $state['job_title'] ?? null,
                    'source' => 'web-to-lead',
                    'lifecycle_stage' => 'lead',
                ]);

                $writer->createActivity($tenantId, $actorId, [
                    'contact_id' => (string) $contact->getKey(),
                    'company_id' => $companyId,
                    'type' => 'note',
                    'subject' => 'Mensaje desde web-to-lead',
                    'body' => (string) ($state['message'] ?? ''),
                ]);
            }, 3);
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->title('No se pudo guardar el contacto')->danger()->send();

            return;
        }

        $this->form->fill();
        Notification::make()->title('Contacto recibido')->success()->send();
    }
}
