<?php

declare(strict_types=1);

namespace App\Models;

/**
 * ToolPolicy - Alias/legacy de AutomationToolPolicy.
 */
class ToolPolicy extends AutomationToolPolicy
{
    protected $table = 'automation_tool_policies';
}
