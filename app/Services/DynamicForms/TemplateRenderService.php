<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicTemplateRendererInterface;
use App\Application\DynamicForms\Contracts\DynamicCompiledCacheInterface;
use App\Application\DynamicForms\Data\DynamicSchemaData;
use App\Application\DynamicForms\Data\DynamicTemplateData;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Services\DynamicForms\Exceptions\InvalidDynamicTemplateException;
use App\Services\DynamicForms\Validation\TemplateBlockContractValidator;
use Barryvdh\DomPDF\Facade\Pdf;

final class TemplateRenderService implements DynamicTemplateRendererInterface
{
    public function __construct(
        private readonly TemplateBlockContractValidator $templateValidator,
        private readonly DynamicRuntimeFeatureManager $featureManager,
        private readonly DynamicCompiledCacheInterface $compiledCache,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $schema
     */
    public function renderHtml(DynamicPrintTemplate $template, array $context, array $schema = []): string
    {
        $template->loadMissing(['entityDefinition', 'activeVersion']);
        $this->assertRuntimeEnabled($template->entityDefinition?->module_key);

        $templatePayload = [
            'template_key' => $template->template_key,
            'action_key' => $template->action_key,
            'name' => $template->name,
            'blocks_json' => is_array($template->blocks_json) ? $template->blocks_json : [],
            'placeholder_whitelist' => is_array($template->placeholder_whitelist) ? $template->placeholder_whitelist : [],
            'contract_family' => data_get($template->activeVersion?->metadata_json, 'contract_family'),
            'contract_semver' => data_get($template->activeVersion?->metadata_json, 'contract_semver'),
            'placeholder_schema_version' => data_get($template->activeVersion?->metadata_json, 'placeholder_schema_version'),
        ];

        if ($template->activeVersion !== null) {
            $tenantId = $template->tenant_id ? (string) $template->tenant_id : null;
            $templatePayload = $this->compiledCache->rememberTemplate(
                tenantId: $tenantId,
                templateKey: (string) $template->template_key,
                templateVersion: (int) $template->activeVersion->version_number,
                resolver: static fn (): array => $templatePayload,
            );
        }

        $templateData = DynamicTemplateData::fromArray($templatePayload);

        $schemaData = DynamicSchemaData::fromArray($schema);

        return $this->renderHtmlFromData($templateData, $context, $schemaData);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $schema
     */
    public function renderPdf(DynamicPrintTemplate $template, array $context, array $schema = []): string
    {
        $html = $this->renderHtml($template, $context, $schema);

        return Pdf::loadHTML($html)
            ->setPaper('A4', 'portrait')
            ->output();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderHtmlFromData(DynamicTemplateData $templateData, array $context, DynamicSchemaData $schemaData): string
    {
        $this->assertRuntimeEnabled((string) ($schemaData->entity['module_key'] ?? ''));
        $this->templateValidator->validate($templateData, $schemaData);

        $allowedPaths = $this->buildAllowedPaths($schemaData, $templateData);
        $renderedBlocks = [];

        foreach ($templateData->blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $renderedBlocks[] = $this->renderBlock($type, $block, $context, $allowedPaths);
        }

        $body = implode("\n", array_filter($renderedBlocks));

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; line-height: 1.4; margin: 24px; }
        h1, h2 { margin: 0 0 10px; }
        .block { margin-bottom: 14px; }
        .kv-row { display: flex; justify-content: space-between; border-bottom: 1px solid #e5e7eb; padding: 5px 0; gap: 10px; }
        .kv-key { font-weight: 700; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        hr { border: 0; border-top: 1px solid #d1d5db; margin: 8px 0; }
    </style>
</head>
<body>
{$body}
</body>
</html>
HTML;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $allowedPaths
     */
    private function renderBlock(string $type, array $block, array $context, array $allowedPaths): string
    {
        return match ($type) {
            'header' => '<div class="block"><h1>'.$this->escapeInline($this->resolveTemplateString((string) ($block['content'] ?? ''), $context, $allowedPaths)).'</h1></div>',
            'paragraph' => '<div class="block"><p>'.$this->escapeInline($this->resolveTemplateString((string) ($block['content'] ?? ''), $context, $allowedPaths)).'</p></div>',
            'footer' => '<div class="block"><small>'.$this->escapeInline($this->resolveTemplateString((string) ($block['content'] ?? ''), $context, $allowedPaths)).'</small></div>',
            'divider' => '<div class="block"><hr></div>',
            'kv' => $this->renderKeyValueBlock($block, $context, $allowedPaths),
            'table' => $this->renderTableBlock($block, $context, $allowedPaths),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $allowedPaths
     */
    private function renderKeyValueBlock(array $block, array $context, array $allowedPaths): string
    {
        $items = $block['items'] ?? [];
        if (! is_array($items)) {
            return '';
        }

        $rows = [];
        foreach ($items as $item) {
            if (! is_array($item) || count($item) < 2) {
                continue;
            }

            $key = $this->escapeInline((string) ($item[0] ?? ''));
            $valueTemplate = (string) ($item[1] ?? '');
            $value = $this->escapeInline($this->resolveTemplateString($valueTemplate, $context, $allowedPaths));

            $rows[] = '<div class="kv-row"><span class="kv-key">'.$key.'</span><span>'.$value.'</span></div>';
        }

        if ($rows === []) {
            return '';
        }

        return '<div class="block">'.implode('', $rows).'</div>';
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $allowedPaths
     */
    private function renderTableBlock(array $block, array $context, array $allowedPaths): string
    {
        $source = (string) ($block['source'] ?? '');
        if ($source === '' || ! str_starts_with($source, 'record.')) {
            throw new InvalidDynamicTemplateException('Table block source must start with record.');
        }

        if (! $this->isPathAllowed($source, $allowedPaths)) {
            throw new InvalidDynamicTemplateException("Table source [{$source}] is not allowed.");
        }

        $rows = data_get($context, $source);
        $columns = $block['columns'] ?? [];

        if (! is_array($rows) || ! is_array($columns) || $columns === []) {
            return '';
        }

        $headers = [];
        foreach ($columns as $column) {
            $headers[] = '<th>'.$this->escapeInline((string) $column).'</th>';
        }

        $bodyRows = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = [];
            foreach ($columns as $column) {
                $cells[] = '<td>'.$this->escapeInline((string) data_get($row, (string) $column, '')).'</td>';
            }

            $bodyRows[] = '<tr>'.implode('', $cells).'</tr>';
        }

        if ($bodyRows === []) {
            return '';
        }

        return '<div class="block"><table><thead><tr>'.implode('', $headers).'</tr></thead><tbody>'.implode('', $bodyRows).'</tbody></table></div>';
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $allowedPaths
     */
    private function resolveTemplateString(string $template, array $context, array $allowedPaths): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function (array $matches) use ($context, $allowedPaths) {
            $path = (string) ($matches[1] ?? '');

            if (! $this->isPathAllowed($path, $allowedPaths)) {
                throw new InvalidDynamicTemplateException("Placeholder [{$path}] is not allowed.");
            }

            $value = data_get($context, $path, '');

            if (is_array($value) || is_object($value)) {
                return '';
            }

            return (string) $value;
        }, $template);
    }

    /**
     * @return array<int, string>
     */
    private function buildAllowedPaths(DynamicSchemaData $schemaData, DynamicTemplateData $templateData): array
    {
        $allowed = [
            'tenant.name',
            'tenant.slug',
            'tenant.phone',
            'tenant.email',
            'tenant.address',
            'record.id',
            'record.status',
            'record.created_at',
            'record.updated_at',
        ];

        foreach ($schemaData->fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldKey = (string) ($field['field_key'] ?? '');
            if ($fieldKey !== '') {
                $allowed[] = 'record.'.$fieldKey;
            }
        }

        foreach ($templateData->placeholderWhitelist as $path) {
            if ($path !== '') {
                $allowed[] = $path;
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * @param  array<int, string>  $allowedPaths
     */
    private function isPathAllowed(string $path, array $allowedPaths): bool
    {
        if (in_array($path, $allowedPaths, true)) {
            return true;
        }

        foreach ($allowedPaths as $allowedPath) {
            if (str_ends_with($allowedPath, '.*') && str_starts_with($path, substr($allowedPath, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    private function escapeInline(string $text): string
    {
        return e($text);
    }

    private function assertRuntimeEnabled(?string $moduleKey): void
    {
        if (! $this->featureManager->isPrintRuntimeEnabled() || ! $this->featureManager->isModuleEnabled($moduleKey)) {
            throw new InvalidDynamicTemplateException("Dynamic print runtime is disabled for module [{$moduleKey}].");
        }
    }
}
