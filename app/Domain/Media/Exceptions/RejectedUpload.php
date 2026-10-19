<?php

namespace App\Domain\Media\Exceptions;

use RuntimeException;

/**
 * An upload the media library refuses. The reason is a translation key under
 * `media.rejected.*`, shown to the person uploading.
 */
final class RejectedUpload extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $replace
     */
    private function __construct(public readonly string $reason, public readonly array $replace = [])
    {
        parent::__construct("Upload rejected: {$reason}");
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function because(string $reason, array $replace = []): self
    {
        return new self($reason, $replace);
    }

    public function userMessage(?string $fileName = null): string
    {
        $message = __("media.rejected.{$this->reason}", $this->replace);

        return $fileName === null ? $message : __('media.rejected.file', ['file' => $fileName, 'reason' => $message]);
    }
}
