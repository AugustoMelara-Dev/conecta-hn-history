/**
 * UpsellCard - Role-Aware Soft Gate Conversion Component
 * 
 * HARDENED VERSION:
 * - Uses route() helper for dynamic routing (no hardcoded paths)
 * - Uses centralized phoneUtils for phone normalization
 * - Graceful degradation when props are missing
 * 
 * This component adapts its content based on WHO is viewing:
 * 
 * OWNER VIEW (isOwner = true):
 *   - Alarming message about hidden products
 *   - CTA: "Upgrade my Plan" → Admin dashboard (dynamic route)
 *   - Amber/gold accent (premium feeling)
 * 
 * CUSTOMER VIEW (isOwner = false):
 *   - Helpful message about more options
 *   - CTA: "Ask the Business" → WhatsApp with sanitized, pre-filled message
 *   - Neutral/blue accent (informational)
 */

import { Link } from '@inertiajs/react';
import { Lock, Unlock, Crown, MessageCircle, Store, ArrowRight, Sparkles, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';

/**
 * Safely get admin route URL with fallback
 * Uses Ziggy's route() helper for dynamic routing
 */
const getAdminRouteUrl = () => {
    try {
        if (typeof route === 'function') {
            try {
                return route('filament.admin.pages.dashboard');
            } catch {
                try {
                    return route('dashboard');
                } catch {
                    return route('mi-cuenta.edit');
                }
            }
        }
    } catch (error) {
        console.warn('UpsellCard: Could not resolve admin route, using fallback');
    }
    return '/app';
};

export default function UpsellCard({
    hiddenCount = 0,
    isOwner = false,
    tenantWhatsapp = '',
    tenantName = '',
    variant = 'default', // 'default' | 'compact' | 'inline'
    className,
}) {
    // Safely handle tenant name (fallback to generic)
    const safeName = tenantName && typeof tenantName === 'string'
        ? tenantName.trim()
        : 'este negocio';

    // Build WhatsApp message
    const whatsappMessage = `¡Hola ${safeName}! 👋

Vi en su página que tienen más productos disponibles. 

¿Me podrían enviar el menú completo? ¡Gracias!`;

    // Use centralized phone utility for normalization and URL building
    const customerWhatsAppUrl = buildWhatsAppUrl(tenantWhatsapp, whatsappMessage);
    const hasValidWhatsApp = isValidWhatsAppPhone(tenantWhatsapp);

    // Get admin route dynamically
    const adminRouteUrl = getAdminRouteUrl();

    // Dynamic content based on role
    const content = isOwner ? {
        // === OWNER VIEW ===
        icon: AlertTriangle,
        iconBg: 'bg-amber-600/20 border-amber-500/30',
        iconColor: 'text-amber-400',
        badgeColor: 'bg-amber-600',
        bgGradient: 'from-amber-950/80 via-orange-950/60 to-slate-900',
        borderColor: 'border-amber-500/30 hover:border-amber-500/60',
        title: hiddenCount === 1
            ? '¡Tienes 1 producto oculto!'
            : `¡Tienes ${hiddenCount} productos ocultos!`,
        body: 'Tus clientes no pueden ver todo tu menú. Desbloquéalos ahora para aumentar tus ventas.',
        ctaText: 'Actualizar mi Plan',
        ctaIcon: Sparkles,
        ctaStyle: 'bg-amber-600 hover:bg-amber-500 text-white',
        footerLeft: 'Plan Semilla Activo',
        footerRight: 'Límite: 12 productos',
    } : {
        // === CUSTOMER VIEW ===
        icon: Store,
        iconBg: 'bg-indigo-600/20 border-indigo-500/30',
        iconColor: 'text-indigo-400',
        badgeColor: 'bg-indigo-600',
        bgGradient: 'from-indigo-950/80 via-purple-950/60 to-slate-900',
        borderColor: 'border-indigo-500/30 hover:border-indigo-500/60',
        title: 'Más opciones disponibles',
        body: hasValidWhatsApp
            ? 'Este negocio tiene más variedad. Contáctalos para conocer el menú completo.'
            : 'Este negocio tiene más variedad disponible en su local.',
        ctaText: hasValidWhatsApp ? 'Preguntar al Negocio' : 'Visitar el Local',
        ctaIcon: hasValidWhatsApp ? MessageCircle : Store,
        ctaStyle: hasValidWhatsApp
            ? 'bg-emerald-600 hover:bg-emerald-500 text-white'
            : 'bg-slate-700 hover:bg-slate-600 text-white',
        footerLeft: safeName,
        footerRight: `+${hiddenCount} productos`,
    };

    const IconComponent = content.icon;
    const CtaIconComponent = content.ctaIcon;

    // Inline variant for compact spaces
    if (variant === 'inline') {
        return (
            <div className={cn(
                "flex items-center justify-center gap-2 py-4 px-6",
                `bg-gradient-to-r ${content.bgGradient}`,
                `border border-dashed ${content.borderColor} rounded-xl`,
                "text-slate-300 text-sm",
                className
            )}>
                <Lock className="w-4 h-4" />
                <span>{hiddenCount}+ productos más</span>
                {isOwner ? (
                    <Link
                        href={adminRouteUrl}
                        className="text-amber-400 hover:text-amber-300 font-medium underline underline-offset-2"
                    >
                        Desbloquear
                    </Link>
                ) : hasValidWhatsApp ? (
                    <a
                        href={customerWhatsAppUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-emerald-400 hover:text-emerald-300 font-medium underline underline-offset-2"
                    >
                        Contactar
                    </a>
                ) : null}
            </div>
        );
    }

    // Main card
    return (
        <div
            className={cn(
                "group bg-gradient-to-br",
                content.bgGradient,
                "rounded-2xl border-2 border-dashed",
                content.borderColor,
                "transition-all duration-300 overflow-hidden relative",
                "hover:scale-[1.02]",
                className
            )}
        >
            {/* Decorative Background Pattern */}
            <div className="absolute inset-0 opacity-10 pointer-events-none">
                <div className={cn(
                    "absolute top-0 left-0 w-32 h-32 rounded-full blur-3xl",
                    isOwner ? "bg-amber-500" : "bg-indigo-500"
                )} />
                <div className={cn(
                    "absolute bottom-0 right-0 w-24 h-24 rounded-full blur-2xl",
                    isOwner ? "bg-orange-500" : "bg-purple-500"
                )} />
            </div>

            {/* Card Content */}
            <div className="aspect-[4/3] relative flex flex-col items-center justify-center p-6 text-center">
                {/* Icon */}
                <div className={cn(
                    "w-16 h-16 rounded-2xl border flex items-center justify-center mb-4",
                    "group-hover:scale-110 transition-transform",
                    content.iconBg
                )}>
                    <IconComponent className={cn("w-8 h-8", content.iconColor)} />
                </div>

                {/* Counter Badge */}
                {hiddenCount > 0 && (
                    <div className={cn(
                        "absolute top-4 right-4 text-white text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1",
                        content.badgeColor
                    )}>
                        <Lock className="w-3 h-3" />
                        +{hiddenCount}
                    </div>
                )}

                {/* Title */}
                <h3 className={cn(
                    "text-lg font-bold mb-1",
                    isOwner ? "text-amber-100" : "text-white"
                )}>
                    {content.title}
                </h3>

                {/* Body */}
                <p className={cn(
                    "text-sm mb-4 max-w-[220px]",
                    isOwner ? "text-amber-200/80" : "text-indigo-300/80"
                )}>
                    {content.body}
                </p>

                {/* CTA Button */}
                {isOwner ? (
                    <Link
                        href={adminRouteUrl}
                        className={cn(
                            "flex items-center gap-2 font-medium text-sm px-4 py-2 rounded-lg transition-all",
                            content.ctaStyle
                        )}
                    >
                        <CtaIconComponent className="w-4 h-4" />
                        <span>{content.ctaText}</span>
                        <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </Link>
                ) : hasValidWhatsApp ? (
                    <a
                        href={customerWhatsAppUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={cn(
                            "flex items-center gap-2 font-medium text-sm px-4 py-2 rounded-lg transition-all",
                            content.ctaStyle
                        )}
                    >
                        <CtaIconComponent className="w-4 h-4" />
                        <span>{content.ctaText}</span>
                        <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </a>
                ) : (
                    <div className={cn(
                        "flex items-center gap-2 font-medium text-sm px-4 py-2 rounded-lg",
                        content.ctaStyle,
                        "cursor-default opacity-80"
                    )}>
                        <CtaIconComponent className="w-4 h-4" />
                        <span>{content.ctaText}</span>
                    </div>
                )}
            </div>

            {/* Bottom Section */}
            <div className={cn(
                "p-4 border-t bg-slate-950/50",
                isOwner ? "border-amber-500/20" : "border-indigo-500/20"
            )}>
                <div className="flex items-center justify-between">
                    <span className="text-xs text-slate-500">{content.footerLeft}</span>
                    <span className={cn(
                        "text-xs font-medium",
                        isOwner ? "text-amber-400" : "text-indigo-400"
                    )}>
                        {content.footerRight}
                    </span>
                </div>
            </div>
        </div>
    );
}
