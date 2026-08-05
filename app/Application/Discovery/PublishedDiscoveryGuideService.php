<?php

declare(strict_types=1);

namespace App\Application\Discovery;

use App\Application\Ai\Conversation\PublicConversationInput;
use App\Application\Markets\MarketContext;
use App\Domain\Discovery\DiscoveryQuery;
use Illuminate\Support\Facades\DB;

/**
 * Public, deterministic adapter over the already-published discovery projection.
 *
 * This class deliberately has no access to canonical catalog models, semantic
 * retrieval, tenant queries, or projection writers. The projection is the sole
 * source of public guide results.
 */
final readonly class PublishedDiscoveryGuideService
{
    public function __construct(private DiscoveryQueryService $discovery) {}

    /**
     * @return array{
     *     answer:string,
     *     suggestions:list<array{label:string,href:string}>,
     *     items:list<array<string,mixed>>,
     *     source:string,
     *     fallback:bool,
     *     meta:array<string,mixed>
     * }
     */
    public function answer(PublicConversationInput $input, MarketContext $market): array
    {
        $baseMeta = [
            'mode' => 'deterministic_public_projection',
            'llm_used' => false,
            'privacy_scope' => 'public_directory_only',
            'source' => 'public_directory',
            'retrieval_source' => 'published_commercial_projection',
            'fallback' => true,
            'market_country_code' => strtoupper($market->countryCode),
            'currency_code' => strtoupper($market->currencyCode),
        ];

        $currencyCode = strtoupper(trim($market->currencyCode));
        $minorUnit = $this->minorUnit($currencyCode);
        if ($minorUnit === null) {
            $baseMeta['configuration_unavailable'] = 'currency_minor_unit';

            return $this->emptyResponse($input, $market, $baseMeta);
        }

        $budgetMinor = $input->budget === null
            ? null
            : $this->budgetMinor($input->budget, $minorUnit);
        if ($input->budget !== null && $budgetMinor === null) {
            $baseMeta['configuration_unavailable'] = 'budget_precision';

            return $this->emptyResponse($input, $market, $baseMeta);
        }

        $resultSet = $this->discovery->search(new DiscoveryQuery(
            searchText: $input->message,
            marketCountryCode: strtoupper($market->countryCode),
            budgetMinor: $budgetMinor,
            currencyCode: $currencyCode,
            limit: 6,
            localitySlug: $input->city,
            businessCategorySlug: $input->category,
        ));

        $items = [];
        foreach ($resultSet->results as $result) {
            $evidenceId = $this->publicEvidenceId($result);
            if ($evidenceId === null) {
                continue;
            }

            $priceMinor = (int) ($result['price_minor'] ?? 0);
            $price = $priceMinor / (10 ** $minorUnit);
            $priceLabel = $this->priceLabel($currencyCode, $priceMinor, $minorUnit);
            $businessUrl = (string) ($result['business_url'] ?? '/directorio');

            $items[] = [
                'type' => (string) ($result['source_type'] ?? 'offer'),
                'source_type' => (string) ($result['source_type'] ?? 'offer'),
                'evidence_id' => $evidenceId,
                'name' => (string) ($result['offer_name'] ?? 'Opcion publicada'),
                'description' => (string) ($result['offer_description'] ?? ''),
                'offer_description' => (string) ($result['offer_description'] ?? ''),
                'price' => $price,
                'price_label' => $priceLabel,
                'business_name' => (string) ($result['business_name'] ?? 'Negocio local'),
                'business_url' => $businessUrl,
                'url' => $businessUrl,
                'availability' => (string) ($result['availability_status'] ?? 'unknown'),
            ];
        }

        $baseMeta['total_results'] = count($items);
        $baseMeta['normalized_terms'] = $resultSet->meta['normalized_terms'] ?? [];

        if ($items === []) {
            return $this->emptyResponse($input, $market, $baseMeta);
        }

        $names = collect($items)->take(3)->map(static fn (array $item): string => sprintf(
            '%s en %s por %s',
            $item['name'],
            $item['business_name'],
            $item['price_label'],
        ))->implode('; ');
        $answer = $input->budget === null
            ? 'Encontre estas opciones publicadas y verificables: '.$names.'.'
            : 'Encontre estas opciones publicadas dentro de tu presupuesto de '
                .$this->priceLabel($currencyCode, (int) $budgetMinor, $minorUnit)
                .': '.$names.'.';

        return [
            'answer' => $answer,
            'suggestions' => collect($items)->map(static fn (array $item): array => [
                'label' => 'Ver '.$item['business_name'],
                'href' => $item['business_url'],
            ])->unique('href')->values()->all(),
            'items' => $items,
            'source' => 'public_directory',
            'fallback' => true,
            'meta' => $baseMeta,
        ];
    }

    /** @param array<string,mixed> $meta @return array<string,mixed> */
    private function emptyResponse(PublicConversationInput $input, MarketContext $market, array $meta): array
    {
        $parts = [];
        if ($input->budget !== null) {
            $parts[] = 'presupuesto '.$this->displayBudget($input->budget, $market->currencyCode);
        }
        if ($input->city !== null) {
            $parts[] = 'ciudad '.$input->city;
        }
        if ($input->category !== null) {
            $parts[] = 'categoria '.$input->category;
        }
        $context = $parts === [] ? '' : ' con '.implode(', ', $parts);

        return [
            'answer' => 'No encontre una coincidencia publica suficiente'.$context.'. Prueba subir el presupuesto, quitar filtros o buscar por categoria.',
            'suggestions' => [
                ['label' => 'Ver todos los negocios', 'href' => '/directorio'],
                ['label' => 'Quitar filtros', 'href' => '/directorio'],
            ],
            'items' => [],
            'source' => 'public_directory',
            'fallback' => true,
            'meta' => $meta,
        ];
    }

    private function minorUnit(string $currencyCode): ?int
    {
        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            return null;
        }

        $minorUnit = DB::table('currencies')->where('code', $currencyCode)->value('minor_unit');
        if (! is_numeric($minorUnit)) {
            return null;
        }

        $minorUnit = (int) $minorUnit;

        return $minorUnit >= 0 && $minorUnit <= 6 ? $minorUnit : null;
    }

    private function budgetMinor(float $budget, int $minorUnit): ?int
    {
        $decimal = json_encode($budget, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        if (! is_string($decimal)
            || preg_match('/^(?<whole>\d+)(?:\.(?<fraction>\d+))?$/', $decimal, $matches) !== 1) {
            return null;
        }

        $scale = 10 ** $minorUnit;
        $fraction = str_pad(substr((string) ($matches['fraction'] ?? ''), 0, $minorUnit), $minorUnit, '0');

        return ((int) $matches['whole'] * $scale) + ($fraction === '' ? 0 : (int) $fraction);
    }

    /** @param array<string,mixed> $result */
    private function publicEvidenceId(array $result): ?string
    {
        $type = (string) ($result['source_type'] ?? '');
        $keys = is_array($result['evidence_keys'] ?? null) ? $result['evidence_keys'] : [];
        foreach ($keys as $key) {
            if (is_string($key) && str_starts_with($key, $type.':')) {
                return $key;
            }
        }

        return null;
    }

    private function priceLabel(string $currencyCode, int $priceMinor, int $minorUnit): string
    {
        $major = $priceMinor / (10 ** $minorUnit);
        $label = $currencyCode === 'HNL' ? 'L.' : $currencyCode;

        return $label.' '.number_format($major, $minorUnit);
    }

    private function displayBudget(float $budget, string $currencyCode): string
    {
        $currencyCode = strtoupper(trim($currencyCode));
        $label = $currencyCode === 'HNL' ? 'L.' : $currencyCode;

        return $label.' '.number_format($budget, 2);
    }
}
