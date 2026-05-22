<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Directory\EloquentDirectoryGuideReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DirectoryGuideChatController extends Controller
{
    public function __construct(
        private readonly EloquentDirectoryGuideReader $reader,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:500'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $message = (string) $validated['message'];
        $budget = isset($validated['budget'])
            ? (float) $validated['budget']
            : $this->extractBudget($message);

        return response()->json($this->reader->answer($message, $budget));
    }

    private function extractBudget(string $message): ?float
    {
        if (! preg_match('/(?:l\.?|lempiras?|hnl)?\s*(\d+(?:[.,]\d{1,2})?)/i', $message, $matches)) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[1]);
    }
}
