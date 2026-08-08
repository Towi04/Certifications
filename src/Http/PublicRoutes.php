<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Auth;
use App\Catalog\CatalogRepository;

final class PublicRoutes
{
    public static function register(Router $router): void
    {
        $repo = static fn (): CatalogRepository => new CatalogRepository();

        $router->get('/certificacion', static function () use ($repo): void {
            $slug = trim((string) ($_GET['slug'] ?? ''));
            $item = $slug !== '' ? $repo()->certificationBySlug($slug) : null;
            if (!$item || !(int) $item['is_published']) {
                http_response_code(404);
                echo 'Producto no encontrado.';
                exit;
            }

            $protocolSteps = [];
            if (!empty($item['protocol_id'])) {
                $protocolSteps = $repo()->protocolSteps((int) $item['protocol_id'], true);
            }

            view('store/show', [
                'title' => $item['name'],
                'item' => $item,
                'protocolSteps' => $protocolSteps,
                'courses' => $repo()->certificationCourses((int) $item['id']),
                'assets' => $repo()->assets('certification', (int) $item['id']),
                'providerAssets' => $repo()->assets('provider', (int) $item['provider_id']),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/adquirir', static function () use ($repo): void {
            $slug = trim((string) ($_GET['slug'] ?? ''));
            $item = $slug !== '' ? $repo()->certificationBySlug($slug) : null;
            if (!$item || !(int) $item['is_published']) {
                http_response_code(404);
                echo 'Producto no encontrado.';
                exit;
            }

            // Si ya está logueado como alumno, ir directo a confirmar
            if (Auth::check()) {
                $role = Auth::user()['role'] ?? '';
                if ($role === 'student' || Auth::isStaffRole($role)) {
                    view('store/acquire_confirm', [
                        'title' => 'Adquirir · ' . $item['name'],
                        'item' => $item,
                        'user' => Auth::user(),
                        'error' => flash('error'),
                        'info' => flash('info'),
                    ]);
                    return;
                }
                if ($role === 'partner') {
                    flash('info', 'Como Teacher Referral, registra alumnos desde tu panel. La compra pública es para alumnos.');
                    header('Location: /partner/certificacion?slug=' . rawurlencode($slug));
                    exit;
                }
            }

            view('store/acquire', [
                'title' => 'Adquirir · ' . $item['name'],
                'item' => $item,
                'error' => flash('error'),
                'info' => flash('info'),
                'old' => flash('old') ?? [],
            ]);
        });

        $router->post('/adquirir', static function () use ($repo): void {
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $item = $slug !== '' ? $repo()->certificationBySlug($slug) : null;
            if (!$item || !(int) $item['is_published']) {
                flash('error', 'Producto no encontrado.');
                header('Location: /');
                exit;
            }

            $mode = (string) ($_POST['mode'] ?? 'register');

            try {
                if ($mode === 'login') {
                    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                    $password = (string) ($_POST['password'] ?? '');
                    if (!Auth::attempt($email, $password)) {
                        throw new \RuntimeException('Correo o contraseña incorrectos.');
                    }
                    $role = Auth::user()['role'] ?? '';
                    if ($role === 'partner') {
                        throw new \RuntimeException('Las cuentas Teacher Referral no usan la compra pública.');
                    }
                } elseif ($mode === 'confirm') {
                    Auth::requireLogin();
                } else {
                    // register
                    if (Auth::check()) {
                        throw new \RuntimeException('Ya tienes sesión iniciada.');
                    }
                    $first = trim((string) ($_POST['first_name'] ?? ''));
                    $last = trim((string) ($_POST['last_name'] ?? ''));
                    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                    $phone = trim((string) ($_POST['phone'] ?? ''));
                    $password = (string) ($_POST['password'] ?? '');
                    $password2 = (string) ($_POST['password_confirm'] ?? '');

                    if ($first === '' || $last === '') {
                        throw new \RuntimeException('Nombre y apellido son obligatorios.');
                    }
                    if ($password !== $password2) {
                        throw new \RuntimeException('Las contraseñas no coinciden.');
                    }

                    // Si el correo ya existe, orientar a login
                    if (Auth::findUserByEmail($email)) {
                        flash('error', 'Ya tienes cuenta con ese correo. Inicia sesión para adquirir.');
                        flash('old', ['email' => $email, 'show_login' => '1']);
                        header('Location: /adquirir?slug=' . rawurlencode($slug));
                        exit;
                    }

                    $userId = Auth::registerStudent([
                        'email' => $email,
                        'first_name' => $first,
                        'last_name' => $last,
                        'phone' => $phone,
                        'password' => $password,
                    ]);
                    Auth::loginById($userId);
                }

                $user = Auth::user();
                if ($user === null) {
                    throw new \RuntimeException('No se pudo autenticar.');
                }

                if (empty($item['protocol_id'])) {
                    flash('info', 'Cuenta lista. Esta certificación aún no tiene protocolo de seguimiento; el equipo te contactará.');
                    header('Location: /alumno');
                    exit;
                }

                $caseId = $repo()->openCertificationCase([
                    'certification_id' => (int) $item['id'],
                    'student_user_id' => (int) $user['id'],
                    'student_email' => (string) $user['email'],
                    'student_name' => trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')))
                        ?: (string) $user['name'],
                    'notes' => 'Adquisición pública desde vitrina',
                ]);

                flash('info', 'Listo. Ya puedes dar seguimiento a tu certificación.');
                header('Location: /alumno/caso?id=' . $caseId);
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                flash('old', [
                    'first_name' => (string) ($_POST['first_name'] ?? ''),
                    'last_name' => (string) ($_POST['last_name'] ?? ''),
                    'email' => (string) ($_POST['email'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'show_login' => $mode === 'login' ? '1' : '',
                ]);
                header('Location: /adquirir?slug=' . rawurlencode($slug));
                exit;
            }
        });

        $router->get('/alumno', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            view('alumno/index', [
                'title' => 'Mi seguimiento',
                'user' => $user,
                'cases' => $repo()->casesForStudentUser((int) $user['id']),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/alumno/caso', static function () use ($repo): void {
            Auth::requireStudent();
            $user = Auth::user();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->certificationCase($id);
            if (!$item || (int) ($item['student_user_id'] ?? 0) !== (int) $user['id']) {
                // staff puede ver cualquiera
                if ($item && Auth::isStaffRole($user['role'] ?? null)) {
                    // ok
                } else {
                    flash('error', 'Caso no encontrado.');
                    header('Location: /alumno');
                    exit;
                }
            }

            view('alumno/caso', [
                'title' => 'Caso #' . $id,
                'item' => $item,
                'steps' => $repo()->certificationCaseSteps($id),
                'phases' => CatalogRepository::protocolPhases(),
                'responsibles' => CatalogRepository::protocolResponsibles(),
                'user' => $user,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });
    }
}
