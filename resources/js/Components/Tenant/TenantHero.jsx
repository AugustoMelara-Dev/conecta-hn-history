/**
 * TenantHero - White-Label Hero Section Component
 * 
 * DESIGN PHILOSOPHY:
 * This component implements "Defensive Design" - it never breaks
 * regardless of what data the tenant provides.
 * 
 * VISUAL LAYERS (bottom to top):
 * 1. Background Layer: Image OR gradient fallback
 * 2. Overlay Layer: Dark overlay for text contrast (WCAG compliance)
 * 3. Content Layer: Logo, name, slogan, badges
 * 
 * CSS VARIABLE ARCHITECTURE:
 * Uses runtime CSS variables for brand customization:
 * - var(--primary-color): Tenant's brand color
 * - var(--hero-bg): Hero image URL (if available)
 */

import { Link } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { MapPin, Star, BadgeCheck, MessageCircle, Phone, Clock, ExternalLink } from 'lucide-react';
import { cn } from '@/lib/utils';
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';

export default function TenantHero({
    tenant,
    isPro = false,
    isOpen = true,
    compact = false, // true for Semilla's simpler header
    className,
}) {
    if (!tenant) return null;

    // Safe accessors with defaults
    const primaryColor = tenant.primary_color || tenant.brand_color || '#6366f1';
    const heroImage = tenant.hero_image || tenant.cover || tenant.banner_path;
    const hasHeroImage = heroImage && typeof heroImage === 'string' && heroImage.length > 0;
    const cityName = typeof tenant.city === 'string' ? tenant.city : tenant.city?.name || 'Honduras';
    const whatsappNumber = tenant.whatsapp || tenant.whatsapp_number;
    const hasValidWhatsApp = isValidWhatsAppPhone(whatsappNumber);

    // Build WhatsApp URL for contact button
    const whatsappUrl = hasValidWhatsApp
        ? buildWhatsAppUrl(whatsappNumber, `¡Hola ${tenant.name}!`)
        : null;

    // Gradient fallback when no image
    const gradientFallback = `linear-gradient(135deg, ${primaryColor} 0%, #0f172a 100%)`;

    return (
        <header className={cn("relative overflow-hidden", className)}>
            {/* ═══════════════════════════════════════════════════════════════
                LAYER 1: BACKGROUND (Image or Gradient Fallback)
            ═══════════════════════════════════════════════════════════════ */}
            <div className={cn(
                "relative overflow-hidden bg-slate-900",
                compact ? "h-48" : "h-[40vh] md:h-[50vh] lg:h-[60vh]"
            )}>
                {hasHeroImage ? (
                    // Image background with lazy load and error handling
                    <img
                        src={heroImage.startsWith('/') ? heroImage : `/storage/${heroImage}`}
                        alt={`${tenant.name} - Portada`}
                        className="w-full h-full object-cover"
                        loading="eager" // Hero is above the fold
                        onError={(e) => {
                            // Fallback to gradient if image fails to load
                            e.target.style.display = 'none';
                            e.target.parentElement.style.background = gradientFallback;
                        }}
                    />
                ) : (
                    // Gradient fallback with subtle pattern
                    <div
                        className="w-full h-full relative"
                        style={{ background: gradientFallback }}
                    >
                        {/* Subtle watermark for visual interest */}
                        {isPro ? (
                            <span className="absolute inset-0 flex items-center justify-center text-white/5 font-sans text-5xl md:text-9xl font-bold tracking-widest uppercase select-none overflow-hidden">
                                {tenant.name}
                            </span>
                        ) : (
                            <div className="absolute inset-0 flex items-center justify-center">
                                <div
                                    className="w-32 h-32 rounded-full opacity-10"
                                    style={{ backgroundColor: 'white' }}
                                />
                            </div>
                        )}
                    </div>
                )}

                {/* ═══════════════════════════════════════════════════════════════
                    LAYER 2: OVERLAY (WCAG Contrast Compliance)
                    IMPORTANT: Always rendered regardless of background type
                ═══════════════════════════════════════════════════════════════ */}
                <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-slate-950/20" />
            </div>

            {/* ═══════════════════════════════════════════════════════════════
                LAYER 3: CONTENT (Logo, Name, Info)
            ═══════════════════════════════════════════════════════════════ */}
            <div className={cn(
                "relative z-10 px-4 md:px-6 lg:px-8",
                compact
                    ? "max-w-3xl mx-auto -mt-16 pb-6 text-center"
                    : "absolute bottom-0 left-0 w-full p-6 md:p-12"
            )}>
                <div className={cn(
                    "flex gap-6",
                    compact
                        ? "flex-col items-center"
                        : "flex-col md:flex-row items-end md:items-center max-w-7xl mx-auto"
                )}>
                    {/* Logo */}
                    <div className={cn(
                        "rounded-2xl border-4 border-slate-950 shadow-2xl overflow-hidden flex-shrink-0",
                        compact ? "w-24 h-24" : "w-24 h-24 md:w-32 md:h-32"
                    )}
                        style={{ backgroundColor: primaryColor }}
                    >
                        {tenant.logo ? (
                            <img
                                src={tenant.logo.startsWith?.('/') ? tenant.logo : `/storage/${tenant.logo}`}
                                alt={`${tenant.name} - Logo`}
                                className="w-full h-full object-cover"
                                onError={(e) => {
                                    // Hide broken image, show fallback initials
                                    e.target.style.display = 'none';
                                }}
                            />
                        ) : null}
                        {/* Fallback initials (always rendered behind image) */}
                        <div className="absolute inset-0 flex items-center justify-center">
                            <span className={cn(
                                "font-bold text-white",
                                compact ? "text-2xl" : "text-3xl"
                            )}>
                                {tenant.name?.substring(0, 2).toUpperCase()}
                            </span>
                        </div>
                    </div>

                    {/* Info */}
                    <div className={cn(compact ? "" : "flex-1 mb-2")}>
                        <h1 className={cn(
                            "font-bold text-white flex items-center gap-3",
                            compact ? "text-2xl justify-center" : "text-3xl md:text-5xl"
                        )}>
                            {tenant.name}
                            {isPro && (
                                <BadgeCheck
                                    className="w-8 h-8 fill-[var(--primary-color)]/30"
                                    style={{ color: primaryColor }}
                                />
                            )}
                        </h1>

                        {/* Slogan (if exists) */}
                        {tenant.slogan && (
                            <p className={cn(
                                "text-white/80 italic mt-1",
                                compact ? "text-sm" : "text-lg"
                            )}>
                                "{tenant.slogan}"
                            </p>
                        )}

                        {/* Badges and Info */}
                        <div className={cn(
                            "flex flex-wrap items-center gap-3 text-white/90 mt-3",
                            compact && "justify-center"
                        )}>
                            <Badge
                                className="text-white border-none backdrop-blur-md hover:opacity-90"
                                style={{ backgroundColor: `${primaryColor}40` }}
                            >
                                {tenant.category?.name || 'General'}
                            </Badge>

                            {/* Rating - Pro only */}
                            {isPro && (
                                <>
                                    <div className="flex items-center gap-1">
                                        <Star className="w-4 h-4 fill-amber-400 text-amber-400" />
                                        <span className="font-bold">{tenant.rating || '4.8'}</span>
                                        <span className="opacity-70">({tenant.reviews_count || 0})</span>
                                    </div>
                                    <span className="hidden md:inline text-white/40">•</span>
                                </>
                            )}

                            <div className="flex items-center gap-1 text-sm text-slate-300">
                                <MapPin className="w-4 h-4" />
                                <span>{cityName}</span>
                            </div>
                        </div>

                        {/* Action Buttons */}
                        <div className={cn(
                            "flex items-center gap-4 mt-4",
                            compact && "justify-center"
                        )}>
                            {/* Status Indicator - Pro only */}
                            {isPro && (
                                <div className="flex items-center gap-2">
                                    <div className={cn(
                                        "w-2 h-2 rounded-full",
                                        isOpen ? "bg-emerald-500" : "bg-slate-500"
                                    )} />
                                    <span className={cn(
                                        "text-sm",
                                        isOpen ? "text-emerald-400" : "text-slate-400"
                                    )}>
                                        {isOpen ? 'Abierto' : 'Cerrado'}
                                    </span>
                                </div>
                            )}

                            {/* WhatsApp Button */}
                            {whatsappUrl && (
                                <a
                                    href={whatsappUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={cn(
                                        "flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all",
                                        compact
                                            ? "bg-emerald-600 hover:bg-emerald-500 text-white"
                                            : "bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 border border-emerald-500/20"
                                    )}
                                >
                                    <MessageCircle className="w-4 h-4" />
                                    WhatsApp
                                </a>
                            )}

                            {/* Phone Button - Compact only */}
                            {compact && tenant.phone && (
                                <a
                                    href={`tel:${tenant.phone}`}
                                    className="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors border border-white/10"
                                >
                                    <Phone className="w-4 h-4" />
                                    Llamar
                                </a>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </header>
    );
}
