<?php

namespace App\Services\JobParsing;

class JobDescriptionCleaner
{
    public function clean(string $htmlOrText): string
    {
        $text = strip_tags($htmlOrText);
        $text = preg_replace('/\s+/', ' ', $text ?? '');

        return trim($text ?? '');
    }
}
