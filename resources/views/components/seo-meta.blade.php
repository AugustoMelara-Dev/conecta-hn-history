{{--
    SEO Meta Tags Component
    
    Motor de Inyección Dinámica de Metadatos SEO
    
    Uso en layouts:
    @include('components.seo-meta', ['model' => $tenant])
    
    O con datos manuales:
    @include('components.seo-meta', [
        'title' => 'Mi Título',
        'description' => 'Mi descripción',
        'image' => '/path/to/image.jpg'
    ])
--}}

@php
    use App\Settings\GeneralSettings;
    
    // Obtener configuración por defecto
    $settings = app(GeneralSettings::class);
    $siteName = config('app.name', 'Conecta HN');
    $siteUrl = config('app.url', 'https://conecta.hn');
    
    // ═══════════════════════════════════════════════════════════════════════
    // LÓGICA DE CASCADA (FALLBACK)
    // Escenario A: Datos específicos del modelo
    // Escenario B: Datos automáticos generados
    // Escenario C: Valores por defecto globales
    // ═══════════════════════════════════════════════════════════════════════
    
    // TÍTULO SEO
    if (isset($model) && method_exists($model, 'getSeoTitle')) {
        $seoTitle = $model->getSeoTitle();
    } elseif (isset($title) && $title) {
        $seoTitle = $title;
    } elseif (isset($model->name)) {
        $seoTitle = "{$model->name} | {$siteName}";
    } else {
        $seoTitle = $siteName . ' - El Directorio de Negocios de Honduras';
    }
    
    // META DESCRIPCIÓN
    if (isset($model) && method_exists($model, 'getSeoDescription')) {
        $seoDescription = $model->getSeoDescription();
    } elseif (isset($description) && $description) {
        $seoDescription = $description;
    } elseif (isset($model->description)) {
        $seoDescription = Str::limit(strip_tags($model->description), 160);
    } else {
        $seoDescription = 'Descubre los mejores negocios de Honduras en Conecta HN. Restaurantes, tiendas, servicios y más.';
    }
    
    // PALABRAS CLAVE
    if (isset($model) && method_exists($model, 'getSeoKeywords')) {
        $seoKeywords = $model->getSeoKeywords();
    } elseif (isset($keywords) && $keywords) {
        $seoKeywords = is_array($keywords) ? implode(', ', $keywords) : $keywords;
    } else {
        $seoKeywords = 'negocios honduras, directorio honduras, empresas tegucigalpa, restaurantes san pedro sula';
    }
    
    // IMAGEN OPEN GRAPH
    if (isset($model) && method_exists($model, 'getOgImage')) {
        $ogImage = $model->getOgImage();
    } elseif (isset($image) && $image) {
        $ogImage = $image;
    } elseif (isset($model->banner_path)) {
        $ogImage = asset('storage/' . $model->banner_path);
    } elseif (isset($model->logo_path)) {
        $ogImage = asset('storage/' . $model->logo_path);
    } else {
        $ogImage = asset('images/og-default.png');
    }
    
    // URL CANÓNICA
    if (isset($model) && method_exists($model, 'getCanonicalUrl')) {
        $canonicalUrl = $model->getCanonicalUrl();
    } elseif (isset($canonical)) {
        $canonicalUrl = $canonical;
    } else {
        $canonicalUrl = url()->current();
    }
    
    // TIPO OG
    $ogType = $ogType ?? 'website';
@endphp

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- META TAGS BÁSICOS --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
@if($seoKeywords)
<meta name="keywords" content="{{ $seoKeywords }}">
@endif
<link rel="canonical" href="{{ $canonicalUrl }}">

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- OPEN GRAPH (Facebook, WhatsApp, LinkedIn) --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="es_HN">

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- TWITTER CARDS --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- DATOS ESTRUCTURADOS JSON-LD (Schema.org) --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
@if(isset($model) && isset($model->name))
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "{{ $schemaType ?? 'LocalBusiness' }}",
    "name": "{{ $model->name }}",
    "description": "{{ $seoDescription }}",
    "url": "{{ $canonicalUrl }}",
    "image": "{{ $ogImage }}"
    @if(isset($model->address))
    ,"address": {
        "@type": "PostalAddress",
        "addressLocality": "{{ $model->city->name ?? 'Honduras' }}",
        "addressCountry": "HN",
        "streetAddress": "{{ $model->address }}"
    }
    @endif
    @if(isset($model->whatsapp_number))
    ,"telephone": "{{ $model->whatsapp_number }}"
    @endif
}
</script>
@endif
