<?php

declare(strict_types=1);

namespace App\Application\Ai\Grounding;

use App\Application\Ai\Security\AiContentCipher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final readonly class GroundingEvidenceProjector
{
    public function __construct(private AiContentCipher $cipher) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<EvidenceSnapshot>
     */
    public function project(
        string $ownerTenantId,
        string $requestMessageId,
        string $visibility,
        array $items,
    ): array {
        $currency = $this->conversationCurrency($ownerTenantId, $requestMessageId);
        $snapshots = [];
        foreach (array_values($items) as $index => $item) {
            $evidenceId = $item['evidence_id'] ?? $item['id'] ?? null;
            if (! is_string($evidenceId)) {
                throw new InvalidArgumentException('AI evidence projection is invalid.');
            }
            [$sourceType, $sourceId] = $this->identity($evidenceId);
            try {
                $current = $this->current(
                    $sourceType,
                    $sourceId,
                    $visibility,
                    $ownerTenantId,
                    $currency,
                    false,
                );
            } catch (InvalidArgumentException) {
                continue;
            }
            $rowId = self::rowId($ownerTenantId, $requestMessageId, $evidenceId);
            $excerpt = json_encode([
                'type' => $sourceType,
                'id' => $evidenceId,
                'name' => $current['name'],
                'description_untrusted' => $current['description'],
                'currency' => $current['currency'],
                'price_minor' => $current['price_minor'],
                'availability' => $current['availability'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $score = isset($item['similarity']) && is_numeric($item['similarity'])
                ? (float) $item['similarity']
                : max(-1.0, 1.0 - ($index * 0.01));

            $snapshots[] = new EvidenceSnapshot(
                evidenceId: $evidenceId,
                sourceType: $sourceType,
                sourceId: $sourceId,
                sourceVersion: $current['source_version'],
                tenantId: $ownerTenantId,
                sourceTenantId: $current['source_tenant_id'],
                visibility: $visibility,
                citationLabel: mb_substr($current['name'], 0, 160),
                freshAt: $current['fresh_at'],
                retrievalRank: $index + 1,
                retrievalScore: $score,
                currency: $current['currency'],
                priceMinor: $current['price_minor'],
                availability: $current['availability'],
                excerpt: $this->cipher->encrypt(
                    $ownerTenantId,
                    $rowId,
                    'evidence_excerpt',
                    mb_substr($excerpt, 0, 1000),
                ),
            );
        }

        return $snapshots;
    }

    /**
     * Locks source owners first and resources second in deterministic order.
     *
     * @param  list<array<string, mixed>>  $snapshots
     */
    public function lockAndVerify(array $snapshots): void
    {
        $sourceTenants = [];
        foreach ($snapshots as $snapshot) {
            if (($snapshot['snapshot'] ?? false) === true) {
                $sourceTenants[(string) $snapshot['source_tenant_id']] = true;
            }
        }
        ksort($sourceTenants, SORT_STRING);
        foreach (array_keys($sourceTenants) as $sourceTenantId) {
            if (DB::table('tenants')->where('id', $sourceTenantId)->lockForUpdate()->first(['id']) === null) {
                throw new InvalidArgumentException('AI conversation evidence is invalid.');
            }
        }

        $ordered = collect($snapshots)
            ->filter(static fn (array $snapshot): bool => ($snapshot['snapshot'] ?? false) === true)
            ->sortBy(static fn (array $snapshot): string => $snapshot['source_type'].'|'.$snapshot['source_id'])
            ->values();
        foreach ($ordered as $snapshot) {
            $current = $this->current(
                (string) $snapshot['source_type'],
                (string) $snapshot['source_id'],
                (string) $snapshot['visibility'],
                (string) $snapshot['tenant_id'],
                (string) $snapshot['currency'],
                true,
            );
            if ($current['source_tenant_id'] !== $snapshot['source_tenant_id']
                || $current['source_version'] !== $snapshot['source_version']
                || $current['currency'] !== $snapshot['currency']
                || $current['price_minor'] !== $snapshot['price_minor']
                || $current['availability'] !== $snapshot['availability']) {
                throw new InvalidArgumentException('AI conversation evidence is invalid.');
            }
        }
    }

    public static function rowId(string $ownerTenantId, string $requestMessageId, string $evidenceId): string
    {
        $responseId = self::stableId("conversation-response|{$ownerTenantId}|{$requestMessageId}");

        return self::stableId("evidence|{$responseId}|{$evidenceId}");
    }

    private static function stableId(string $value): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, "https://dovra.app/ai/{$value}")->toString();
    }

    /** @return array{string,string} */
    private function identity(string $evidenceId): array
    {
        $parts = explode(':', $evidenceId, 2);
        if (count($parts) !== 2 || ! in_array($parts[0], ['product', 'service'], true)
            || $parts[1] === '' || mb_strlen($parts[1]) > 64) {
            throw new InvalidArgumentException('AI evidence projection is invalid.');
        }

        return [$parts[0], $parts[1]];
    }

    private function conversationCurrency(string $tenantId, string $requestMessageId): string
    {
        $currency = DB::table('ai_messages as message')
            ->join('ai_conversations as conversation', function ($join): void {
                $join->on('conversation.id', '=', 'message.conversation_id')
                    ->on('conversation.tenant_id', '=', 'message.tenant_id');
            })
            ->where('message.id', $requestMessageId)
            ->where('message.tenant_id', $tenantId)
            ->value('conversation.currency');
        if (! is_string($currency) || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('AI evidence projection is invalid.');
        }

        return $currency;
    }

    /**
     * @return array{source_tenant_id:string,source_version:string,name:string,description:string,fresh_at:CarbonImmutable,currency:string,price_minor:int,availability:string}
     */
    private function current(
        string $sourceType,
        string $sourceId,
        string $visibility,
        string $ownerTenantId,
        string $currency,
        bool $lock,
    ): array {
        if (! in_array($visibility, ['public', 'private'], true) || ! in_array($sourceType, ['product', 'service'], true)) {
            throw new InvalidArgumentException('AI evidence projection is invalid.');
        }
        $table = $sourceType === 'product' ? 'products' : 'services';
        $query = DB::table($table)->where('id', $sourceId);
        if ($visibility === 'private') {
            $query->where('tenant_id', $ownerTenantId);
        }
        $this->eligibleSource($query, $table, $visibility);
        if ($lock) {
            $query->lockForUpdate();
        }
        $source = $query->first();
        if ($source === null) {
            throw new InvalidArgumentException('AI evidence projection is invalid.');
        }
        $sourceTenantId = (string) $source->tenant_id;
        $tenant = DB::table('tenants')->where('id', $sourceTenantId)->first();
        if ($tenant === null || ($visibility === 'private' && $sourceTenantId !== $ownerTenantId)
            || ($visibility === 'public' && ! $this->publicTenantEligible($tenant))) {
            throw new InvalidArgumentException('AI evidence projection is invalid.');
        }
        $sourceCurrency = $this->sourceCurrency($tenant, $sourceTenantId, $lock);
        if ($sourceCurrency !== $currency) {
            throw new InvalidArgumentException('AI evidence projection is invalid.');
        }
        $declaredCurrency = strtoupper((string) ($source->currency ?? ''));
        if ($declaredCurrency !== '' && $declaredCurrency !== $sourceCurrency) {
            throw new InvalidArgumentException('AI evidence projection is invalid.');
        }
        $availability = $sourceType === 'service'
            ? 'available'
            : $this->productAvailability($source);
        if ($visibility === 'public' && $availability === 'unavailable') {
            throw new InvalidArgumentException('AI evidence projection is invalid.');
        }
        $freshAt = CarbonImmutable::parse((string) $source->updated_at);
        $freshnessDays = max(1, (int) config('ai.knowledge.freshness_days', 30));
        if ($freshAt->lt(CarbonImmutable::now()->subDays($freshnessDays))) {
            throw new InvalidArgumentException('AI evidence projection is invalid.');
        }
        $canonical = [
            'availability' => $availability,
            'currency' => $sourceCurrency,
            'description' => mb_substr(strip_tags((string) ($source->description ?? '')), 0, 500),
            'name' => mb_substr(strip_tags((string) ($source->name ?? '')), 0, 160),
            'price_minor' => $this->priceMinor($source),
            'source_id' => (string) $source->id,
            'source_tenant_id' => $sourceTenantId,
            'source_type' => $sourceType,
            'updated_at' => $freshAt->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'visibility' => $visibility,
        ];

        return [
            ...$canonical,
            'source_version' => hash('sha256', json_encode(
                $canonical,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            )),
            'fresh_at' => $freshAt,
        ];
    }

    private function eligibleSource(Builder $query, string $table, string $visibility): void
    {
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', true);
        }
        if ($visibility === 'public' && $table === 'products' && Schema::hasColumn($table, 'is_visible')) {
            $query->where('is_visible', true);
        }
        if ($visibility === 'public' && Schema::hasColumn($table, 'status')) {
            $query->whereIn('status', ['active', 'approved', 'published']);
        }
    }

    private function publicTenantEligible(object $tenant): bool
    {
        return (! property_exists($tenant, 'is_active') || (bool) $tenant->is_active)
            && (! property_exists($tenant, 'subscription_status')
                || in_array((string) $tenant->subscription_status, ['active', 'trial'], true));
    }

    private function sourceCurrency(object $tenant, string $sourceTenantId, bool $lock): string
    {
        $query = DB::table('tenant_markets')
            ->where('tenant_id', $sourceTenantId)
            ->where('is_primary', true);
        if ($lock) {
            $query->lockForUpdate();
        }
        $currency = $query->value('currency_code');
        if (is_string($currency) && preg_match('/^[A-Z]{3}$/', $currency) === 1) {
            return $currency;
        }
        if (property_exists($tenant, 'city_id') && $tenant->city_id !== null) {
            return 'HNL';
        }

        throw new InvalidArgumentException('AI evidence projection is invalid.');
    }

    private function productAvailability(object $product): string
    {
        if (! (bool) ($product->manage_stock ?? false)) {
            return 'available';
        }
        if ((int) ($product->stock_quantity ?? 0) > 0) {
            return 'available';
        }

        return (bool) ($product->allow_backorders ?? false) ? 'backorder' : 'unavailable';
    }

    private function priceMinor(object $source): int
    {
        return $source->price_minor === null
            ? (int) round(((float) $source->price) * 100)
            : (int) $source->price_minor;
    }
}
