<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Config\Env;

final class MoodleClient
{
    public function call(string $function, array $params = []): array
    {
        $url = Env::require('MOODLE_URL');
        $token = Env::require('MOODLE_TOKEN');

        $payload = array_merge($params, [
            'wstoken' => $token,
            'wsfunction' => $function,
            'moodlewsrestformat' => 'json',
        ]);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('No se pudo iniciar cURL para Moodle.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException("cURL Moodle: {$error}");
        }

        if ($raw === false || $raw === '') {
            throw new \RuntimeException("Respuesta vacía de Moodle (HTTP {$status}).");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Moodle no devolvió JSON válido.');
        }

        if (isset($decoded['exception'])) {
            $message = (string) ($decoded['message'] ?? $decoded['exception']);
            $code = (string) ($decoded['errorcode'] ?? 'moodle_error');
            throw new \RuntimeException("Moodle [{$code}]: {$message}");
        }

        return $decoded;
    }

    /** @return list<array<string, mixed>> */
    public function getCourses(): array
    {
        $result = $this->call('core_course_get_courses');
        return array_values($result);
    }

    /** @return list<array<string, mixed>> */
    public function getUsersByField(string $field, array $values): array
    {
        $params = ['field' => $field];
        foreach (array_values($values) as $i => $value) {
            $params['values[' . $i . ']'] = (string) $value;
        }
        $result = $this->call('core_user_get_users_by_field', $params);

        return array_values(is_array($result) ? $result : []);
    }

    public function findUserByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $users = $this->getUsersByField('email', [$email]);
        foreach ($users as $user) {
            if (is_array($user) && !empty($user['id'])) {
                return $user;
            }
        }

        return null;
    }

    public function findUserByUsername(string $username): ?array
    {
        $username = strtolower(trim($username));
        if ($username === '') {
            return null;
        }
        $users = $this->getUsersByField('username', [$username]);
        foreach ($users as $user) {
            if (is_array($user) && !empty($user['id'])) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return array{id:int,username:string,password:?string,created:bool}
     */
    public function createUser(array $data): array
    {
        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $firstname = trim((string) ($data['firstname'] ?? 'Alumno'));
        $lastname = trim((string) ($data['lastname'] ?? 'Doceo'));
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($username === '' || $email === '' || $password === '') {
            throw new \InvalidArgumentException('username, email y password son obligatorios para crear usuario Moodle.');
        }

        $result = $this->call('core_user_create_users', [
            'users[0][username]' => $username,
            'users[0][password]' => $password,
            'users[0][firstname]' => mb_substr($firstname !== '' ? $firstname : 'Alumno', 0, 100),
            'users[0][lastname]' => mb_substr($lastname !== '' ? $lastname : 'Doceo', 0, 100),
            'users[0][email]' => $email,
            'users[0][auth]' => 'manual',
            'users[0][lang]' => 'es',
            'users[0][mailformat]' => 1,
        ]);

        $created = is_array($result[0] ?? null) ? $result[0] : null;
        if (!$created || empty($created['id'])) {
            throw new \RuntimeException('Moodle no devolvió el ID del usuario creado.');
        }

        return [
            'id' => (int) $created['id'],
            'username' => (string) ($created['username'] ?? $username),
            'password' => $password,
            'created' => true,
        ];
    }

    public function enrolUser(
        int $userId,
        int $courseId,
        int $roleId = 5,
        ?int $timestart = null,
        ?int $timeend = null,
        int $suspend = 0
    ): void {
        if ($userId < 1 || $courseId < 1) {
            throw new \InvalidArgumentException('userId y courseId Moodle inválidos.');
        }
        $params = [
            'enrolments[0][roleid]' => $roleId,
            'enrolments[0][userid]' => $userId,
            'enrolments[0][courseid]' => $courseId,
            'enrolments[0][suspend]' => $suspend > 0 ? 1 : 0,
        ];
        if ($timestart !== null && $timestart > 0) {
            $params['enrolments[0][timestart]'] = $timestart;
        }
        if ($timeend !== null && $timeend > 0) {
            $params['enrolments[0][timeend]'] = $timeend;
        }
        $this->call('enrol_manual_enrol_users', $params);
    }

    /** Suspende (1) o reactiva (0) la matrícula manual en un curso. */
    public function setEnrolmentSuspended(int $userId, int $courseId, int $suspend = 1, int $roleId = 5): void
    {
        $this->enrolUser($userId, $courseId, $roleId, null, null, $suspend > 0 ? 1 : 0);
    }
}
