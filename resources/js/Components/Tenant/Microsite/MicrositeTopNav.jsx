import { Link } from '@inertiajs/react';
import { ArrowUpRight, Store } from 'lucide-react';

import { Button } from '@/Components/ui/button';

export default function MicrositeTopNav({
    isOwner = false,
    ownerPanelHref = null,
    businessLabel = 'Negocios',
}) {
    return (
        <nav className="fixed inset-x-0 top-0 z-50 border-b border-border bg-background/84 backdrop-blur-[20px]">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Button
                    asChild
                    className="h-auto rounded-full px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.16em]"
                >
                    <Link href={route('public.explore')}>
                        <Store data-icon="inline-start" aria-hidden="true" />
                        {businessLabel}
                    </Link>
                </Button>

                <Link href={route('home')} className="rounded-lg text-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                    <span
                        className="block font-headline text-lg font-extrabold tracking-tight text-foreground sm:text-xl"
                    >
                        Vito Business OS
                    </span>
                    <span
                        className="hidden font-label text-[10px] font-semibold uppercase tracking-[0.28em] text-muted-foreground sm:block"
                    >
                        Vito Business OS
                    </span>
                </Link>

                <div className="flex items-center gap-3">
                    {isOwner && ownerPanelHref ? (
                        <Button
                            asChild
                            variant="outline"
                            className="hidden h-auto rounded-full px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.16em] sm:inline-flex"
                        >
                            <a href={ownerPanelHref}>
                                Abrir panel
                                <ArrowUpRight data-icon="inline-end" aria-hidden="true" />
                            </a>
                        </Button>
                    ) : null}
                </div>
            </div>
        </nav>
    );
}
