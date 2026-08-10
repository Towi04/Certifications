<?php

declare(strict_types=1);

namespace App\Exports;

use App\Config\Env;

/**
 * Genera archivos de inscripción en el formato que pide cada certificadora.
 */
final class ProviderExportGenerator
{
    public const FORMAT_NONE = 'none';
    public const FORMAT_UKS_CSV = 'uks_csv';
    public const FORMAT_TOEFL_XLSX = 'toefl_xlsx';
    public const FORMAT_LINGUASKILL_XLSX = 'linguaskill_xlsx';

    /** @return array<string, string> */
    public static function formats(): array
    {
        return [
            self::FORMAT_NONE => 'Ninguno',
            self::FORMAT_UKS_CSV => 'UKS — CSV alumnos',
            self::FORMAT_TOEFL_XLSX => 'TOEFL — Excel inscripción',
            self::FORMAT_LINGUASKILL_XLSX => 'Cambridge Linguaskill — Excel CS',
        ];
    }

    /**
     * @param array<string, mixed> $case Caso enriquecido (alumno + certificación)
     * @return array{relative: string, absolute: string, filename: string, mime: string}
     */
    public function generate(string $format, array $case): array
    {
        return match ($format) {
            self::FORMAT_UKS_CSV => $this->uksCsv($case),
            self::FORMAT_TOEFL_XLSX => $this->toeflXlsx($case),
            self::FORMAT_LINGUASKILL_XLSX => $this->linguaskillXlsx($case),
            default => throw new \InvalidArgumentException('Formato de exportación no configurado: ' . $format),
        };
    }

    /** @param array<string, mixed> $case */
    private function uksCsv(array $case): array
    {
        $parts = $this->nameParts($case);
        $matricula = trim((string) ($case['folio_id'] ?? ''));
        if ($matricula === '') {
            $matricula = 'PDV-' . (int) ($case['id'] ?? 0);
        }

        $filename = 'uks_' . $this->safeSlug((string) ($case['student_name'] ?? 'alumno')) . '_' . (int) ($case['id'] ?? 0) . '.csv';
        [$relative, $absolute] = $this->destPath($filename);

        $fh = fopen($absolute, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('No se pudo crear el CSV UKS.');
        }
        // BOM UTF-8: Excel / portales Windows reconocen acentos (Matrícula, Electrónico)
        fwrite($fh, "\xEF\xBB\xBF");
        // Encabezados exactos de Plantilla Instituto DOCEO.csv
        fputcsv($fh, ['Matrícula', 'Apellido Paterno', 'Apellido Materno', 'Nombre(s)', 'Correo Electrónico']);
        fputcsv($fh, [
            $matricula,
            $parts['last_p'],
            $parts['last_m'],
            $parts['first'],
            (string) ($case['student_email'] ?? ''),
        ]);
        fclose($fh);

        return [
            'relative' => $relative,
            'absolute' => $absolute,
            'filename' => $filename,
            'mime' => 'text/csv; charset=UTF-8',
        ];
    }

    /** @param array<string, mixed> $case */
    private function toeflXlsx(array $case): array
    {
        $org = $this->orgContact();
        $parts = $this->nameParts($case);
        $birth = $this->parseDate($case['student_birth_date'] ?? null);
        $exam = $this->parseDate($case['exam_date'] ?? null) ?? new \DateTimeImmutable('today');
        $inscrito = new \DateTimeImmutable('today');

        $apellidos = self::toeflText(trim($parts['last_p'] . ' ' . $parts['last_m']));
        $nombres = self::toeflText($parts['first']);
        $completo = self::toeflText(trim($nombres . ' ' . $apellidos));
        $sexo = $this->sexFM($case['student_sex'] ?? null);
        $pais = self::toeflText((string) ($case['student_nationality'] ?? 'MEX'));
        if ($pais === '') {
            $pais = 'MEX';
        }
        if (mb_strlen($pais) > 3) {
            $pais = mb_substr($pais, 0, 3);
        }

        $mes = $birth ? strtoupper($birth->format('M')) : '';
        // TOEFL plantilla usa abreviaturas en español (ENE, FEB, MAR…)
        $mesMap = [
            'JAN' => 'ENE', 'FEB' => 'FEB', 'MAR' => 'MAR', 'APR' => 'ABR',
            'MAY' => 'MAY', 'JUN' => 'JUN', 'JUL' => 'JUL', 'AUG' => 'AGO',
            'SEP' => 'SEP', 'OCT' => 'OCT', 'NOV' => 'NOV', 'DEC' => 'DIC',
        ];
        $mesEs = $mesMap[$mes] ?? $mes;

        $sheetColegio = [
            'name' => 'DATOS DEL COLEGIO',
            'rows' => [
                ['FORMATO DE INSCRIPCIÓN'],
                ['Certificaciones TOEFL'],
                [],
                ['Fecha', $inscrito->format('Y-m-d')],
                ['*Todos los campos son obligatorios.'],
                [],
                ['NOMBRE DEL COLEGIO:', $org['org']],
                [],
                ['Fecha Inscripción', $inscrito->format('Y-m-d')],
                ['DATOS DEL CONTACTO PARA EXÁMENES'],
                ['NOMBRE:', $org['name']],
                [],
                [],
                ['CARGO EN LA INSTITUCION:', $org['role']],
                [],
                [],
                ['TELÉFONO:', $org['phone']],
                [],
                [],
                ['CELULAR:', $org['mobile']],
                [],
                [],
                ['CORREO ELECTRÓNICO:', $org['email']],
            ],
        ];

        $sheetItp = [
            'name' => 'TOEFL ITP',
            'rows' => [
                ['Inscripción de candidatos'],
                ['TOEFL Exams'],
                ['Nombre del colegio:', $org['org']],
                ['Correo de responsable:', $org['email']],
                ['Fecha de inscripción:', $inscrito->format('Y-m-d')],
                ['Fecha de examen:', $exam->format('Y-m-d')],
                [],
                [],
                [],
                [],
                ['Total Candidatos TOEFL ITP:', 1],
                [],
                [],
                ['***Favor de escribir los datos de los candidatos en Mayúsculas y sin acentos***'],
                ['AL ENVIAR ESTE LISTADO SE ACEPTA LA OBLIGACIÓN DE PAGO DEL NÚMERO DE CANDIDATOS AQUÍ REGISTRADOS.'],
                [],
                [],
                ['', '', '', '', '', 'FECHA DE NACIMIENTO'],
                ['ID CAND', 'APELLIDOS', 'NOMBRE(S)', 'NOMBRE(S) Y APELLIDOS', 'PAIS', 'MES', 'DIA', 'AÑO', 'SEXO(F/M)', 'NACIONALIDAD'],
                [
                    1,
                    $apellidos,
                    $nombres,
                    $completo,
                    $pais,
                    $mesEs,
                    $birth ? (int) $birth->format('j') : '',
                    $birth ? (int) $birth->format('Y') : '',
                    $sexo,
                    $pais,
                ],
            ],
        ];

        $filename = 'toefl_' . $this->safeSlug($completo !== '' ? $completo : 'candidato') . '_' . (int) ($case['id'] ?? 0) . '.xlsx';
        [$relative, $absolute] = $this->destPath($filename);
        SimpleXlsxWriter::write($absolute, [$sheetColegio, $sheetItp]);

        return [
            'relative' => $relative,
            'absolute' => $absolute,
            'filename' => $filename,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /** @param array<string, mixed> $case */
    private function linguaskillXlsx(array $case): array
    {
        $org = $this->orgContact();
        $parts = $this->nameParts($case);
        $birth = $this->parseDate($case['student_birth_date'] ?? null);
        $exam = $this->parseDate($case['exam_date'] ?? null);
        $hora = trim((string) ($case['exam_time'] ?? ''));
        if ($hora !== '' && !str_contains(mb_strtolower($hora), 'hrs')) {
            $hora .= ' hrs';
        }

        $genero = $this->sexMaleFemale($case['student_sex'] ?? null);
        $version = 'REMOTA';
        $fechaApp = $exam ? self::spanishLongDate($exam) : '';
        $fechaNac = $birth ? $birth->format('Y-m-d') : '';

        $rows = [
            [],
            [],
            [],
            [],
            [],
            [],
            ['', '', 'Llenar con letra mayúscula. Favor de revisar los datos antes de enviar.'],
            [],
            ['', 'Preparation Centre:', '', self::upper($org['org_legal'])],
            ['', 'Nombre del contacto:', '', self::upper($org['name'])],
            ['', 'Teléfono del contacto:', '', $org['phone']],
            [],
            ['', 'No.', 'NOMBRE', 'APELLIDOS', 'GÉNERO', 'FECHA DE NACIMIENTO', "VERSIÓN\n(PRESENCIAL O REMOTA)", 'FECHA DE APLICACIÓN', 'HORARIO', 'CORREO ELECTRÓNICO', 'TELÉFONO'],
            [
                '',
                1,
                self::upper($parts['first']),
                self::upper(trim($parts['last_p'] . ' ' . $parts['last_m'])),
                $genero,
                $fechaNac,
                $version,
                self::upper($fechaApp),
                self::upper($hora),
                (string) ($case['student_email'] ?? ''),
                (string) ($case['student_phone'] ?? ''),
            ],
        ];

        $filename = 'linguaskill_' . $this->safeSlug((string) ($case['student_name'] ?? 'alumno')) . '_' . (int) ($case['id'] ?? 0) . '.xlsx';
        [$relative, $absolute] = $this->destPath($filename);
        SimpleXlsxWriter::write($absolute, [['name' => 'Linguaskill', 'rows' => $rows]]);

        return [
            'relative' => $relative,
            'absolute' => $absolute,
            'filename' => $filename,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /** @return array{org: string, org_legal: string, name: string, role: string, phone: string, mobile: string, email: string} */
    private function orgContact(): array
    {
        return [
            'org' => (string) (Env::get('DOCEO_ORG_NAME', 'INSTITUTO DOCEO') ?? 'INSTITUTO DOCEO'),
            'org_legal' => (string) (Env::get('DOCEO_ORG_LEGAL', 'INSTITUTO DOCEO S.C.') ?? 'INSTITUTO DOCEO S.C.'),
            'name' => (string) (Env::get('DOCEO_CONTACT_NAME', 'Francisco Guzmán') ?? 'Francisco Guzmán'),
            'role' => (string) (Env::get('DOCEO_CONTACT_ROLE', 'Supervisor') ?? 'Supervisor'),
            'phone' => (string) (Env::get('DOCEO_CONTACT_PHONE', '4778775112') ?? '4778775112'),
            'mobile' => (string) (Env::get('DOCEO_CONTACT_MOBILE', '4641149116') ?? '4641149116'),
            'email' => (string) (Env::get('DOCEO_CONTACT_EMAIL', 'media.doceo@gmail.com') ?? 'media.doceo@gmail.com'),
        ];
    }

    /**
     * @param array<string, mixed> $case
     * @return array{first: string, last_p: string, last_m: string}
     */
    private function nameParts(array $case): array
    {
        $lastP = trim((string) ($case['student_last_name_p'] ?? ''));
        $lastM = trim((string) ($case['student_last_name_m'] ?? ''));
        $first = trim((string) ($case['student_name'] ?? ''));

        // Si solo hay student_name completo, intentar separar.
        if ($lastP === '' && $first !== '' && str_contains($first, ' ')) {
            $bits = preg_split('/\s+/', $first) ?: [];
            if (count($bits) >= 3) {
                $lastM = array_pop($bits) ?: '';
                $lastP = array_pop($bits) ?: '';
                $first = implode(' ', $bits);
            } elseif (count($bits) === 2) {
                $lastP = $bits[1];
                $first = $bits[0];
            }
        }

        return ['first' => $first, 'last_p' => $lastP, 'last_m' => $lastM];
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function sexFM(mixed $sex): string
    {
        $s = mb_strtolower(trim((string) $sex));
        if ($s === '') {
            return '';
        }
        if (str_starts_with($s, 'f') || $s === 'mujer' || $s === 'female') {
            return 'F';
        }

        return 'M';
    }

    private function sexMaleFemale(mixed $sex): string
    {
        return $this->sexFM($sex) === 'F' ? 'FEMALE' : 'MALE';
    }

    /** @return array{0: string, 1: string} relative, absolute */
    private function destPath(string $filename): array
    {
        $relative = 'uploads/exports/' . $filename;
        $absolute = dirname(__DIR__, 2) . '/storage/' . $relative;
        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear storage/uploads/exports.');
        }

        return [$relative, $absolute];
    }

    private function safeSlug(string $value): string
    {
        $v = self::toeflText($value);
        $v = preg_replace('/[^A-Z0-9]+/', '_', $v) ?? 'file';
        $v = trim($v, '_');
        $slug = $v !== '' ? $v : 'file';
        if (function_exists('mb_substr')) {
            return mb_substr($slug, 0, 40);
        }

        return substr($slug, 0, 40);
    }

    public static function toeflText(string $value): string
    {
        $value = self::upper($value);
        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
        ];

        return strtr($value, $map);
    }

    public static function upper(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }

        return strtoupper($value);
    }

    public static function spanishLongDate(\DateTimeInterface $date): string
    {
        $months = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];
        $m = (int) $date->format('n');

        return $date->format('d') . ' DE ' . ($months[$m] ?? '') . ' DE ' . $date->format('Y');
    }
}
