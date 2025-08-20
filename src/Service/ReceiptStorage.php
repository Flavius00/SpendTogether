<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ReceiptStorage
{
    public function __construct(private readonly string $targetDirectory)
    {
    }

    public function store(UploadedFile $file, ?string $existingFilename = null): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBase = preg_replace('~[^a-zA-Z0-9_-]+~', '-', $originalName) ?: 'receipt';
        $ext = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $newFilename = sprintf('%s-%s.%s', $safeBase, bin2hex(random_bytes(6)), $ext);

        $file->move($this->targetDirectory, $newFilename);

        if ($existingFilename && $existingFilename !== $newFilename) {
            $this->remove($existingFilename);
        }

        return $newFilename;
    }

    public function remove(?string $filename): void
    {
        if (!$filename) {
            return;
        }
        $path = $this->path($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function path(string $filename): string
    {
        return rtrim($this->targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    }
}
