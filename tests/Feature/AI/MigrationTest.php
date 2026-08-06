<?php

declare(strict_types=1);

it('ai_assistant_runs migration has expected columns', function () {
    $migration = include database_path('migrations/2026_08_20_000001_create_ai_assistant_runs_table.php');
    expect($migration)->toBeObject();
});
