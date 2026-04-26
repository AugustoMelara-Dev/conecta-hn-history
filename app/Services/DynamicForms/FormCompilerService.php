<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicFormCompilerInterface;
use App\Application\DynamicForms\Data\DynamicSchemaData;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class FormCompilerService implements DynamicFormCompilerInterface
{
    public function __construct(
        private readonly ConditionEvaluator $conditionEvaluator
    ) {}

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, Component>
     */
    public function buildFilamentSchema(array $schema, string $statePrefix = 'payload_json'): array
    {
        $fields = collect($schema['fields'] ?? [])
            ->filter(static fn ($field): bool => is_array($field) && ($field['status'] ?? 'draft') !== 'archived')
            ->sortBy('sort_order')
            ->values();

        /** @var array<string, Component> $componentsByKey */
        $componentsByKey = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['field_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $componentsByKey[$key] = $this->buildFieldComponent($field, $statePrefix);
        }

        $layout = collect($schema['layouts'] ?? [])
            ->first(static fn ($item): bool => is_array($item) && ($item['layout_key'] ?? null) === 'form');

        if (! is_array($layout)) {
            return array_values($componentsByKey);
        }

        $layoutJson = $layout['layout_json'] ?? null;
        if (! is_array($layoutJson)) {
            return array_values($componentsByKey);
        }

        $sections = Arr::get($layoutJson, 'sections');
        if (! is_array($sections) || $sections === []) {
            $fieldOrder = Arr::get($layoutJson, 'field_order', []);
            if (! is_array($fieldOrder) || $fieldOrder === []) {
                return array_values($componentsByKey);
            }

            return collect($fieldOrder)
                ->map(static fn ($fieldKey) => $componentsByKey[(string) $fieldKey] ?? null)
                ->filter(static fn ($component): bool => $component instanceof Component)
                ->values()
                ->all();
        }

        $resolvedSections = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $fieldKeys = $section['fields'] ?? [];
            if (! is_array($fieldKeys) || $fieldKeys === []) {
                continue;
            }

            $sectionComponents = collect($fieldKeys)
                ->map(static fn ($fieldKey) => $componentsByKey[(string) $fieldKey] ?? null)
                ->filter(static fn ($component): bool => $component instanceof Component)
                ->values()
                ->all();

            if ($sectionComponents === []) {
                continue;
            }

            $resolvedSections[] = Section::make((string) ($section['title'] ?? 'Seccion'))
                ->description((string) ($section['description'] ?? ''))
                ->columns((int) ($section['columns'] ?? 1))
                ->schema($sectionComponents);
        }

        return $resolvedSections !== [] ? $resolvedSections : array_values($componentsByKey);
    }

    /**
     * @return array<int, Component>
     */
    public function compileFormSchema(DynamicSchemaData $schema, string $statePrefix = 'payload_json'): array
    {
        return $this->buildFilamentSchema($schema->toArray(), $statePrefix);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function compileValidationRules(array $schema, array $payload = []): array
    {
        /** @var array<string, array<int, string>> $rules */
        $rules = [];

        foreach ($schema['fields'] ?? [] as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['field_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $fieldRules = ['nullable'];
            if ((bool) ($field['is_required'] ?? false)) {
                $fieldRules = ['required'];
            }

            $conditions = $field['conditions_json'] ?? null;
            if (is_array($conditions) && isset($conditions['required_if']) && is_array($conditions['required_if'])) {
                $requiredIf = $this->conditionEvaluator->evaluate($conditions['required_if'], $payload);
                if ($requiredIf) {
                    $fieldRules = ['required'];
                }
            }

            $customRules = $field['validation_rules'] ?? [];
            if (is_array($customRules)) {
                foreach ($customRules as $customRule) {
                    if (is_string($customRule) && $customRule !== '') {
                        $fieldRules[] = $customRule;
                    }
                }
            }

            $rules[$key] = array_values(array_unique($fieldRules));
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function compileValidationRulesFromData(DynamicSchemaData $schema, array $payload = []): array
    {
        return $this->compileValidationRules($schema->toArray(), $payload);
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function buildFieldComponent(array $field, string $statePrefix): Component
    {
        $fieldType = (string) ($field['field_type'] ?? 'text');
        $fieldKey = (string) ($field['field_key'] ?? 'field');
        $statePath = "{$statePrefix}.{$fieldKey}";

        $component = match ($fieldType) {
            'textarea' => Textarea::make($statePath),
            'number' => TextInput::make($statePath)->numeric(),
            'money' => TextInput::make($statePath)->numeric()->prefix('L'),
            'date' => DatePicker::make($statePath),
            'datetime' => DateTimePicker::make($statePath)->seconds(false),
            'select' => Select::make($statePath)->options($this->toSelectOptions($field))->native(false),
            'multiselect' => Select::make($statePath)->options($this->toSelectOptions($field))->multiple()->native(false),
            'boolean' => Toggle::make($statePath),
            'file' => FileUpload::make($statePath)->directory('dynamic-fields'),
            'signature' => Textarea::make($statePath)->rows(3),
            'richtext' => RichEditor::make($statePath),
            default => TextInput::make($statePath),
        };

        $component = $component
            ->label((string) ($field['label'] ?? $fieldKey))
            ->placeholder((string) ($field['placeholder'] ?? ''))
            ->helperText((string) ($field['help_text'] ?? ''));

        if ((bool) ($field['is_required'] ?? false)) {
            $component = $component->required();
        }

        $conditions = $field['conditions_json'] ?? [];
        if (is_array($conditions) && isset($conditions['show_if']) && is_array($conditions['show_if'])) {
            $component = $component->visible(function (Get $get) use ($conditions, $statePrefix): bool {
                $payload = $this->snapshotPayload($get, $statePrefix);

                return $this->conditionEvaluator->evaluate($conditions['show_if'], $payload);
            });
        }

        if (is_array($conditions) && isset($conditions['readonly_if']) && is_array($conditions['readonly_if'])) {
            $component = $component->disabled(function (Get $get) use ($conditions, $statePrefix): bool {
                $payload = $this->snapshotPayload($get, $statePrefix);

                return $this->conditionEvaluator->evaluate($conditions['readonly_if'], $payload);
            });
        }

        $defaultValue = $field['default_value'] ?? null;
        if ($defaultValue !== null) {
            $component = $component->default($defaultValue);
        }

        return $component;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, string>
     */
    private function toSelectOptions(array $field): array
    {
        $options = $field['options_json'] ?? [];
        if (! is_array($options)) {
            return [];
        }

        /** @var array<string, string> $normalized */
        $normalized = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                $value = (string) ($option['value'] ?? '');
                $label = (string) ($option['label'] ?? $value);
                if ($value !== '') {
                    $normalized[$value] = $label;
                }
                continue;
            }

            if (is_string($option) && $option !== '') {
                $normalized[$option] = $option;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPayload(Get $get, string $statePrefix): array
    {
        $state = $get($statePrefix);

        return is_array($state) ? $state : [];
    }
}
