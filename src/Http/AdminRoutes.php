<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Auth;
use App\Catalog\CatalogRepository;
use App\Support\Str;
use App\Support\Uploader;

final class AdminRoutes
{
    public static function register(Router $router): void
    {
        $repo = static fn (): CatalogRepository => new CatalogRepository();

        $providerTabUrl = static function (int $id, string $tab): string {
            return '/admin/providers/edit?id=' . $id . '&tab=' . rawurlencode($tab);
        };

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
                'tab' => 'proveedor',
                'agreements' => [],
                'certifications' => [],
                'contacts' => [],
                'venues' => [],
                'notes' => [],
                'error' => flash('error'),
                'info' => flash('info'),
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
            $repo()->ensureLegacyContactMigrated($id);
            $allowedTabs = ['proveedor', 'contactos', 'sedes', 'autorizacion', 'convenio', 'certificaciones', 'notas'];
            $tab = (string) ($_GET['tab'] ?? 'proveedor');
            if (!in_array($tab, $allowedTabs, true)) {
                $tab = 'proveedor';
            }
            $editVenue = null;
            $editVenueId = (int) ($_GET['edit_venue'] ?? 0);
            if ($tab === 'sedes' && $editVenueId > 0) {
                $editVenue = $repo()->providerVenue($id, $editVenueId);
            }
            $editContact = null;
            $editContactId = (int) ($_GET['edit_contact'] ?? 0);
            if ($tab === 'contactos' && $editContactId > 0) {
                $editContact = $repo()->providerContact($id, $editContactId);
            }
            $showForm = isset($_GET['form']) || $editVenue !== null || $editContact !== null;
            view('admin/providers/form', [
                'title' => 'Editar proveedor',
                'item' => $item,
                'tab' => $tab,
                'agreements' => $repo()->providerAgreements($id),
                'certifications' => $repo()->certificationsByProvider($id),
                'contacts' => $repo()->providerContacts($id),
                'venues' => $repo()->providerVenues($id),
                'editVenue' => $editVenue,
                'editContact' => $editContact,
                'showForm' => $showForm,
                'notes' => $repo()->providerNotes($id),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/providers/toggle-active', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            $item = $repo()->provider($id);
            if (!$item) {
                flash('error', 'Proveedor no encontrado.');
                header('Location: /admin/providers');
                exit;
            }
            $newActive = !(int) $item['is_active'];
            $repo()->setProviderActive($id, $newActive);
            flash('info', $newActive ? 'Proveedor activado.' : 'Proveedor desactivado.');
            header('Location: /admin/providers');
            exit;
        });

        $router->post('/admin/providers/save', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $tab = (string) ($_POST['tab'] ?? 'proveedor');
            $existing = $id ? $repo()->provider($id) : null;

            try {
                $code = strtoupper(trim((string) ($_POST['code'] ?? ($existing['code'] ?? ''))));
                $name = trim((string) ($_POST['name'] ?? ($existing['name'] ?? '')));
                if ($code === '' || $name === '') {
                    throw new \RuntimeException('"Convenio con" y "Certificaciones de" son obligatorios.');
                }

                $iconPath = $existing['logo_icon_path'] ?? $existing['logo_path'] ?? null;
                $fullPath = $existing['logo_full_path'] ?? null;
                $authType = $existing['auth_proof_type'] ?? 'none';
                $authUrl = $existing['auth_proof_url'] ?? null;
                $authPath = $existing['auth_proof_path'] ?? null;
                $website = $existing['website_url'] ?? null;
                $isActive = $existing ? (int) $existing['is_active'] : 1;

                if ($tab === 'proveedor' || !$existing) {
                    $website = trim((string) ($_POST['website_url'] ?? '')) ?: null;
                    if (!empty($_FILES['logo_icon']['name'])) {
                        $newIcon = Uploader::storeImage($_FILES['logo_icon'], 'providers/icons', 320, 320);
                        if ($iconPath) {
                            Uploader::delete((string) $iconPath);
                        }
                        $iconPath = $newIcon;
                    }
                    if (!empty($_FILES['logo_full']['name'])) {
                        $newFull = Uploader::storeImage($_FILES['logo_full'], 'providers/full', 900, 400);
                        if ($fullPath) {
                            Uploader::delete((string) $fullPath);
                        }
                        $fullPath = $newFull;
                    }
                }

                if ($tab === 'autorizacion') {
                    $authType = (string) ($_POST['auth_proof_type'] ?? 'none');
                    if (!in_array($authType, ['none', 'url', 'document'], true)) {
                        $authType = 'none';
                    }
                    if ($authType === 'url') {
                        $authUrl = trim((string) ($_POST['auth_proof_url'] ?? '')) ?: null;
                        if ($authUrl === null) {
                            throw new \RuntimeException('Indica el enlace de distribuidor autorizado.');
                        }
                        if ($authPath) {
                            Uploader::delete((string) $authPath);
                            $authPath = null;
                        }
                    } elseif ($authType === 'document') {
                        if (!empty($_FILES['auth_proof_file']['name'])) {
                            $newPath = Uploader::store($_FILES['auth_proof_file'], 'providers/auth');
                            if ($authPath) {
                                Uploader::delete((string) $authPath);
                            }
                            $authPath = $newPath;
                        }
                        if (!$authPath) {
                            throw new \RuntimeException('Sube el documento de autorización.');
                        }
                        $authUrl = null;
                    } else {
                        if ($authPath) {
                            Uploader::delete((string) $authPath);
                        }
                        $authPath = null;
                        $authUrl = null;
                    }
                }

                $savedId = $repo()->saveProvider([
                    'code' => $code,
                    'name' => $name,
                    'website_url' => $website,
                    'logo_path' => $iconPath,
                    'logo_icon_path' => $iconPath,
                    'logo_full_path' => $fullPath,
                    'auth_proof_type' => $authType,
                    'auth_proof_url' => $authUrl,
                    'auth_proof_path' => $authPath,
                    'is_active' => $isActive,
                ], $id);

                flash('info', 'Guardado correctamente.');
                $nextTab = in_array($tab, ['proveedor', 'autorizacion'], true) ? $tab : 'proveedor';
                header('Location: ' . $providerTabUrl($savedId, $nextTab));
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? $providerTabUrl($id, $tab) : '/admin/providers/create'));
                exit;
            }
        });

        $router->post('/admin/providers/contact', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $contactId = (int) ($_POST['contact_id'] ?? 0) ?: null;
            $name = trim((string) ($_POST['name'] ?? ''));
            $redirectForm = static function () use ($providerTabUrl, $providerId, $contactId): void {
                $loc = $providerTabUrl($providerId, 'contactos') . ($contactId ? '&edit_contact=' . $contactId : '&form=1');
                header('Location: ' . $loc);
                exit;
            };
            if ($providerId < 1 || $name === '') {
                flash('error', 'El nombre del contacto es obligatorio.');
                $redirectForm();
            }
            try {
                $role = (string) ($_POST['role'] ?? 'general');
                $allowedRoles = ['ventas', 'soporte', 'finanzas', 'general', 'otro'];
                if (!in_array($role, $allowedRoles, true)) {
                    $role = 'general';
                }
                if ($role === 'otro') {
                    $custom = trim((string) ($_POST['role_custom'] ?? ''));
                    if ($custom === '') {
                        throw new \RuntimeException('Especifica el nombre del rol (Otro).');
                    }
                    $role = $custom;
                }
                $payload = [
                    'provider_id' => $providerId,
                    'role' => $role,
                    'name' => $name,
                    'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
                    'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
                    'whatsapp' => trim((string) ($_POST['whatsapp'] ?? '')) ?: null,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'is_primary' => isset($_POST['is_primary']),
                ];
                if ($contactId) {
                    if (!$repo()->providerContact($providerId, $contactId)) {
                        throw new \RuntimeException('Contacto no encontrado.');
                    }
                    $repo()->updateProviderContact($contactId, $payload);
                    flash('info', 'Contacto actualizado.');
                } else {
                    $repo()->addProviderContact($payload);
                    flash('info', 'Contacto agregado.');
                }
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                $redirectForm();
            }
            header('Location: ' . $providerTabUrl($providerId, 'contactos'));
            exit;
        });

        $router->post('/admin/providers/contact/delete', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $contactId = (int) ($_POST['contact_id'] ?? 0);
            $repo()->deleteProviderContact($providerId, $contactId);
            flash('info', 'Contacto eliminado.');
            header('Location: ' . $providerTabUrl($providerId, 'contactos'));
            exit;
        });

        $router->post('/admin/providers/venue', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $venueId = (int) ($_POST['venue_id'] ?? 0) ?: null;
            $venueType = (string) ($_POST['venue_type'] ?? 'fixed');
            if (!in_array($venueType, ['fixed', 'subcentro'], true)) {
                $venueType = 'fixed';
            }
            $city = trim((string) ($_POST['city'] ?? ''));
            $state = trim((string) ($_POST['state'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $address = trim((string) ($_POST['address_line'] ?? ''));

            $redirectError = static function () use ($providerTabUrl, $providerId, $venueId): void {
                $loc = $providerTabUrl($providerId, 'sedes');
                if ($venueId) {
                    $loc .= '&edit_venue=' . $venueId;
                }
                header('Location: ' . $loc);
                exit;
            };

            if ($providerId < 1 || $city === '') {
                flash('error', 'La ciudad es obligatoria.');
                $redirectError();
            }
            if ($venueType === 'fixed' && ($name === '' || $address === '')) {
                flash('error', 'En sede fija: lugar y dirección son obligatorios.');
                $redirectError();
            }
            if ($venueType === 'subcentro') {
                if ($state === '') {
                    flash('error', 'En subcentro: estado y ciudad son obligatorios.');
                    $redirectError();
                }
                if ($name === '') {
                    $name = 'Subcentro ' . ($state !== '' ? $state : $city);
                }
                // Dirección física se define por aplicación (más adelante).
                $address = '';
            }

            $payload = [
                'provider_id' => $providerId,
                'venue_type' => $venueType,
                'name' => $name,
                'address_line' => $address !== '' ? $address : null,
                'address_line2' => $venueType === 'fixed'
                    ? (trim((string) ($_POST['address_line2'] ?? '')) ?: null)
                    : null,
                'neighborhood' => $venueType === 'fixed'
                    ? (trim((string) ($_POST['neighborhood'] ?? '')) ?: null)
                    : null,
                'city' => $city,
                'state' => $state !== '' ? $state : null,
                'postal_code' => $venueType === 'fixed'
                    ? (trim((string) ($_POST['postal_code'] ?? '')) ?: null)
                    : null,
                'country' => trim((string) ($_POST['country'] ?? 'México')) ?: 'México',
                'contact_name' => trim((string) ($_POST['contact_name'] ?? '')) ?: null,
                'contact_phone' => trim((string) ($_POST['contact_phone'] ?? '')) ?: null,
                'contact_email' => trim((string) ($_POST['contact_email'] ?? '')) ?: null,
                'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                'is_active' => 1,
            ];
            try {
                if ($venueId) {
                    if (!$repo()->providerVenue($providerId, $venueId)) {
                        throw new \RuntimeException('Sede no encontrada.');
                    }
                    $repo()->updateProviderVenue($venueId, $payload);
                    flash('info', $venueType === 'subcentro' ? 'Subcentro actualizado.' : 'Sede actualizada.');
                } else {
                    $repo()->addProviderVenue($payload);
                    flash('info', $venueType === 'subcentro' ? 'Subcentro agregado.' : 'Sede agregada.');
                }
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                $redirectError();
            }
            header('Location: ' . $providerTabUrl($providerId, 'sedes'));
            exit;
        });

        $router->post('/admin/providers/venue/toggle-active', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $venueId = (int) ($_POST['venue_id'] ?? 0);
            $venue = $repo()->providerVenue($providerId, $venueId);
            if (!$venue) {
                flash('error', 'Sede no encontrada.');
                header('Location: ' . $providerTabUrl($providerId, 'sedes'));
                exit;
            }
            $newActive = !(int) $venue['is_active'];
            $repo()->setProviderVenueActive($providerId, $venueId, $newActive);
            flash('info', $newActive ? 'Sede activada.' : 'Sede desactivada (oculta, no eliminada).');
            header('Location: ' . $providerTabUrl($providerId, 'sedes'));
            exit;
        });

        $router->post('/admin/providers/venue/delete', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $venueId = (int) ($_POST['venue_id'] ?? 0);
            $repo()->deleteProviderVenue($providerId, $venueId);
            flash('info', 'Sede eliminada.');
            header('Location: ' . $providerTabUrl($providerId, 'sedes'));
            exit;
        });

        $router->post('/admin/providers/agreement', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $label = trim((string) ($_POST['label'] ?? ''));
            if ($providerId < 1 || $label === '') {
                flash('error', 'Indica una etiqueta para el convenio.');
                header('Location: ' . $providerTabUrl($providerId, 'convenio'));
                exit;
            }
            try {
                if (empty($_FILES['agreement_file']['name'])) {
                    throw new \RuntimeException('Sube el PDF del convenio.');
                }
                $path = Uploader::store($_FILES['agreement_file'], 'providers/agreements');
                $year = trim((string) ($_POST['year'] ?? ''));
                $signedOn = trim((string) ($_POST['signed_on'] ?? ''));
                $repo()->addProviderAgreement([
                    'provider_id' => $providerId,
                    'label' => $label,
                    'year' => $year !== '' ? (int) $year : null,
                    'file_path' => $path,
                    'signed_on' => $signedOn !== '' ? $signedOn : null,
                    'notes' => null,
                    'is_current' => isset($_POST['is_current']),
                ]);
                flash('info', 'Convenio subido.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'convenio'));
            exit;
        });

        $router->post('/admin/providers/agreement/current', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $agreementId = (int) ($_POST['agreement_id'] ?? 0);
            $repo()->setCurrentProviderAgreement($providerId, $agreementId);
            flash('info', 'Convenio marcado como vigente.');
            header('Location: ' . $providerTabUrl($providerId, 'convenio'));
            exit;
        });

        $router->post('/admin/providers/agreement/delete', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $agreementId = (int) ($_POST['agreement_id'] ?? 0);
            $row = $repo()->deleteProviderAgreement($providerId, $agreementId);
            if ($row) {
                Uploader::delete((string) $row['file_path']);
                flash('info', 'Convenio eliminado.');
            }
            header('Location: ' . $providerTabUrl($providerId, 'convenio'));
            exit;
        });

        $router->post('/admin/providers/certifications', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $names = $_POST['names'] ?? [];
            if (!is_array($names)) {
                $names = [];
            }
            if ($providerId < 1) {
                flash('error', 'Proveedor inválido.');
                header('Location: /admin/providers');
                exit;
            }
            try {
                $created = $repo()->createCertificationStubs($providerId, $names);
                flash('info', $created > 0
                    ? "Se agregaron {$created} certificaciones."
                    : 'No se agregó ninguna (filas vacías).');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'certificaciones'));
            exit;
        });

        $router->post('/admin/providers/certification/toggle-published', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $certificationId = (int) ($_POST['certification_id'] ?? 0);
            if ($providerId < 1 || $certificationId < 1 || !$repo()->certificationBelongsToProvider($certificationId, $providerId)) {
                flash('error', 'Certificación no encontrada.');
                header('Location: ' . $providerTabUrl($providerId, 'certificaciones'));
                exit;
            }
            $cert = $repo()->certification($certificationId);
            $newPublished = !((int) ($cert['is_published'] ?? 0) === 1);
            $repo()->setCertificationPublished($certificationId, $newPublished);
            flash('info', $newPublished ? 'Certificación publicada.' : 'Certificación ocultada.');
            header('Location: ' . $providerTabUrl($providerId, 'certificaciones'));
            exit;
        });

        $router->post('/admin/providers/note', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $body = trim((string) ($_POST['body'] ?? ''));
            if ($providerId < 1 || $body === '') {
                flash('error', 'Escribe la nota.');
                header('Location: ' . $providerTabUrl($providerId, 'notas'));
                exit;
            }
            $user = Auth::user();
            $repo()->addProviderNote($providerId, $body, $user ? (int) $user['id'] : null);
            flash('info', 'Nota agregada.');
            header('Location: ' . $providerTabUrl($providerId, 'notas'));
            exit;
        });

        $router->post('/admin/providers/note/delete', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $noteId = (int) ($_POST['note_id'] ?? 0);
            $repo()->deleteProviderNote($providerId, $noteId);
            flash('info', 'Nota eliminada.');
            header('Location: ' . $providerTabUrl($providerId, 'notas'));
            exit;
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
                'assets' => $repo()->assets('agreement', $id),
                'assetTypes' => CatalogRepository::assetTypesFor('agreement'),
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
                'assets' => $repo()->assets('certification', $id),
                'assetTypes' => CatalogRepository::assetTypesFor('certification'),
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
                'history' => $repo()->partnerAssignmentHistory($id),
                'error' => flash('error'),
                'info' => flash('info'),
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
                $admin = Auth::user();
                $repo()->savePartner([
                    'user_id' => $userId,
                    'partner_tier_id' => $tierId > 0 ? $tierId : null,
                    'current_agreement_id' => $agreementId > 0 ? $agreementId : null,
                    'organization' => trim((string) ($_POST['organization'] ?? '')) ?: null,
                    'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'assignment_reason' => trim((string) ($_POST['assignment_reason'] ?? '')) ?: 'Asignación admin',
                ], $id, $admin ? (int) $admin['id'] : null);
                flash('info', 'Partner guardado.');
                header('Location: /admin/partners');
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/partners/edit?id=' . $id : '/admin/partners/create'));
                exit;
            }
        });

        $router->post('/admin/assets/upload', static function () use ($repo): void {
            Auth::requireAdmin();
            $ownerType = (string) ($_POST['owner_type'] ?? '');
            $ownerId = (int) ($_POST['owner_id'] ?? 0);
            $assetType = (string) ($_POST['asset_type'] ?? 'other');
            $redirect = (string) ($_POST['redirect'] ?? '/admin');
            if (!str_starts_with($redirect, '/admin')) {
                $redirect = '/admin';
            }
            $allowedOwners = ['provider', 'certification', 'course', 'agreement'];
            if (!in_array($ownerType, $allowedOwners, true) || $ownerId < 1) {
                flash('error', 'Destino de asset inválido.');
                header('Location: ' . $redirect);
                exit;
            }
            $allowedTypes = CatalogRepository::assetTypesFor($ownerType);
            if (!in_array($assetType, $allowedTypes, true)) {
                flash('error', 'Tipo de asset no válido.');
                header('Location: ' . $redirect);
                exit;
            }
            try {
                if (empty($_FILES['file'])) {
                    throw new \RuntimeException('Selecciona un archivo.');
                }
                $path = Uploader::store($_FILES['file'], $ownerType);
                $repo()->saveAsset([
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'asset_type' => $assetType,
                    'file_path' => $path,
                    'title' => trim((string) ($_POST['title'] ?? '')) ?: null,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                ]);
                flash('info', 'Archivo subido.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $redirect);
            exit;
        });

        $router->post('/admin/assets/delete', static function () use ($repo): void {
            Auth::requireAdmin();
            $assetId = (int) ($_POST['asset_id'] ?? 0);
            $redirect = (string) ($_POST['redirect'] ?? '/admin');
            if (!str_starts_with($redirect, '/admin')) {
                $redirect = '/admin';
            }
            $asset = $repo()->deleteAsset($assetId);
            if ($asset) {
                Uploader::delete((string) $asset['file_path']);
                flash('info', 'Asset eliminado.');
            } else {
                flash('error', 'Asset no encontrado.');
            }
            header('Location: ' . $redirect);
            exit;
        });
    }
}
