/**
 * TenantFooter - Shared Footer Component for Both Tiers
 *
 * For Semilla: Shows "Powered by Vito Business OS" branding.
 * For Pro: Shows clean footer without branding.
 */

import { Link } from '@inertiajs/react';
import { Zap } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

export default function TenantFooter({
    tenant,
    tenantPlan,
    className,
}) {
    if (!tenant) return null;

    if (tenantPlan !== 'semilla') {
        return null;
    }

    return (
        <div
            className={cn(
                'fixed inset-x-0 bottom-0 z-30 border-t bg-background/95 px-4 py-3 text-foreground shadow-sm backdrop-blur',
                className
            )}
        >
            <div className="mx-auto flex max-w-3xl flex-col items-center justify-between gap-3 text-center sm:flex-row sm:text-left">
                <div>
                    <p className="inline-flex items-center gap-2 text-sm font-medium">
                        <Zap className="size-4 text-primary" aria-hidden="true" />
                        Powered by <span className="font-semibold">Vito Business OS</span>
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Crea tu micrositio gratis en Vito App
                    </p>
                </div>
                <Button size="sm" asChild>
                    <Link href="/registrar-negocio">Crear mi micrositio</Link>
                </Button>
            </div>
        </div>
    );
}
