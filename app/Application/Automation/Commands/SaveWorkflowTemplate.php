<?php

declare(strict_types=1);

namespace App\Application\Automation\Commands;

use App\Models\AutomationWorkflowDefinition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SaveWorkflowTemplate - Guarda un template en el catálogo.
 */
class SaveWorkflowTemplate extends Command
{
    protected $signature = 'workflows:save-template {name} {slug} {--category=general}';

    protected $description = 'Save a workflow as a reusable template';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->argument('slug');
        $category = $this->option('category');

        AutomationWorkflowDefinition::create([
            'name' => $name,
            'slug' => $slug,
            'category' => $category,
            'tenant_id' => null, // Global template
            'is_active' => false,
            'status' => 'draft',
        ]);

        $this->info("Template {$slug} saved successfully.");
        return self::SUCCESS;
    }
}
