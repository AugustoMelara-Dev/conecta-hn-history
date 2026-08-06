<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ToolPolicy - Alias/legacy de AutomationToolPolicy.
 */
class ToolPolicy extends AutomationToolPolicy
{
    protected $table = 'automation_tool_policies';
}
