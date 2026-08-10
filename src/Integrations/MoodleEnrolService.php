<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Catalog\CatalogRepository;
use App\Config\Env;
use App\Mail\CaseMailService;

/**
 * Tras el pago: crea usuario Moodle si no existe y matricula en cursos
 * ligados a la certificación (platform_type=moodle + moodle_course_id).
 */
final class MoodleEnrolService
{
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
     *   enrolled?: list<array{course_id:int,moodle_course_id:int,name:string}>,
     *   access_mail?: bool,
     *   error?: string
     * }
     */
    public function ensureAccessForCase(int $caseId, ?int $actorUserId = null): array
    {
        if (!Env::isFilled('MOODLE_URL') || !Env::isFilled('MOODLE_TOKEN')) {
            return ['skipped' => true, 'reason' => 'moodle_not_configured'];
        }

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
            $username = $this->suggestUsername($email, $caseId);
            $password = $this->generatePassword();
            // Evitar colisión de username
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

        $enrolled = [];
        foreach ($courses as $course) {
            $moodleCourseId = (int) ($course['moodle_course_id'] ?? 0);
            if ($moodleCourseId < 1) {
                continue;
            }
            $this->moodle->enrolUser($moodleUserId, $moodleCourseId);
            $enrolled[] = [
                'course_id' => (int) $course['id'],
                'moodle_course_id' => $moodleCourseId,
                'name' => (string) ($course['name'] ?? ''),
            ];
        }

        $fields = [
            'moodle_user' => $username,
        ];
        if ($created && $password !== null) {
            $fields['moodle_password'] = $password;
        }
        $this->repo->updateCertificationCase($caseId, $fields);

        $note = $created
            ? 'Usuario Moodle creado (' . $username . ') y matriculado en ' . count($enrolled) . ' curso(s).'
            : 'Usuario Moodle existente (' . $username . ') matriculado en ' . count($enrolled) . ' curso(s).';
        try {
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

    private function suggestUsername(string $email, int $caseId): string
    {
        $local = strtolower((string) strstr($email, '@', true));
        $local = preg_replace('/[^a-z0-9._-]+/', '', $local) ?: '';
        if ($local === '' || strlen($local) < 3) {
            $local = 'alumno' . $caseId;
        }
        if (strlen($local) > 50) {
            $local = substr($local, 0, 50);
        }

        return $local;
    }

    private function generatePassword(): string
    {
        // Moodle exige complejidad: mayúscula, minúscula, número y símbolo.
        $base = bin2hex(random_bytes(4));

        return 'Doceo!' . $base . '9';
    }
}
