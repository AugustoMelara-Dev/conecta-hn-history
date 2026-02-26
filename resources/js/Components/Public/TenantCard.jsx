/**
 * Components/Public/TenantCard.jsx - Business Card for Directory
 *
 * FEATURES:
 * - Lazy loaded cover image
 * - Logo avatar overlay
 * - Category badge
 * - Entire card is clickable link
 *
 * @param {Object} props
 * @param {Object} props.tenant - Tenant object
 * @param {number} props.tenant.id
 * @param {string} props.tenant.name
 * @param {string} props.tenant.slug
 * @param {string|null} props.tenant.cover_url
 * @param {string|null} props.tenant.logo_url
 * @param {string|null} props.tenant.category
 * @param {string|null} props.tenant.city
 * @param {string|null} props.tenant.description
 * @param {boolean} props.tenant.is_open - Current open status
 */

import { Link } from '@inertiajs/react';

export default function TenantCard({ tenant }) {
    // FIX #6: Defensive programming - prevent crash if tenant.name is undefined
    // Principle: Nullish Coalescing for fail-safe rendering
    const firstLetter = tenant?.name?.[0]?.toUpperCase() || '?';

    // Placeholder image for missing covers
    const placeholderCover = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="200"%3E%3Crect fill="%23e5e7eb" width="400" height="200"/%3E%3C/svg%3E';
    const placeholderLogo = `data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="80" height="80"%3E%3Crect fill="%236366f1" width="80" height="80" rx="12"/%3E%3Ctext x="40" y="50" text-anchor="middle" fill="white" font-size="32" font-weight="bold"%3E${firstLetter}%3C/text%3E%3C/svg%3E`;

    return (
        <Link
            href={route('tenant.microsite.show', { tenant: tenant.slug })}
            className="group block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg hover:border-gray-200 transition-all duration-300"
        >
            {/* Cover Image */}
            <div className="relative aspect-[16/9] bg-gray-100 overflow-hidden">
                <img
                    src={tenant.cover_url || placeholderCover}
                    alt={`${tenant.name} cover`}
                    loading="lazy"
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                />

                {/* Open/Closed Badge */}
                {typeof tenant.is_open === 'boolean' && (
                    <div className={`absolute top-3 right-3 px-2.5 py-1 rounded-full text-xs font-bold ${tenant.is_open
                        ? 'bg-emerald-500 text-white'
                        : 'bg-gray-800/80 text-gray-200'
                        }`}>
                        {tenant.is_open ? 'Abierto' : 'Cerrado'}
                    </div>
                )}

                {/* Logo Avatar */}
                <div className="absolute -bottom-8 left-4">
                    <div className="w-16 h-16 rounded-xl border-4 border-white shadow-lg overflow-hidden bg-white">
                        <img
                            src={tenant.logo_url || placeholderLogo}
                            alt={`${tenant.name} logo`}
                            loading="lazy"
                            className="w-full h-full object-cover"
                        />
                    </div>
                </div>
            </div>

            {/* Content */}
            <div className="pt-10 pb-5 px-5">
                {/* Name */}
                <h3 className="font-bold text-gray-900 text-lg mb-1 group-hover:text-blue-600 transition-colors">
                    {tenant.name}
                </h3>

                {/* Location & Category */}
                <div className="flex items-center gap-2 text-sm text-gray-500 mb-3">
                    {tenant.city && (
                        <span className="flex items-center gap-1">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {tenant.city}
                        </span>
                    )}
                    {tenant.category && (
                        <span className="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">
                            {tenant.category}
                        </span>
                    )}
                </div>

                {/* Description */}
                {tenant.description && (
                    <p className="text-sm text-gray-600 line-clamp-2">
                        {tenant.description}
                    </p>
                )}
            </div>
        </Link>
    );
}
