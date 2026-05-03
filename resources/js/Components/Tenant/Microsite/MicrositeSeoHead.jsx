import { Head } from '@inertiajs/react';
import { safeJsonLd } from '@/lib/safeJsonLd';

export default function MicrositeSeoHead({
    pageTitle,
    pageDescription,
    pageImage,
    canonicalUrl,
    seoStructuredData,
}) {
    return (
        <Head title={pageTitle}>
            <meta name="description" content={pageDescription} />
            <meta property="og:title" content={pageTitle} />
            <meta property="og:description" content={pageDescription} />
            <meta property="og:type" content="business.business" />
            <meta property="og:image" content={pageImage} />
            {canonicalUrl ? <link rel="canonical" href={canonicalUrl} /> : null}
            {seoStructuredData ? (
                <script
                    type="application/ld+json"
                    // trusted-jsonld: escape script-breaking chars before injection.
                    dangerouslySetInnerHTML={{ __html: safeJsonLd(seoStructuredData) }}
                />
            ) : null}
        </Head>
    );
}
