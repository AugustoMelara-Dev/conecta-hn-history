<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Handoff\AcceptAiHandoff;
use App\Application\Ai\Handoff\AiHandoffConflictException;
use App\Application\Ai\Handoff\AiHandoffForbiddenException;
use App\Application\Ai\Handoff\AiHandoffNotFoundException;
use App\Application\Ai\Handoff\AiHandoffService;
use App\Application\Ai\Handoff\AssignAiHandoff;
use App\Application\Ai\Handoff\ResolveAiHandoff;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class AiHandoffController extends Controller
{
    public function __construct(
        private readonly AiHandoffService $handoffs,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->respond(fn (): array => $this->handoffs->queue(
            $this->tenantId(),
            $this->actorId($request),
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 25),
        ));
    }

    public function show(Request $request, string $handoff): JsonResponse
    {
        return $this->respond(fn (): array => [
            'data' => $this->handoffs->show($this->tenantId(), $handoff, $this->actorId($request)),
        ]);
    }

    public function assign(Request $request, string $handoff): JsonResponse
    {
        $data = $request->validate([
            'assignee_id' => ['required', 'uuid'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $tenantId = $this->tenantId();
        $actorId = $this->actorId($request);

        return $this->respond(function () use ($data, $tenantId, $actorId, $handoff): array {
            $this->handoffs->assign(new AssignAiHandoff(
                $tenantId,
                $handoff,
                $actorId,
                (string) $data['assignee_id'],
                (int) $data['expected_version'],
            ));

            return ['data' => $this->handoffs->show($tenantId, $handoff, $actorId)];
        });
    }

    public function accept(Request $request, string $handoff): JsonResponse
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $tenantId = $this->tenantId();
        $actorId = $this->actorId($request);

        return $this->respond(function () use ($data, $tenantId, $actorId, $handoff): array {
            $this->handoffs->accept(new AcceptAiHandoff(
                $tenantId,
                $handoff,
                $actorId,
                (int) $data['expected_version'],
            ));

            return ['data' => $this->handoffs->show($tenantId, $handoff, $actorId)];
        });
    }

    public function resolve(Request $request, string $handoff): JsonResponse
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'resolution_note' => ['required', 'string', 'min:1', 'max:2000'],
            'return_to_agent' => ['sometimes', 'boolean'],
        ]);
        $tenantId = $this->tenantId();
        $actorId = $this->actorId($request);

        return $this->respond(function () use ($data, $tenantId, $actorId, $handoff): array {
            $this->handoffs->resolve(new ResolveAiHandoff(
                $tenantId,
                $handoff,
                $actorId,
                (int) $data['expected_version'],
                (string) $data['resolution_note'],
                (bool) ($data['return_to_agent'] ?? false),
            ));

            return ['data' => $this->handoffs->show($tenantId, $handoff, $actorId)];
        });
    }

    private function tenantId(): string
    {
        if (! $this->tenantContext->isInitialized() || $this->tenantContext->isRoot()) {
            abort(403);
        }

        return $this->tenantContext->id();
    }

    private function actorId(Request $request): string
    {
        $user = $request->user();
        abort_unless(is_object($user), 401);

        return (string) $user->getKey();
    }

    /** @param callable():array<string,mixed> $callback */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (AiHandoffNotFoundException) {
            abort(404);
        } catch (AiHandoffForbiddenException) {
            abort(403);
        } catch (AiHandoffConflictException) {
            abort(409);
        } catch (InvalidArgumentException) {
            abort(422);
        }
    }
}
