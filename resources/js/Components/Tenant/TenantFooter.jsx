/**
 * TenantFooter - Shared Footer Component for Both Tiers
 * 
 * For Semilla: Shows "Powered by Vito Business OS" branding
 * For Pro: Shows clean footer without branding
 */

import { Link } from '@inertiajs/react';
import { MapPin, Clock, Phone, Globe, MessageCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function TenantFooter({
    tenant,
    tenantPlan,
    isOpen = true,
    className,
}) {
    if (!tenant) return null;

    // For Pro tier, show a minimal footer
    if (tenantPlan !== 'semilla') {
        return null; // Pro doesn't need a branding footer
    }

    // Semilla tier gets the "Powered by" footer
    return (
        <div className={cn(
            "fixed bottom-0 left-0 right-0 z-30",
            "bg-slate-900/95 backdrop-blur-md border-t border-white/10",
            "py-3 px-4",
            className
        )}>
            <div className="max-w-3xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
                <div>
                    <p className="text-sm font-medium text-white">
                        ⚡ Powered by <span className="text-indigo-400">Vito Business OS</span>
                    </p>
                    <p className="text-xs text-slate-500">
                        Crea tu tienda digital gratis
                    </p>
                </div>
                <Link
                    href="/registrar-negocio"
                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    Crear mi Tienda
                </Link>
            </div>
        </div>
    );
}
