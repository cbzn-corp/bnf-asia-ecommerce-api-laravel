<?php

declare(strict_types=1);

namespace App\Support\Email;

final class EmailHtmlLayout
{
    /** Storefront brand tokens — ecommerce-storefront/tailwind.config.ts */
    private const COLOR_PRIMARY = '#8B0000';

    private const COLOR_HEADING = '#111111';

    private const COLOR_BODY = '#333333';

    private const COLOR_MUTED = '#666666';

    private const COLOR_BORDER = '#E0E0E0';

    private const COLOR_SURFACE = '#FFFFFF';

    private const COLOR_SURFACE_MUTED = '#F5F5F5';

    public static function wrap(
        string $subject,
        string $bodyHtml,
        string $brandName = 'BNF Asia',
        ?string $headline = null,
    ): string {
        $brand = e($brandName);
        $title = e($headline ?: $subject);
        $content = self::sanitizeBody($bodyHtml);
        $year = date('Y');
        $primary = self::COLOR_PRIMARY;
        $heading = self::COLOR_HEADING;
        $bodyColor = self::COLOR_BODY;
        $muted = self::COLOR_MUTED;
        $border = self::COLOR_BORDER;
        $surface = self::COLOR_SURFACE;
        $surfaceMuted = self::COLOR_SURFACE_MUTED;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background-color:{$surfaceMuted};font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{$surfaceMuted};padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background-color:{$surface};border:1px solid {$border};border-radius:12px;overflow:hidden;box-shadow:0 1px 3px 0 rgb(0 0 0 / 0.06);">
          <tr>
            <td style="background-color:{$primary};padding:18px 24px;text-align:center;">
              <span style="font-size:18px;font-weight:700;color:#ffffff;letter-spacing:0.04em;text-transform:uppercase;">{$brand}</span>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 28px 8px;">
              <h1 style="margin:0;font-size:22px;line-height:1.35;font-weight:700;color:{$heading};">{$title}</h1>
            </td>
          </tr>
          <tr>
            <td class="email-body" style="padding:8px 28px 24px;font-size:15px;line-height:1.6;color:{$bodyColor};">
              {$content}
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px;">
              <div style="height:1px;background-color:{$border};"></div>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 28px 28px;font-size:12px;line-height:1.6;color:{$muted};">
              <p style="margin:0 0 10px;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:{$primary};">About these emails</p>
              <p style="margin:0 0 12px;">You are receiving this message because of activity on your {$brand} account or order.</p>
              <p style="margin:0;">&mdash; {$brand} &middot; &copy; {$year}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: bool}>  $rows
     */
    public static function detailTable(array $rows): string
    {
        $heading = self::COLOR_HEADING;
        $bodyColor = self::COLOR_BODY;
        $muted = self::COLOR_MUTED;
        $border = self::COLOR_BORDER;
        $surfaceMuted = self::COLOR_SURFACE_MUTED;
        $body = '';
        foreach ($rows as $index => $row) {
            [$label, $value, $strong] = [$row[0], $row[1], $row[2] ?? false];
            $safeLabel = e($label);
            $safeValue = $strong
                ? '<strong style="color:'.$heading.';">'.e($value).'</strong>'
                : e($value);
            $borderStyle = $index > 0 ? 'border-top:1px solid '.$border.';' : '';
            $body .= <<<ROW
<tr>
  <td style="padding:12px 0;{$borderStyle}font-size:14px;color:{$muted};width:38%;">{$safeLabel}</td>
  <td style="padding:12px 0;{$borderStyle}font-size:14px;color:{$bodyColor};text-align:right;">{$safeValue}</td>
</tr>
ROW;
        }

        return <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;background-color:{$surfaceMuted};border:1px solid {$border};border-radius:10px;">
  <tr>
    <td style="padding:4px 16px 8px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        {$body}
      </table>
    </td>
  </tr>
</table>
HTML;
    }

    public static function button(string $url, string $label): string
    {
        $href = e($url);
        $text = e(mb_strtoupper($label));
        $primary = self::COLOR_PRIMARY;

        return <<<HTML
<table role="presentation" border="0" cellspacing="0" cellpadding="0" class="email-cta" style="margin:20px 0 0;">
  <tr>
    <td align="center" bgcolor="{$primary}" style="background-color:{$primary};border-radius:3px;">
      <a class="email-btn" href="{$href}" target="_blank" rel="noopener noreferrer" style="display:block;padding:12px 24px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;line-height:20px;color:#ffffff;text-decoration:none;background-color:{$primary};border:1px solid {$primary};border-radius:3px;mso-padding-alt:12px 24px;">
        <span style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;line-height:20px;"><font color="#ffffff">{$text}</font></span>
      </a>
    </td>
  </tr>
</table>
HTML;
    }

    public static function plainTextToHtml(string $text): string
    {
        $lines = preg_split("/\r\n|\r|\n/", trim($text)) ?: [];
        $parts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts[] = '<p style="margin:0 0 12px;color:'.self::COLOR_BODY.';">'.e($line).'</p>';
        }

        return $parts !== [] ? implode('', $parts) : '<p style="margin:0;color:'.self::COLOR_BODY.';">&nbsp;</p>';
    }

    private static function sanitizeBody(string $html): string
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return '<p style="margin:0;color:'.self::COLOR_BODY.';">&nbsp;</p>';
        }

        $protected = [];
        $withoutCtas = preg_replace_callback(
            '/<table[^>]*\bclass="[^"]*email-cta[^"]*"[^>]*>.*?<\/table>/is',
            static function (array $matches) use (&$protected): string {
                $key = '__EMAIL_CTA_'.count($protected).'__';
                $protected[$key] = $matches[0];

                return $key;
            },
            $trimmed,
        ) ?? $trimmed;

        $styled = preg_replace_callback(
            '/<a\s+([^>]*?)>/i',
            static function (array $matches): string {
                $attrs = $matches[1];
                if (str_contains($attrs, 'email-btn')) {
                    return '<a '.$attrs.'>';
                }
                if (preg_match('/\sstyle=/i', $attrs)) {
                    return '<a '.$attrs.'>';
                }

                return '<a style="color:'.self::COLOR_PRIMARY.';text-decoration:underline;" '.$attrs.'>';
            },
            $withoutCtas,
        ) ?? $withoutCtas;

        $styled = preg_replace(
            '/<(p|li|div)(?![^>]*style=)/i',
            '<$1 style="margin:0 0 12px;color:'.self::COLOR_BODY.';" ',
            $styled,
        ) ?? $styled;

        $styled = preg_replace(
            '/<(strong|b)(?![^>]*style=)/i',
            '<$1 style="color:'.self::COLOR_HEADING.';" ',
            $styled,
        ) ?? $styled;

        if ($protected !== []) {
            $styled = str_replace(array_keys($protected), array_values($protected), $styled);
        }

        return $styled;
    }
}
