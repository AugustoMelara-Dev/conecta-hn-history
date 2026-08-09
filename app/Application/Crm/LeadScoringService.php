<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Domain\Crm\Enums\LifecycleStage;
use App\Domain\Crm\ValueObjects\DealScore;
use App\Models\Contact;
use Illuminate\Support\Facades\Cache;

/**
 * LeadScoringService - Calcula score de lead/contact (0-100).
 *
 * Factores:
 * - Lifecycle stage (lead/qualified/proposal/negotiation/won/lost)
 * - Engagement (actividades últimos 30 días)
 * - Datos completos (email, phone, job_title)
 * - Fuente (referral > organic > paid)
 * - Recencia (last_contact_at reciente suma)
 *
 * Costo AI: $0.01/score (async queue, model gpt-4o-mini).
 */
class LeadScoringService
{
    public function score(Contact $contact): DealScore
    {
        $cacheKey = "lead_score:contact:{$contact->id}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($contact) {
            $factors = $this->calculateFactors($contact);
            $value = $this->calculateScore($factors);

            return DealScore::fromFactors($value, $factors);
        });
    }

    /**
     * @return array<string, int>
     */
    private function calculateFactors(Contact $contact): array
    {
        return [
            'lifecycle_stage' => $this->scoreFromLifecycleStage($contact->lifecycle_stage),
            'engagement' => $this->scoreFromEngagement($contact),
            'data_completeness' => $this->scoreFromDataCompleteness($contact),
            'source' => $this->scoreFromSource($contact->source),
            'recency' => $this->scoreFromRecency($contact),
        ];
    }

    private function calculateScore(array $factors): int
    {
        // Weighted sum: lifecycle 40%, engagement 25%, completeness 15%, source 10%, recency 10%
        $weights = [
            'lifecycle_stage' => 0.40,
            'engagement' => 0.25,
            'data_completeness' => 0.15,
            'source' => 0.10,
            'recency' => 0.10,
        ];

        $score = 0;
        foreach ($factors as $key => $value) {
            $score += $value * ($weights[$key] ?? 0);
        }

        return (int) round($score);
    }

    private function scoreFromLifecycleStage(string $stage): int
    {
        $enum = LifecycleStage::tryFrom($stage);
        if ($enum === null) {
            return 10;
        }

        return match ($enum) {
            LifecycleStage::Won => 100,
            LifecycleStage::Negotiation => 75,
            LifecycleStage::Proposal => 50,
            LifecycleStage::Qualified => 30,
            LifecycleStage::Lead => 15,
            LifecycleStage::Lost => 0,
        };
    }

    private function scoreFromEngagement(Contact $contact): int
    {
        $activitiesCount = $contact->activities()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return match (true) {
            $activitiesCount >= 10 => 100,
            $activitiesCount >= 5 => 75,
            $activitiesCount >= 3 => 50,
            $activitiesCount >= 1 => 25,
            default => 0,
        };
    }

    private function scoreFromDataCompleteness(Contact $contact): int
    {
        $score = 0;
        if ($contact->email) {
            $score += 30;
        }
        if ($contact->phone) {
            $score += 25;
        }
        if ($contact->job_title) {
            $score += 20;
        }
        if ($contact->company_id) {
            $score += 25;
        }

        return $score;
    }

    private function scoreFromSource(?string $source): int
    {
        if (! $source) {
            return 30;
        }

        return match (strtolower($source)) {
            'referral' => 100,
            'organic', 'web' => 80,
            'social' => 60,
            'email', 'newsletter' => 50,
            'paid', 'ads' => 40,
            'cold', 'outbound' => 30,
            default => 50,
        };
    }

    private function scoreFromRecency(Contact $contact): int
    {
        if (! $contact->last_contact_at) {
            return 20;
        }
        $daysSince = $contact->last_contact_at->diffInDays(now());

        return match (true) {
            $daysSince <= 1 => 100,
            $daysSince <= 7 => 80,
            $daysSince <= 30 => 60,
            $daysSince <= 90 => 40,
            $daysSince <= 180 => 20,
            default => 10,
        };
    }
}
