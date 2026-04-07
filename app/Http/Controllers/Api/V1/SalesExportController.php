<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Reporting\Commands\QueueSalesExportCommand;
use App\Application\Reporting\Handlers\QueueSalesExportHandler;
use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SalesExportController extends Controller
{
    public function __construct(
        private StatefulGuard $auth,
        private TenantContext $tenantContext,
        private LoggerInterface $log,
        private FilesystemFactory $storage
    ) {}

    /**
     * @throws ValidationException
     */
    public function store(
        Request $request,
        QueueSalesExportHandler $handler
    ): JsonResponse {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,preparing,ready,delivered,cancelled'],
        ]);

        /** @var User|null $user */
        $user = $this->auth->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Debes autenticarte para solicitar una exportacion.',
            ], 401);
        }

        $criteriaData = [
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'status' => $validated['status'] ?? null,
        ];

        $tenantId = $this->tenantContext->getTenantUuid();

        $handler->handle(new QueueSalesExportCommand(
            requestorId: $user->getAuthIdentifier(),
            tenantId: $tenantId,
            criteriaData: $criteriaData,
            requestIp: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? 'Unknown',
        ));

        $this->log->info('SalesExport job dispatched', [
            'user_id' => $user->getAuthIdentifier(),
            'tenant_id' => $tenantId,
            'criteria' => $criteriaData,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'La exportacion se esta procesando en segundo plano. Te notificaremos cuando este lista.',
            'status' => 'processing',
        ], 202);
    }

    public function download(Request $request): StreamedResponse|JsonResponse
    {
        $encodedPath = $request->query('path');
        $requestedTenantId = (string) $request->query('tenant', '');

        if (! is_string($encodedPath) || $encodedPath === '' || $requestedTenantId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parametros de descarga invalidos.',
            ], 400);
        }

        $filePath = base64_decode($encodedPath, true);

        if ($filePath === false) {
            return response()->json([
                'success' => false,
                'message' => 'Ruta de archivo invalida.',
            ], 400);
        }

        /** @var User|null $user */
        $user = $this->auth->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Debes autenticarte para descargar este archivo.',
            ], 401);
        }

        $userTenantId = (string) ($user->tenant_id ?? '');

        if ($userTenantId !== $requestedTenantId) {
            $this->log->warning('Unauthorized export download attempt', [
                'user_id' => $user->getAuthIdentifier(),
                'user_tenant_id' => $userTenantId,
                'requested_tenant_id' => $requestedTenantId,
                'file_path' => $filePath,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para descargar este archivo.',
            ], 403);
        }

        if (! $this->storage->disk('local')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo ya no esta disponible. Los reportes expiran despues de 24 horas.',
            ], 404);
        }

        return $this->storage->disk('local')->download($filePath, basename($filePath), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
