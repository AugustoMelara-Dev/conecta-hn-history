<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;

class EmailReplyAssistant extends BaseAssistant
{
    public function key(): string
    {
        return 'email_reply';
    }

    public function name(): string
    {
        return 'Email Reply Draft';
    }

    public function description(): string
    {
        return 'Genera reply a mensaje de inbox.';
    }

    public function estimateCost(): float
    {
        return 0.04;
    }

    public function category(): string
    {
        return 'Communications';
    }

    public function execute(array $input): AssistantResult
    {
        if (! isset($input['inbox_message_id'])) {
            return AssistantResult::failure('Missing inbox_message_id');
        }

        return $this->mockOutput($input, [
            'reply_subject' => 'Re: Tu consulta',
            'reply_body' => 'Gracias por tu mensaje. Te respondo a continuaci�n...',
            'requires_review' => true,
        ]);
    }
}
