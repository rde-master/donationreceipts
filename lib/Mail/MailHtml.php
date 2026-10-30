<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Mail;

/**
 * HTML mail bodies from the WYSIWYG editor:
 *  - sanitize(): keeps only an allow-list of tags and attributes (what the
 *    editor produces), everything else is dropped or unwrapped;
 *  - render(): fills {placeholders} with HTML-escaped values; link
 *    placeholders become links or buttons;
 *  - toText(): the plain-text alternative sent along with every HTML mail;
 *  - fromStored(): templates saved as plain text before 0.9.0 become HTML.
 */
class MailHtml {
    /** Placeholders holding a URL: rendered as link, and usable as link target. */
    public const LINK_KEYS = ['link', 'ordnerlink'];
    /** Button placeholders and the URL placeholder and caption they use. */
    public const BUTTONS = [
        'linkknopf' => ['link', 'Zum Rückmeldeformular'],
        'ordnerknopf' => ['ordnerlink', 'Zu Ihren Zuwendungsbestätigungen'],
    ];

    /** Path of uploaded mail images; the id is captured. */
    public const IMAGE_PATTERN = '#/apps/donationreceipts/api/mail-images/([a-f0-9]{32})$#';

    private const BLOCKS = ['p', 'h1', 'h2', 'h3', 'blockquote', 'ul', 'ol', 'li'];
    private const INLINE = ['strong', 'b', 'em', 'i', 'u', 's', 'a', 'br', 'img'];
    /** Dropped together with their content. */
    private const DROP = ['script', 'style', 'head', 'title', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form'];
    private const ALIGN = ['left', 'center', 'right', 'justify'];

    /**
     * Templates stored as plain text (and the text defaults) become HTML:
     * blank lines separate paragraphs, single line breaks stay.
     */
    public static function fromStored(string $value): string {
        if (preg_match('/<(p|br|h[1-3]|ul|ol|blockquote|div|strong|em|a|img)\b/i', $value) === 1) {
            return $value;
        }
        $paragraphs = preg_split('/\R{2,}/u', trim($value)) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) === '') {
                continue;
            }
            $html .= '<p>' . preg_replace('/\R/u', '<br>', htmlspecialchars($paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</p>';
        }
        return $html;
    }

    /**
     * Only what the editor can produce survives: block and inline tags,
     * text-align, links to http(s)/mailto or a link placeholder, and
     * uploaded images.
     */
    public static function sanitize(string $html): string {
        if (trim($html) === '') {
            return '';
        }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $doc->getElementsByTagName('body')->item(0);
        return $body === null ? '' : self::children($body);
    }

    /**
     * Fills the placeholders of a sanitized template. Values are escaped
     * (line breaks kept); {link} etc. become links, {linkknopf} etc. buttons,
     * href="{link}" the URL itself. Unknown placeholders stay.
     *
     * @param array<string, string> $values
     */
    public static function render(string $html, array $values): string {
        $html = preg_replace_callback('/href="\{([a-z]+)\}"/', static function (array $m) use ($values): string {
            return in_array($m[1], self::LINK_KEYS, true) && isset($values[$m[1]])
                ? 'href="' . self::escape($values[$m[1]]) . '"'
                : $m[0];
        }, $html) ?? $html;

        return preg_replace_callback('/\{([a-z]+)\}/', static function (array $m) use ($values): string {
            $key = $m[1];
            if (isset(self::BUTTONS[$key])) {
                [$linkKey, $caption] = self::BUTTONS[$key];
                if (!isset($values[$linkKey])) {
                    return $m[0];
                }
                return '<a href="' . self::escape($values[$linkKey]) . '" style="display:inline-block;padding:10px 20px;'
                    . 'background:#0082c9;color:#ffffff;border-radius:6px;text-decoration:none;font-weight:bold">'
                    . self::escape($caption) . '</a>';
            }
            if (!isset($values[$key])) {
                return $m[0];
            }
            if (in_array($key, self::LINK_KEYS, true)) {
                return '<a href="' . self::escape($values[$key]) . '">' . self::escape($values[$key]) . '</a>';
            }
            return str_replace(["\r\n", "\n", "\r"], '<br>', self::escape($values[$key]));
        }, $html) ?? $html;
    }

    /**
     * Complete HTML document around a body fragment, with simple inline
     * styles that mail programs understand.
     */
    public static function document(string $body): string {
        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="margin:0;padding:16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#222222">'
            . '<div style="max-width:640px">'
            . preg_replace('/<img /', '<img style="max-width:100%;height:auto" ', $body)
            . '</div></body></html>';
    }

    /**
     * Plain-text alternative: paragraphs separated by blank lines, list
     * items with "- ", links as "Text (URL)".
     */
    public static function toText(string $html): string {
        if (trim($html) === '') {
            return '';
        }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $doc->getElementsByTagName('body')->item(0);
        $text = $body === null ? '' : self::text($body);
        $text = preg_replace('/[ \t]*\n[ \t]*/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    private static function escape(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function children(\DOMNode $node): string {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::node($child);
        }
        return $out;
    }

    private static function node(\DOMNode $node): string {
        if ($node instanceof \DOMText) {
            return self::escape($node->data);
        }
        if (!$node instanceof \DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DROP, true)) {
            return '';
        }
        if (!in_array($tag, self::BLOCKS, true) && !in_array($tag, self::INLINE, true)) {
            // unknown wrapper (div, span, font, ...): keep the content
            return self::children($node);
        }
        if ($tag === 'br') {
            return '<br>';
        }
        if ($tag === 'img') {
            $src = $node->getAttribute('src');
            if (preg_match(self::IMAGE_PATTERN, $src) !== 1 || preg_match('#^[/a-zA-Z0-9._-]+$#', $src) !== 1) {
                return '';
            }
            $attrs = ' src="' . self::escape($src) . '" alt="' . self::escape($node->getAttribute('alt')) . '"';
            $width = $node->getAttribute('width');
            if (preg_match('/^\d{1,4}$/', $width) === 1) {
                $attrs .= ' width="' . $width . '"';
            }
            return '<img' . $attrs . '>';
        }

        $attrs = '';
        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            $placeholder = preg_match('/^\{([a-z]+)\}$/', $href, $m) === 1 && in_array($m[1], self::LINK_KEYS, true);
            if (!$placeholder && preg_match('#^(https?://|mailto:)#i', $href) !== 1) {
                return self::children($node);
            }
            $attrs = ' href="' . self::escape($href) . '"';
        }
        if (in_array($tag, self::BLOCKS, true)
            && preg_match('/text-align:\s*(' . implode('|', self::ALIGN) . ')/i', $node->getAttribute('style'), $m) === 1) {
            $attrs .= ' style="text-align: ' . strtolower($m[1]) . '"';
        }
        return '<' . $tag . $attrs . '>' . self::children($node) . '</' . $tag . '>';
    }

    private static function text(\DOMNode $node): string {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $out .= preg_replace('/\s+/u', ' ', $child->data);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            $inner = self::text($child);
            switch ($tag) {
                case 'br':
                    $out .= "\n";
                    break;
                case 'p':
                case 'h1':
                case 'h2':
                case 'h3':
                case 'blockquote':
                case 'div':
                    $out .= "\n\n" . trim($inner) . "\n\n";
                    break;
                case 'ul':
                case 'ol':
                    $out .= "\n" . $inner . "\n";
                    break;
                case 'li':
                    $out .= "\n- " . trim($inner);
                    break;
                case 'a':
                    $href = $child->getAttribute('href');
                    $label = trim($inner);
                    $out .= $label === '' || $label === $href || preg_match('#^mailto:#i', $href) === 1 ? ($label !== '' ? $label : $href) : $label . ' (' . $href . ')';
                    break;
                case 'img':
                    $out .= $child->getAttribute('alt');
                    break;
                default:
                    $out .= $inner;
            }
        }
        return $out;
    }
}
