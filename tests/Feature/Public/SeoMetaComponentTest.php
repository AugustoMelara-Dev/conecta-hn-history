<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

beforeEach(function (): void {
    config([
        'app.name' => 'Dovra AI',
        'app.url' => 'https://conecta.hn',
    ]);
});

it('renders canonical public SEO defaults without legacy brand copy', function (): void {
    Route::get('/__seo/defaults', fn () => view('components.seo-meta', [
        'title' => 'Directorio de negocios en Honduras',
        'description' => 'Encuentra comercios locales, servicios y restaurantes cerca de ti.',
        'canonical' => '/directorio',
    ]));

    get('/__seo/defaults')
        ->assertOk()
        ->assertSee('<title>Directorio de negocios en Honduras</title>', false)
        ->assertSee('<meta name="description" content="Encuentra comercios locales, servicios y restaurantes cerca de ti.">', false)
        ->assertSee('<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">', false)
        ->assertSee('<link rel="canonical" href="https://conecta.hn/directorio">', false)
        ->assertSee('<meta property="og:site_name" content="Dovra AI">', false)
        ->assertDontSee('Vito', false);
});

it('renders structured data as valid escaped JSON-LD', function (): void {
    $model = (object) [
        'name' => 'Café "El Centro"',
        'description' => 'Café local con postres & reservas.',
        'address' => 'Boulevard Morazán #123',
        'whatsapp_number' => '+504 9999-9999',
        'city' => (object) ['name' => 'Tegucigalpa'],
    ];

    Route::get('/__seo/jsonld', fn () => view('components.seo-meta', [
        'model' => $model,
        'canonical' => '/t/cafe-el-centro',
        'marketCode' => 'HN',
    ]));

    $response = get('/__seo/jsonld')
        ->assertOk()
        ->assertSee('<script type="application/ld+json">', false);

    $html = $response->getContent();
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    expect($matches[1] ?? null)->not->toBeNull();

    $schema = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

    expect($schema)
        ->toMatchArray([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => 'Café "El Centro"',
            'url' => 'https://conecta.hn/t/cafe-el-centro',
            'telephone' => '+504 9999-9999',
        ])
        ->and($schema['address'])
        ->toMatchArray([
            '@type' => 'PostalAddress',
            'streetAddress' => 'Boulevard Morazán #123',
            'addressLocality' => 'Tegucigalpa',
            'addressCountry' => 'HN',
        ]);
});
