<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config\Env;

/**
 * Plantilla HTML común de correos Instituto DOCEO.
 * Las plantillas de BD solo deben contener el cuerpo (texto/HTML interno).
 */
final class MailBranding
{
    public const BRAND_BLUE = '#315285';
    public const BRAND_GRAY = '#C4C4C4';
    public const LOGO_URL = 'https://institutodoceo.com/img/emails/logo.png';

    /** @return list<array{label:string,url:string,icon:string}> */
    public static function socialLinks(?string $appUrl = null): array
    {
        $base = rtrim($appUrl ?? self::appUrl(), '/');
        $icon = static fn (string $name): string => $base . '/assets/brand/email/' . $name . '.png';

        return [
            ['label' => 'Facebook', 'url' => 'https://www.facebook.com/institutodoceo', 'icon' => $icon('facebook')],
            ['label' => 'X', 'url' => 'https://x.com/institutodoceo', 'icon' => $icon('x')],
            ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@institutodoceo', 'icon' => $icon('youtube')],
            ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/institutodoceo', 'icon' => $icon('linkedin')],
            ['label' => 'WhatsApp', 'url' => 'https://wa.me/524778775112', 'icon' => $icon('whatsapp')],
            ['label' => 'Telegram', 'url' => 'https://t.me/+524778775112', 'icon' => $icon('telegram')],
        ];
    }

    public static function appUrl(): string
    {
        return rtrim((string) (Env::get('APP_URL', 'https://pdv.institutodoceo.com') ?? 'https://pdv.institutodoceo.com'), '/');
    }

    public static function logoUrl(): string
    {
        $custom = trim((string) (Env::get('MAIL_LOGO_URL', '') ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        return self::LOGO_URL;
    }

    /**
     * Botón estilo Doceo (homogéneo en todas las plantillas).
     */
    public static function button(string $label, string $url): string
    {
        $url = trim($url);
        if ($url === '' || $label === '') {
            return '';
        }
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return '<p style="margin:16px 0;text-align:center;">'
            . '<a href="' . $safeUrl . '" target="_blank" rel="noopener" '
            . 'style="display:inline-block;background-color:' . self::BRAND_BLUE . ';border:none;color:#ffffff;'
            . 'padding:10px 15px;text-decoration:none;margin:4px 2px;cursor:pointer;'
            . 'border-radius:12px;font-family:Arial,sans-serif;font-size:14px;font-weight:700;">'
            . $safeLabel . '</a></p>';
    }

    /**
     * Envuelve el cuerpo HTML en la plantilla institucional (logo + pie con redes).
     * Si el contenido ya trae la plantilla completa, no lo duplica.
     */
    public static function wrap(string $innerHtml, ?string $appUrl = null): string
    {
        $innerHtml = trim($innerHtml);
        if ($innerHtml === '') {
            $innerHtml = '<p style="margin:0;color:' . self::BRAND_BLUE . ';">&nbsp;</p>';
        }

        // Evitar doble envoltura si la plantilla ya es el diseño completo.
        if (self::looksFullyBranded($innerHtml)) {
            if (!str_contains(strtolower($innerHtml), '<html')) {
                return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#ffffff;">'
                    . $innerHtml . '</body></html>';
            }

            return $innerHtml;
        }

        $app = htmlspecialchars($appUrl ?? self::appUrl(), ENT_QUOTES, 'UTF-8');
        $logo = htmlspecialchars(self::logoUrl(), ENT_QUOTES, 'UTF-8');
        $blue = self::BRAND_BLUE;
        $gray = self::BRAND_GRAY;

        $socialCells = '';
        foreach (self::socialLinks($appUrl ?? self::appUrl()) as $i => $social) {
            $pad = $i === 0 ? '0' : '0 0 0 10px';
            $socialCells .= '<td style="padding:' . $pad . ';width:38px;">'
                . '<a href="' . htmlspecialchars($social['url'], ENT_QUOTES, 'UTF-8') . '" style="color:#c4c4c4;" target="_blank" rel="noopener">'
                . '<img src="' . htmlspecialchars($social['icon'], ENT_QUOTES, 'UTF-8') . '" '
                . 'alt="' . htmlspecialchars($social['label'], ENT_QUOTES, 'UTF-8') . '" width="38" height="38" '
                . 'style="height:auto;display:block;border:0;border-radius:19px;" />'
                . '</a></td>';
        }

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Instituto DOCEO</title></head>'
            . '<body style="margin:0;padding:0;background:#ffffff;">'
            . '<table role="presentation" style="width:100%;border-collapse:collapse;border:0;border-spacing:0;background:#ffffff;"><tbody>'
            . '<tr><td align="center" style="padding:0;">'
            . '<table role="presentation" style="width:602px;max-width:100%;border-collapse:collapse;border:1px solid #cccccc;border-spacing:0;text-align:left;"><tbody>'

            // Header logo
            . '<tr><td align="center" style="padding:40px 0 30px 0;background:' . $gray . ';">'
            . '<a href="' . $app . '" style="text-decoration:none;">'
            . '<img src="' . $logo . '" alt="Instituto DOCEO" width="300" '
            . 'style="height:auto;display:block;border:0;max-width:90%;" />'
            . '</a></td></tr>'

            // Body
            . '<tr><td style="padding:36px 30px 42px 30px;font-family:Arial,sans-serif;color:' . $blue . ';font-size:15px;line-height:1.55;">'
            . $innerHtml
            . '</td></tr>'

            // Footer
            . '<tr><td style="padding:30px;background:' . $blue . ';">'
            . '<table role="presentation" style="width:100%;border-collapse:collapse;border:0;border-spacing:0;font-size:24px;font-family:Arial,sans-serif;"><tbody><tr>'
            . '<td style="padding:0;width:50%;" align="left">'
            . '<p style="margin:0;font-size:12px;line-height:16px;font-family:Arial,sans-serif;color:#ffffff;">'
            . '<a href="https://campus.institutodoceo.com" style="color:#ffffff;text-decoration:underline;">Página oficial</a><br />'
            . 'Instituto DOCEO<br />'
            . 'Contacto: <a href="mailto:info@institutodoceo.com" style="color:#ffffff;text-decoration:underline;">info@institutodoceo.com</a>'
            . '</p></td>'
            . '<td style="padding:0;width:50%;" align="right">'
            . '<table role="presentation" style="border-collapse:collapse;border:0;border-spacing:0;"><tbody><tr>'
            . $socialCells
            . '</tr></tbody></table>'
            . '</td></tr></tbody></table>'
            . '</td></tr>'

            . '</tbody></table></td></tr></tbody></table>'
            . '</body></html>';
    }

    /** Convierte texto plano a HTML mínimo y lo envuelve. */
    public static function wrapPlainText(string $plainText, ?string $appUrl = null): string
    {
        $escaped = nl2br(htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $html = '<div style="color:' . self::BRAND_BLUE . ';font-family:Arial,sans-serif;font-size:15px;line-height:1.55;text-align:left;">'
            . $escaped
            . '</div>';

        return self::wrap($html, $appUrl);
    }

    private static function looksFullyBranded(string $html): bool
    {
        $hay = strtolower($html);
        if (str_contains($hay, 'institutodoceo.com/img/emails/logo.png')) {
            return true;
        }
        $hasWidth = str_contains($hay, 'width: 602px') || str_contains($hay, 'width:602px');
        $hasFooter = str_contains($hay, 'background: #315285') || str_contains($hay, 'background:#315285');

        return $hasWidth && $hasFooter;
    }
}
