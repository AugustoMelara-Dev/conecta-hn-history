import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';

import { ActionLink, Eyebrow, SectionTitle } from './MicrositePrimitives';

export function resolveVerticalActionTarget(target, primaryWhatsAppUrl, bookingUrl) {
    if (target === 'whatsapp') {
        return primaryWhatsAppUrl;
    }

    if (target === 'booking') {
        return bookingUrl;
    }

    return target;
}

export function InlineAction({ href, label, external = false }) {
    if (!href) {
        return null;
    }

    return (
        <ActionLink
            href={href}
            label={label}
            variant="link"
            external={external}
        />
    );
}

export function FocusCard({ card, eyebrow }) {
    return (
        <Card className="rounded-[1.7rem] shadow-sm">
            <CardHeader>
                <Eyebrow>{eyebrow}</Eyebrow>
                <CardTitle className="font-headline text-2xl">
                    {card.title}
                </CardTitle>
                <CardDescription className="leading-7">
                    {card.copy}
                </CardDescription>
            </CardHeader>
            <CardFooter>
                <InlineAction
                    href={card.actionHref}
                    label={card.actionLabel}
                    external={card.external}
                />
            </CardFooter>
        </Card>
    );
}

export function JourneyStepCard({ step }) {
    return (
        <Card className="rounded-[1.5rem] border-white/10 bg-background/5 p-5 text-background" data-testid="vertical-journey-step">
            <CardHeader className="p-0">
                <Eyebrow dark>{step.eyebrow || 'Paso'}</Eyebrow>
                <CardTitle className="mt-3 font-headline text-xl">
                    {step.title}
                </CardTitle>
                <CardDescription className="mt-3 leading-7 text-background/75">
                    {step.copy}
                </CardDescription>
            </CardHeader>
        </Card>
    );
}

export function SignalCard({ signal }) {
    return (
        <Card className="rounded-[1.6rem] p-5 shadow-sm">
            <CardHeader className="p-0">
                <Eyebrow>{signal.label}</Eyebrow>
                <CardTitle className="mt-3 font-headline text-3xl font-extrabold tracking-tight text-foreground">
                    {signal.value}
                </CardTitle>
                <CardDescription className="mt-2 leading-7">{signal.helper}</CardDescription>
            </CardHeader>
        </Card>
    );
}

export function GrowthPlaybookCard({ card, primaryWhatsAppUrl, bookingUrl }) {
    const href = resolveVerticalActionTarget(card.cta_target, primaryWhatsAppUrl, bookingUrl);
    const isExternal = typeof href === 'string' && href.startsWith('http');

    return (
        <Card className="rounded-[1.5rem] p-5" data-testid="growth-playbook-card">
            <CardHeader className="p-0">
                <Eyebrow>{card.eyebrow || 'Bloque'}</Eyebrow>
                <CardTitle className="mt-3 font-headline text-xl">
                    {card.title}
                </CardTitle>
                <CardDescription className="mt-3 leading-7">{card.copy}</CardDescription>
            </CardHeader>
            <CardFooter className="p-0 pt-5">
                <InlineAction href={href} label={card.cta_label} external={isExternal} />
            </CardFooter>
        </Card>
    );
}

export function VerticalIntroCard({ profile, trustPoints }) {
    return (
        <Card className="rounded-[1.8rem] bg-muted p-6">
            <CardHeader className="p-0">
                <Eyebrow>Vertical especializada</Eyebrow>
                <SectionTitle className="mt-3 text-3xl">
                    {profile.vertical_label || profile.label}
                </SectionTitle>
                <CardDescription className="mt-3 leading-7">
                    Esta vitrina ya no habla solo del tipo de negocio: aterriza la propuesta para una industria concreta y hace más claro cómo comprar, reservar o consultar.
                </CardDescription>
            </CardHeader>

            {trustPoints.length > 0 ? (
                <CardContent className="flex flex-wrap gap-3 p-0 pt-6">
                    {trustPoints.map((point) => (
                        <Badge key={point} variant="secondary" className="rounded-full px-4 py-2 font-label text-xs uppercase tracking-[0.16em]">
                            {point}
                        </Badge>
                    ))}
                </CardContent>
            ) : null}
        </Card>
    );
}

export function VerticalBlockCard({ block, profile, primaryWhatsAppUrl, bookingUrl }) {
    const href = resolveVerticalActionTarget(block.cta_target, primaryWhatsAppUrl, bookingUrl);
    const isExternal = typeof href === 'string' && href.startsWith('http');

    return (
        <Card className="rounded-[1.8rem] p-6 shadow-sm" data-testid="vertical-block-card">
            <CardHeader className="p-0">
                <Eyebrow>{profile.vertical_label || profile.label}</Eyebrow>
                <CardTitle className="mt-3 font-headline text-2xl">
                    {block.title}
                </CardTitle>
                <CardDescription className="mt-3 leading-7">
                    {block.subtitle}
                </CardDescription>
            </CardHeader>

            {Array.isArray(block.bullets) && block.bullets.length > 0 ? (
                <CardContent className="p-0 pt-5">
                    <ul className="flex flex-col gap-3 text-sm leading-7 text-muted-foreground">
                        {block.bullets.map((bullet) => (
                            <li key={bullet} className="flex items-start gap-3">
                                <span className="mt-2 size-2.5 rounded-full bg-primary" aria-hidden="true" />
                                <span>{bullet}</span>
                            </li>
                        ))}
                    </ul>
                </CardContent>
            ) : null}

            <CardFooter className="p-0 pt-6">
                <InlineAction href={href} label={block.cta_label} external={isExternal} />
            </CardFooter>
        </Card>
    );
}
