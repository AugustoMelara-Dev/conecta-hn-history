<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Contracts;

use App\Application\DynamicForms\Data\DynamicSchemaData;
use App\Application\DynamicForms\Data\DynamicTemplateData;
use App\Models\Dynamic\DynamicPrintTemplate;

interface DynamicTemplateRendererInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function renderHtml(DynamicPrintTemplate $template, array $context, array $schema = []): string;

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderPdf(DynamicPrintTemplate $template, array $context, array $schema = []): string;

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderHtmlFromData(DynamicTemplateData $templateData, array $context, DynamicSchemaData $schemaData): string;
}

