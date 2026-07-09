<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class EmailHtmlSanitizerService
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        // Received emails are rendered inside the app UI, not as a standalone
        // document: no scripts, forms, embeds, or external frames.
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'hr', 'span', 'div', 'a[href]',
            'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'small', 'sub', 'sup', 'blockquote',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'td[colspan|rowspan]', 'th[colspan|rowspan]',
            'img[src|alt|width|height]',
            'font[color|size|face]',
        ]));
        $config->set('HTML.AllowedAttributes', 'a.href,td.colspan,td.rowspan,th.colspan,th.rowspan,img.src,img.alt,img.width,img.height,font.color,font.size,font.face');
        $config->set('CSS.AllowedProperties', 'color,background-color,font-size,font-weight,font-style,text-align,text-decoration');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('URI.DisableExternalResources', true);
        $config->set('HTML.TargetBlank', true);

        $this->purifier = new HTMLPurifier($config);
    }

    public function sanitize(string $html): string
    {
        return $this->purifier->purify($html);
    }
}
