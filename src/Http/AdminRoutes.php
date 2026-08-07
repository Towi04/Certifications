<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Auth;
use App\Catalog\CatalogRepository;
use App\Support\Str;

final class AdminRoutes
{
    public static function register(Router $router): void
    {
        $repo = static fn (): CatalogRepository => new CatalogRepository();

        $router->get('/admin/providers', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/providers/index', [
                'title' => 'Proveedores',
                'items' => $repo()->providers(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/providers/create', static function (): void {
            Auth::requireAdmin();
            view('admin/providers/form', [
                'title' => 'Nuevo proveedor',
                'item' => null,
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/providers/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->provider($id);
            if (!$item) {
                flash('error', 'Proveedor no encontrado.');
                header('Location: /admin/providers');
                exit;
            }
            view('admin/providers/form', [
                'title' => 'Editar proveedor',
                'item' => $item,
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/providers/save', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($code === '' || $name === '') {
                flash('error', 'Código y nombre son obligatorios.');
                header('Location: ' . ($id ? '/admin/providers/edit?id=' . $id : '/admin/providers/create'));
                exit;
            }
            try {
                $repo()->saveProvider([
                    'code' => $code,
                    'name' => $name,
                    'website_url' => trim((string) ($_POST['website_url'] ?? '')) ?: null,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $id);
                flash('info', 'Proveedor guardado.');
                header('Location: /admin/providers');
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/providers/edit?id=' . $id : '/admin/providers/create'));
                exit;
            }
        });

        $router->get('/admin/protocols', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/protocols/index', [
                'title' => 'Protocolos',
                'items' => $repo()->protocols(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/protocols/create', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/protocols/form', [
                'title' => 'Nuevo protocolo',
                'item' => null,
                'providers' => $repo()->providers(true),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/protocols/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->protocol($id);
            if (!$item) {
                flash('error', 'Protocolo no encontrado.');
                header('Location: /admin/protocols');
                exit;
            }
            view('admin/protocols/form', [
                'title' => 'Editar protocolo',
                'item' => $item,
                'providers' => $repo()->providers(true),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/protocols/save', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($code === '' || $name === '') {
                flash('error', 'Código y nombre son obligatorios.');
                header('Location: ' . ($id ? '/admin/protocols/edit?id=' . $id : '/admin/protocols/create'));
                exit;
            }
            try {
                $repo()->saveProtocol([
                    'provider_id' => (int) ($_POST['provider_id'] ?? 0) ?: null,
                    'code' => $code,
                    'name' => $name,
                    'modality' => (string) ($_POST['modality'] ?? 'online'),
                    'procedure_html' => trim((string) ($_POST['procedure_html'] ?? '')) ?: null,
                    'requires_regulation_signature' => isset($_POST['requires_regulation_signature']) ? 1 : 0,
                    'requires_software' => isset($_POST['requires_software']) ? 1 : 0,
                    'requires_zoom' => isset($_POST['requires_zoom']) ? 1 : 0,
                    'requires_vm' => isset($_POST['requires_vm']) ? 1 : 0,
                    'uses_inventory' => isset($_POST['uses_inventory']) ? 1 : 0,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $id);
                flash('info', 'Protocolo guardado.');
                header('Location: /admin/protocols');
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/protocols/edit?id=' . $id : '/admin/protocols/create'));
                exit;
            }
        });

        $router->get('/admin/courses', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/courses/index', [
                'title' => 'Cursos',
                'items' => $repo()->courses(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/courses/create', static function (): void {
            Auth::requireAdmin();
            view('admin/courses/form', [
                'title' => 'Nuevo curso',
                'item' => null,
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/courses/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->course($id);
            if (!$item) {
                flash('error', 'Curso no encontrado.');
                header('Location: /admin/courses');
                exit;
            }
            view('admin/courses/form', [
                'title' => 'Editar curso',
                'item' => $item,
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/courses/save', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($code === '' || $name === '') {
                flash('error', 'Código y nombre son obligatorios.');
                header('Location: ' . ($id ? '/admin/courses/edit?id=' . $id : '/admin/courses/create'));
                exit;
            }
            try {
                $moodleId = trim((string) ($_POST['moodle_course_id'] ?? ''));
                $repo()->saveCourse([
                    'code' => $code,
                    'name' => $name,
                    'platform_type' => (string) ($_POST['platform_type'] ?? 'moodle'),
                    'external_url' => trim((string) ($_POST['external_url'] ?? '')) ?: null,
                    'moodle_course_id' => $moodleId !== '' ? (int) $moodleId : null,
                    'access_notes' => trim((string) ($_POST['access_notes'] ?? '')) ?: null,
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $id);
                flash('info', 'Curso guardado.');
                header('Location: /admin/courses');
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/courses/edit?id=' . $id : '/admin/courses/create'));
                exit;
            }
        });

        $router->get('/admin/tiers', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/tiers/index', [
                'title' => 'Convenios (niveles)',
                'items' => $repo()->partnerTiers(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/tiers/create', static function (): void {
            Auth::requireAdmin();
            view('admin/tiers/form', [
                'title' => 'Nuevo nivel Teacher Referral',
                'item' => null,
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/tiers/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->partnerTierById($id);
            if (!$item) {
                flash('error', 'Nivel no encontrado.');
                header('Location: /admin/tiers');
                exit;
            }
            view('admin/tiers/form', [
                'title' => 'Editar nivel',
                'item' => $item,
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/tiers/save', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($code === '' || $name === '') {
                flash('error', 'Código y nombre son obligatorios.');
                header('Location: ' . ($id ? '/admin/tiers/edit?id=' . $id : '/admin/tiers/create'));
                exit;
            }
            try {
                $repo()->savePartnerTier([
                    'code' => $code,
                    'name' => $name,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $id);
                flash('info', 'Nivel guardado.');
                header('Location: /admin/tiers');
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/tiers/edit?id=' . $id : '/admin/tiers/create'));
                exit;
            }
        });

        $router->get('/admin/agreements', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/agreements/index', [
                'title' => 'Versiones de convenio',
                'items' => $repo()->agreements(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/agreements/create', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/agreements/form', [
                'title' => 'Nueva versión de convenio',
                'item' => null,
                'tiers' => $repo()->partnerTiers(true),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/agreements/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->agreement($id);
            if (!$item) {
                flash('error', 'Convenio no encontrado.');
                header('Location: /admin/agreements');
                exit;
            }
            view('admin/agreements/form', [
                'title' => 'Editar convenio',
                'item' => $item,
                'tiers' => $repo()->partnerTiers(true),
                'prices' => $repo()->agreementPrices($id),
                'certifications' => $repo()->certifications(),
                'error' => flash('error'),
                'info' => flash('info'),
            ]);
        });

        $router->post('/admin/agreements/save', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $name = trim((string) ($_POST['name'] ?? ''));
            $tierId = (int) ($_POST['partner_tier_id'] ?? 0);
            if ($name === '' || $tierId < 1) {
                flash('error', 'Nombre y nivel son obligatorios.');
                header('Location: ' . ($id ? '/admin/agreements/edit?id=' . $id : '/admin/agreements/create'));
                exit;
            }
            try {
                $savedId = $repo()->saveAgreement([
                    'partner_tier_id' => $tierId,
                    'name' => $name,
                    'year' => (int) ($_POST['year'] ?? date('Y')),
                    'valid_from' => (string) ($_POST['valid_from'] ?? date('Y-01-01')),
                    'valid_to' => trim((string) ($_POST['valid_to'] ?? '')) ?: null,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'is_current' => isset($_POST['is_current']) ? 1 : 0,
                ], $id);
                flash('info', 'Convenio guardado.');
                header('Location: /admin/agreements/edit?id=' . $savedId);
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/agreements/edit?id=' . $id : '/admin/agreements/create'));
                exit;
            }
        });

        $router->post('/admin/agreements/price', static function () use ($repo): void {
            Auth::requireAdmin();
            $agreementId = (int) ($_POST['agreement_id'] ?? 0);
            $certificationId = (int) ($_POST['certification_id'] ?? 0);
            $price = (float) ($_POST['price'] ?? 0);
            if ($agreementId < 1 || $certificationId < 1) {
                flash('error', 'Selecciona certificación y precio.');
                header('Location: /admin/agreements/edit?id=' . $agreementId);
                exit;
            }
            $repo()->upsertAgreementPrice($agreementId, $certificationId, $price);
            flash('info', 'Precio de convenio actualizado.');
            header('Location: /admin/agreements/edit?id=' . $agreementId);
            exit;
        });

        $router->get('/admin/certifications', static function () use ($repo): void {
            Auth::requireAdmin();
            $filters = [
                'provider_id' => $_GET['provider_id'] ?? '',
                'is_published' => $_GET['is_published'] ?? '',
                'q' => trim((string) ($_GET['q'] ?? '')),
            ];
            view('admin/certifications/index', [
                'title' => 'Certificaciones',
                'items' => $repo()->certifications($filters),
                'providers' => $repo()->providers(true),
                'filters' => $filters,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/certifications/create', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/certifications/form', [
                'title' => 'Nueva certificación',
                'item' => null,
                'providers' => $repo()->providers(true),
                'protocols' => $repo()->protocols(true),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/certifications/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->certification($id);
            if (!$item) {
                flash('error', 'Certificación no encontrada.');
                header('Location: /admin/certifications');
                exit;
            }
            view('admin/certifications/form', [
                'title' => 'Editar certificación',
                'item' => $item,
                'providers' => $repo()->providers(true),
                'protocols' => $repo()->protocols(true),
                'linkedCourses' => $repo()->certificationCourses($id),
                'courses' => $repo()->courses(true),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/certifications/save', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $name = trim((string) ($_POST['name'] ?? ''));
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            if ($name === '' || $code === '' || $providerId < 1) {
                flash('error', 'Nombre, código y proveedor son obligatorios.');
                header('Location: ' . ($id ? '/admin/certifications/edit?id=' . $id : '/admin/certifications/create'));
                exit;
            }

            $slug = trim((string) ($_POST['slug'] ?? ''));
            if ($slug === '') {
                $slug = Str::slug($name);
            } else {
                $slug = Str::slug($slug);
            }

            $publicPrice = trim((string) ($_POST['public_price'] ?? ''));
            $cenniFee = trim((string) ($_POST['cenni_fee'] ?? ''));
            $conocerFee = trim((string) ($_POST['conocer_fee'] ?? ''));
            $protocolId = (int) ($_POST['protocol_id'] ?? 0);

            try {
                $savedId = $repo()->saveCertification([
                    'provider_id' => $providerId,
                    'protocol_id' => $protocolId > 0 ? $protocolId : null,
                    'code' => $code,
                    'slug' => $slug,
                    'name' => $name,
                    'modality' => (string) ($_POST['modality'] ?? 'online'),
                    'short_description' => trim((string) ($_POST['short_description'] ?? '')) ?: null,
                    'description_html' => trim((string) ($_POST['description_html'] ?? '')) ?: null,
                    'syllabus_html' => trim((string) ($_POST['syllabus_html'] ?? '')) ?: null,
                    'duration_label' => trim((string) ($_POST['duration_label'] ?? '')) ?: null,
                    'audience' => trim((string) ($_POST['audience'] ?? '')) ?: null,
                    'public_price' => $publicPrice !== '' ? (float) $publicPrice : null,
                    'currency' => (string) ($_POST['currency'] ?? 'MXN'),
                    'cenni_eligible' => isset($_POST['cenni_eligible']) ? 1 : 0,
                    'cenni_doc_type' => (string) ($_POST['cenni_doc_type'] ?? 'none'),
                    'cenni_included' => isset($_POST['cenni_included']) ? 1 : 0,
                    'cenni_fee' => $cenniFee !== '' ? (float) $cenniFee : null,
                    'conocer_eligible' => isset($_POST['conocer_eligible']) ? 1 : 0,
                    'conocer_fee' => $conocerFee !== '' ? (float) $conocerFee : null,
                    'is_published' => isset($_POST['is_published']) ? 1 : 0,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                ], $id);
                flash('info', 'Certificación guardada.');
                header('Location: /admin/certifications/edit?id=' . $savedId);
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/certifications/edit?id=' . $id : '/admin/certifications/create'));
                exit;
            }
        });

        $router->post('/admin/certifications/attach-course', static function () use ($repo): void {
            Auth::requireAdmin();
            $certificationId = (int) ($_POST['certification_id'] ?? 0);
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $relationType = (string) ($_POST['relation_type'] ?? 'included');
            $bundlePrice = trim((string) ($_POST['bundle_price'] ?? ''));
            if ($certificationId < 1 || $courseId < 1) {
                flash('error', 'Selecciona un curso.');
                header('Location: /admin/certifications/edit?id=' . $certificationId);
                exit;
            }
            try {
                $repo()->attachCertificationCourse(
                    $certificationId,
                    $courseId,
                    $relationType,
                    $bundlePrice !== '' ? (float) $bundlePrice : null,
                    trim((string) ($_POST['notes'] ?? '')) ?: null
                );
                flash('info', 'Curso vinculado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/certifications/edit?id=' . $certificationId);
            exit;
        });

        $router->post('/admin/certifications/detach-course', static function () use ($repo): void {
            Auth::requireAdmin();
            $certificationId = (int) ($_POST['certification_id'] ?? 0);
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $repo()->detachCertificationCourse($certificationId, $courseId);
            flash('info', 'Curso desvinculado.');
            header('Location: /admin/certifications/edit?id=' . $certificationId);
            exit;
        });

        $router->get('/admin/partners', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/partners/index', [
                'title' => 'Partners TR',
                'items' => $repo()->partners(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/partners/create', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/partners/form', [
                'title' => 'Asignar partner',
                'item' => null,
                'users' => $repo()->usersAvailableForPartner(),
                'tiers' => $repo()->partnerTiers(true),
                'agreements' => $repo()->agreements(),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/partners/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->partner($id);
            if (!$item) {
                flash('error', 'Partner no encontrado.');
                header('Location: /admin/partners');
                exit;
            }
            view('admin/partners/form', [
                'title' => 'Editar partner',
                'item' => $item,
                'users' => $repo()->usersAvailableForPartner((int) $item['user_id']),
                'tiers' => $repo()->partnerTiers(true),
                'agreements' => $repo()->agreements(),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/partners/save', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $userId = (int) ($_POST['user_id'] ?? 0);
            $tierId = (int) ($_POST['partner_tier_id'] ?? 0);
            $agreementId = (int) ($_POST['current_agreement_id'] ?? 0);
            if ($userId < 1) {
                flash('error', 'Selecciona un usuario.');
                header('Location: ' . ($id ? '/admin/partners/edit?id=' . $id : '/admin/partners/create'));
                exit;
            }
            try {
                $repo()->savePartner([
                    'user_id' => $userId,
                    'partner_tier_id' => $tierId > 0 ? $tierId : null,
                    'current_agreement_id' => $agreementId > 0 ? $agreementId : null,
                    'organization' => trim((string) ($_POST['organization'] ?? '')) ?: null,
                    'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                ], $id);
                flash('info', 'Partner guardado.');
                header('Location: /admin/partners');
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/partners/edit?id=' . $id : '/admin/partners/create'));
                exit;
            }
        });
    }
}
