<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Reporting\ReportRegistry;
use App\Application\Reporting\ReportScheduler;
use App\Domain\Access\Enums\Permission;
use App\Domain\Reporting\Enums\ReportFormat;
use App\Domain\Reporting\Enums\ReportFrequency;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\ReportSchedule;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ReportsSchedule extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'Programación de reportes';

    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?string $navigationLabel = 'Programación';

    protected static ?int $navigationSort = 52;

    protected string $view = 'filament.app.pages.reports.reports-schedule';

    public ?string $scheduleReportKey = null;

    public string $scheduleFrequency = 'weekly';

    public string $scheduleFormat = 'pdf';

    public string $scheduleRecipients = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Tenant
            && app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_VIEW);
    }

    public function getViewData(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return [
            'schedules' => $tenantId
                ? ReportSchedule::where('tenant_id', $tenantId)->latest()->get()
                : collect(),
            'reports' => app(ReportRegistry::class)->all(),
            'frequencies' => ReportFrequency::options(),
            'formats' => ReportFormat::options(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function createSchedule(array $data = []): void
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();
        if (! $user instanceof User || ! $tenant instanceof Tenant
            || ! app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_EXPORT)) {
            Notification::make()->title('No hay un negocio activo.')->danger()->send();

            return;
        }

        $tenantId = $tenant->getKey();

        $key = ReportKey::tryFrom((string) ($data['report_key'] ?? $this->scheduleReportKey ?? ''));
        $frequency = ReportFrequency::tryFrom((string) ($data['frequency'] ?? $this->scheduleFrequency));
        $format = ReportFormat::tryFrom((string) ($data['format'] ?? $this->scheduleFormat));
        $submittedRecipients = $data['recipients'] ?? preg_split('/[,;\n]+/', $this->scheduleRecipients);
        $recipients = array_values(array_unique(array_slice(array_filter(array_map(
            static fn (mixed $email): string => mb_strtolower(trim(is_string($email) ? $email : '')),
            is_array($submittedRecipients) ? $submittedRecipients : [],
        ), static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false), 0, 10)));

        if ($key === null || $frequency === null || $format === null
            || ! app(ReportRegistry::class)->has($key) || $recipients === []) {
            Notification::make()->title('Revisa los datos de la programación.')->danger()->send();

            return;
        }

        app(ReportScheduler::class)->schedule($key->value, (string) $tenantId, $frequency->value, $recipients, $format);
        Notification::make()->title('Programación creada')->success()->send();
    }
}
