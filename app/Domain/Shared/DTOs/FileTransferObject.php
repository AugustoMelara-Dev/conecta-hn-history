<?php

declare(strict_types=1);

namespace App\Domain\Shared\DTOs;

use Illuminate\Http\UploadedFile;

/**
 * FileTransferObject - HTTP-Agnostic File Representation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL COMPLIANCE: No Illuminate\Http\UploadedFile references.      ║
 * ║  This DTO abstracts file uploads from the HTTP layer.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Controllers are responsible for mapping UploadedFile → FileTransferObject.
 */
final readonly class FileTransferObject
{
    public function __construct(
        /** Absolute path to the temporary file */
        public string $tempPath,

        /** Original filename with extension */
        public string $originalName,

        /** MIME type (image/png, image/jpeg, etc.) */
        public string $mimeType,

        /** File size in bytes */
        public int $size,
    ) {}

    /**
     * Get file extension from original name.
     */
    public function getExtension(): string
    {
        return pathinfo($this->originalName, PATHINFO_EXTENSION);
    }

    /**
     * Check if file is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    /**
     * Create from Laravel UploadedFile (Controller layer only).
     *
     * @param  UploadedFile  $uploadedFile
     */
    public static function fromUploadedFile(object $uploadedFile): self
    {
        return new self(
            tempPath: $uploadedFile->getRealPath(),
            originalName: $uploadedFile->getClientOriginalName(),
            mimeType: $uploadedFile->getMimeType(),
            size: $uploadedFile->getSize(),
        );
    }
}
