/**
 * TenantHeader - Shared Header Component for Both Tiers
 * 
 * This component is shared between FreeBusinessPage and ProBusinessPage
 * to reduce code duplication and ensure consistent UI changes.
 */

import { Link } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { MapPin, Star, BadgeCheck, Store } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function TenantHeader({
    tenant,
    isPro = false,
    compact = false, // true for Semilla's simpler header
    className,
}) {
    if (!tenant) return null;

    const cityName = typeof tenant.city === 'string' ? tenant.city : tenant.city?.name || 'Honduras';

    return (
        <header className={cn("relative", className)}>
            {/* Cover Image */}
            <div className={cn(
                "relative overflow-hidden bg-slate-800",
                compact ? "h-40" : "h-[40vh] md:h-[50vh]"
            )}>
                {tenant.cover || tenant.banner_path ? (
                    <img
                        src={tenant.cover || `/storage/${tenant.banner_path}`}
                        alt={tenant.name}
                        className="w-full h-full object-cover opacity-60"
                    />
                ) : (
                    <div
                        className="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-800 to-slate-900"
                        style={{ backgroundColor: tenant.primary_color || '#6366f1' }}
                    >
                        {compact ? (
                            <Store className="w-20 h-20 text-slate-700" />
                        ) : (
                            <span className="text-white/10 font-sans text-5xl md:text-9xl font-bold tracking-widest uppercase select-none">
                                {tenant.name}
                            </span>
                        )}
                    </div>
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent" />
            </div>

            {/* Profile Overlay */}
            <div className={cn(
                "relative z-20 px-4 md:px-6 lg:px-8",
                compact ? "max-w-3xl mx-auto -mt-16 pb-6 text-center" : "absolute bottom-0 left-0 w-full p-6 md:p-12"
            )}>
                <div className={cn(
                    "flex gap-6",
                    compact ? "flex-col items-center" : "flex-col md:flex-row items-end md:items-center max-w-7xl mx-auto"
                )}>
                    {/* Logo */}
                    <div className={cn(
                        "bg-slate-800 rounded-2xl border-4 border-slate-950 shadow-2xl overflow-hidden flex-shrink-0",
                        compact ? "w-24 h-24" : "w-24 h-24 md:w-32 md:h-32"
                    )}>
                        {tenant.logo ? (
                            <img
                                src={tenant.logo}
                                alt="Logo"
                                className="w-full h-full object-cover"
                            />
                        ) : (
                            <div
                                className="w-full h-full flex items-center justify-center"
                                style={{ backgroundColor: tenant.primary_color || '#6366f1' }}
                            >
                                <span className={cn(
                                    "font-bold text-white",
                                    compact ? "text-2xl" : "text-3xl"
                                )}>
                                    {tenant.name?.substring(0, 2).toUpperCase()}
                                </span>
                            </div>
                        )}
                    </div>

                    {/* Info */}
                    <div className={cn(compact ? "" : "flex-1 mb-2")}>
                        <h1 className={cn(
                            "font-bold text-white flex items-center gap-3",
                            compact ? "text-2xl justify-center" : "text-3xl md:text-5xl"
                        )}>
                            {tenant.name}
                            {isPro && <BadgeCheck className="w-8 h-8 text-indigo-400 fill-indigo-900/50" />}
                        </h1>

                        <div className={cn(
                            "flex flex-wrap items-center gap-2 text-white/90 mt-2",
                            compact ? "justify-center" : "gap-3"
                        )}>
                            <Badge className="bg-white/10 hover:bg-white/20 text-white border-none backdrop-blur-md">
                                {tenant.category?.name || 'General'}
                            </Badge>

                            {isPro && (
                                <>
                                    <div className="flex items-center gap-1">
                                        <Star className="w-4 h-4 fill-amber-400 text-amber-400" />
                                        <span className="font-bold">{tenant.rating || '4.8'}</span>
                                    </div>
                                    <span className="hidden md:inline text-white/40">•</span>
                                </>
                            )}

                            <div className="flex items-center gap-1 text-sm text-slate-400">
                                <MapPin className="w-4 h-4" />
                                <span>{cityName}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
    );
}
