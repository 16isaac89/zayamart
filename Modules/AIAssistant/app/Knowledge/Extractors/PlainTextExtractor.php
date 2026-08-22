<?php

namespace Modules\AIAssistant\app\Knowledge\Extractors;

use Modules\AIAssistant\app\Contracts\TextExtractorInterface;

class PlainTextExtractor implements TextExtractorInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'txt';
    }

    public function extract(string $absolutePath): string
    {
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new \RuntimeException('Could not read this text file.');
        }

        return $content;
    }
}
