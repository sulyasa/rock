<?php

declare(strict_types=1);

namespace App\Services;

class BadWordFilter
{
    /**
     * Russian and English inappropriate words list (dictionary).
     *
     * @var array<int, string>
     */
    private array $badWords = [
        'хуй', 'пизда', 'ебать', 'сука', 'бля', 'мудак', 'гандон', 'член',
        'ebat', 'suka', 'blyad', 'fuck', 'shit', 'asshole', 'bitch'
    ];

    /**
     * Filter bad words in the message and replace them with asterisks.
     *
     * @param string $text The input message text
     * @return string Censored message text
     */
    public function clean(string $text): string
    {
        $cleaned = $text;

        foreach ($this->badWords as $badWord) {
            // Case-insensitive match with boundary checks
            $pattern = '/\b' . preg_quote($badWord, '/') . '\b/iu';
            
            // For languages without space boundaries (like Russian sometimes), or simple replacement:
            $cleaned = preg_replace_callback($pattern, function (array $matches): string {
                return str_repeat('*', mb_strlen($matches[0]));
            }, $cleaned);

            // Also check for sub-string occurrences for Russian bad words as they are often concatenated
            if (mb_strpos(mb_strtolower($text), $badWord) !== false) {
                $patternSub = '/' . preg_quote($badWord, '/') . '/iu';
                $cleaned = preg_replace_callback($patternSub, function (array $matches): string {
                    return str_repeat('*', mb_strlen($matches[0]));
                }, $cleaned);
            }
        }

        return $cleaned;
    }
}
