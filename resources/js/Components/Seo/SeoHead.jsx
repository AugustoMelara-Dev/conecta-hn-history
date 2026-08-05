import React from 'react';
import { Head } from '@inertiajs/react';

import { PUBLIC_BRAND } from '@/lib/publicBrand';
import { safeJsonLd } from '@/lib/safeJsonLd';

/**
 * SeoHead - Polimorphic SEO Component
 * 
 * Standardizes the injection of meta tags, Open Graph, Twitter Cards,
 * and JSON-LD Schema for maximum search engine and social compliance.
 * 
 * @param {string} title - Page Title (Browser Tab & Search Result).
 * @param {string} description - Meta description (Search Snippet).
 * @param {string} image - Social Share Image (Absolute URL).
 * @param {string} url - Canonical URL.
 * @param {string} type - Open Graph Type (default: 'website').
 * @param {object} schema - JSON-LD Schema Structure (LocalBusiness, etc).
 */
export default function SeoHead({
    title,
    description,
    image,
    url,
    type = 'website',
    schema = null
}) {
    // ═══════════════════════════════════════════════════════════════════════
    // DEFAULTS & FALLBACKS
    // ═══════════════════════════════════════════════════════════════════════
    const siteName = PUBLIC_BRAND.name;
    // Fallback image prevents "Grey Card" in WhatsApp/iMessage
    const defaultImage = "/images/og-default.svg";
    const finalImage = image || defaultImage;
    // URL fallback validation (SSR safe check ideally, but defaulting to prop)
    const finalUrl = url;

    // ═══════════════════════════════════════════════════════════════════════
    // JSON-LD INJECTION (Security Critical: Serialized)
    // ═══════════════════════════════════════════════════════════════════════
    const jsonLdScript = schema ? (
        <script
            type="application/ld+json"
            dangerouslySetInnerHTML={{ __html: safeJsonLd(schema) }}
        />
    ) : null;

    return (
        <Head>
            {/* 1. Standard Visibility */}
            <title>{title}</title>
            <meta name="description" content={description} />

            {/* 2. Social Protocol (Open Graph / Facebook / WhatsApp) */}
            <meta property="og:type" content={type} />
            <meta property="og:title" content={title} />
            <meta property="og:description" content={description} />
            <meta property="og:image" content={finalImage} />
            <meta property="og:url" content={finalUrl} />
            <meta property="og:site_name" content={siteName} />

            {/* 3. Twitter Cards (Large Image for CTR) */}
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content={title} />
            <meta name="twitter:description" content={description} />
            <meta name="twitter:image" content={finalImage} />

            {/* 4. Semantic Web (Google Rich Snippets) */}
            {jsonLdScript}
        </Head>
    );
}
