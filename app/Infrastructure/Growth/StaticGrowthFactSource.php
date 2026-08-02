<?php

declare(strict_types=1);

namespace App\Infrastructure\Growth;

use App\Domain\Growth\Contracts\GrowthFactSource;
use App\Domain\Growth\Data\MarketingClaim;
use App\Domain\Growth\Enums\EvidenceLabel;

final class StaticGrowthFactSource implements GrowthFactSource
{
    public function marketingClaims(): array
    {
        return [
            new MarketingClaim(
                title: 'Dovra AI',
                statement: 'The repository uses Dovra AI as the canonical product identity and keeps legal-entity fields unset until external confirmation exists.',
                label: EvidenceLabel::RepositoryProven,
                evidencePaths: [
                    'tests/Feature/DevOps/DovraProductTruthContractTest.php',
                    'docs/dovra/evidence/program-00-2026-07-28.md',
                ],
            ),
            new MarketingClaim(
                title: 'Global market onboarding',
                statement: 'The repository contains non-Honduras registration and market-context handling; Honduras remains the first Market Pack, not a silent default for every tenant.',
                label: EvidenceLabel::RepositoryProven,
                evidencePaths: [
                    'tests/Feature/Auth/GlobalMarketRegistrationTest.php',
                    'docs/dovra/evidence/program-03-2026-07-28.md',
                ],
            ),
            new MarketingClaim(
                title: 'Channel contracts',
                statement: 'Web chat, widget, email, WhatsApp, and voice contracts are represented in repository tests and fail closed when provider capability is unavailable.',
                label: EvidenceLabel::RepositoryProven,
                evidencePaths: [
                    'tests/Feature/Channels/ChannelContractTest.php',
                    'docs/dovra/evidence/program-07-2026-08-01.md',
                ],
            ),
            new MarketingClaim(
                title: 'Buyer-action preparation',
                statement: 'The repository can prepare buyer actions from constrained discovery without claiming payment capture, execution, or inventory guarantees.',
                label: EvidenceLabel::Implemented,
                evidencePaths: [
                    'app/Application/Discovery/BuyerActionPreparationService.php',
                    'tests/Feature/AI/PublicGroundedDiscoveryTest.php',
                ],
            ),
            new MarketingClaim(
                title: 'Acquisition scale-up',
                statement: 'Paid acquisition, partner expansion, and live deployment performance stay in a hypothesis bucket until production telemetry and external channel approvals exist.',
                label: EvidenceLabel::Hypothesis,
                evidencePaths: [
                    'docs/superpowers/specs/2026-07-27-dovra-ai-global-conversational-business-os-design.md',
                    'docs/dovra/evidence/program-12-2026-08-01.md',
                ],
            ),
        ];
    }
}
