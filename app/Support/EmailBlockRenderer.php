<?php

namespace App\Support;

class EmailBlockRenderer
{
    /**
     * Compile a blocks array into responsive, email-client-compatible HTML.
     *
     * Single source of truth for block→HTML compilation, shared by the
     * EmailBuilder (live preview + "use in campaign") and the template
     * catalog seeders (which need a `body_html` value at seed time).
     * Uses TABLE-based layout with inline styles for maximum compatibility.
     *
     * @param  array<int, array{type?: string, data?: array<string, mixed>}>  $blocks
     */
    public static function compile(array $blocks): string
    {
        $rows = '';
        foreach ($blocks as $block) {
            $rows .= self::compileBlock($block);
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Email</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .fluid { max-width: 100% !important; height: auto !important; margin-left: auto !important; margin-right: auto !important; }
            .stack-column { display: block !important; width: 100% !important; max-width: 100% !important; direction: ltr !important; }
            .stack-column-center { text-align: center !important; }
            .center-on-narrow { text-align: center !important; display: block !important; margin-left: auto !important; margin-right: auto !important; float: none !important; }
            table.center-on-narrow { display: inline-block !important; }
            .mobile-padding { padding-left: 16px !important; padding-right: 16px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#F3F4F6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
    <center style="width:100%;background-color:#F3F4F6;">
        <!--[if mso]>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center"><tr><td>
        <![endif]-->
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:600px;margin:0 auto;" class="email-container">
            {$rows}
        </table>
        <!--[if mso]>
        </td></tr></table>
        <![endif]-->
    </center>
</body>
</html>
HTML;
    }

    /**
     * Compile a single block to its HTML table-row representation.
     *
     * @param  array{type?: string, data?: array<string, mixed>}  $block
     */
    protected static function compileBlock(array $block): string
    {
        $type = $block['type'] ?? '';
        $data = $block['data'] ?? [];

        return match ($type) {
            'header' => self::compileHeader($data),
            'text' => self::compileText($data),
            'image' => self::compileImage($data),
            'button' => self::compileButton($data),
            'divider' => self::compileDivider($data),
            'columns' => self::compileColumns($data),
            'spacer' => self::compileSpacer($data),
            'social' => self::compileSocial($data),
            'footer' => self::compileFooter($data),
            default => '',
        };
    }

    protected static function compileHeader(array $data): string
    {
        $bgColor = e($data['bg_color'] ?? '#4F46E5');
        $companyName = e($data['company_name'] ?? '');
        $logoUrl = e($data['logo_url'] ?? '');

        $logoHtml = '';
        if (! empty($logoUrl)) {
            $logoHtml = '<img src="' . $logoUrl . '" alt="' . $companyName . '" width="40" height="40" style="display:inline-block;vertical-align:middle;margin-right:12px;border-radius:8px;">';
        }

        return <<<HTML
<tr>
    <td style="background-color:{$bgColor};padding:24px 32px;text-align:center;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="text-align:center;font-size:20px;font-weight:700;color:#FFFFFF;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                    {$logoHtml}{$companyName}
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected static function compileText(array $data): string
    {
        $align = e($data['align'] ?? 'left');
        $fontSize = intval($data['font_size'] ?? 16);
        $content = $data['content'] ?? '';

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:24px 32px;text-align:{$align};font-size:{$fontSize}px;line-height:1.6;color:#1E293B;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;" class="mobile-padding">
        {$content}
    </td>
</tr>
HTML;
    }

    protected static function compileImage(array $data): string
    {
        $src = e($data['src'] ?? '');
        $alt = e($data['alt'] ?? '');
        $width = intval($data['width'] ?? 100);
        $linkUrl = e($data['link_url'] ?? '');

        $widthPx = intval(600 * $width / 100);
        $widthStyle = $width < 100 ? "width:{$widthPx}px;max-width:{$width}%;" : 'width:100%;';

        $imgTag = '<img src="' . $src . '" alt="' . $alt . '" style="' . $widthStyle . 'height:auto;display:block;margin:0 auto;border:0;" class="fluid">';

        if (! empty($linkUrl)) {
            $imgTag = '<a href="' . $linkUrl . '" target="_blank" style="text-decoration:none;">' . $imgTag . '</a>';
        }

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:0;text-align:center;">
        {$imgTag}
    </td>
</tr>
HTML;
    }

    protected static function compileButton(array $data): string
    {
        $text = e($data['text'] ?? 'Click Here');
        $url = e($data['url'] ?? '#');
        $bgColor = e($data['bg_color'] ?? '#4F46E5');
        $textColor = e($data['text_color'] ?? '#FFFFFF');
        $align = e($data['align'] ?? 'center');

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:24px 32px;text-align:{$align};" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;" align="{$align}">
            <tr>
                <td style="border-radius:8px;background-color:{$bgColor};">
                    <a href="{$url}" target="_blank" style="display:inline-block;padding:14px 32px;font-size:16px;font-weight:600;color:{$textColor};text-decoration:none;border-radius:8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                        <!--[if mso]>&nbsp;&nbsp;&nbsp;<![endif]-->{$text}<!--[if mso]>&nbsp;&nbsp;&nbsp;<![endif]-->
                    </a>
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected static function compileDivider(array $data): string
    {
        $color = e($data['color'] ?? '#E5E7EB');
        $width = intval($data['width'] ?? 100);
        $style = e($data['style'] ?? 'solid');

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:16px 32px;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="{$width}%" align="center">
            <tr>
                <td style="border-top:1px {$style} {$color};font-size:0;line-height:0;">&nbsp;</td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected static function compileColumns(array $data): string
    {
        $leftContent = $data['left_content'] ?? '';
        $rightContent = $data['right_content'] ?? '';

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:24px 32px;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <!--[if mso]><td valign="top" width="264"><![endif]-->
                <td width="48%" style="padding-right:12px;vertical-align:top;font-size:16px;line-height:1.6;color:#1E293B;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;" class="stack-column">
                    {$leftContent}
                </td>
                <!--[if mso]></td><td valign="top" width="264"><![endif]-->
                <td width="48%" style="padding-left:12px;vertical-align:top;font-size:16px;line-height:1.6;color:#1E293B;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;" class="stack-column">
                    {$rightContent}
                </td>
                <!--[if mso]></td><![endif]-->
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected static function compileSpacer(array $data): string
    {
        $height = intval($data['height'] ?? 30);

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;font-size:0;line-height:0;height:{$height}px;">&nbsp;</td>
</tr>
HTML;
    }

    protected static function compileSocial(array $data): string
    {
        $links = $data['links'] ?? [];

        $iconsHtml = '';
        foreach ($links as $link) {
            $platform = e($link['platform'] ?? '');
            $url = e($link['url'] ?? '#');
            $label = ucfirst($platform);

            $colors = [
                'twitter' => '#1DA1F2',
                'linkedin' => '#0A66C2',
                'facebook' => '#1877F2',
                'instagram' => '#E4405F',
                'youtube' => '#FF0000',
                'github' => '#333333',
            ];
            $color = $colors[$platform] ?? '#64748B';

            $iconsHtml .= <<<HTML
                <td style="padding:0 8px;">
                    <a href="{$url}" target="_blank" style="display:inline-block;width:36px;height:36px;line-height:36px;text-align:center;background-color:{$color};color:#FFFFFF;border-radius:50%;text-decoration:none;font-size:14px;font-weight:600;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                        {$label[0]}
                    </a>
                </td>
HTML;
        }

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:24px 32px;text-align:center;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto;">
            <tr>
                {$iconsHtml}
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected static function compileFooter(array $data): string
    {
        $text = e($data['text'] ?? '');
        $unsubscribeText = e($data['unsubscribe_text'] ?? 'Unsubscribe');

        return <<<HTML
<tr>
    <td style="background-color:#F8FAFC;padding:24px 32px;text-align:center;border-top:1px solid #E5E7EB;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="font-size:13px;line-height:1.5;color:#94A3B8;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                    {$text}
                </td>
            </tr>
            <tr>
                <td style="font-size:13px;line-height:1.5;color:#94A3B8;text-align:center;padding-top:8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                    <a href="{{unsubscribe_url}}" style="color:#64748B;text-decoration:underline;">{$unsubscribeText}</a>
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    /**
     * Render a template's blocks array into preview-safe HTML.
     */
    public static function renderBlocksPreview(array $blocks): string
    {
        $html = '<div style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;background:#ffffff;">';

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $data = $block['data'] ?? [];

            switch ($type) {
                case 'header':
                    $bgColor = $data['bg_color'] ?? '#6366F1';
                    $company = $data['company_name'] ?? '';
                    $html .= "<div style=\"background:{$bgColor};padding:24px 32px;text-align:center;\"><span style=\"color:#ffffff;font-size:18px;font-weight:700;\">" . e($company) . "</span></div>";
                    break;

                case 'text':
                    $content = $data['content'] ?? '';
                    $html .= "<div style=\"padding:16px 32px;\">{$content}</div>";
                    break;

                case 'image':
                    $src = $data['src'] ?? '';
                    $alt = $data['alt'] ?? '';
                    if ($src) {
                        $html .= "<div style=\"padding:0 32px;\"><img src=\"" . e($src) . "\" alt=\"" . e($alt) . "\" style=\"width:100%;height:auto;display:block;border-radius:8px;\" /></div>";
                    }
                    break;

                case 'button':
                    $text = $data['text'] ?? 'Click Here';
                    $bgColor = $data['bg_color'] ?? '#6366F1';
                    $textColor = $data['text_color'] ?? '#FFFFFF';
                    $html .= "<div style=\"padding:16px 32px;text-align:" . ($data['align'] ?? 'center') . ";\"><a style=\"display:inline-block;padding:12px 28px;background:{$bgColor};color:{$textColor};text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;\">" . e($text) . "</a></div>";
                    break;

                case 'columns':
                    $left = $data['left_content'] ?? '';
                    $right = $data['right_content'] ?? '';
                    $html .= "<div style=\"padding:8px 32px;\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\"><tr><td width=\"48%\" style=\"vertical-align:top;padding:8px;\">{$left}</td><td width=\"4%\"></td><td width=\"48%\" style=\"vertical-align:top;padding:8px;\">{$right}</td></tr></table></div>";
                    break;

                case 'divider':
                    $color = $data['color'] ?? '#E5E7EB';
                    $html .= "<div style=\"padding:8px 32px;\"><hr style=\"border:none;border-top:1px solid {$color};\" /></div>";
                    break;

                case 'spacer':
                    $height = $data['height'] ?? '16';
                    $html .= "<div style=\"height:{$height}px;\"></div>";
                    break;

                case 'social':
                    $html .= "<div style=\"padding:16px 32px;text-align:center;\">";
                    foreach ($data['links'] ?? [] as $link) {
                        $platform = ucfirst($link['platform'] ?? '');
                        $html .= "<a style=\"display:inline-block;margin:0 8px;color:#6366F1;text-decoration:none;font-size:13px;font-weight:500;\">" . e($platform) . "</a>";
                    }
                    $html .= "</div>";
                    break;

                case 'footer':
                    $text = $data['text'] ?? '';
                    $unsub = $data['unsubscribe_text'] ?? 'Unsubscribe';
                    $html .= "<div style=\"padding:20px 32px;text-align:center;background:#F8FAFC;border-top:1px solid #E5E7EB;\"><p style=\"margin:0 0 8px 0;font-size:12px;color:#94A3B8;\">" . e($text) . "</p><a style=\"font-size:12px;color:#6366F1;text-decoration:underline;\">" . e($unsub) . "</a></div>";
                    break;
            }
        }

        $html .= '</div>';

        return $html;
    }
}
