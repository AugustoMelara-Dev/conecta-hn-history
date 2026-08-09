<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\CrmCompany;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use Filament\Facades\Filament;
use Filament\Forms\Components\CaptchaField;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * WebToLeadPage - Formulario público de captura de leads.
 *
 * - Rate limit: 60 req/min por IP
 * - Captcha placeholder (integrar hCaptcha/reCAPTCHA en producción)
 * - Crea Contact + crea Deal en pipeline por defecto
 */
class WebToLeadPage extends Page
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
        // Public page - no auth required
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cuéntanos sobre ti')
                    ->description('Nos pondremos en contacto contigo en menos de 24 horas.')
                    ->components([
                        TextInput::make('first_name')->label('Nombre')->required()->maxLength(100),
                        TextInput::make('last_name')->label('Apellido')->required()->maxLength(100),
                        TextInput::make('email')->label('Email')->email()->required()->maxLength(255),
                        TextInput::make('phone')->label('Teléfono')->tel()->maxLength(50),
                        TextInput::make('company_name')->label('Empresa')->maxLength(200),
                        TextInput::make('job_title')->label('Cargo')->maxLength(100),
                        Textarea::make('message')->label('Mensaje')->rows(4),
                        CaptchaField::make('captcha')->label('Verificación'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        // Rate limit per IP
        $key = 'web-to-lead:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            Notification::make()
                ->title('Demasiadas solicitudes')
                ->body('Intenta nuevamente en un minuto.')
                ->danger()
                ->send();

            return;
        }
        RateLimiter::hit($key, 60);

        DB::transaction(function () use ($state) {
            $tenantId = Filament::getTenant()?->getKey() ?? config('app.default_tenant_id');

            $company = null;
            if (! empty($state['company_name'])) {
                $company = CrmCompany::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => $state['company_name']],
                    ['tenant_id' => $tenantId, 'name' => $state['company_name']],
                );
            }

            $contact = Contact::create([
                'tenant_id' => $tenantId,
                'company_id' => $company?->id,
                'first_name' => $state['first_name'],
                'last_name' => $state['last_name'],
                'email' => $state['email'],
                'phone' => $state['phone'] ?? null,
                'job_title' => $state['job_title'] ?? null,
                'source' => 'web-to-lead',
                'lifecycle_stage' => 'lead',
            ]);

            $defaultPipeline = CrmPipeline::where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->first();
            if ($defaultPipeline) {
                $firstStage = $defaultPipeline->stages()->orderBy('sort_order')->first();
                if ($firstStage) {
                    CrmDeal::create([
                        'tenant_id' => $tenantId,
                        'pipeline_id' => $defaultPipeline->id,
                        'stage_id' => $firstStage->id,
                        'contact_id' => $contact->id,
                        'company_id' => $company?->id,
                        'name' => 'Lead desde web: '.$contact->full_name,
                        'value' => 0,
                        'currency' => 'HNL',
                        'probability' => 10,
                        'status' => 'open',
                    ]);
                }
            }

            CrmActivity::create([
                'tenant_id' => $tenantId,
                'contact_id' => $contact->id,
                'type' => 'note',
                'subject' => 'Mensaje desde web-to-lead',
                'body' => $state['message'] ?? '',
            ]);
        });

        $this->form->fill();
        Notification::make()
            ->title('¡Gracias!')
            ->body('Hemos recibido tu información.')
            ->success()
            ->send();
    }
}
