<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Application\BusinessAi\Contracts\BusinessAiAuthorizerInterface;
use App\Application\BusinessAi\Contracts\BusinessRagServiceInterface;
use App\Jobs\BusinessAi\ReindexTenantBusinessKnowledgeJob;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class BusinessCopilot extends Page
{
    protected static bool $isDiscovered = false;

    protected static string $routePath = 'copilot';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Operacion';

    protected static ?string $navigationLabel = 'Copiloto IA';

    protected static ?string $title = 'Copiloto privado';

    protected string $view = 'filament.app.pages.business-copilot';

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::$routePath;
    }

    public string $question = '';

    public ?string $answer = null;

    public ?string $statusMessage = null;

    public ?string $diagnosticState = null;

    public ?string $probableCause = null;

    public ?string $recommendedAction = null;

    public ?string $traceId = null;

    /** @var list<array<string, mixed>> */
    public array $sources = [];

    /** @var array<string, mixed> */
    public array $meta = [];

    /** @var list<string> */
    public array $suggestedQuestions = [
        'Que productos o servicios deberia empujar hoy?',
        'Que clientes o pedidos necesitan seguimiento?',
        'Que esta frenando mis ventas esta semana?',
        'Que debo configurar para que mi negocio aparezca mejor?',
    ];

    public function ask(): void
    {
        $this->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $tenant = $this->tenant();
        $user = $this->user();

        if (! $this->authorizer()->authorize($user, $tenant)) {
            $this->setDiagnostic(
                state: 'Sin permiso',
                cause: 'Tu rol actual no tiene permiso para usar IA privada del negocio.',
                action: 'Pide a un owner/admin que habilite el permiso AI_USE o usa una cuenta con acceso operativo.'
            );

            return;
        }

        $this->traceId = (string) request()->headers->get('x-request-id', (string) str()->uuid());

        try {
            $payload = $this->rag()->answer(
                tenant: $tenant,
                query: $this->question,
                userId: (string) $user->getAuthIdentifier(),
                limit: 6,
            );

            $this->answer = (string) ($payload['answer'] ?? '');
            $this->sources = (array) ($payload['sources'] ?? []);
            $this->meta = (array) ($payload['meta'] ?? []);

            $degradedReason = $this->meta['degraded_reason'] ?? null;
            $this->setDiagnostic(
                state: $this->sources === [] ? 'Sin conocimiento indexado' : 'Respuesta generada',
                cause: is_string($degradedReason) && $degradedReason !== ''
                    ? 'El copiloto respondio con modo degradado: '.$degradedReason.'.'
                    : 'Se consultaron fuentes privadas del tenant activo.',
                action: $this->sources === []
                    ? 'Reindexa el conocimiento del negocio y vuelve a preguntar.'
                    : 'Revisa las fuentes y ejecuta la accion recomendada dentro del panel.'
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->setDiagnostic(
                state: 'No se pudo responder',
                cause: 'El copiloto encontro un error tecnico al consultar conocimiento privado.',
                action: 'Reintenta. Si sigue fallando, comparte la referencia con soporte.'
            );
        }
    }

    public function reindex(): void
    {
        $tenant = $this->tenant();
        $user = $this->user();

        if (! $this->authorizer()->authorize($user, $tenant)) {
            $this->setDiagnostic(
                state: 'Sin permiso',
                cause: 'Tu rol actual no puede reindexar conocimiento privado.',
                action: 'Pide a un owner/admin que ejecute la reindexacion.'
            );

            return;
        }

        ReindexTenantBusinessKnowledgeJob::dispatch(
            tenantId: (string) $tenant->getKey(),
            requestedBy: (string) $user->getAuthIdentifier(),
        );

        $this->setDiagnostic(
            state: 'Reindexacion en cola',
            cause: 'El copiloto esta actualizando el conocimiento privado en segundo plano para no bloquear el panel.',
            action: 'Espera unos minutos y vuelve a preguntar. Si sigue sin fuentes, comparte la referencia con soporte.'
        );

        Notification::make()
            ->title('Reindexacion en cola')
            ->body('El conocimiento privado se actualizara en segundo plano.')
            ->success()
            ->send();
    }

    public function useSuggestion(string $question): void
    {
        $this->question = $question;
    }

    private function setDiagnostic(string $state, string $cause, string $action): void
    {
        $this->diagnosticState = $state;
        $this->probableCause = $cause;
        $this->recommendedAction = $action;
        $this->statusMessage = $state;
        $this->traceId ??= (string) request()->headers->get('x-request-id', (string) str()->uuid());
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }

    private function user(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function authorizer(): BusinessAiAuthorizerInterface
    {
        return app(BusinessAiAuthorizerInterface::class);
    }

    private function rag(): BusinessRagServiceInterface
    {
        return app(BusinessRagServiceInterface::class);
    }
}
