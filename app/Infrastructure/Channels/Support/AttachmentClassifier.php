<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Support;

use App\Application\Channels\ChannelAttachmentPolicy;

final class AttachmentClassifier
{
    /**
     * @param  list<array<string, mixed>>  $attachments
     * @return list<array<string, mixed>>
     */
    public static function classify(array $attachments): array
    {
        return ChannelAttachmentPolicy::canonicalize($attachments);
    }
}
