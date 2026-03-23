import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export default function TenantFooter({
    tenant,
    tenantPlan,
    isOpen = true,
    className,
}) {
    if (!tenant || !isOpen) {
        return null;
    }

    if (tenantPlan !== 'semilla') {
        return null;
    }

    return (
        <div
            className={cn(
                'fixed bottom-0 left-0 right-0 z-30 border-t border-slate-200 bg-[#0f172a]/96 px-4 py-3 backdrop-blur-md',
                className,
            )}
        >
            <div className="mx-auto flex max-w-3xl flex-col items-center justify-between gap-3 text-center sm:flex-row sm:text-left">
                <div>
                    <p className="text-sm font-medium text-white">
                        Impulsado por <span className="text-emerald-300">Vito Business OS</span>
                    </p>
                    <p className="text-xs text-slate-400">
                        Conecta HN te da una presencia publica clara y lista para vender.
                    </p>
                </div>

                <Link
                    href={route('tenant.register')}
                    className="rounded-lg bg-emerald-400 px-4 py-2 text-sm font-medium text-slate-950 transition-colors hover:bg-emerald-300"
                >
                    Registrar mi negocio
                </Link>
            </div>
        </div>
    );
}
