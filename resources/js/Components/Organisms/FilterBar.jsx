import { router, usePage } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';

export default function FilterBar({ categories = [] }) {
    const { url: currentUrl } = usePage();
    const searchParams = new URLSearchParams(currentUrl.split('?')[1] || '');
    const activeCategory = searchParams.get('category');

    const handleCategoryClick = (categorySlug) => {
        router.get(
            route('directory.index'),
            { category: categorySlug },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const clearFilters = () => {
        router.get(
            route('directory.index'),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    return (
        <div className="w-full border-b bg-background">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="relative">
                    {/* Horizontal scroll container */}
                    <div className="flex gap-2 overflow-x-auto pb-3 pt-4 scrollbar-hide snap-x snap-mandatory">
                        {/* Clear all filter */}
                        <Badge
                            variant={!activeCategory ? 'default' : 'outline'}
                            className="cursor-pointer snap-start shrink-0 transition-all hover:scale-105"
                            onClick={clearFilters}
                        >
                            Todas
                        </Badge>

                        {/* Category pills */}
                        {categories.map((category) => (
                            <Badge
                                key={category.id}
                                variant={activeCategory === category.slug ? 'default' : 'outline'}
                                className="cursor-pointer snap-start shrink-0 transition-all hover:scale-105"
                                onClick={() => handleCategoryClick(category.slug)}
                            >
                                {category.name}
                            </Badge>
                        ))}
                    </div>

                    {/* Fade gradient on right edge */}
                    <div className="absolute right-0 top-0 bottom-0 w-12 bg-gradient-to-l from-background to-transparent pointer-events-none" />
                </div>
            </div>
        </div>
    );
}
