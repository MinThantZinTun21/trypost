<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;

class ContentSanitizer
{
    /**
     * The editor stores HTML; every kept platform publishes plain text.
     */
    public function sanitize(string $content, Platform $platform): string
    {
        return $this->stripHtml($content);
    }

    /**
     * The content as a reader will see it, which is what a character limit applies
     * to. Every kept platform publishes plain text, so the sanitized form is
     * literally what gets posted and counts as-is.
     */
    public function displayText(string $content, Platform $platform): string
    {
        return $this->sanitize($content, $platform);
    }

    private function stripHtml(string $content): string
    {
        // Convert <p> tags to newlines
        $content = preg_replace('/<p[^>]*>/i', '', $content);
        $content = str_replace('</p>', "\n", $content);

        // Convert <br> to newlines
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);

        // Convert list items to dash prefix
        $content = preg_replace('/<li[^>]*>/i', '- ', $content);
        $content = str_replace('</li>', "\n", $content);

        // Strip remaining HTML tags
        $content = strip_tags($content);

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up excessive newlines (max 2 consecutive)
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        return trim($content);
    }
}
