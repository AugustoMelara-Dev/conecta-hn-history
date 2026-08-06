<?php

declare(strict_types=1);

use App\Application\Automation\TemplateCatalog;

it('TemplateCatalog has 12 templates', function () {
    $catalog = new TemplateCatalog();
    $templates = $catalog->all();

    expect($templates)->toHaveCount(12);
});

it('each template has required keys', function () {
    $catalog = new TemplateCatalog();

    foreach ($catalog->all() as $template) {
        expect($template)->toHaveKeys(['name', 'slug', 'description', 'category', 'graph']);
        expect($template['graph'])->toHaveKeys(['nodes', 'edges']);
    }
});

it('findBySlug returns template by slug', function () {
    $catalog = new TemplateCatalog();
    $template = $catalog->findBySlug('welcome-new-contact');

    expect($template)->not->toBeNull();
    expect($template['name'])->toBe('Welcome new contact');
});

it('findBySlug returns null for unknown slug', function () {
    $catalog = new TemplateCatalog();
    expect($catalog->findBySlug('non-existent'))->toBeNull();
});
