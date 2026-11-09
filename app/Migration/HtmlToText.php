<?php

namespace App\Migration;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Converts WordPress post HTML into the plain text the public pages render.
 *
 * This is a hard requirement of the migration, not a cosmetic step: the News,
 * Project, Event and Value-chain detail pages render their body as escaped
 * text split on blank lines. Writing raw WXR HTML into those columns would
 * print literal `<div class="elementor-...">` markup on the public site.
 *
 * Rules:
 *  - script/style/noscript/iframe/media tags are removed entirely;
 *  - images are dropped (they become media records, never prose);
 *  - block elements become paragraph breaks, <br> becomes a line break;
 *  - headings keep their text on their own paragraph;
 *  - list items keep a bullet or a number;
 *  - table cells stay distinguishable within a row;
 *  - links keep their text and never leak a raw tag or a URL;
 *  - entities are decoded, blank-line runs collapsed, result trimmed.
 *
 * The DOM path handles ordinary and mildly malformed HTML; a regex fallback
 * keeps the conversion total when even DOMDocument cannot load the fragment.
 */
final class HtmlToText
{
    /** Elements whose contents are never prose. */
    private const SKIP = [
        'script', 'style', 'noscript', 'template', 'head', 'title', 'meta', 'link',
        'svg', 'iframe', 'object', 'embed', 'applet', 'canvas',
        'img', 'picture', 'source', 'video', 'audio', 'track',
        'form', 'input', 'button', 'select', 'option', 'textarea', 'label',
    ];

    /** Elements that start a new paragraph. */
    private const BLOCK = [
        'address', 'article', 'aside', 'blockquote', 'body', 'dd', 'div', 'dl', 'dt',
        'fieldset', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'table',
        'tbody', 'tfoot', 'thead', 'tr', 'ul',
    ];

    /**
     * @param  string|null  $html  Raw `content:encoded` (or description) HTML.
     */
    public function convert(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $text = $this->hasDom()
            ? $this->convertWithDom($html)
            : $this->convertWithoutDom($html);

        return $this->normalise($text);
    }

    private function hasDom(): bool
    {
        return class_exists(DOMDocument::class);
    }

    private function convertWithDom(string $html): string
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        // Encode non-ASCII as numeric entities first: DOMDocument otherwise
        // guesses ISO-8859-1 and mangles en-dashes and curly quotes.
        $encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

        $loaded = $document->loadHTML(
            '<!DOCTYPE html><html><body>'.$encoded.'</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $loaded ? $document->getElementsByTagName('body')->item(0) : null;

        if ($body === null) {
            return $this->convertWithoutDom($html);
        }

        return $this->renderChildren($body);
    }

    private function renderChildren(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= $this->renderNode($child);
        }

        return $text;
    }

    private function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            // Keep the text exactly as authored; spacing is normalised later.
            return str_replace("\u{00A0}", ' ', $node->nodeValue ?? '');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::SKIP, true)) {
            return '';
        }

        if ($tag === 'br') {
            return "\n";
        }

        if ($tag === 'td' || $tag === 'th') {
            $cell = $this->normalise($this->renderChildren($node));

            return $cell === '' ? '' : $cell.' | ';
        }

        if ($tag === 'li') {
            $item = $this->normalise($this->renderChildren($node));

            // No trailing newline: consecutive items must stay adjacent lines,
            // not become separate paragraphs.
            return $item === '' ? '' : "\n".$this->listMarker($node).$item;
        }

        $inner = $this->renderChildren($node);

        if (in_array($tag, self::BLOCK, true)) {
            return "\n\n".$inner."\n\n";
        }

        return $inner;
    }

    /**
     * "• " for unordered lists, "1. " for ordered ones.
     */
    private function listMarker(DOMElement $item): string
    {
        $parent = $item->parentNode;

        if (! $parent instanceof DOMElement || strtolower($parent->nodeName) !== 'ol') {
            return '• ';
        }

        $position = 0;

        foreach ($parent->childNodes as $sibling) {
            if ($sibling instanceof DOMElement && strtolower($sibling->nodeName) === 'li') {
                $position++;

                if ($sibling->isSameNode($item)) {
                    break;
                }
            }
        }

        return max(1, $position).'. ';
    }

    /**
     * Last-resort converter for fragments DOMDocument refuses to load.
     */
    private function convertWithoutDom(string $html): string
    {
        $text = preg_replace(
            '#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is',
            '',
            $html,
        ) ?? $html;

        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<li\b[^>]*>#i', "\n• ", $text) ?? $text;
        $text = preg_replace(
            '#</(?:p|div|h[1-6]|li|tr|blockquote|section|article|table|ul|ol|dd|dt)>#i',
            "\n\n",
            $text,
        ) ?? $text;

        $text = strip_tags($text);

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function normalise(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}"], [' ', '', ''], $text);

        // Tabs and runs of spaces collapse; newlines are structure, not spacing.
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        // Drop the trailing table-cell separator left by renderNode().
        $text = preg_replace('/ \|$/mu', '', $text) ?? $text;

        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
