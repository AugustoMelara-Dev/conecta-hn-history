/**
 * CategoryNav.jsx - Horizontal Scrollable Category Navigation
 *
 * ARCHITECTURE:
 * - Mobile-first horizontal scroll tabs
 * - Smooth scroll to section on click (no history pollution)
 * - Dynamic brand color injection
 * - Scrollbar hidden for app-like UX
 *
 * @typedef {Object} Category
 * @property {string|number} id - Section ID for scroll targeting
 * @property {string} name - Display name
 */

import { useState, useEffect, useCallback } from 'react';
import PropTypes from 'prop-types';

/**
 * CategoryNav - Sticky Horizontal Category Tabs
 *
 * @param {Object} props
 * @param {Category[]} props.categories - Array of menu sections
 * @param {string} props.primaryColor - Tenant's brand hex color
 * @param {string} [props.className] - Additional CSS classes
 */
export default function CategoryNav({
    categories = [],
    primaryColor = '#000000',
    className = '',
}) {
    const [activeCategory, setActiveCategory] = useState(null);

    // ═══════════════════════════════════════════════════════════════════════
    // SCROLL HANDLER: Track which section is currently in view
    // ═══════════════════════════════════════════════════════════════════════
    const handleScroll = useCallback(() => {
        if (!categories.length) return;

        const scrollPosition = window.scrollY + 150; // Offset for sticky headers

        for (let i = categories.length - 1; i >= 0; i--) {
            const element = document.getElementById(`category-${categories[i].id}`);
            if (element && element.offsetTop <= scrollPosition) {
                setActiveCategory(categories[i].id);
                return;
            }
        }

        // Default to first category if above all
        setActiveCategory(categories[0]?.id);
    }, [categories]);

    useEffect(() => {
        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll(); // Initial check

        return () => window.removeEventListener('scroll', handleScroll);
    }, [handleScroll]);

    // ═══════════════════════════════════════════════════════════════════════
    // CLICK HANDLER: Smooth scroll to target section
    // ═══════════════════════════════════════════════════════════════════════
    const scrollToCategory = (categoryId) => {
        const element = document.getElementById(`category-${categoryId}`);
        if (element) {
            // Calculate offset for sticky headers (navbar + category nav)
            const offset = 128; // 64px navbar + 48px category nav + 16px padding
            const elementPosition = element.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - offset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth',
            });

            setActiveCategory(categoryId);
        }
    };

    // ═══════════════════════════════════════════════════════════════════════
    // DEFENSIVE: Return null if no categories
    // ═══════════════════════════════════════════════════════════════════════
    if (!categories || categories.length === 0) {
        return null;
    }

    return (
        <nav
            className={`
                sticky top-16 z-40 bg-white border-b border-gray-100
                ${className}
            `}
            aria-label="Navegación de categorías"
        >
            <div className="max-w-7xl mx-auto px-4">
                {/* Horizontal Scroll Container */}
                <ul
                    className="
                        flex items-center gap-1 py-2
                        overflow-x-auto scrollbar-hide
                        -mx-4 px-4
                    "
                    style={{
                        WebkitOverflowScrolling: 'touch',
                        scrollbarWidth: 'none',
                        msOverflowStyle: 'none',
                    }}
                >
                    {categories.map((category) => {
                        const isActive = activeCategory === category.id;

                        return (
                            <li key={category.id} className="flex-shrink-0">
                                <button
                                    type="button"
                                    onClick={() => scrollToCategory(category.id)}
                                    className={`
                                        px-4 py-2 rounded-full text-sm font-medium
                                        whitespace-nowrap transition-all duration-200
                                        focus:outline-none focus:ring-2 focus:ring-offset-2
                                        ${isActive
                                            ? 'text-white shadow-md'
                                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                        }
                                    `}
                                    style={{
                                        backgroundColor: isActive ? primaryColor : undefined,
                                        '--tw-ring-color': primaryColor,
                                    }}
                                    aria-current={isActive ? 'true' : undefined}
                                >
                                    {category.name}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </div>

            {/* CSS to hide scrollbar across browsers */}
            <style jsx>{`
                .scrollbar-hide::-webkit-scrollbar {
                    display: none;
                }
                .scrollbar-hide {
                    -ms-overflow-style: none;
                    scrollbar-width: none;
                }
            `}</style>
        </nav>
    );
}

CategoryNav.propTypes = {
    categories: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
            name: PropTypes.string.isRequired,
        })
    ),
    primaryColor: PropTypes.string,
    className: PropTypes.string,
};
