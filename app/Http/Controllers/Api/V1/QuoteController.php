<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Quotes\CanonicalQuoteWriter;
use App\Application\Quotes\Commands\CreateQuoteCommand;
use App\Application\Quotes\Commands\ReviseQuoteCommand;
use App\Application\Quotes\Commands\TransitionQuoteCommand;
use App\Application\Quotes\QuoteWriteResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Quotes\StoreQuoteRequest;
use App\Http\Requests\Api\V1\Quotes\TransitionQuoteRequest;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class QuoteController extends Controller
{
    public function __construct(private readonly CanonicalQuoteWriter $writer) {}

    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $data = $request->validated();
        $result = $this->writer->create(new CreateQuoteCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            idempotencyKey: (string) $data['idempotency_key'],
            locationId: (string) $data['location_id'],
            items: $data['items'],
            customerProfileId: isset($data['customer_profile_id']) ? (int) $data['customer_profile_id'] : null,
            leadId: isset($data['lead_id']) ? (int) $data['lead_id'] : null,
            crmDealId: isset($data['crm_deal_id']) ? (int) $data['crm_deal_id'] : null,
            source: (string) ($data['source'] ?? 'merchant'),
            expiresAt: isset($data['expires_at'])
                ? CarbonImmutable::parse((string) $data['expires_at'])->utc()
                : null,
        ));

        return response()->json($this->response($result), $result->replayed ? 200 : 201);
    }

    public function revise(StoreQuoteRequest $request, string $quote): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $data = $request->validated();
        $result = $this->writer->revise(new ReviseQuoteCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            quoteId: $quote,
            idempotencyKey: (string) $data['idempotency_key'],
            items: $data['items'],
            expiresAt: isset($data['expires_at'])
                ? CarbonImmutable::parse((string) $data['expires_at'])->utc()
                : null,
        ));

        return response()->json($this->response($result), $result->replayed ? 200 : 201);
    }

    public function transition(TransitionQuoteRequest $request, string $quote): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $data = $request->validated();
        $command = new TransitionQuoteCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            quoteId: $quote,
            idempotencyKey: (string) $data['idempotency_key'],
            targetStatus: (string) $data['target_status'],
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            metadata: $data['metadata'] ?? [],
        );
        $result = match ($command->targetStatus) {
            Quote::STATUS_ISSUED => $this->writer->issue($command),
            Quote::STATUS_ACCEPTED => $this->writer->accept($command),
            Quote::STATUS_REJECTED => $this->writer->reject($command),
            Quote::STATUS_CANCELLED => $this->writer->cancel($command),
            Quote::STATUS_EXPIRED => $this->writer->expire($command),
            Quote::ACTION_CONVERT_TO_ORDER => $this->writer->convertToOrder($command),
            Quote::ACTION_CONVERT_TO_RESERVATION => $this->writer->convertToReservation($command),
            default => throw new \LogicException('Unsupported validated quote transition.'),
        };

        return response()->json($this->response($result));
    }

    /** @return array<string, mixed> */
    private function response(QuoteWriteResult $result): array
    {
        $quote = $result->quote->loadMissing('items');

        return [
            'data' => [
                'id' => (string) $quote->getKey(),
                'public_id' => (string) $quote->public_id,
                'tenant_id' => (string) $quote->tenant_id,
                'status' => (string) $quote->status,
                'currency' => (string) $quote->currency,
                'version' => (int) $quote->version,
                'subtotal_minor' => (int) $quote->subtotal_minor,
                'discount_minor' => (int) $quote->discount_minor,
                'tax_minor' => (int) $quote->tax_minor,
                'total_minor' => (int) $quote->total_minor,
                'expires_at' => $quote->expires_at?->toIso8601String(),
                'converted_order_id' => $quote->converted_order_id,
                'converted_appointment_id' => $quote->converted_appointment_id,
                'items' => $quote->items->map(fn ($item): array => [
                    'id' => (int) $item->id,
                    'product_id' => $item->product_id,
                    'service_id' => $item->service_id,
                    'description' => (string) $item->description,
                    'quantity' => (int) $item->quantity,
                    'unit_price_minor' => (int) $item->unit_price_minor,
                    'line_total_minor' => (int) $item->line_total_minor,
                ])->values()->all(),
            ],
            'replayed' => $result->replayed,
            'outcome' => $result->outcome,
            'revised_quote_id' => $result->revisedQuote?->getKey(),
        ];
    }
}
