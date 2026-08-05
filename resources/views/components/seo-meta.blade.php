{{--
    SEO meta component for Blade-rendered public pages.

    Usage:
    @include('components.seo-meta', ['model' => $tenant])
    @include('components.seo-meta', [
        'title' => 'Dovra AI',
        'description' => 'AI employees that turn business conversations into measurable results.',
        'image' => asset('images/og-default.png'),
    ])
--}}

@php
    use Illuminate\Support\Str;

    $siteName = (string) config('dovra.identity.name');
    $siteUrl = rtrim((string) config('app.url'), '/');
    $marketCode = strtoupper((string) (
        $marketCode
        ?? data_get($model ?? null, 'country.code')
        ?? data_get($model ?? null, 'country_code')
        ?? ''
    ));
    $presentationLocale = str_replace('-', '_', (string) ($ogLocale ?? 'en_US'));
    $alternateLocales = array_values(array_unique(array_filter([
        ...((array) ($ogAlternateLocales ?? [])),
        $marketCode === 'HN' && $presentationLocale !== 'es_HN' ? 'es_HN' : null,
    ])));

    $cleanText = static fn (mixed $value, int $limit = 160): string => Str::limit(
        trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?: ''),
        $limit,
        ''
    );

    if (isset($model) && method_exists($model, 'getSeoTitle')) {
        $seoTitle = (string) $model->getSeoTitle();
    } elseif (! empty($title)) {
        $seoTitle = (string) $title;
    } elseif (isset($model) && ! empty($model->name)) {
        $seoTitle = "{$model->name} | {$siteName}";
    } else {
        $seoTitle = "{$siteName} - ".config('dovra.identity.category');
    }

    if (isset($model) && method_exists($model, 'getSeoDescription')) {
        $seoDescription = $cleanText($model->getSeoDescription());
    } elseif (! empty($description)) {
        $seoDescription = $cleanText($description);
    } elseif (isset($model) && ! empty($model->description)) {
        $seoDescription = $cleanText($model->description);
    } else {
        $seoDescription = (string) config('dovra.identity.commercial_promise');
    }

    if (isset($model) && method_exists($model, 'getSeoKeywords')) {
        $seoKeywords = $model->getSeoKeywords();
    } elseif (! empty($keywords)) {
        $seoKeywords = $keywords;
    } else {
        $seoKeywords = [];
    }

    $seoKeywords = is_array($seoKeywords)
        ? implode(', ', array_filter(array_map('strval', $seoKeywords)))
        : (string) $seoKeywords;

    if (isset($model) && method_exists($model, 'getOgImage')) {
        $ogImage = (string) $model->getOgImage();
    } elseif (! empty($image)) {
        $ogImage = (string) $image;
    } elseif (! empty($ogImage)) {
        $ogImage = (string) $ogImage;
    } elseif (isset($model) && ! empty($model->banner_path)) {
        $ogImage = asset('storage/'.$model->banner_path);
    } elseif (isset($model) && ! empty($model->logo_path)) {
        $ogImage = asset('storage/'.$model->logo_path);
    } else {
        $ogImage = asset('images/og-default.png');
    }

    if (! Str::startsWith($ogImage, ['http://', 'https://'])) {
        $ogImage = url($ogImage);
    }

    if (isset($model) && method_exists($model, 'getCanonicalUrl')) {
        $canonicalUrl = (string) $model->getCanonicalUrl();
    } elseif (! empty($canonical)) {
        $canonicalUrl = (string) $canonical;
    } else {
        $canonicalUrl = url()->current();
    }

    $canonicalUrl = Str::startsWith($canonicalUrl, ['http://', 'https://'])
        ? $canonicalUrl
        : $siteUrl.'/'.ltrim($canonicalUrl, '/');

    $ogType = $ogType ?? 'website';
    $robots = $robots ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';

    $schema = null;
    if (isset($model) && ! empty($model->name)) {
        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => $schemaType ?? 'LocalBusiness',
            'name' => (string) $model->name,
            'description' => $seoDescription,
            'url' => $canonicalUrl,
            'image' => $ogImage,
            'address' => ! empty($model->address) ? array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => (string) $model->address,
                'addressLocality' => data_get($model, 'city.name'),
                'addressCountry' => $marketCode !== '' ? $marketCode : null,
            ]) : null,
            'telephone' => ! empty($model->whatsapp_number) ? (string) $model->whatsapp_number : null,
        ]);
    }
@endphp

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $robots }}">
@if($seoKeywords !== '')
    <meta name="keywords" content="{{ $seoKeywords }}">
@endif
<link rel="canonical" href="{{ $canonicalUrl }}">

<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="{{ $presentationLocale }}">
@foreach($alternateLocales as $alternateLocale)
<meta property="og:locale:alternate" content="{{ $alternateLocale }}">
@endforeach

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">

@if($schema)
<script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endif
