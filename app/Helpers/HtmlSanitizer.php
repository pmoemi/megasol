<?php

namespace App\Helpers;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
    protected static ?HTMLPurifier $purifier = null;

    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return static::purifier()->purify($html);
    }

    protected static function purifier(): HTMLPurifier
    {
        if (static::$purifier !== null) {
            return static::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', storage_path('app/htmlpurifier'));
        $config->set('HTML.Allowed', implode(',', [
            'p[style]',
            'br',
            'span[style]',
            'div[style]',
            'strong',
            'em',
            'u',
            's',
            'a[href|title|target|style]',
            'ul[style]',
            'ol[style]',
            'li[style]',
            'h1[style]',
            'h2[style]',
            'h3[style]',
            'h4[style]',
            'h5[style]',
            'h6[style]',
            'img[src|alt|width|height|style]',
            'table[style]',
            'thead',
            'tbody',
            'tr',
            'td[style]',
            'th[style]',
            'blockquote[style]',
        ]));
        $config->set('CSS.AllowedProperties', [
            'color', 'background-color', 'text-align', 'font-size', 'font-weight',
            'font-style', 'text-decoration', 'padding', 'margin', 'border',
            'width', 'height', 'line-height',
        ]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        return static::$purifier = new HTMLPurifier($config);
    }
}
