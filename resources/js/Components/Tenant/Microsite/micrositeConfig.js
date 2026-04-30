import { CalendarClock, ShoppingBag, Store, UtensilsCrossed } from 'lucide-react';

export const PLAN = { SEMILLA: 'semilla', PRO: 'pro', ENTERPRISE: 'enterprise' };

export const PLAN_LABELS = {
    [PLAN.SEMILLA]: 'Plan Semilla',
    [PLAN.PRO]: 'Business Pro',
    [PLAN.ENTERPRISE]: 'Enterprise',
};

export const FALLBACK_IMAGE = 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=1200&h=900&fit=crop';

export const BUSINESS_THEMES = {
    retail: {
        label: 'Retail',
        accentClass: 'from-primary/45 via-accent/20 to-transparent',
        chipClass: 'bg-primary/10 text-primary',
        icon: ShoppingBag,
    },
    food: {
        label: 'Food',
        accentClass: 'from-secondary/70 via-accent/20 to-transparent',
        chipClass: 'bg-secondary text-secondary-foreground',
        icon: UtensilsCrossed,
    },
    service: {
        label: 'Service',
        accentClass: 'from-primary/40 via-secondary/20 to-transparent',
        chipClass: 'bg-primary/10 text-primary',
        icon: CalendarClock,
    },
    hybrid: {
        label: 'Hybrid',
        accentClass: 'from-accent/30 via-primary/10 to-transparent',
        chipClass: 'bg-accent text-accent-foreground',
        icon: Store,
    },
};
