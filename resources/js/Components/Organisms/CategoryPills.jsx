import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import * as Icons from 'lucide-react';

export default function CategoryPills({ categories, activeCategory }) {
    return (
        <div className="w-full overflow-x-auto pb-4 pt-2 no-scrollbar">
            <div className="flex gap-3 px-1 min-w-max">
                {/* 'All' Option */}
                <Link
                    href={route('directory.index')}
                    preserveState
                    preserveScroll
                    className={cn(
                        "flex items-center gap-2 px-4 py-2 rounded-full border text-sm font-medium transition-all duration-200 hover:shadow-md",
                        !activeCategory
                            ? "bg-foreground text-background border-foreground"
                            : "bg-background text-foreground border-border hover:border-foreground/50"
                    )}
                >
                    <Icons.LayoutGrid className="w-4 h-4" />
                    Todos
                </Link>

                {categories.map((category) => {
                    // Dynamic Icon Rendering
                    const IconComponent = Icons[category.icon] || Icons.Tag;

                    return (
                        <Link
                            key={category.id}
                            href={route('directory.index', { category: category.slug })}
                            preserveState
                            preserveScroll
                            only={['tenants', 'filters']}
                            className={cn(
                                "flex items-center gap-2 px-4 py-2 rounded-full border text-sm font-medium transition-all duration-200 hover:shadow-md",
                                activeCategory === category.slug
                                    ? "bg-foreground text-background border-foreground"
                                    : "bg-background text-foreground border-border hover:border-foreground/50"
                            )}
                        >
                            <IconComponent className="w-4 h-4" />
                            {category.name}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
