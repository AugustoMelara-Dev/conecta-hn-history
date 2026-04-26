<?php

declare(strict_types=1);

namespace App\Services\DynamicForms\Validation;

use App\Services\DynamicForms\Exceptions\InvalidDynamicTemplateException;

final class FieldHookPolicyValidator
{
    /**
     * @param  array<string, mixed>  $field
     */
    public function validate(array $field): void
    {
        $hooks = $field['hooks_json'] ?? [];
        if (! is_array($hooks) || $hooks === []) {
            return;
        }

        $allowedActions = config('dynamic-forms.allowed_hook_actions', []);
        if (! is_array($allowedActions)) {
            $allowedActions = [];
        }

        foreach ($hooks as $hook) {
            if (! is_array($hook)) {
                throw new InvalidDynamicTemplateException('Invalid field hook format. Each hook must be an object.');
            }

            $action = (string) ($hook['action'] ?? '');
            if ($action === '' || ! in_array($action, $allowedActions, true)) {
                throw new InvalidDynamicTemplateException("Hook action [{$action}] is not allowed.");
            }
        }
    }
}

