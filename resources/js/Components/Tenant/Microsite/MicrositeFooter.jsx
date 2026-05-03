import { Link } from '@inertiajs/react';
import { ArrowUpRight, MessageCircle } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { PUBLIC_BRAND } from '@/lib/publicBrand';
import { cn } from '@/lib/utils';

export default function MicrositeFooter({
    tenant,
    profile,
    primaryWhatsAppUrl,
    isSeedPlan = false,
}) {
    return (
        <footer className="border-t border-border bg-background py-10">
            <div className="mx-auto flex max-w-7xl flex-col gap-5 px-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <p
                        className="font-label text-[11px] font-bold uppercase tracking-[0.2em] text-muted-foreground"
                    >
                        Impulsado por {PUBLIC_BRAND.name}
                    </p>
                    <h3
                        className="mt-3 font-headline text-2xl font-bold text-foreground"
                    >
                        {tenant.name}
                    </h3>
                    <p className="mt-2 max-w-xl text-sm leading-7 text-muted-foreground">
                        {profile.footer_copy}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    {primaryWhatsAppUrl ? (
                        <Button
                            asChild
                            className="h-auto rounded-full px-5 py-3 font-label text-xs font-semibold uppercase tracking-[0.16em]"
                        >
                            <a href={primaryWhatsAppUrl} target="_blank" rel="noopener noreferrer">
                                <MessageCircle data-icon="inline-start" aria-hidden="true" />
                                Contactar
                            </a>
                        </Button>
                    ) : null}

                    <Button
                        asChild
                        variant="outline"
                        className={cn(
                            'h-auto rounded-full px-5 py-3 font-label text-xs font-semibold uppercase tracking-[0.16em]',
                            isSeedPlan ? 'text-foreground' : 'text-muted-foreground'
                        )}
                    >
                        <Link href={route('tenant.register')}>
                            Crear mi negocio
                            <ArrowUpRight data-icon="inline-end" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
            </div>
        </footer>
    );
}
