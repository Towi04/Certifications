<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Iconos visuales para habilidades y modalidad de certificaciones.
 */
final class CertIcons
{
    /** @return array<string, string> skill key => short label */
    public static function skillLabels(): array
    {
        return [
            'listening' => 'Listening',
            'reading' => 'Reading',
            'writing' => 'Writing',
            'speaking' => 'Speaking',
            'use_of_english' => 'Use of English / Grammar',
            'vocabulary' => 'Vocabulary',
        ];
    }

    public static function skillSvg(string $key): string
    {
        return match ($key) {
            // Audífonos
            'listening' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 13v1.5A6.5 6.5 0 0 0 10.5 21h.5v-5H8.5a1.5 1.5 0 0 1-1.5-1.5V13H4Zm16 0h-3v1.5A1.5 1.5 0 0 1 15.5 16H13v5h.5A6.5 6.5 0 0 0 20 14.5V13Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4 13a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            // Libro
            'reading' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5.5C6.5 4 9 4 12 5.5C15 4 17.5 4 20 5.5V19c-2.5-1.5-5-1.5-8 0-3-1.5-5.5-1.5-8 0V5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 5.5V19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            // Lápiz
            'writing' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4l11-11a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13.5 6.5l3 3M4 20l.7-2.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            // Burbuja de diálogo
            'speaking' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 5.5h11a3.5 3.5 0 0 1 3.5 3.5v4A3.5 3.5 0 0 1 16 16.5h-5.2L6 20.2V16.5H5A3.5 3.5 0 0 1 1.5 13V9A3.5 3.5 0 0 1 5 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7.5 10.2h8M7.5 13h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            // Ortografía / gramática (Aa + subrayado corrector)
            'use_of_english' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.5 17 8 6.5h1.8L13.3 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.1 13.2h5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M15.2 17V9.8c0-1.7 1.1-2.8 2.7-2.8 1.5 0 2.6 1 2.6 2.6V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M15.2 12.8h5.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4.2 20.2h15.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            // Manzana
            'vocabulary' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 7.2c0-2 .9-3.7 2.4-4.7-.2 2.1.5 3.7 2.1 4.7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M12 21.2c-2.2 0-6.5-1.7-7.6-5.5C3.2 12.2 5 8.6 8.2 8.2c1.2-.1 2.3.3 3.1 1.1.8-.8 1.9-1.2 3.1-1.1 3.2.4 5 4 3.8 7.5-1.1 3.8-5.4 5.5-7.6 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
            default => '',
        };
    }

    public static function modalitySvg(string $modality): string
    {
        return match ($modality) {
            // PC / monitor
            'online' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="12.5" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 20.5h8M12 16.5v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            // Papel / documento
            'paper' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.5h7.5L19 8v12.5a1.5 1.5 0 0 1-1.5 1.5H7A1.5 1.5 0 0 1 5.5 20.5V5A1.5 1.5 0 0 1 7 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14.5 3.5V8H19M8.5 12h7M8.5 15.5h7M8.5 19h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            default => '',
        };
    }

    /**
     * @param mixed $skillsJson
     * @return list<string>
     */
    public static function decodeSkillKeys(mixed $skillsJson): array
    {
        if ($skillsJson === null || $skillsJson === '') {
            return [];
        }
        $decoded = is_string($skillsJson) ? json_decode($skillsJson, true) : $skillsJson;
        if (!is_array($decoded)) {
            return [];
        }
        $allowed = array_keys(self::skillLabels());
        $out = [];
        foreach ($decoded as $key) {
            $key = (string) $key;
            if (in_array($key, $allowed, true)) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /** HTML de iconos de habilidades (vacío si no es examen de nivel). */
    public static function skillsHtml(mixed $skillsJson, bool $isLevelExam, string $sizeClass = ''): string
    {
        if (!$isLevelExam) {
            return '';
        }
        $keys = self::decodeSkillKeys($skillsJson);
        if ($keys === []) {
            return '';
        }
        $labels = self::skillLabels();
        $class = trim('cert-meta-icons ' . $sizeClass);
        $parts = ['<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" role="list">'];
        foreach ($keys as $key) {
            $svg = self::skillSvg($key);
            if ($svg === '') {
                continue;
            }
            $label = $labels[$key] ?? $key;
            $parts[] = '<span class="cert-meta-icon" role="listitem" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . $svg . '</span>';
        }
        $parts[] = '</span>';
        return implode('', $parts);
    }

    public static function modalityHtml(string $modality, string $sizeClass = ''): string
    {
        $svg = self::modalitySvg($modality);
        if ($svg === '') {
            return '';
        }
        $label = $modality === 'paper' ? 'Paper' : ($modality === 'online' ? 'Online' : $modality);
        $class = trim('cert-meta-icons ' . $sizeClass);
        return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"><span class="cert-meta-icon cert-meta-icon--modality" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . $svg . '</span></span>';
    }
}
