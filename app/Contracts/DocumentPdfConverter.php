<?php

namespace App\Contracts;

interface DocumentPdfConverter
{
    /**
     * Convert a full HTML document into PDF binary contents.
     */
    public function convert(string $html): string;
}
