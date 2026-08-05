<?php

declare(strict_types=1);

namespace Tests\Fakes\AI;

use App\Application\Ai\Contracts\AiProvider;
use App\Application\Ai\DTOs\AiProviderRequest;
use App\Application\Ai\DTOs\AiProviderResult;
use Closure;
use RuntimeException;
use Throwable;

final class FakeAiProvider implements AiProvider
{
    /** @var list<AiProviderRequest> */
    public array $requests = [];

    /** @var list<AiProviderResult|Throwable> */
    private array $outcomes = [];

    private ?Closure $beforeGenerate = null;

    public function __construct(private readonly string $providerName = 'fake') {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function queueResult(AiProviderResult $result): void
    {
        $this->outcomes[] = $result;
    }

    public function queueFailure(Throwable $failure): void
    {
        $this->outcomes[] = $failure;
    }

    public function beforeGenerate(callable $callback): void
    {
        $this->beforeGenerate = Closure::fromCallable($callback);
    }

    public function generate(AiProviderRequest $request): AiProviderResult
    {
        $this->requests[] = $request;
        ($this->beforeGenerate ?? static fn (): null => null)();
        $outcome = array_shift($this->outcomes);

        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        if (! $outcome instanceof AiProviderResult) {
            throw new RuntimeException('Fake AI provider has no queued outcome.');
        }

        return $outcome;
    }
}
