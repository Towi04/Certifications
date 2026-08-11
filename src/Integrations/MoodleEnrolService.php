<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Catalog\CatalogRepository;
use App\Config\Env;
use App\Mail\CaseMailService;

/**
 * Tras el pago: crea usuario Moodle si no existe y matricula en cursos
 * ligados a la certificación (platform_type=moodle + moodle_course_id).
 * El acceso se limita a N meses (default 6); la prórroga extiende otros 6.
 */
final class MoodleEnrolService
{
    public const DEFAULT_ACCESS_MONTHS = 6;
    public const PRORROGA_MONTHS = 6;

    public function __construct(
        private readonly CatalogRepository $repo,
        private readonly MoodleClient $moodle = new MoodleClient(),
        private readonly ?CaseMailService $mail = null,
    ) {
    }

    private function mailer(): CaseMailService
    {
        return $this->mail ?? new CaseMailService($this->repo);
    }

    /**
     * @return array{
     *   skipped?: bool,
     *   reason?: string,
     *   moodle_user_id?: int,
     *   username?: string,
     *   created_user?: bool,
     *   enrolled?: list<array{course_id:int,moodle_course_id:int,name:string,access_ends_at?:string}>,
     *   access_mail?: bool,
     *   error?: string
     * }
     */
    public function ensureAccessForCase(int $caseId, ?int $actorUserId = null): array
    {
        if (!Env::isFilled('MOODLE_URL') || !Env::isFilled('MOODLE_TOKEN')) {
            return ['skipped' => true, 'reason' => 'moodle_not_configured'];
        }

        $this->repo->ensureCourseAccessTables();

        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }

        $certId = (int) ($case['certification_id'] ?? 0);
        $courses = $certId > 0 ? $this->repo->moodleCoursesForCertification($certId) : [];
        if ($courses === []) {
            return ['skipped' => true, 'reason' => 'no_moodle_courses'];
        }

        $email = strtolower(trim((string) ($case['student_email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('El caso no tiene correo de alumno válido para Moodle.');
        }

        $firstname = trim((string) ($case['student_name'] ?? 'Alumno'));
        $lastname = trim(
            trim((string) ($case['student_last_name_p'] ?? '')) . ' '
            . trim((string) ($case['student_last_name_m'] ?? ''))
        );
        if ($lastname === '') {
            $lastname = 'Doceo';
        }

        $existingUsername = trim((string) ($case['moodle_user'] ?? ''));
        $user = null;
        $created = false;
        $password = null;
        $stage = 'buscar_usuario';

        try {
        if ($existingUsername !== '') {
            $user = $this->moodle->findUserByUsername($existingUsername);
        }
        if (!$user) {
            $user = $this->moodle->findUserByEmail($email);
        }

        if ($user) {
            $moodleUserId = (int) $user['id'];
            $username = (string) ($user['username'] ?? $existingUsername);
        } else {
            $stage = 'crear_usuario';
            $username = $this->suggestUsername($email, $caseId);
            $password = $this->generatePassword();
            if ($this->moodle->findUserByUsername($username)) {
                $username = $username . $caseId;
            }
            $createdUser = $this->moodle->createUser([
                'username' => $username,
                'password' => $password,
                'firstname' => $firstname !== '' ? $firstname : 'Alumno',
                'lastname' => $lastname,
                'email' => $email,
            ]);
            $moodleUserId = $createdUser['id'];
            $username = $createdUser['username'];
            $password = $createdUser['password'];
            $created = true;
        }

        $now = new \DateTimeImmutable('now');
        $enrolled = [];
        foreach ($courses as $course) {
            $moodleCourseId = (int) ($course['moodle_course_id'] ?? 0);
            $courseId = (int) ($course['id'] ?? 0);
            if ($moodleCourseId < 1 || $courseId < 1) {
                continue;
            }

            $existing = $this->repo->caseMoodleEnrolmentByCaseCourse($caseId, $courseId);
            $months = max(1, (int) ($course['access_months'] ?? self::DEFAULT_ACCESS_MONTHS));
            if ($months < 1) {
                $months = self::DEFAULT_ACCESS_MONTHS;
            }

            // Si ya hay ventana vigente, no acortar; solo asegurar matrícula activa.
            if ($existing && strtotime((string) ($existing['access_ends_at'] ?? '')) > time()
                && ($existing['status'] ?? '') === 'active') {
                $endsAt = new \DateTimeImmutable((string) $existing['access_ends_at']);
                $startsAt = new \DateTimeImmutable((string) ($existing['access_starts_at'] ?? 'now'));
            } elseif ($existing && strtotime((string) ($existing['access_ends_at'] ?? '')) > time()) {
                // Había fecha futura pero estaba suspendido → reactivar hasta esa fecha
                $endsAt = new \DateTimeImmutable((string) $existing['access_ends_at']);
                $startsAt = new \DateTimeImmutable((string) ($existing['access_starts_at'] ?? 'now'));
            } else {
                $startsAt = $now;
                $endsAt = $now->modify('+' . $months . ' months');
            }

            $stage = 'matricular_curso_' . $moodleCourseId;
            $this->moodle->enrolUser(
                $moodleUserId,
                $moodleCourseId,
                5,
                $startsAt->getTimestamp(),
                $endsAt->getTimestamp(),
                0
            );

            $rowId = $this->repo->upsertCaseMoodleEnrolment([
                'case_id' => $caseId,
                'course_id' => $courseId,
                'moodle_user_id' => $moodleUserId,
                'moodle_course_id' => $moodleCourseId,
                'access_starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'access_ends_at' => $endsAt->format('Y-m-d H:i:s'),
                'status' => 'active',
            ]);

            $enrolled[] = [
                'course_id' => $courseId,
                'moodle_course_id' => $moodleCourseId,
                'name' => (string) ($course['name'] ?? ''),
                'access_ends_at' => $endsAt->format('Y-m-d H:i:s'),
                'enrolment_id' => $rowId,
            ];
        }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Moodle falló en etapa “' . $stage . '”: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $fields = [
            'moodle_user' => $username,
        ];
        if ($created && $password !== null && $password !== '') {
            $fields['moodle_password'] = $password;
        }
        $this->repo->updateCertificationCase($caseId, $fields);

        $note = $created
            ? 'Usuario Moodle creado (' . $username . ') y matriculado en ' . count($enrolled) . ' curso(s) (acceso 6 meses).'
            : 'Usuario Moodle existente (' . $username . ') matriculado en ' . count($enrolled) . ' curso(s) (acceso limitado).';
        if ($created && ($password === null || $password === '')) {
            $note .= ' Contraseña generada por Moodle (revisa correo del alumno o restablécela en campus).';
        } try {
            $this->repo->markCaseStepDoneByKeywords(
                $caseId,
                ['moodle', 'acceso al curso', 'plataforma', 'campus'],
                $actorUserId,
                $note
            );
        } catch (\Throwable) {
        }

        $accessMail = false;
        $this->repo->ensureMoodleAccessMailTemplate();
        $moodleTpl = $this->repo->mailTemplateByCode('moodle_acceso');
        if ($moodleTpl && (int) ($moodleTpl['is_active'] ?? 0) === 1) {
            try {
                $this->mailer()->sendTemplate($caseId, 'moodle_acceso', $actorUserId);
                $accessMail = true;
            } catch (\Throwable) {
            }
        }

        return [
            'moodle_user_id' => $moodleUserId,
            'username' => $username,
            'created_user' => $created,
            'enrolled' => $enrolled,
            'access_mail' => $accessMail,
        ];
    }

    /**
     * Extiende el acceso Moodle de una matrícula (+ meses, default 6).
     *
     * @return array{enrolment_id:int,access_ends_at:string}
     */
    public function extendEnrolment(int $enrolmentId, int $months = self::PRORROGA_MONTHS, ?int $actorUserId = null): array
    {
        if (!Env::isFilled('MOODLE_URL') || !Env::isFilled('MOODLE_TOKEN')) {
            throw new \RuntimeException('Moodle no está configurado.');
        }
        $this->repo->ensureCourseAccessTables();
        $enrol = $this->repo->caseMoodleEnrolment($enrolmentId);
        if (!$enrol) {
            throw new \RuntimeException('Matrícula Moodle no encontrada.');
        }
        $months = max(1, $months);
        $now = new \DateTimeImmutable('now');
        $currentEnd = !empty($enrol['access_ends_at'])
            ? new \DateTimeImmutable((string) $enrol['access_ends_at'])
            : $now;
        $base = $currentEnd > $now ? $currentEnd : $now;
        $newEnd = $base->modify('+' . $months . ' months');
        $startsAt = !empty($enrol['access_starts_at'])
            ? new \DateTimeImmutable((string) $enrol['access_starts_at'])
            : $now;

        $moodleUserId = (int) ($enrol['moodle_user_id'] ?? 0);
        $moodleCourseId = (int) ($enrol['moodle_course_id'] ?? 0);
        if ($moodleUserId < 1 || $moodleCourseId < 1) {
            throw new \RuntimeException('La matrícula no tiene IDs Moodle.');
        }

        $this->moodle->enrolUser(
            $moodleUserId,
            $moodleCourseId,
            5,
            $startsAt->getTimestamp(),
            $newEnd->getTimestamp(),
            0
        );

        $this->repo->updateCaseMoodleEnrolment($enrolmentId, [
            'access_ends_at' => $newEnd->format('Y-m-d H:i:s'),
            'status' => 'active',
            'last_synced_at' => date('Y-m-d H:i:s'),
            'moodle_user_id' => $moodleUserId,
            'moodle_course_id' => $moodleCourseId,
        ]);

        try {
            $this->repo->markCaseStepDoneByKeywords(
                (int) $enrol['case_id'],
                ['prorroga', 'prórroga', 'moodle', 'acceso'],
                $actorUserId,
                'Prórroga Moodle +' . $months . ' meses hasta ' . $newEnd->format('Y-m-d')
            );
        } catch (\Throwable) {
        }

        return [
            'enrolment_id' => $enrolmentId,
            'access_ends_at' => $newEnd->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Suspende en Moodle las matrículas vencidas y marca status=expired.
     *
     * @return array{suspended:int,errors:list<string>}
     */
    public function suspendExpiredEnrolments(int $limit = 200): array
    {
        $this->repo->ensureCourseAccessTables();
        $rows = $this->repo->expiredActiveMoodleEnrolments($limit);
        $suspended = 0;
        $errors = [];
        if (!Env::isFilled('MOODLE_URL') || !Env::isFilled('MOODLE_TOKEN')) {
            foreach ($rows as $row) {
                $this->repo->updateCaseMoodleEnrolment((int) $row['id'], [
                    'status' => 'expired',
                    'last_synced_at' => date('Y-m-d H:i:s'),
                ]);
                $suspended++;
            }

            return ['suspended' => $suspended, 'errors' => ['moodle_not_configured_db_only']];
        }

        foreach ($rows as $row) {
            try {
                $uid = (int) ($row['moodle_user_id'] ?? 0);
                $cid = (int) ($row['moodle_course_id'] ?? 0);
                if ($uid > 0 && $cid > 0) {
                    $this->moodle->setEnrolmentSuspended($uid, $cid, 1);
                }
                $this->repo->updateCaseMoodleEnrolment((int) $row['id'], [
                    'status' => 'expired',
                    'last_synced_at' => date('Y-m-d H:i:s'),
                ]);
                $suspended++;
            } catch (\Throwable $e) {
                $errors[] = '#' . ($row['id'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        return ['suspended' => $suspended, 'errors' => $errors];
    }

    private function suggestUsername(string $email, int $caseId): string
    {
        $local = strtolower((string) strstr($email, '@', true));
        $username = MoodleClient::sanitizeUsername($local !== '' ? $local : ('alumno' . $caseId));
        // Si el local del correo quedó demasiado corto, anclar al caso
        if (strlen($username) < 3) {
            $username = MoodleClient::sanitizeUsername('alumno' . $caseId);
        }

        return $username;
    }

    private function generatePassword(): string
    {
        return MoodleClient::strongPassword();
    }
}
