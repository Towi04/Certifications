<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config\Env;
use App\Integrations\Mailer;
use App\Users\UserRepository;

/**
 * Correo al alumno tras adquirir una certificación desde la vitrina pública.
 */
final class StudentAcquireMailer
{
    public function sendWelcome(array $user, array $case, array $certification): void
    {
        $email = (string) ($user['email'] ?? $case['student_email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Correo del alumno inválido.');
        }

        $first = trim((string) ($user['first_name'] ?? $case['student_name'] ?? 'alumno'));
        $certName = (string) ($certification['name'] ?? $case['certification_name'] ?? 'tu certificación');
        $examDate = trim((string) ($case['exam_date'] ?? ''));
        $examTime = trim((string) ($case['exam_time'] ?? ''));
        $loginUrl = $this->appUrl('/login');
        $caseUrl = $this->appUrl('/alumno/caso?id=' . (int) ($case['id'] ?? 0));
        $password = UserRepository::DEFAULT_PASSWORD;

        $when = 'la fecha acordada';
        if ($examDate !== '') {
            $when = $examDate . ($examTime !== '' ? ' a las ' . $examTime : '');
        }

        $subject = 'Tu examen ' . $certName . ' — seguimiento Instituto Doceo';

        $body = "Hola {$first},\n\n";
        $body .= "Registramos tu solicitud de {$certName}.\n";
        $body .= "Fecha tentativa de examen: {$when}.\n\n";
        $body .= "Creamos un acceso para que puedas dar seguimiento a la gestión de tu examen:\n";
        $body .= "- Correo: {$email}\n";
        $body .= "- Contraseña temporal: {$password}\n";
        $body .= "- Entrar: {$loginUrl}\n";
        $body .= "- Tu seguimiento: {$caseUrl}\n\n";
        $body .= "Importante:\n";
        $body .= "1) En tu seguimiento ya puedes firmar el reglamento y realizar el pago (link OpenPay).\n";
        $body .= "2) Un día antes de tu examen te enviaremos el código de acceso, o puedes entrar a tu cuenta para ver si ya fue asignado.\n";
        $body .= "3) Después de presentar el examen, en tu cuenta podrás dar seguimiento al estado de tu certificado y al trámite CENNI.\n\n";
        $body .= "Te deseamos mucho éxito.\n";
        $body .= "Instituto Doceo\n";
        $body .= (Env::get('SMTP_FROM') ?: 'certificaciones@institutodoceo.com');

        $mailer = new Mailer();
        $mailer->send($email, $subject, $body);
    }

    private function appUrl(string $path): string
    {
        $base = rtrim((string) (Env::get('APP_URL') ?: ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return $base . $path;
    }
}
