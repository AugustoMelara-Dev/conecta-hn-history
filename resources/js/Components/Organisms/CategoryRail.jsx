import { useRef, useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import * as Icons from 'lucide-react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

const ICON_MAP = {
    'restaurantes': 'Utensils',
    'tecnologia': 'Laptop',
    'servicios': 'Briefcase',
    'salud': 'HeartPulse',
    'moda': 'Shirt',
    'hogar': 'Home',
    'automotriz': 'Car',
    'entretenimiento': 'Ticket',
    'deportes': 'Dumbbell',
    'mascotas': 'Dog',
    'educacion': 'GraduationCap',
    'belleza': 'Sparkles',
    'turismo': 'Plane',
    'bienes-raices': 'Building',
    'legal': 'Scale',
    'construccion': 'Hammer',
    'eventos': 'PartyPopper',
    'supermercados': 'ShoppingBasket',
    'finanzas': 'Banknote',
    'logistica': 'Truck',
};

export default function CategoryRail({ categories }) {
    const scrollContainerRef = useRef(null);
    const [showLeftArrow, setShowLeftArrow] = useState(false);
    const [showRightArrow, setShowRightArrow] = useState(true);

    const checkScroll = () => {
        if (scrollContainerRef.current) {
            const { scrollLeft, scrollWidth, clientWidth } = scrollContainerRef.current;
            setShowLeftArrow(scrollLeft > 0);
            setShowRightArrow(scrollLeft < scrollWidth - clientWidth - 10); // 10px buffer
        }
    };

    useEffect(() => {
        checkScroll();
        const container = scrollContainerRef.current;
        if (container) {
            container.addEventListener('scroll', checkScroll);
            window.addEventListener('resize', checkScroll);
            return () => {
                container.removeEventListener('scroll', checkScroll);
                window.removeEventListener('resize', checkScroll);
            };
        }
    }, [categories]);

    const scroll = (direction) => {
        if (scrollContainerRef.current) {
            const { clientWidth } = scrollContainerRef.current;
            const scrollAmount = clientWidth * 0.75; // Scroll 75% of view width
            scrollContainerRef.current.scrollBy({
                left: direction === 'left' ? -scrollAmount : scrollAmount,
                behavior: 'smooth',
            });
        }
    };

    return (
        <div className="w-full bg-background border-b border-border/40 py-4">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative group">

                {/* Left Arrow */}
                {showLeftArrow && (
                    <button
                        onClick={() => scroll('left')}
                        className="absolute left-0 top-1/2 -translate-y-1/2 z-20 bg-background/80 backdrop-blur-sm border border-border rounded-full p-2 shadow-lg hover:bg-accent transition-all opacity-0 group-hover:opacity-100 duration-300 ml-2"
                        aria-label="Scroll left"
                    >
                        <ChevronLeft className="w-5 h-5 text-foreground" />
                    </button>
                )}

                {/* Right Arrow */}
                {showRightArrow && (
                    <button
                        onClick={() => scroll('right')}
                        className="absolute right-0 top-1/2 -translate-y-1/2 z-20 bg-background/80 backdrop-blur-sm border border-border rounded-full p-2 shadow-lg hover:bg-accent transition-all opacity-0 group-hover:opacity-100 duration-300 mr-2"
                        aria-label="Scroll right"
                    >
                        <ChevronRight className="w-5 h-5 text-foreground" />
                    </button>
                )}

                {/* Scroll Container */}
                <div
                    ref={scrollContainerRef}
                    className="flex gap-3 overflow-x-auto scrollbar-hide pb-2 px-1 snap-x snap-mandatory"
                    style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
                >
                    {categories.map((category) => {
                        const IconComponent = Icons[ICON_MAP[category.slug]] || Icons.Store;
                        const isActive = route().current('directory.index') && new URLSearchParams(window.location.search).get('category') === category.slug;

                        return (
                            <Link
                                key={category.id}
                                href={route('directory.index', { category: category.slug })}
                                className={cn(
                                    "flex items-center gap-2 px-4 py-2 rounded-full border text-sm font-medium whitespace-nowrap transition-all duration-200 snap-start select-none",
                                    isActive
                                        ? "bg-primary text-primary-foreground border-primary shadow-md"
                                        : "bg-card text-muted-foreground border-border hover:border-primary/50 hover:text-foreground hover:bg-accent/50"
                                )}
                            >
                                <IconComponent className={cn("w-4 h-4", isActive ? "text-primary-foreground" : "text-muted-foreground")} />
                                {category.name}
                            </Link>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
