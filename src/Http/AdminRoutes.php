<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Auth;
use App\Catalog\CatalogRepository;
use App\Config\Env;
use App\Partners\PartnerAgreementService;
use App\Support\SecretBox;
use App\Support\Str;
use App\Support\Uploader;
use App\Users\UserRepository;

final class AdminRoutes
{
    public static function register(Router $router): void
    {
        $repo = static fn (): CatalogRepository => new CatalogRepository();

        $providerTabUrl = static function (int $id, string $tab): string {
            return '/admin/providers/edit?id=' . $id . '&tab=' . rawurlencode($tab);
        };

        $protocolEditUrl = static function (int $id, ?string $tab = null): string {
            $allowed = ['general', 'requisitos', 'correos', 'acciones', 'pasos'];
            if ($tab === null) {
                $tab = trim((string) ($_POST['tab'] ?? $_GET['tab'] ?? 'general'));
            }
            if (!in_array($tab, $allowed, true)) {
                $tab = 'general';
            }

            return '/admin/protocols/edit?id=' . $id . '&tab=' . rawurlencode($tab);
        };

        $partnerEditUrl = static function (int $id, ?string $tab = null): string {
            $allowed = ['datos', 'envio', 'facturacion', 'historial'];
            if ($tab === null) {
                $tab = trim((string) ($_POST['tab'] ?? $_GET['tab'] ?? 'datos'));
            }
            if (!in_array($tab, $allowed, true)) {
                $tab = 'datos';
            }

            return '/admin/partners/edit?id=' . $id . '&tab=' . rawurlencode($tab);
        };

        $caseViewUrl = static function (int $id, ?string $tab = null): string {
            $allowed = ['alumno', 'reglamento', 'accesos', 'resultados', 'pago', 'operacion', 'cenni', 'adjuntos', 'protocolo'];
            if ($tab === null) {
                $tab = trim((string) ($_POST['tab'] ?? $_GET['tab'] ?? 'alumno'));
            }
            if (!in_array($tab, $allowed, true)) {
                $tab = 'alumno';
            }

            return '/admin/cases/view?id=' . $id . '&tab=' . rawurlencode($tab);
        };

        $certEditUrl = static function (int $id, ?string $tab = null): string {
            $allowed = ['general', 'contenido', 'nivel', 'precios', 'adquisicion', 'elegibilidad', 'cursos', 'assets'];
            if ($tab === null) {
                $tab = trim((string) ($_POST['tab'] ?? $_GET['tab'] ?? 'general'));
            }
            if (!in_array($tab, $allowed, true)) {
                $tab = 'general';
            }

            return '/admin/certifications/edit?id=' . $id . '&tab=' . rawurlencode($tab);
        };

        $saveDocumentFromPost = static function (
            CatalogRepository $repo,
            ?int $id,
            ?array $existing,
            bool $requireProviderId = true
        ): int {
            $title = trim((string) ($_POST['title'] ?? ''));
            $version = trim((string) ($_POST['version'] ?? ''));
            $providerId = (int) ($_POST['provider_id'] ?? ($existing['provider_id'] ?? 0));
            $docType = (string) ($_POST['doc_type'] ?? 'other');
            if (!isset(CatalogRepository::documentTypes()[$docType])) {
                $docType = 'other';
            }

            $scopeType = (string) ($_POST['scope_type'] ?? ($existing['scope_type'] ?? 'provider'));
            if (!in_array($scopeType, ['provider', 'group', 'certification'], true)) {
                $scopeType = 'provider';
            }
            $providerGroupId = null;
            $certificationId = null;
            if ($scopeType === 'group') {
                $providerGroupId = (int) ($_POST['provider_group_id'] ?? ($existing['provider_group_id'] ?? 0));
                if ($providerGroupId <= 0) {
                    throw new \RuntimeException('Selecciona el grupo de alcance.');
                }
            } elseif ($scopeType === 'certification') {
                $certificationId = (int) ($_POST['certification_id'] ?? ($existing['certification_id'] ?? 0));
                if ($certificationId <= 0) {
                    throw new \RuntimeException('Selecciona la certificación de alcance.');
                }
            }

            if ($title === '' || $version === '') {
                throw new \RuntimeException('Nombre y versión son obligatorios.');
            }
            if ($requireProviderId && $providerId <= 0) {
                throw new \RuntimeException('Proveedor obligatorio.');
            }

            $code = strtoupper(trim((string) ($_POST['code'] ?? ($existing['code'] ?? ''))));
            if ($code === '') {
                $code = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '_', Str::slug($title) ?: 'DOC') ?? 'DOC');
                $code = trim($code, '_');
                if (strlen($code) > 48) {
                    $code = substr($code, 0, 48);
                }
                $verSlug = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '', $version) ?: 'V');
                $code = $code . '_V' . $verSlug;
            }

            $filePath = $existing['file_path'] ?? null;
            $hasUpload = isset($_FILES['file']) && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if ($hasUpload) {
                $newPath = Uploader::storeDocument($_FILES['file'], 'documents');
                if (!empty($filePath) && $filePath !== $newPath) {
                    Uploader::delete((string) $filePath);
                }
                $filePath = $newPath;
            }

            if (($filePath === null || $filePath === '') && !$id) {
                throw new \RuntimeException('Debes subir el archivo del documento.');
            }

            $savedId = $repo->saveDocument([
                'provider_id' => $providerId,
                'scope_type' => $scopeType,
                'provider_group_id' => $providerGroupId,
                'certification_id' => $certificationId,
                'code' => $code,
                'title' => $title,
                'version' => $version,
                'doc_type' => $docType,
                'file_path' => $filePath,
                'body_html' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ], $id);

            if ($docType === 'regulation') {
                $repo->syncRegulationLinksFromDocument($savedId);
            }

            return $savedId;
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
                'accounts' => [],
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
            $repo()->ensureProviderSetupSchema();
            $repo()->ensureCambridgeAndSepSchemaAndSeeds();
            $allowedTabs = [
                'proveedor', 'contactos', 'sedes', 'fechas', 'autorizacion', 'convenio', 'cuentas', 'links',
                'certificaciones', 'grupos', 'documentos', 'campos', 'notas',
            ];
            $tab = (string) ($_GET['tab'] ?? 'proveedor');
            if (!in_array($tab, $allowedTabs, true)) {
                $tab = 'proveedor';
            }
            $editVenue = null;
            $editVenueId = (int) ($_GET['edit_venue'] ?? 0);
            if ($tab === 'sedes' && $editVenueId > 0) {
                $editVenue = $repo()->providerVenue($id, $editVenueId);
            }
            $editSitting = null;
            $editSittingId = (int) ($_GET['edit_sitting'] ?? 0);
            if ($tab === 'fechas' && $editSittingId > 0) {
                $sittingRow = $repo()->examSitting($editSittingId);
                if ($sittingRow && (int) $sittingRow['provider_id'] === $id) {
                    $editSitting = $sittingRow;
                }
            }
            $editContact = null;
            $editContactId = (int) ($_GET['edit_contact'] ?? 0);
            if ($tab === 'contactos' && $editContactId > 0) {
                $editContact = $repo()->providerContact($id, $editContactId);
            }
            $editAccount = null;
            $editAccountId = (int) ($_GET['edit_account'] ?? 0);
            if ($tab === 'cuentas' && $editAccountId > 0) {
                $editAccount = $repo()->providerAccount($id, $editAccountId);
            }
            $editLink = null;
            $editLinkId = (int) ($_GET['edit_link'] ?? 0);
            if ($tab === 'links' && $editLinkId > 0) {
                $linkRow = $repo()->providerLink($editLinkId);
                if ($linkRow && (int) $linkRow['provider_id'] === $id) {
                    $editLink = $linkRow;
                }
            }
            $editGroup = null;
            $editGroupId = (int) ($_GET['edit_group'] ?? 0);
            if ($tab === 'grupos' && $editGroupId > 0) {
                $groupRow = $repo()->providerGroup($editGroupId);
                if ($groupRow && (int) $groupRow['provider_id'] === $id) {
                    $editGroup = $groupRow;
                }
            }
            $editDocument = null;
            $editDocId = (int) ($_GET['edit_doc'] ?? 0);
            if ($tab === 'documentos' && $editDocId > 0) {
                $docRow = $repo()->document($editDocId);
                if ($docRow && (int) $docRow['provider_id'] === $id) {
                    $editDocument = $docRow;
                }
            }
            $showForm = isset($_GET['form'])
                || $editVenue !== null
                || $editSitting !== null
                || $editContact !== null
                || $editAccount !== null
                || $editLink !== null
                || $editGroup !== null
                || $editDocument !== null;
            view('admin/providers/form', [
                'title' => 'Editar proveedor',
                'item' => $item,
                'tab' => $tab,
                'agreements' => $repo()->providerAgreements($id),
                'certifications' => $repo()->certificationsByProvider($id),
                'contacts' => $repo()->providerContacts($id),
                'venues' => $repo()->providerVenues($id),
                'exam_sittings' => $repo()->examSittingsForProvider($id),
                'accounts' => $repo()->providerAccounts($id),
                'editVenue' => $editVenue,
                'editSitting' => $editSitting,
                'editContact' => $editContact,
                'editAccount' => $editAccount,
                'editLink' => $editLink,
                'editGroup' => $editGroup,
                'editDocument' => $editDocument,
                'groups' => $repo()->providerGroups($id),
                'provider_documents' => $repo()->documents($id),
                'provider_links' => $repo()->providerLinks($id),
                'provider_reg_fields' => $repo()->getProviderRegistrationFields($id),
                'docTypes' => CatalogRepository::documentTypes(),
                'linkTypes' => CatalogRepository::providerLinkTypes(),
                'appUrl' => rtrim((string) (Env::get('APP_URL', '') ?? ''), '/'),
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

        $router->post('/admin/providers/delete', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            $item = $repo()->provider($id);
            if (!$item) {
                flash('error', 'Proveedor no encontrado.');
                header('Location: /admin/providers');
                exit;
            }
            try {
                $repo()->deleteProvider($id);
                flash('info', 'Proveedor «' . $item['name'] . '» eliminado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: /admin/providers/edit?id=' . $id);
                exit;
            }
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
                $brandWebsite = $existing['brand_website_url'] ?? null;
                $orgKind = (string) ($existing['org_kind'] ?? 'certifier');
                $isActive = $existing ? (int) $existing['is_active'] : 1;

                if ($tab === 'proveedor' || !$existing) {
                    $website = trim((string) ($_POST['website_url'] ?? '')) ?: null;
                    $brandWebsite = trim((string) ($_POST['brand_website_url'] ?? '')) ?: null;
                    $orgKind = (string) ($_POST['org_kind'] ?? $orgKind);
                    if (!isset(CatalogRepository::providerOrgKinds()[$orgKind])) {
                        $orgKind = 'certifier';
                    }
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
                    'org_kind' => $orgKind,
                    'website_url' => $website,
                    'brand_website_url' => $brandWebsite,
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
                $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
                $repo()->addProviderAgreement([
                    'provider_id' => $providerId,
                    'label' => $label,
                    'year' => $year !== '' ? (int) $year : null,
                    'file_path' => $path,
                    'signed_on' => $signedOn !== '' ? $signedOn : null,
                    'notes' => $notes,
                    'is_current' => isset($_POST['is_current']),
                ]);
                flash('info', 'Convenio subido. Puedes tener varios vigentes a la vez.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'convenio'));
            exit;
        });

        $router->post('/admin/providers/agreement/toggle-active', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $agreementId = (int) ($_POST['agreement_id'] ?? 0);
            $activate = isset($_POST['activate']) && (string) $_POST['activate'] === '1';
            $repo()->setProviderAgreementActive($providerId, $agreementId, $activate);
            flash('info', $activate ? 'Convenio reactivado (vigente).' : 'Convenio descontinuado.');
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

        $router->post('/admin/providers/account', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $accountId = (int) ($_POST['account_id'] ?? 0) ?: null;
            $label = trim((string) ($_POST['label'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $portalUrl = trim((string) ($_POST['portal_url'] ?? ''));
            $isSite = $username === '';
            $redirectForm = static function () use ($providerTabUrl, $providerId, $accountId): void {
                $loc = $providerTabUrl($providerId, 'cuentas') . ($accountId ? '&edit_account=' . $accountId : '&form=1');
                header('Location: ' . $loc);
                exit;
            };
            if ($providerId < 1 || $label === '') {
                flash('error', 'La etiqueta del portal/sitio es obligatoria.');
                $redirectForm();
            }
            try {
                if ($isSite) {
                    if ($portalUrl === '') {
                        throw new \RuntimeException('En un sitio (sin usuario) la URL es obligatoria.');
                    }
                    if (trim($password) !== '') {
                        throw new \RuntimeException('Si no hay usuario, no envíes contraseña. Déjala vacía.');
                    }
                } elseif (!$accountId && trim($password) === '') {
                    throw new \RuntimeException('Si hay usuario, la contraseña es obligatoria.');
                }

                $payload = [
                    'provider_id' => $providerId,
                    'label' => $label,
                    'portal_url' => $portalUrl !== '' ? $portalUrl : null,
                    'username' => $isSite ? null : $username,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                if ($accountId) {
                    $existing = $repo()->providerAccount($providerId, $accountId);
                    if (!$existing) {
                        throw new \RuntimeException('Cuenta no encontrada.');
                    }
                    if ($isSite) {
                        $payload['password_enc'] = ''; // limpia credencial
                    } elseif (trim($password) !== '') {
                        $payload['password_enc'] = SecretBox::encrypt($password);
                    } else {
                        // Conserva la contraseña solo si el registro ya tenía usuario/login.
                        if (trim((string) ($existing['username'] ?? '')) === '') {
                            throw new \RuntimeException('Al convertir un sitio en cuenta con usuario, indica la contraseña.');
                        }
                        $payload['password_enc'] = null; // no cambiar
                    }
                    $repo()->updateProviderAccount($accountId, $payload);
                    flash('info', $isSite ? 'Sitio actualizado.' : 'Cuenta actualizada.');
                } else {
                    $payload['password_enc'] = $isSite ? null : SecretBox::encrypt($password);
                    $payload['is_active'] = 1;
                    $repo()->addProviderAccount($payload);
                    flash('info', $isSite ? 'Sitio agregado.' : 'Cuenta agregada.');
                }
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                $redirectForm();
            }
            header('Location: ' . $providerTabUrl($providerId, 'cuentas'));
            exit;
        });

        $router->post('/admin/providers/account/delete', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $accountId = (int) ($_POST['account_id'] ?? 0);
            $repo()->deleteProviderAccount($providerId, $accountId);
            flash('info', 'Cuenta eliminada.');
            header('Location: ' . $providerTabUrl($providerId, 'cuentas'));
            exit;
        });

        $router->post('/admin/providers/account/reveal', static function () use ($repo): void {
            Auth::requireAdmin();
            header('Content-Type: application/json; charset=UTF-8');
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $accountId = (int) ($_POST['account_id'] ?? 0);
            $systemPassword = (string) ($_POST['system_password'] ?? '');
            if (!Auth::verifyCurrentPassword($systemPassword)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Contraseña del sistema incorrecta.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $account = $repo()->providerAccount($providerId, $accountId);
            if (!$account) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Cuenta no encontrada.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (trim((string) ($account['username'] ?? '')) === '' || trim((string) ($account['password_enc'] ?? '')) === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Este registro es un sitio sin contraseña.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            try {
                $plain = SecretBox::decrypt((string) ($account['password_enc'] ?? ''));
            } catch (\Throwable $e) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(['ok' => true, 'password' => $plain], JSON_UNESCAPED_UNICODE);
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

        $router->post('/admin/providers/group/save', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($providerId < 1 || $name === '') {
                flash('error', 'Nombre y proveedor son obligatorios.');
                header('Location: ' . $providerTabUrl($providerId, 'grupos'));
                exit;
            }
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            if ($code === '') {
                $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', Str::slug($name) ?: 'GRUPO') ?? 'GRUPO');
                $code = trim($code, '_') ?: 'GRUPO';
                if (strlen($code) > 48) {
                    $code = substr($code, 0, 48);
                }
            }
            try {
                $repo()->saveProviderGroup([
                    'provider_id' => $providerId,
                    'code' => $code,
                    'name' => $name,
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $id);
                flash('info', $id ? 'Grupo actualizado.' : 'Grupo creado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'grupos'));
            exit;
        });

        $router->post('/admin/providers/group/delete', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $groupId = (int) ($_POST['group_id'] ?? 0);
            try {
                $group = $repo()->providerGroup($groupId);
                if ($group && (int) $group['provider_id'] === $providerId) {
                    $repo()->deleteProviderGroup($groupId);
                    flash('info', 'Grupo eliminado.');
                }
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'grupos'));
            exit;
        });

        $router->post('/admin/providers/group/assign', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $groupId = (int) ($_POST['group_id'] ?? 0);
            $certIds = $_POST['certification_ids'] ?? [];
            if (!is_array($certIds)) {
                $certIds = [];
            }
            try {
                $repo()->assignCertificationsToGroup($providerId, $groupId, $certIds);
                flash('info', 'Certificaciones asignadas al grupo.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'grupos'));
            exit;
        });

        $router->post('/admin/providers/group/assign-all', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            if ($providerId < 1) {
                flash('error', 'Proveedor no válido.');
                header('Location: /admin/providers');
                exit;
            }
            $map = $_POST['cert_group'] ?? [];
            if (!is_array($map)) {
                $map = [];
            }
            try {
                $validGroupIds = [];
                foreach ($repo()->providerGroups($providerId) as $g) {
                    $validGroupIds[(int) $g['id']] = true;
                }
                $certs = $repo()->certificationsByProvider($providerId);
                foreach ($certs as $c) {
                    $certId = (int) $c['id'];
                    if (!array_key_exists((string) $certId, $map) && !array_key_exists($certId, $map)) {
                        continue;
                    }
                    $raw = $map[(string) $certId] ?? $map[$certId] ?? 0;
                    $groupId = (int) $raw;
                    if ($groupId > 0 && !isset($validGroupIds[$groupId])) {
                        $groupId = 0;
                    }
                    $repo()->setCertificationGroup($certId, $groupId > 0 ? $groupId : null);
                }
                flash('info', 'Asignaciones de grupo guardadas.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'grupos'));
            exit;
        });

        $router->post('/admin/providers/document/save', static function () use ($repo, $providerTabUrl, $saveDocumentFromPost): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $existing = $id ? $repo()->document($id) : null;
            if ($id && (!$existing || (int) $existing['provider_id'] !== $providerId)) {
                flash('error', 'Documento no encontrado.');
                header('Location: ' . $providerTabUrl($providerId, 'documentos'));
                exit;
            }
            try {
                $saveDocumentFromPost($repo(), $id, $existing);
                flash('info', 'Documento guardado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'documentos'));
            exit;
        });

        $router->post('/admin/providers/document/delete', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $docId = (int) ($_POST['document_id'] ?? ($_POST['id'] ?? 0));
            try {
                $doc = $repo()->document($docId);
                if ($doc && (int) $doc['provider_id'] === $providerId) {
                    $repo()->deleteDocument($docId);
                    flash('info', 'Documento eliminado.');
                }
            } catch (\Throwable $e) {
                flash('error', 'No se pudo eliminar: ' . $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'documentos'));
            exit;
        });

        $router->post('/admin/providers/link/save', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            if ($providerId < 1) {
                flash('error', 'Proveedor no válido.');
                header('Location: /admin/providers');
                exit;
            }
            if ($id) {
                $existing = $repo()->providerLink($id);
                if (!$existing || (int) $existing['provider_id'] !== $providerId) {
                    flash('error', 'Link no encontrado.');
                    header('Location: ' . $providerTabUrl($providerId, 'links'));
                    exit;
                }
            }
            try {
                $repo()->saveProviderLink([
                    'provider_id' => $providerId,
                    'code' => (string) ($_POST['code'] ?? ''),
                    'label' => (string) ($_POST['label'] ?? ''),
                    'url' => (string) ($_POST['url'] ?? ''),
                    'link_type' => (string) ($_POST['link_type'] ?? 'other'),
                    'scope_type' => (string) ($_POST['scope_type'] ?? 'provider'),
                    'provider_group_id' => $_POST['provider_group_id'] ?? null,
                    'certification_id' => $_POST['certification_id'] ?? null,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $id);
                flash('info', 'Link guardado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'links'));
            exit;
        });

        $router->post('/admin/providers/link/delete', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $linkId = (int) ($_POST['link_id'] ?? ($_POST['id'] ?? 0));
            try {
                $link = $repo()->providerLink($linkId);
                if ($link && (int) $link['provider_id'] === $providerId) {
                    $repo()->deleteProviderLink($linkId);
                    flash('info', 'Link eliminado.');
                }
            } catch (\Throwable $e) {
                flash('error', 'No se pudo eliminar: ' . $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'links'));
            exit;
        });

        $router->post('/admin/providers/fields/save', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            if ($providerId < 1) {
                flash('error', 'Proveedor no válido.');
                header('Location: /admin/providers');
                exit;
            }

            $catalog = CatalogRepository::registrationFieldCatalog();
            $fields = [];
            $builtinKeys = $_POST['builtin_fields'] ?? [];
            if (!is_array($builtinKeys)) {
                $builtinKeys = [];
            }
            foreach ($builtinKeys as $key) {
                $key = (string) $key;
                if (!isset($catalog[$key]) || !empty($catalog[$key]['locked'])) {
                    continue;
                }
                $meta = $catalog[$key];
                $fields[] = [
                    'key' => $key,
                    'label' => (string) ($meta['label'] ?? $key),
                    'type' => (string) ($meta['type'] ?? 'text'),
                    'source' => 'builtin',
                ];
            }

            $rawCustom = $_POST['custom_fields'] ?? [];
            if (is_array($rawCustom)) {
                foreach ($rawCustom as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    if (!empty($row['delete'])) {
                        continue;
                    }
                    $label = trim((string) ($row['label'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $fields[] = [
                        'key' => trim((string) ($row['key'] ?? '')),
                        'label' => $label,
                        'type' => (string) ($row['type'] ?? 'text'),
                        'source' => 'custom',
                    ];
                }
            }

            try {
                $repo()->saveProviderRegistrationFields($providerId, $fields);
                flash('info', 'Campos de adquisición guardados.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $providerTabUrl($providerId, 'campos'));
            exit;
        });

        $router->post('/admin/providers/sitting/save', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $sittingId = (int) ($_POST['sitting_id'] ?? 0) ?: null;
            if ($providerId < 1) {
                flash('error', 'Proveedor inválido.');
                header('Location: /admin/providers');
                exit;
            }
            try {
                $repo()->ensureCambridgeAndSepSchemaAndSeeds();
                $repo()->saveExamSitting([
                    'provider_id' => $providerId,
                    'certification_id' => (int) ($_POST['certification_id'] ?? 0) ?: null,
                    'modality' => (string) ($_POST['modality'] ?? 'online_venue'),
                    'exam_date' => trim((string) ($_POST['exam_date'] ?? '')),
                    'registration_deadline' => trim((string) ($_POST['registration_deadline'] ?? '')),
                    'label' => trim((string) ($_POST['label'] ?? '')),
                    'venue_id' => (int) ($_POST['venue_id'] ?? 0) ?: null,
                    'capacity' => trim((string) ($_POST['capacity'] ?? '')),
                    'notes' => trim((string) ($_POST['notes'] ?? '')),
                    'is_published' => isset($_POST['is_published']) ? 1 : 0,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $sittingId);
                flash('info', 'Fecha de aplicación guardada.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                $loc = $providerTabUrl($providerId, 'fechas') . ($sittingId ? '&edit_sitting=' . $sittingId : '&form=1');
                header('Location: ' . $loc);
                exit;
            }
            header('Location: ' . $providerTabUrl($providerId, 'fechas'));
            exit;
        });

        $router->post('/admin/providers/sitting/delete', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $sittingId = (int) ($_POST['sitting_id'] ?? 0);
            if ($providerId > 0 && $sittingId > 0) {
                $row = $repo()->examSitting($sittingId);
                if ($row && (int) $row['provider_id'] === $providerId) {
                    $repo()->deleteExamSitting($sittingId);
                    flash('info', 'Fecha eliminada.');
                }
            }
            header('Location: ' . $providerTabUrl($providerId, 'fechas'));
            exit;
        });

        $router->post('/admin/providers/sitting/toggle-active', static function () use ($repo, $providerTabUrl): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $sittingId = (int) ($_POST['sitting_id'] ?? 0);
            $active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;
            if ($providerId > 0 && $sittingId > 0) {
                $row = $repo()->examSitting($sittingId);
                if ($row && (int) $row['provider_id'] === $providerId) {
                    $repo()->setExamSittingActive($sittingId, $active === 1);
                    flash('info', $active ? 'Fecha activada.' : 'Fecha desactivada.');
                }
            }
            header('Location: ' . $providerTabUrl($providerId, 'fechas'));
            exit;
        });

        $router->get('/admin/documents', static function () use ($repo): void {
            Auth::requireAdmin();
            $providerFilter = (int) ($_GET['provider_id'] ?? 0) ?: null;
            view('admin/documents/index', [
                'title' => 'Documentos',
                'items' => $repo()->documents($providerFilter),
                'providers' => $repo()->providers(true),
                'docTypes' => CatalogRepository::documentTypes(),
                'providerFilter' => $providerFilter,
                'appUrl' => rtrim((string) (Env::get('APP_URL', '') ?? ''), '/'),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/documents/create', static function () use ($repo): void {
            Auth::requireAdmin();
            $repo()->ensureProviderSetupSchema();
            $groupsByProvider = [];
            $certsByProvider = [];
            foreach ($repo()->providers(true) as $p) {
                $pid = (int) $p['id'];
                $groupsByProvider[$pid] = $repo()->providerGroups($pid, true);
                $certsByProvider[$pid] = $repo()->certificationsByProvider($pid);
            }
            view('admin/documents/form', [
                'title' => 'Nuevo documento',
                'item' => null,
                'providers' => $repo()->providers(true),
                'docTypes' => CatalogRepository::documentTypes(),
                'groupsByProvider' => $groupsByProvider,
                'certsByProvider' => $certsByProvider,
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/documents/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->document($id);
            if (!$item) {
                flash('error', 'Documento no encontrado.');
                header('Location: /admin/documents');
                exit;
            }
            $providerId = (int) ($item['provider_id'] ?? 0);
            view('admin/documents/form', [
                'title' => 'Editar documento',
                'item' => $item,
                'providers' => $repo()->providers(true),
                'docTypes' => CatalogRepository::documentTypes(),
                'groups' => $providerId > 0 ? $repo()->providerGroups($providerId, true) : [],
                'certifications' => $providerId > 0 ? $repo()->certificationsByProvider($providerId) : [],
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/documents/save', static function () use ($repo, $saveDocumentFromPost): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $existing = $id ? $repo()->document($id) : null;
            if ($id && !$existing) {
                flash('error', 'Documento no encontrado.');
                header('Location: /admin/documents');
                exit;
            }

            try {
                $saveDocumentFromPost($repo(), $id, $existing);
                flash('info', 'Documento guardado.');
                header('Location: /admin/documents');
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/documents/edit?id=' . $id : '/admin/documents/create'));
                exit;
            }
        });

        $router->post('/admin/documents/delete', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            try {
                if ($id > 0) {
                    $repo()->deleteDocument($id);
                    flash('info', 'Documento eliminado.');
                }
            } catch (\Throwable $e) {
                flash('error', 'No se pudo eliminar: ' . $e->getMessage());
            }
            header('Location: /admin/documents');
            exit;
        });

        $parseInventoryRows = static function (string $text, ?array $file): array {
            $rows = [];
            $blob = $text;
            if ($file && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $tmp = (string) ($file['tmp_name'] ?? '');
                if ($tmp !== '' && is_readable($tmp)) {
                    $blob = (string) file_get_contents($tmp);
                }
            }
            $blob = str_replace(["\r\n", "\r"], "\n", $blob);
            if (str_starts_with($blob, "\xEF\xBB\xBF")) {
                $blob = substr($blob, 3);
            }
            $lines = preg_split('/\n+/', $blob) ?: [];
            $first = true;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (str_contains($line, "\t")) {
                    $parts = preg_split("/\t+/", $line) ?: [];
                } else {
                    $parts = str_getcsv($line);
                }
                $parts = array_values(array_map(static fn ($p) => trim((string) $p), $parts));
                if ($first) {
                    $first = false;
                    $joined = strtolower(implode('|', $parts));
                    if (str_contains($joined, 'exam') || str_contains($joined, 'access')
                        || str_contains($joined, 'clave') || str_contains($joined, 'folio')) {
                        continue;
                    }
                }
                if (count($parts) < 2) {
                    continue;
                }
                $rows[] = ['exam_id' => $parts[0], 'access_code' => $parts[1]];
            }

            return $rows;
        };

        $router->get('/admin/inventory', static function () use ($repo): void {
            Auth::requireAdmin();
            $repo()->ensureInventoryAndResultColumns();
            $status = trim((string) ($_GET['status'] ?? ''));
            $providerId = (int) ($_GET['provider_id'] ?? 0) ?: null;
            $certId = (int) ($_GET['certification_id'] ?? 0) ?: null;
            view('admin/inventory/index', [
                'title' => 'Inventario de códigos',
                'items' => $repo()->inventoryCodes($status !== '' ? $status : null, $certId, $providerId),
                'counts' => $repo()->inventoryCounts($certId, $providerId),
                'providers' => $repo()->providers(true),
                'certifications' => $repo()->certifications(null),
                'status' => $status,
                'providerFilter' => $providerId,
                'certFilter' => $certId,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/inventory/import', static function () use ($repo, $parseInventoryRows): void {
            Auth::requireAdmin();
            try {
                $rows = $parseInventoryRows(
                    (string) ($_POST['codes_text'] ?? ''),
                    isset($_FILES['codes_file']) ? $_FILES['codes_file'] : null
                );
                if ($rows === []) {
                    throw new \RuntimeException('No se detectaron códigos. Usa ExamID,Contraseña por línea.');
                }
                $result = $repo()->importInventoryCodes(
                    $rows,
                    (int) ($_POST['provider_id'] ?? 0) ?: null,
                    (int) ($_POST['certification_id'] ?? 0) ?: null,
                    trim((string) ($_POST['batch_label'] ?? '')) ?: null
                );
                $msg = 'Importados: ' . $result['inserted'] . ' · omitidos/duplicados: ' . $result['skipped'] . '.';
                if ($result['errors'] !== []) {
                    $msg .= ' ' . implode(' ', $result['errors']);
                }
                flash($result['inserted'] > 0 ? 'info' : 'error', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/inventory');
            exit;
        });

        $router->post('/admin/inventory/void', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            try {
                $repo()->voidInventoryCode($id);
                flash('info', 'Código anulado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/inventory');
            exit;
        });

        $router->get('/admin/actions', static function () use ($repo): void {
            Auth::requireAdmin();
            $actions = new \App\Workflow\ActionRepository();
            $actions->ensureSchema();
            view('admin/actions/index', [
                'title' => 'Acciones',
                'items' => $actions->all(false),
                'handlers' => \App\Workflow\ActionRepository::handlers(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/actions/create', static function () use ($repo): void {
            Auth::requireAdmin();
            (new \App\Workflow\ActionRepository())->ensureSchema();
            view('admin/actions/form', [
                'title' => 'Nueva acción',
                'item' => null,
                'handlers' => \App\Workflow\ActionRepository::handlers(),
                'triggerOptions' => \App\Workflow\ActionRepository::triggerOptions(),
                'requireOptions' => \App\Workflow\ActionRepository::requireOptions(),
                'mail_templates' => $repo()->mailTemplates(true),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/actions/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $actions = new \App\Workflow\ActionRepository();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $actions->find($id);
            if (!$item) {
                flash('error', 'Acción no encontrada.');
                header('Location: /admin/actions');
                exit;
            }
            view('admin/actions/form', [
                'title' => 'Editar acción · ' . $item['code'],
                'item' => $item,
                'handlers' => \App\Workflow\ActionRepository::handlers(),
                'triggerOptions' => \App\Workflow\ActionRepository::triggerOptions(),
                'requireOptions' => \App\Workflow\ActionRepository::requireOptions(),
                'mail_templates' => $repo()->mailTemplates(true),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/actions/save', static function (): void {
            Auth::requireAdmin();
            $actions = new \App\Workflow\ActionRepository();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            try {
                $saved = $actions->save([
                    'code' => (string) ($_POST['code'] ?? ''),
                    'name' => (string) ($_POST['name'] ?? ''),
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'handler' => (string) ($_POST['handler'] ?? 'send_mail'),
                    'mail_template_code' => (string) ($_POST['mail_template_code'] ?? ''),
                    'button_label' => (string) ($_POST['button_label'] ?? ''),
                    'show_as_button' => isset($_POST['show_as_button']),
                    'auto_triggers' => $_POST['auto_triggers'] ?? [],
                    'requires_json' => $_POST['requires_json'] ?? [],
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                    'is_active' => isset($_POST['is_active']),
                ], $id);
                flash('info', 'Acción guardada.');
                header('Location: /admin/actions/edit?id=' . $saved);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/actions/edit?id=' . $id : '/admin/actions/create'));
            }
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
            $tab = trim((string) ($_GET['tab'] ?? 'general'));
            if ($tab !== 'general') {
                $tab = 'general';
            }
            view('admin/protocols/form', [
                'title' => 'Nuevo protocolo',
                'tab' => $tab,
                'item' => null,
                'steps' => [],
                'providers' => $repo()->providers(true),
                'export_formats' => \App\Exports\ProviderExportGenerator::formats(),
                'mail_templates' => $repo()->mailTemplates(true),
                'workflow_actions' => (new \App\Workflow\ActionRepository())->all(true),
                'protocol_action_ids' => [],
                'phases' => CatalogRepository::protocolPhases(),
                'responsibles' => CatalogRepository::protocolResponsibles(),
                'error' => flash('error'),
                'info' => flash('info'),
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
            $actionRepo = new \App\Workflow\ActionRepository();
            $assigned = $actionRepo->protocolActions($id, false);
            $tab = trim((string) ($_GET['tab'] ?? 'general'));
            $allowedTabs = ['general', 'requisitos', 'correos', 'acciones', 'pasos'];
            if (!in_array($tab, $allowedTabs, true)) {
                $tab = 'general';
            }
            if ((int) ($_GET['step'] ?? 0) > 0) {
                $tab = 'pasos';
            }
            view('admin/protocols/form', [
                'title' => 'Editar protocolo',
                'tab' => $tab,
                'item' => $item,
                'steps' => $repo()->protocolSteps($id),
                'providers' => $repo()->providers(true),
                'export_formats' => \App\Exports\ProviderExportGenerator::formats(),
                'mail_templates' => $repo()->mailTemplates(true),
                'workflow_actions' => $actionRepo->all(true),
                'protocol_action_ids' => array_map(static fn (array $a): int => (int) $a['id'], $assigned),
                'phases' => CatalogRepository::protocolPhases(),
                'responsibles' => CatalogRepository::protocolResponsibles(),
                'error' => flash('error'),
                'info' => flash('info'),
            ]);
        });

        $router->post('/admin/protocols/save', static function () use ($repo, $protocolEditUrl): void {
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
                $savedId = $repo()->saveProtocol([
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
                    'export_format' => (string) ($_POST['export_format'] ?? 'none'),
                    'provider_request_template' => trim((string) ($_POST['provider_request_template'] ?? '')) ?: null,
                    'student_access_template' => trim((string) ($_POST['student_access_template'] ?? '')) ?: null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $id);
                $actionIds = $_POST['action_ids'] ?? [];
                if (!is_array($actionIds)) {
                    $actionIds = [];
                }
                (new \App\Workflow\ActionRepository())->setProtocolActions($savedId, $actionIds);
                flash('info', 'Protocolo y acciones guardados.');
                header('Location: ' . $protocolEditUrl($savedId));
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? $protocolEditUrl($id) : '/admin/protocols/create'));
                exit;
            }
        });

        $router->post('/admin/protocols/steps/save', static function () use ($repo, $protocolEditUrl): void {
            Auth::requireAdmin();
            $protocolId = (int) ($_POST['protocol_id'] ?? 0);
            $stepId = (int) ($_POST['step_id'] ?? 0) ?: null;
            $title = trim((string) ($_POST['title'] ?? ''));
            if ($protocolId <= 0 || $title === '') {
                flash('error', 'Protocolo y título del paso son obligatorios.');
                header('Location: /admin/protocols');
                exit;
            }
            if (!$repo()->protocol($protocolId)) {
                flash('error', 'Protocolo no encontrado.');
                header('Location: /admin/protocols');
                exit;
            }

            $phase = (string) ($_POST['phase'] ?? 'pre_exam');
            if (!isset(CatalogRepository::protocolPhases()[$phase])) {
                $phase = 'pre_exam';
            }
            $responsible = (string) ($_POST['responsible'] ?? 'student');
            if (!isset(CatalogRepository::protocolResponsibles()[$responsible])) {
                $responsible = 'student';
            }
            $orderRaw = trim((string) ($_POST['sort_order'] ?? ''));
            $sortOrder = $orderRaw !== '' ? (int) $orderRaw : $repo()->nextProtocolStepOrder($protocolId);
            $triggerRaw = trim((string) ($_POST['trigger_days_after_exam'] ?? ''));

            try {
                $repo()->saveProtocolStep([
                    'protocol_id' => $protocolId,
                    'sort_order' => $sortOrder,
                    'phase' => $phase,
                    'title' => $title,
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'responsible' => $responsible,
                    'trigger_days_after_exam' => $triggerRaw !== '' ? (int) $triggerRaw : null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $stepId);
                flash('info', 'Paso guardado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $protocolEditUrl($protocolId, 'pasos'));
            exit;
        });

        $router->post('/admin/protocols/steps/delete', static function () use ($repo, $protocolEditUrl): void {
            Auth::requireAdmin();
            $protocolId = (int) ($_POST['protocol_id'] ?? 0);
            $stepId = (int) ($_POST['step_id'] ?? 0);
            try {
                if ($stepId > 0) {
                    $repo()->deleteProtocolStep($stepId);
                    flash('info', 'Paso eliminado.');
                }
            } catch (\Throwable $e) {
                flash('error', 'No se pudo eliminar (puede estar en uso en un caso): ' . $e->getMessage());
            }
            header('Location: ' . $protocolEditUrl($protocolId, 'pasos'));
            exit;
        });

        $router->post('/admin/protocols/steps/move', static function () use ($repo, $protocolEditUrl): void {
            Auth::requireAdmin();
            $protocolId = (int) ($_POST['protocol_id'] ?? 0);
            $stepId = (int) ($_POST['step_id'] ?? 0);
            $direction = (string) ($_POST['direction'] ?? '');
            try {
                if ($protocolId <= 0 || $stepId <= 0) {
                    throw new \RuntimeException('Protocolo y paso son obligatorios.');
                }
                if (!$repo()->protocol($protocolId)) {
                    throw new \RuntimeException('Protocolo no encontrado.');
                }
                $repo()->moveProtocolStep($protocolId, $stepId, $direction);
                flash('info', 'Orden de pasos actualizado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $protocolEditUrl($protocolId, 'pasos'));
            exit;
        });

        $router->post('/admin/protocols/steps/reorder', static function () use ($repo, $protocolEditUrl): void {
            Auth::requireAdmin();
            $protocolId = (int) ($_POST['protocol_id'] ?? 0);
            $orderRaw = $_POST['step_order'] ?? [];
            if (!is_array($orderRaw)) {
                $orderRaw = [];
            }
            $orderedIds = array_values(array_filter(array_map('intval', $orderRaw)));
            try {
                if ($protocolId <= 0) {
                    throw new \RuntimeException('Protocolo inválido.');
                }
                if (!$repo()->protocol($protocolId)) {
                    throw new \RuntimeException('Protocolo no encontrado.');
                }
                $repo()->reorderProtocolSteps($protocolId, $orderedIds);
                flash('info', 'Pasos reordenados.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $protocolEditUrl($protocolId, 'pasos'));
            exit;
        });

        $router->get('/admin/pendientes', static function () use ($repo): void {
            Auth::requireAdmin();
            $filter = trim((string) ($_GET['filter'] ?? 'needs_admin'));
            $allowed = array_keys(\App\Catalog\CatalogRepository::caseAttentionFilters());
            if (!in_array($filter, $allowed, true)) {
                $filter = 'needs_admin';
            }
            view('admin/pendientes', [
                'title' => 'Pendientes operativos',
                'items' => $repo()->opsBoardCases($filter, 300),
                'pending_prorrogas' => $repo()->pendingCourseProrrogas(50),
                'filter' => $filter,
                'filters' => \App\Catalog\CatalogRepository::caseAttentionFilters(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/cases', static function () use ($repo): void {
            Auth::requireAdmin();
            (new \App\Workflow\ActionRepository())->ensureSchema();
            $items = $repo()->certificationCases(200);
            $runner = new \App\Workflow\ActionRunner($repo());
            $caseButtons = [];
            foreach ($items as $item) {
                $caseButtons[(int) $item['id']] = $runner->buttonsForCase($item);
            }
            view('admin/cases/index', [
                'title' => 'Casos de certificación',
                'items' => $items,
                'case_buttons' => $caseButtons,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/cases/run-action', static function () use ($repo): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $actionId = (int) ($_POST['action_id'] ?? 0);
            $user = Auth::user();
            $returnTo = trim((string) ($_POST['return_to'] ?? ''));
            if ($returnTo === '' || !str_starts_with($returnTo, '/admin/cases')) {
                $returnTo = '/admin/cases';
            }
            try {
                $runner = new \App\Workflow\ActionRunner($repo());
                $result = $runner->run(
                    $caseId,
                    $actionId,
                    'button',
                    $user ? (int) $user['id'] : null,
                    isset($_FILES['payment_proof']) ? $_FILES['payment_proof'] : null
                );
                flash($result['ok'] ? 'info' : 'error', $result['message']);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $returnTo);
            exit;
        });

        $router->get('/admin/cases/create', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/cases/form', [
                'title' => 'Abrir caso',
                'certifications' => $repo()->certifications(),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/cases/save', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $certId = (int) ($_POST['certification_id'] ?? 0);
            $name = trim((string) ($_POST['student_name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['student_email'] ?? '')));
            if ($certId <= 0 || $name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Certificación, nombre y correo válido son obligatorios.');
                header('Location: /admin/cases/create');
                exit;
            }
            try {
                $examDate = trim((string) ($_POST['exam_date'] ?? ''));
                $caseId = $repo()->openCertificationCase([
                    'certification_id' => $certId,
                    'student_name' => $name,
                    'student_email' => $email,
                    'exam_date' => $examDate !== '' ? $examDate : null,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                ]);
                try {
                    (new \App\Workflow\ActionRunner($repo()))->runTriggers(
                        $caseId,
                        'registration_complete',
                        Auth::user() ? (int) Auth::user()['id'] : null
                    );
                } catch (\Throwable) {
                }
                try {
                    (new \App\Payments\OpenPayPaymentService($repo()))->ensureSpeiCharge($caseId, false, true);
                    flash('info', 'Caso abierto con CLABE OpenPay. El alumno inicia en el paso 1 del protocolo.');
                } catch (\Throwable $payErr) {
                    flash('info', 'Caso abierto. OpenPay aún no generó CLABE: ' . $payErr->getMessage());
                }
                header('Location: ' . $caseViewUrl($caseId));
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: /admin/cases/create');
                exit;
            }
        });

        $router->get('/admin/cases/view', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $repo()->ensureInventoryAndResultColumns();
            $item = $repo()->certificationCaseDetailed($id);
            if (!$item) {
                flash('error', 'Caso no encontrado.');
                header('Location: /admin/cases');
                exit;
            }
            $regulationDoc = null;
            $regDocId = (int) ($item['regulation_document_id'] ?? 0);
            if ($regDocId > 0) {
                $regulationDoc = $repo()->document($regDocId);
            }
            $tab = trim((string) ($_GET['tab'] ?? 'alumno'));
            $requiresRegulation = !empty($item['requires_regulation_signature']);
            $cenniProcess = (string) ($item['cenni_process'] ?? 'none');
            $hasCenni = $cenniProcess !== '' && $cenniProcess !== 'none';
            $allowedCaseTabs = ['alumno', 'accesos', 'resultados', 'pago', 'operacion', 'adjuntos', 'protocolo'];
            if ($requiresRegulation) {
                array_splice($allowedCaseTabs, 1, 0, ['reglamento']);
            }
            if ($hasCenni) {
                $opIdx = array_search('operacion', $allowedCaseTabs, true);
                if ($opIdx === false) {
                    $allowedCaseTabs[] = 'cenni';
                } else {
                    array_splice($allowedCaseTabs, $opIdx + 1, 0, ['cenni']);
                }
            }
            if (!in_array($tab, $allowedCaseTabs, true)) {
                $tab = 'alumno';
            }
            $providerId = (int) ($item['provider_id'] ?? 0);
            $providerFields = $providerId > 0 ? $repo()->availableFieldsForCertification($providerId) : [];
            $studentFields = CatalogRepository::caseAdminVisibleStudentFields(
                $item['registration_fields_json'] ?? null,
                $providerFields
            );
            try {
                $mailSvc = new \App\Mail\CaseMailService($repo());
                $mailSvc->ensureItepStudentResultTemplates();
                $mailSvc->ensureCenniMailTemplates();
            } catch (\Throwable) {
            }
            view('admin/cases/show', [
                'title' => 'Caso #' . $id,
                'tab' => $tab,
                'item' => $item,
                'case_tabs' => $allowedCaseTabs,
                'student_fields' => $studentFields,
                'requires_regulation' => $requiresRegulation,
                'regulation_doc' => $regulationDoc,
                'steps' => $repo()->certificationCaseSteps($id),
                'attachments' => $repo()->caseAttachments($id),
                'cenni_docs_case' => $hasCenni ? $repo()->caseCenniDocuments($id) : [],
                'payment_share_url' => (static function () use ($repo, $item, $id): string {
                    $rel = trim((string) ($item['payment_proof_path'] ?? ''));
                    if ($rel === '') {
                        return '';
                    }
                    $att = $repo()->ensureCaseFileShare($id, 'payment', $rel, 'Comprobante de pago');

                    return $att ? $repo()->caseAttachmentShareUrl($att) : '';
                })(),
                'provider_payment_share_url' => (static function () use ($repo, $item, $id): string {
                    $repo()->ensureUksFlowSchemaAndSeeds();
                    $rel = trim((string) ($item['provider_payment_proof_path'] ?? ''));
                    if ($rel === '') {
                        return '';
                    }
                    $att = $repo()->ensureCaseFileShare($id, 'provider_payment', $rel, 'Comprobante Doceo → proveedor');

                    return $att ? $repo()->caseAttachmentShareUrl($att) : '';
                })(),
                'export_share_url' => (static function () use ($repo, $item, $id): string {
                    $rel = trim((string) ($item['provider_export_path'] ?? ''));
                    if ($rel === '') {
                        return '';
                    }
                    $att = $repo()->ensureCaseFileShare($id, 'export', $rel, 'Exportación proveedor');

                    return $att ? $repo()->caseAttachmentShareUrl($att) : '';
                })(),
                'mail_log' => $repo()->caseMailLog($id),
                'access_mail_sent' => $repo()->caseAccessMailAlreadySent(
                    $id,
                    trim((string) ($item['student_access_template'] ?? '')) ?: null
                ),
                'mail_templates' => $repo()->mailTemplates(true),
                'export_formats' => \App\Exports\ProviderExportGenerator::formats(),
                'cenni_statuses' => \App\Payments\OpenPayPaymentService::cenniStatuses(),
                'cenni_processes' => \App\Payments\OpenPayPaymentService::cenniProcesses(),
                'moodle_enrolments' => $repo()->caseMoodleEnrolments($id),
                'course_prorrogas' => $repo()->courseProrrogasForCase($id),
                'phases' => CatalogRepository::protocolPhases(),
                'responsibles' => CatalogRepository::protocolResponsibles(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/cases/update', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            if ($caseId <= 0 || !$repo()->certificationCase($caseId)) {
                flash('error', 'Caso no encontrado.');
                header('Location: /admin/cases');
                exit;
            }
            try {
                $map = [
                    'student_name', 'student_last_name_p', 'student_last_name_m', 'student_email', 'student_phone',
                    'student_curp', 'student_birth_date', 'student_sex', 'student_nationality',
                    'exam_date', 'exam_time', 'reschedule_date', 'reschedule_time',
                    'folio_id', 'access_key', 'zoom_url', 'prep_doc_url', 'access_doc_url',
                    'moodle_user', 'moodle_password', 'results_url', 'score_url', 'certificate_url',
                    'exam_outcome', 'invalidation_reason', 'cancel_reason', 'cc_email', 'notes',
                ];
                $fields = [];
                foreach ($map as $key) {
                    if (!array_key_exists($key, $_POST)) {
                        continue;
                    }
                    $val = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
                    if ($key === 'student_email' || $key === 'cc_email') {
                        $val = strtolower((string) $val);
                    }
                    $fields[$key] = $val;
                }
                $repo()->updateCertificationCase($caseId, $fields);
                $caseAfter = $repo()->certificationCaseDetailed($caseId);
                if ($caseAfter) {
                    $folioReady = trim((string) ($caseAfter['folio_id'] ?? '')) !== ''
                        && trim((string) ($caseAfter['access_key'] ?? '')) !== '';
                    $moodleReady = trim((string) ($caseAfter['moodle_user'] ?? '')) !== '';
                    if ($folioReady || $moodleReady) {
                        try {
                            (new \App\Workflow\ActionRunner($repo()))->runTriggers(
                                $caseId,
                                'access_data_ready',
                                Auth::user() ? (int) Auth::user()['id'] : null
                            );
                        } catch (\Throwable) {
                        }
                    }
                }
                flash('info', 'Datos del caso guardados.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/confirm-payment', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $result = $svc->confirmPaymentAndRequestProvider(
                    $caseId,
                    isset($_FILES['payment_proof']) ? $_FILES['payment_proof'] : null,
                    $user ? (int) $user['id'] : null
                );
                $msg = 'Pago confirmado.';
                if ($result['export']) {
                    $msg .= ' Exportación generada: ' . $result['export']['filename'] . '.';
                }
                if ($result['mailed']) {
                    $msg .= ' Correo (“' . ($result['template'] ?? '') . '”) enviado a ' . $result['to'] . '.';
                    if (!empty($result['links_only']) && is_array($result['links_only'])) {
                        $msg .= ' Links en el correo: ' . implode(', ', $result['links_only']) . '.';
                    }
                    $flashType = 'info';
                } else {
                    $msg .= ' ' . ($result['mail_skip'] ?? 'No se envió correo al proveedor.');
                    $flashType = 'error';
                }
                $moodle = $result['moodle'] ?? null;
                if (is_array($moodle) && empty($moodle['skipped']) && empty($moodle['error'])) {
                    $msg .= ' Moodle: ' . (!empty($moodle['created_user']) ? 'usuario creado' : 'usuario existente')
                        . ' · ' . count($moodle['enrolled'] ?? []) . ' curso(s).';
                } elseif (is_array($moodle) && !empty($moodle['error'])) {
                    $msg .= ' Moodle: ' . $moodle['error'];
                }
                $inv = $result['fulfill']['inventory'] ?? null;
                if (is_array($inv)) {
                    if (!empty($inv['assigned'])) {
                        $msg .= ' Código inventario: ' . ($inv['exam_id'] ?? '') . '.';
                    } elseif (!empty($inv['error'])) {
                        $msg .= ' Inventario: ' . $inv['error'];
                        $flashType = 'error';
                    }
                }
                $accessMail = $result['fulfill']['access_mail'] ?? null;
                if (is_array($accessMail)) {
                    if (!empty($accessMail['sent'])) {
                        $msg .= ' Acceso alumno (“' . ($accessMail['template'] ?? '') . '”) enviado.';
                    } elseif (!empty($accessMail['error'])) {
                        $msg .= ' Acceso alumno: ' . $accessMail['error'];
                    }
                }
                flash($flashType, $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/mark-payment', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $result = $svc->markPaymentReceived(
                    $caseId,
                    trim((string) ($_POST['payment_method'] ?? 'other')),
                    isset($_FILES['payment_proof']) ? $_FILES['payment_proof'] : null,
                    trim((string) ($_POST['payment_note'] ?? '')) ?: null,
                    $user ? (int) $user['id'] : null
                );
                $labels = [
                    'cash' => 'efectivo',
                    'transfer' => 'transferencia',
                    'openpay' => 'OpenPay',
                    'other' => 'otro',
                ];
                $label = $labels[$result['payment_method']] ?? $result['payment_method'];
                $msg = 'Pago marcado como recibido (' . $label . ') el ' . $result['payment_confirmed_at']
                    . '. El alumno ya puede continuar. Esto no envía correo al proveedor.';
                $moodle = $result['moodle'] ?? null;
                if (is_array($moodle) && empty($moodle['skipped']) && empty($moodle['error'])) {
                    $msg .= ' Moodle: ' . (!empty($moodle['created_user']) ? 'usuario creado' : 'usuario existente')
                        . ' · ' . count($moodle['enrolled'] ?? []) . ' curso(s).';
                } elseif (is_array($moodle) && !empty($moodle['error'])) {
                    $msg .= ' Moodle: ' . $moodle['error'];
                }
                $inv = $result['fulfill']['inventory'] ?? null;
                if (is_array($inv)) {
                    if (!empty($inv['assigned'])) {
                        $msg .= ' Código inventario: ' . ($inv['exam_id'] ?? '') . '.';
                    } elseif (!empty($inv['error'])) {
                        $msg .= ' Inventario: ' . $inv['error'];
                    }
                }
                $accessMail = $result['fulfill']['access_mail'] ?? null;
                if (is_array($accessMail) && !empty($accessMail['sent'])) {
                    $msg .= ' Acceso alumno (“' . ($accessMail['template'] ?? '') . '”) enviado.';
                } elseif (is_array($accessMail) && !empty($accessMail['error'])) {
                    $msg .= ' Acceso alumno: ' . $accessMail['error'];
                }
                flash('info', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/openpay-spei', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $force = isset($_POST['force_new']);
            try {
                $fields = (new \App\Payments\OpenPayPaymentService($repo()))->ensureSpeiCharge($caseId, $force, true);
                flash('info', 'CLABE OpenPay lista: ' . ($fields['openpay_clabe'] ?? '') . ' · $' . number_format((float) ($fields['openpay_amount'] ?? 0), 2));
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/cenni-status', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $svc->ensureCenniMailTemplates();
                $result = $svc->updateCenniStatus(
                    $caseId,
                    trim((string) ($_POST['cenni_status'] ?? 'none')),
                    trim((string) ($_POST['cenni_folio'] ?? '')) ?: null,
                    trim((string) ($_POST['cenni_notes'] ?? '')) ?: null,
                    true,
                    $user ? (int) $user['id'] : null,
                    array_key_exists('cenni_download_url', $_POST)
                        ? trim((string) $_POST['cenni_download_url'])
                        : null,
                    array_key_exists('cenni_sep_url', $_POST)
                        ? trim((string) $_POST['cenni_sep_url'])
                        : null,
                    trim((string) ($_POST['template_code'] ?? '')) ?: null
                );
                $msg = 'Estatus CENNI actualizado: ' . $result['status'] . '.';
                if (!empty($result['mailed'])) {
                    $msg .= ' Correo (“' . ($result['template'] ?? '') . '”) enviado'
                        . (!empty($result['to']) ? ' a ' . $result['to'] : '') . '.';
                }
                flash('info', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId, 'cenni'));
            exit;
        });

        $router->post('/admin/cases/cenni-doc-review', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $attachmentId = (int) ($_POST['attachment_id'] ?? 0);
            $status = trim((string) ($_POST['review_status'] ?? ''));
            $user = Auth::user();
            try {
                $att = $repo()->caseAttachment($attachmentId);
                if (!$att || (int) ($att['case_id'] ?? 0) !== $caseId) {
                    throw new \RuntimeException('Documento no pertenece a este caso.');
                }
                $repo()->reviewCaseAttachment(
                    $attachmentId,
                    $status,
                    trim((string) ($_POST['review_notes'] ?? '')) ?: null,
                    $user ? (int) $user['id'] : null
                );
                $label = trim((string) ($att['label'] ?? $att['kind'] ?? 'Documento'));
                flash(
                    'info',
                    $status === 'approved'
                        ? $label . ' aprobado.'
                        : $label . ' rechazado.'
                );
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId, 'cenni'));
            exit;
        });

        $router->post('/admin/cases/cenni-notify-docs', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $result = $svc->notifyCenniDocumentReview(
                    $caseId,
                    $user ? (int) $user['id'] : null
                );
                $msg = 'Revisión CENNI registrada (' . $result['status'] . ').';
                if (!empty($result['mailed'])) {
                    $msg .= ' Correo (“' . ($result['template'] ?? '') . '”) enviado'
                        . (!empty($result['to']) ? ' a ' . $result['to'] : '') . '.';
                }
                flash('info', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId, 'cenni'));
            exit;
        });

        $router->post('/admin/cases/regenerate-export', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $export = $svc->regenerateExport($caseId, $user ? (int) $user['id'] : null);
                flash('info', 'Archivo regenerado: ' . $export['filename']);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/regenerate-signed-regulation', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $result = $repo()->regenerateCaseRegulationSignedPdf(
                    $caseId,
                    $user ? (int) $user['id'] : null
                );
                flash('info', 'PDF del reglamento regenerado con la hoja de firma al final.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId, 'reglamento'));
            exit;
        });

        $router->post('/admin/cases/send-provider-request', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $result = $svc->sendProviderRequest(
                    $caseId,
                    isset($_FILES['payment_proof']) ? $_FILES['payment_proof'] : null,
                    $user ? (int) $user['id'] : null,
                    !isset($_POST['skip_export'])
                );
                $msg = 'Solicitud al proveedor enviada con plantilla “' . ($result['template'] ?? '') . '”';
                if (!empty($result['to'])) {
                    $msg .= ' a ' . $result['to'];
                }
                $msg .= '.';
                if (!empty($result['export']['filename'])) {
                    $msg .= ' Exportación: ' . $result['export']['filename'] . '.';
                }
                if (!empty($result['links_only']) && is_array($result['links_only'])) {
                    $msg .= ' Links: ' . implode(', ', $result['links_only']) . '.';
                }
                flash('info', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/uks-post-exam-thanks', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $result = $svc->sendUksPostExamThanks($caseId, $user ? (int) $user['id'] : null);
                if (!empty($result['ok'])) {
                    flash('info', 'Correo post-examen enviado'
                        . (!empty($result['to']) ? ' a ' . $result['to'] : '') . '.');
                } else {
                    flash('error', $result['error'] ?? 'No se pudo enviar el correo post-examen.');
                }
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId, 'operacion'));
            exit;
        });

        $router->post('/admin/cases/reschedule', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $result = $svc->rescheduleAndNotifyProvider(
                    $caseId,
                    trim((string) ($_POST['reschedule_date'] ?? '')),
                    trim((string) ($_POST['reschedule_time'] ?? '')),
                    trim((string) ($_POST['reschedule_reason'] ?? '')) ?: null,
                    isset($_FILES['payment_proof']) ? $_FILES['payment_proof'] : null,
                    $user ? (int) $user['id'] : null,
                    !isset($_POST['skip_notify'])
                );
                if (!empty($result['mailed'])) {
                    flash('info', 'Reagenda guardada y correo enviado a ' . ($result['to'] ?? '')
                        . ' (plantilla ' . ($result['template'] ?? '') . ').');
                } else {
                    flash('info', 'Reagenda guardada sin notificar al proveedor.');
                }
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/send-mail', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $code = trim((string) ($_POST['template_code'] ?? ''));
            $user = Auth::user();
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                if (isset($_FILES['payment_proof'])
                    && (int) ($_FILES['payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
                ) {
                    $path = \App\Support\Uploader::store($_FILES['payment_proof'], 'cases/' . $caseId);
                    $repo()->addCaseAttachment(
                        $caseId,
                        'payment',
                        'Comprobante de pago',
                        $path,
                        $user ? (int) $user['id'] : null
                    );
                    $repo()->updateCertificationCase($caseId, [
                        'payment_proof_path' => $path,
                    ]);
                }
                $result = $svc->sendTemplate($caseId, $code, $user ? (int) $user['id'] : null);
                flash('info', 'Correo enviado a ' . $result['to'] . ' (' . $result['subject'] . ').');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->get('/admin/cases/download-export', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_GET['id'] ?? 0);
            $item = $repo()->certificationCase($caseId);
            if (!$item || empty($item['provider_export_path'])) {
                flash('error', 'No hay archivo de exportación en este caso.');
                header('Location: ' . $caseViewUrl($caseId));
                exit;
            }
            $rel = ltrim((string) $item['provider_export_path'], '/');
            $abs = BASE_PATH . '/storage/' . $rel;
            if (!is_file($abs)) {
                flash('error', 'El archivo ya no existe en disco.');
                header('Location: ' . $caseViewUrl($caseId));
                exit;
            }
            $name = basename($abs);
            $mime = str_ends_with(strtolower($name), '.csv')
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . (string) filesize($abs));
            readfile($abs);
            exit;
        });

        $router->post('/admin/cases/complete-step', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $caseStepId = (int) ($_POST['case_step_id'] ?? 0);
            $user = Auth::user();
            try {
                $repo()->completeCaseStep(
                    $caseId,
                    $caseStepId,
                    $user ? (int) $user['id'] : null,
                    trim((string) ($_POST['notes'] ?? '')) ?: null
                );
                flash('info', 'Paso marcado como hecho. Se avanzó al siguiente.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->get('/admin/courses', static function () use ($repo): void {
            Auth::requireAdmin();
            $repo()->ensureCourseAccessTables();
            view('admin/courses/index', [
                'title' => 'Cursos',
                'items' => $repo()->coursesWithCertificationLinks(),
                'certifications' => $repo()->certifications(),
                'relationTypes' => CatalogRepository::courseRelationTypes(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/courses/attach-certification', static function () use ($repo): void {
            Auth::requireAdmin();
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $certificationId = (int) ($_POST['certification_id'] ?? 0);
            $relationType = (string) ($_POST['relation_type'] ?? 'included');
            $priceRaw = trim((string) ($_POST['bundle_price'] ?? ''));
            if ($courseId < 1 || $certificationId < 1) {
                flash('error', 'Selecciona la certificación a vincular.');
                header('Location: /admin/courses');
                exit;
            }
            try {
                $needsPrice = $relationType === 'sold_separate';
                $price = null;
                if ($needsPrice) {
                    if ($priceRaw === '' || !is_numeric($priceRaw)) {
                        throw new \InvalidArgumentException('Indica el precio del curso (vendido por separado).');
                    }
                    $price = (float) $priceRaw;
                } elseif ($relationType === 'bundle_discount' && $priceRaw !== '' && is_numeric($priceRaw)) {
                    $price = (float) $priceRaw;
                }
                $repo()->attachCertificationCourse(
                    $certificationId,
                    $courseId,
                    $relationType,
                    $price,
                    trim((string) ($_POST['notes'] ?? '')) ?: null
                );
                flash('info', 'Curso vinculado a la certificación.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/courses');
            exit;
        });

        $router->post('/admin/courses/detach-certification', static function () use ($repo): void {
            Auth::requireAdmin();
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $certificationId = (int) ($_POST['certification_id'] ?? 0);
            $repo()->detachCertificationCourse($certificationId, $courseId);
            flash('info', 'Vínculo con la certificación eliminado.');
            header('Location: /admin/courses');
            exit;
        });

        $router->get('/admin/courses/create', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/courses/form', [
                'title' => 'Nuevo curso',
                'item' => null,
                'protocols' => $repo()->protocols(true),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/courses/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $repo()->ensureCourseAccessTables();
            $repo()->ensureProductAssetsSchema();
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
                'protocols' => $repo()->protocols(true),
                'assets' => $repo()->assets('course', $id),
                'assetTypes' => CatalogRepository::assetTypesFor('course'),
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
                $protocolId = (int) ($_POST['protocol_id'] ?? 0);
                $standalone = isset($_POST['standalone']) ? 1 : 0;
                $savedId = $repo()->saveCourse([
                    'protocol_id' => $protocolId > 0 ? $protocolId : null,
                    'code' => $code,
                    'name' => $name,
                    'platform_type' => (string) ($_POST['platform_type'] ?? 'moodle'),
                    'external_url' => trim((string) ($_POST['external_url'] ?? '')) ?: null,
                    'moodle_course_id' => $moodleId !== '' ? (int) $moodleId : null,
                    'access_months' => (int) ($_POST['access_months'] ?? 6) ?: 6,
                    'prorroga_price' => trim((string) ($_POST['prorroga_price'] ?? '')) !== ''
                        ? (float) $_POST['prorroga_price']
                        : null,
                    'access_notes' => trim((string) ($_POST['access_notes'] ?? '')) ?: null,
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'standalone' => $standalone,
                ], $id);
                if ($standalone === 1) {
                    $repo()->setCourseStandalone($savedId, true);
                }
                flash('info', 'Curso guardado.');
                header('Location: /admin/courses');
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/courses/edit?id=' . $id : '/admin/courses/create'));
                exit;
            }
        });

        $router->post('/admin/courses/delete', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id < 1) {
                flash('error', 'Curso inválido.');
                header('Location: /admin/courses');
                exit;
            }
            try {
                $result = $repo()->deleteCourse($id);
                if (!empty($result['deactivated'])) {
                    flash(
                        'info',
                        'El curso tiene matrículas Moodle históricas: se desactivó y quedó marcado como sin certificación (no se eliminó el registro).'
                    );
                } else {
                    flash('info', 'Curso eliminado.');
                }
            } catch (\Throwable $e) {
                flash('error', 'No se pudo eliminar el curso: ' . $e->getMessage());
            }
            header('Location: /admin/courses');
            exit;
        });

        $router->post('/admin/courses/mark-standalone', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            $standalone = !isset($_POST['standalone']) || (int) $_POST['standalone'] === 1;
            if ($id < 1) {
                flash('error', 'Curso inválido.');
                header('Location: /admin/courses');
                exit;
            }
            try {
                $repo()->setCourseStandalone($id, $standalone);
                flash(
                    'info',
                    $standalone
                        ? 'Curso marcado como sin certificación (no requiere vínculo).'
                        : 'Curso vuelve a poder vincularse con una certificación.'
                );
            } catch (\Throwable $e) {
                flash('error', 'No se pudo actualizar el curso: ' . $e->getMessage());
            }
            $back = trim((string) ($_POST['redirect'] ?? ''));
            header('Location: ' . ($back !== '' ? $back : '/admin/courses'));
            exit;
        });

        $router->get('/admin/tiers', static function (): void {
            Auth::requireAdmin();
            header('Location: /admin/partners?tab=niveles');
            exit;
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
                header('Location: /admin/partners?tab=niveles');
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
                flash('info', 'Nivel TR guardado.');
                header('Location: /admin/partners?tab=niveles');
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
                'title' => 'Versiones de convenio TR',
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
                'assignments' => [],
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
                'title' => 'Editar / publicar convenio',
                'item' => $item,
                'tiers' => $repo()->partnerTiers(true),
                'assignments' => $repo()->agreementAssignments($id),
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
                $existing = $id ? $repo()->agreement($id) : null;
                $pdfPath = $existing['pdf_path'] ?? null;
                $file = $_FILES['blank_pdf'] ?? null;
                if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $pdfPath = Uploader::store($file, 'agreements');
                }

                $savedId = $repo()->saveAgreement([
                    'partner_tier_id' => $tierId,
                    'name' => $name,
                    'year' => (int) ($_POST['year'] ?? date('Y')),
                    'valid_from' => (string) ($_POST['valid_from'] ?? date('Y-01-01')),
                    'valid_to' => trim((string) ($_POST['valid_to'] ?? '')) ?: null,
                    'pdf_path' => $pdfPath,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'is_current' => 0,
                    'sign_deadline_days' => max(1, (int) ($_POST['sign_deadline_days'] ?? 15)),
                ], $id);
                flash('info', 'Versión guardada. Usa “Publicar a partners del nivel” para asignarla y notificar.');
                header('Location: /admin/agreements/edit?id=' . $savedId);
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/agreements/edit?id=' . $id : '/admin/agreements/create'));
                exit;
            }
        });

        $router->post('/admin/agreements/publish', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            $admin = Auth::user();
            try {
                if ($id < 1) {
                    throw new \RuntimeException('Convenio inválido.');
                }
                $result = (new PartnerAgreementService($repo()))->publishVersion(
                    $id,
                    $admin ? (int) $admin['id'] : null,
                    max(1, (int) ($_POST['sign_deadline_days'] ?? 15))
                );
                $msg = 'Publicado: ' . $result['assigned'] . ' partner(s) asignados, '
                    . $result['notified'] . ' correo(s) enviados.';
                if ($result['mail_errors'] !== []) {
                    $msg .= ' Algunos correos fallaron: ' . implode(' · ', array_slice($result['mail_errors'], 0, 3));
                }
                flash('info', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/agreements/edit?id=' . $id);
            exit;
        });

        $router->post('/admin/agreements/approve-signature', static function () use ($repo): void {
            Auth::requireAdmin();
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $agreementId = (int) ($_POST['agreement_id'] ?? 0);
            $admin = Auth::user();
            try {
                $repo()->approvePartnerSignature($assignmentId, $admin ? (int) $admin['id'] : 0);
                flash('info', 'Convenio firmado confirmado. El TR recuperó acceso completo.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/agreements/edit?id=' . $agreementId);
            exit;
        });

        $router->post('/admin/agreements/reject-signature', static function () use ($repo): void {
            Auth::requireAdmin();
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $agreementId = (int) ($_POST['agreement_id'] ?? 0);
            $admin = Auth::user();
            try {
                $repo()->rejectPartnerSignature(
                    $assignmentId,
                    $admin ? (int) $admin['id'] : 0,
                    trim((string) ($_POST['reject_reason'] ?? ''))
                );
                flash('info', 'Convenio rechazado. El TR sigue restringido hasta subir una versión correcta.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/agreements/edit?id=' . $agreementId);
            exit;
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

        $router->get('/admin/certifications/pricing', static function () use ($repo): void {
            Auth::requireAdmin();
            $providerId = (int) ($_GET['provider_id'] ?? 0);
            $q = trim((string) ($_GET['q'] ?? ''));
            $items = [];
            $regulations = [];
            if ($providerId > 0) {
                $items = $repo()->certificationsPricingMatrix($providerId, $q !== '' ? $q : null);
                $regulations = $repo()->regulationDocuments($providerId);
            }
            view('admin/certifications/pricing', [
                'title' => 'Precios',
                'providers' => $repo()->providers(true),
                'tiers' => $repo()->partnerTiers(true),
                'items' => $items,
                'regulations' => $regulations,
                'filters' => [
                    'provider_id' => $providerId > 0 ? (string) $providerId : '',
                    'q' => $q,
                ],
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/certifications/pricing/save', static function () use ($repo): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $q = trim((string) ($_POST['q'] ?? ''));
            $rows = $_POST['rows'] ?? [];
            if (!is_array($rows) || $rows === []) {
                flash('error', 'No hay filas para guardar.');
                header('Location: /admin/certifications/pricing?provider_id=' . $providerId);
                exit;
            }
            try {
                $allowedIds = [];
                foreach ($repo()->certifications($providerId > 0 ? ['provider_id' => $providerId] : null) as $cert) {
                    $allowedIds[(int) $cert['id']] = true;
                }
                $tierIds = [];
                foreach ($repo()->partnerTiers(true) as $tier) {
                    $tierIds[(int) $tier['id']] = true;
                }
                $parsed = [];
                $losses = [];
                foreach ($rows as $certId => $row) {
                    $certId = (int) $certId;
                    if ($certId < 1 || !isset($allowedIds[$certId]) || !is_array($row)) {
                        continue;
                    }
                    $costRaw = trim((string) ($row['cost_price'] ?? ''));
                    $publicRaw = trim((string) ($row['public_price'] ?? ''));
                    $cost = $costRaw !== '' ? (float) $costRaw : null;
                    $public = $publicRaw !== '' ? (float) $publicRaw : null;
                    if ($cost !== null && $public !== null && $public + 0.00001 < $cost) {
                        $losses[] = "#{$certId} público";
                    }
                    $tierPrices = [];
                    $rawTiers = $row['tier_prices'] ?? [];
                    if (is_array($rawTiers)) {
                        foreach ($rawTiers as $tid => $price) {
                            $tid = (int) $tid;
                            if (!isset($tierIds[$tid])) {
                                continue;
                            }
                            $priceRaw = trim((string) $price);
                            if ($priceRaw === '') {
                                $tierPrices[$tid] = null;
                                continue;
                            }
                            $tierPrice = (float) $priceRaw;
                            if ($cost !== null && $tierPrice + 0.00001 < $cost) {
                                $losses[] = "#{$certId} TR{$tid}";
                            }
                            $tierPrices[$tid] = $priceRaw;
                        }
                    }
                    $parsed[] = [
                        'id' => $certId,
                        'cost' => $costRaw !== '' ? $costRaw : null,
                        'public' => $publicRaw !== '' ? $publicRaw : null,
                        'tiers' => $tierPrices,
                        'doc_id' => (int) ($row['regulation_document_id'] ?? 0),
                    ];
                }
                if ($losses !== []) {
                    throw new \RuntimeException(
                        'No se guardó: hay precios de venta menores al costo (pérdida). Revisa: '
                        . implode(', ', array_slice($losses, 0, 8))
                        . (count($losses) > 8 ? '…' : '')
                    );
                }
                $saved = 0;
                foreach ($parsed as $row) {
                    $repo()->updateCertificationPrices($row['id'], $row['cost'], $row['public']);
                    $repo()->saveCertificationTierPrices($row['id'], $row['tiers']);
                    $repo()->setCertificationRegulationDocument(
                        $row['id'],
                        $row['doc_id'] > 0 ? $row['doc_id'] : null
                    );
                    $saved++;
                }
                flash('info', "Guardado: {$saved} certificación(es).");
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            $loc = '/admin/certifications/pricing?provider_id=' . $providerId;
            if ($q !== '') {
                $loc .= '&q=' . rawurlencode($q);
            }
            header('Location: ' . $loc);
            exit;
        });

        $router->post('/admin/certifications/pricing/assign-regulation', static function () use ($repo): void {
            Auth::requireAdmin();
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $documentId = (int) ($_POST['document_id'] ?? 0);
            $q = trim((string) ($_POST['q'] ?? ''));
            try {
                if ($providerId <= 0 || $documentId <= 0) {
                    throw new \RuntimeException('Proveedor y reglamento son obligatorios.');
                }
                $doc = $repo()->document($documentId);
                if (!$doc || ($doc['doc_type'] ?? '') !== 'regulation') {
                    throw new \RuntimeException('El documento no es un reglamento válido.');
                }
                if (!empty($doc['provider_id']) && (int) $doc['provider_id'] !== $providerId) {
                    throw new \RuntimeException('Ese reglamento pertenece a otro proveedor.');
                }
                $n = $repo()->assignRegulationToProviderCertifications($providerId, $documentId);
                flash('info', "Reglamento asignado a {$n} certificación(es) de la empresa.");
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            $loc = '/admin/certifications/pricing?provider_id=' . $providerId;
            if ($q !== '') {
                $loc .= '&q=' . rawurlencode($q);
            }
            header('Location: ' . $loc);
            exit;
        });

        $router->get('/admin/certifications/create', static function () use ($repo): void {
            Auth::requireAdmin();
            $repo()->ensureProviderSetupSchema();
            $providerGroupsMap = [];
            $providerFieldsMap = [];
            foreach ($repo()->providers(true) as $p) {
                $pid = (int) $p['id'];
                $providerGroupsMap[$pid] = $repo()->providerGroups($pid, true);
                $providerFieldsMap[$pid] = $repo()->availableFieldsForCertification($pid);
            }
            view('admin/certifications/form', [
                'title' => 'Nueva certificación',
                'item' => null,
                'providers' => $repo()->providers(true),
                'protocols' => $repo()->protocols(true),
                'tiers' => $repo()->partnerTiers(true),
                'tierPrices' => [],
                'provider_groups' => [],
                'provider_available_fields' => [],
                'provider_groups_map' => $providerGroupsMap,
                'provider_fields_map' => $providerFieldsMap,
                'cenni_product_options' => $repo()->certifications(null),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/certifications/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $repo()->ensureUksFlowSchemaAndSeeds();
            $repo()->ensureCambridgeAndSepSchemaAndSeeds();
            $repo()->ensureProductAssetsSchema();
            $item = $repo()->certification($id);
            if (!$item) {
                flash('error', 'Certificación no encontrada.');
                header('Location: /admin/certifications');
                exit;
            }
            $providerId = (int) ($item['provider_id'] ?? 0);
            $tab = trim((string) ($_GET['tab'] ?? 'general'));
            $allowedCertTabs = ['general', 'contenido', 'nivel', 'precios', 'adquisicion', 'elegibilidad', 'cursos', 'assets'];
            if (!in_array($tab, $allowedCertTabs, true)) {
                $tab = 'general';
            }
            view('admin/certifications/form', [
                'title' => 'Editar certificación',
                'tab' => $tab,
                'item' => $item,
                'providers' => $repo()->providers(true),
                'protocols' => $repo()->protocols(true),
                'tiers' => $repo()->partnerTiers(true),
                'tierPrices' => $repo()->certificationTierPrices($id),
                'linkedCourses' => $repo()->certificationCourses($id),
                'courses' => $repo()->courses(true),
                'assets' => $repo()->assets('certification', $id),
                'assetTypes' => CatalogRepository::assetTypesFor('certification'),
                'documents' => $repo()->documents(null, true),
                'cenni_instruction_doc_id' => (int) (($repo()->certificationDocumentsByStage($id, 'cenni')[0]['id'] ?? 0)),
                'cenni_product_options' => $repo()->certifications(null),
                'provider_groups' => $providerId > 0 ? $repo()->providerGroups($providerId, true) : [],
                'provider_available_fields' => $providerId > 0 ? $repo()->availableFieldsForCertification($providerId) : [],
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/certifications/save', static function () use ($repo, $certEditUrl): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $name = trim((string) ($_POST['name'] ?? ''));
            $existing = $id ? $repo()->certification($id) : null;
            if ($name === '') {
                flash('error', 'El nombre es obligatorio.');
                header('Location: ' . ($id ? $certEditUrl($id) : '/admin/certifications/create'));
                exit;
            }

            $providerId = $existing
                ? (int) $existing['provider_id']
                : (int) ($_POST['provider_id'] ?? 0);
            if ($providerId < 1) {
                flash('error', 'Selecciona el proveedor (o créala desde Proveedores).');
                header('Location: ' . ($id ? $certEditUrl($id) : '/admin/certifications/create'));
                exit;
            }

            if ($existing) {
                $code = (string) $existing['code'];
                $slug = (string) $existing['slug'];
            } else {
                $alloc = $repo()->allocateCertificationCodeSlug($name);
                $code = $alloc['code'];
                $slug = $alloc['slug'];
            }

            $modality = (string) ($_POST['modality'] ?? 'online');
            if (!isset(CatalogRepository::modalities()[$modality])) {
                $modality = 'online';
            }

            $isLevelExam = isset($_POST['is_level_exam']) ? 1 : 0;
            $skills = [];
            if ($isLevelExam) {
                $rawSkills = $_POST['skills'] ?? [];
                if (!is_array($rawSkills)) {
                    $rawSkills = [];
                }
                $allowedSkills = array_keys(CatalogRepository::certificationSkills());
                foreach ($rawSkills as $skill) {
                    $skill = (string) $skill;
                    if (in_array($skill, $allowedSkills, true)) {
                        $skills[] = $skill;
                    }
                }
            }

            $cenniEligible = isset($_POST['cenni_eligible']) ? 1 : 0;
            $cenniDocType = 'none';
            $cenniFee = null;
            $cenniIncluded = 0;
            if ($cenniEligible) {
                $cenniDocType = (string) ($_POST['cenni_doc_type'] ?? 'constancia');
                if (!isset(CatalogRepository::cenniDocTypes()[$cenniDocType])) {
                    $cenniDocType = 'constancia';
                }
                $cenniFeeRaw = trim((string) ($_POST['cenni_fee'] ?? '0'));
                $cenniFee = $cenniFeeRaw !== '' ? (float) $cenniFeeRaw : 0.0;
                $cenniIncluded = $cenniFee <= 0 ? 1 : 0;
            }

            $conocerEligible = isset($_POST['conocer_eligible']) ? 1 : 0;
            $conocerFee = null;
            if ($conocerEligible) {
                $conocerFeeRaw = trim((string) ($_POST['conocer_fee'] ?? ''));
                $conocerFee = $conocerFeeRaw !== '' ? (float) $conocerFeeRaw : 0.0;
            }

            $publicPrice = trim((string) ($_POST['public_price'] ?? ''));
            $costPrice = trim((string) ($_POST['cost_price'] ?? ''));
            $protocolId = (int) ($_POST['protocol_id'] ?? 0);
            $intent = (string) ($_POST['intent'] ?? 'save');
            // Guardar no oculta; Publicar marca visible. Ocultar = ojo en listados.
            if ($intent === 'publish') {
                $isPublished = 1;
            } else {
                $isPublished = $existing ? (int) ($existing['is_published'] ?? 0) : 0;
            }

            $rawRanges = $_POST['score_ranges'] ?? [];
            if (!is_array($rawRanges)) {
                $rawRanges = [];
            }
            $scoreRanges = CatalogRepository::decodeScoreRanges($rawRanges);

            $rawRegFields = $_POST['registration_fields'] ?? [];
            if (!is_array($rawRegFields)) {
                $rawRegFields = [];
            }
            // Campos personalizados solo desde el catálogo del proveedor (no se inventan en la cert)
            $customFields = [];
            $modes = [];
            $available = $repo()->availableFieldsForCertification($providerId);
            $allowedKeys = [];
            foreach ($available as $af) {
                $key = (string) ($af['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $allowedKeys[$key] = true;
                $mode = (string) ($rawRegFields[$key] ?? 'off');
                if (!in_array($mode, ['off', 'optional', 'required'], true)) {
                    $mode = 'off';
                }
                if (!empty($af['locked']) || (($af['source'] ?? '') === 'builtin' && in_array($key, ['first_name', 'last_name_p', 'email'], true))) {
                    $mode = 'required';
                }
                if (($af['source'] ?? '') === 'custom') {
                    if ($mode === 'optional' || $mode === 'required') {
                        $customFields[] = [
                            'key' => $key,
                            'label' => (string) ($af['label'] ?? $key),
                            'type' => (string) ($af['type'] ?? 'text'),
                            'mode' => $mode,
                        ];
                    }
                    continue;
                }
                $modes[$key] = $mode;
            }
            // Conservar modos posted solo si están permitidos
            foreach ($rawRegFields as $k => $v) {
                $k = (string) $k;
                if (!isset($allowedKeys[$k]) || isset($modes[$k])) {
                    continue;
                }
                $mode = (string) $v;
                if (in_array($mode, ['off', 'optional', 'required'], true)) {
                    $modes[$k] = $mode;
                }
            }
            $weekdaysPost = $_POST['exam_weekday'] ?? [];
            if (!is_array($weekdaysPost)) {
                $weekdaysPost = [];
            }
            $weekdays = [];
            foreach (CatalogRepository::weekdayLabels() as $n => $_) {
                $row = is_array($weekdaysPost[(string) $n] ?? null)
                    ? $weekdaysPost[(string) $n]
                    : (is_array($weekdaysPost[$n] ?? null) ? $weekdaysPost[$n] : []);
                $timesRaw = (string) ($row['times'] ?? '');
                $weekdays[(string) $n] = [
                    'enabled' => !empty($row['enabled']),
                    'kind' => (string) ($row['kind'] ?? 'range'),
                    'time_start' => trim((string) ($row['time_start'] ?? '09:00')),
                    'time_end' => trim((string) ($row['time_end'] ?? '18:00')),
                    'times' => $timesRaw,
                ];
            }
            $schedule = [
                'time_start' => trim((string) ($_POST['exam_time_start'] ?? '09:00')),
                'time_end' => trim((string) ($_POST['exam_time_end'] ?? '18:00')),
                'slot_minutes' => (int) ($_POST['exam_slot_minutes'] ?? 30),
                'extraordinary_enabled' => isset($_POST['exam_extraordinary_enabled']) ? 1 : 0,
                'extraordinary_fee' => (float) ($_POST['exam_extraordinary_fee'] ?? 0),
                'extraordinary_warning' => trim((string) ($_POST['exam_extraordinary_warning'] ?? '')),
                'weekdays' => $weekdays,
            ];
            $registrationFields = CatalogRepository::encodeRegistrationConfig([
                'modes' => $modes,
                'custom' => $customFields,
                'schedule' => $schedule,
            ]);

            $rawTierPrices = $_POST['tier_prices'] ?? [];
            if (!is_array($rawTierPrices)) {
                $rawTierPrices = [];
            }

            $providerGroupId = trim((string) ($_POST['provider_group_id'] ?? ''));
            $providerGroupId = $providerGroupId !== '' ? (int) $providerGroupId : null;

            try {
                $savedId = $repo()->saveCertification([
                    'provider_id' => $providerId,
                    'provider_group_id' => $providerGroupId,
                    'protocol_id' => $protocolId > 0 ? $protocolId : null,
                    'code' => $code,
                    'slug' => $slug,
                    'name' => $name,
                    'modality' => $modality,
                    'short_description' => trim((string) ($_POST['short_description'] ?? '')) ?: null,
                    'value_points_json' => CatalogRepository::encodeValuePoints(
                        (string) ($_POST['value_points'] ?? '')
                    ),
                    'registration_fields_json' => $registrationFields,
                    'description_html' => trim((string) ($_POST['description_html'] ?? '')) ?: null,
                    'syllabus_html' => is_array($existing) ? ($existing['syllabus_html'] ?? null) : null,
                    'duration_label' => trim((string) ($_POST['duration_label'] ?? '')) ?: null,
                    'audience' => trim((string) ($_POST['audience'] ?? '')) ?: null,
                    'is_level_exam' => $isLevelExam,
                    'skills_json' => $isLevelExam ? $skills : null,
                    'score_ranges_json' => $scoreRanges,
                    'score_range' => CatalogRepository::formatScoreRangesSummary($scoreRanges),
                    'public_price' => $publicPrice !== '' ? (float) $publicPrice : null,
                    'cost_price' => $costPrice !== '' ? (float) $costPrice : null,
                    'currency' => 'MXN',
                    'cenni_eligible' => $cenniEligible,
                    'cenni_doc_type' => $cenniDocType,
                    'cenni_included' => $cenniIncluded,
                    'cenni_fee' => $cenniFee,
                    'cenni_process' => $cenniEligible
                        ? (in_array((string) ($_POST['cenni_process'] ?? ''), ['uks_external', 'doceo_managed', 'none'], true)
                            ? (string) $_POST['cenni_process']
                            : 'doceo_managed')
                        : 'none',
                    'cenni_late_certification_id' => ((int) ($_POST['cenni_late_certification_id'] ?? 0)) ?: null,
                    'conocer_eligible' => $conocerEligible,
                    'conocer_fee' => $conocerFee,
                    'is_published' => $isPublished,
                    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                ], $id);

                // Solo persistir precios de niveles activos conocidos
                $allowedTiers = [];
                foreach ($repo()->partnerTiers(true) as $tier) {
                    $tid = (int) $tier['id'];
                    $allowedTiers[$tid] = $rawTierPrices[$tid] ?? ($rawTierPrices[(string) $tid] ?? '');
                }
                $repo()->saveCertificationTierPrices($savedId, $allowedTiers);

                if (array_key_exists('cenni_instruction_document_id', $_POST)) {
                    $cenniDocId = (int) ($_POST['cenni_instruction_document_id'] ?? 0);
                    $repo()->setCertificationStageDocument(
                        $savedId,
                        'cenni',
                        $cenniDocId > 0 ? $cenniDocId : null
                    );
                }

                flash('info', $intent === 'publish' ? 'Certificación publicada.' : 'Certificación guardada.');
                header('Location: ' . $certEditUrl($savedId));
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? $certEditUrl($id) : '/admin/certifications/create'));
                exit;
            }
        });

        $router->post('/admin/certifications/toggle-published', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            $item = $repo()->certification($id);
            if (!$item) {
                flash('error', 'Certificación no encontrada.');
                header('Location: /admin/certifications');
                exit;
            }
            $newPublished = !((int) ($item['is_published'] ?? 0) === 1);
            $repo()->setCertificationPublished($id, $newPublished);
            flash('info', $newPublished ? 'Certificación publicada.' : 'Certificación ocultada.');
            $redirect = trim((string) ($_POST['redirect'] ?? ''));
            if ($redirect !== '' && str_starts_with($redirect, '/admin/')) {
                header('Location: ' . $redirect);
            } else {
                header('Location: /admin/certifications');
            }
            exit;
        });

        $router->post('/admin/certifications/attach-course', static function () use ($repo, $certEditUrl): void {
            Auth::requireAdmin();
            $certificationId = (int) ($_POST['certification_id'] ?? 0);
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $relationType = (string) ($_POST['relation_type'] ?? 'included');
            $bundlePrice = trim((string) ($_POST['bundle_price'] ?? ''));
            if ($certificationId < 1 || $courseId < 1) {
                flash('error', 'Selecciona un curso.');
                header('Location: ' . $certEditUrl($certificationId, 'cursos'));
                exit;
            }
            try {
                $price = null;
                if ($relationType === 'sold_separate' || $relationType === 'bundle_discount') {
                    $price = $bundlePrice !== '' && is_numeric($bundlePrice) ? (float) $bundlePrice : null;
                }
                $repo()->attachCertificationCourse(
                    $certificationId,
                    $courseId,
                    $relationType,
                    $price,
                    trim((string) ($_POST['notes'] ?? '')) ?: null
                );
                flash('info', 'Curso vinculado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $certEditUrl($certificationId, 'cursos'));
            exit;
        });

        $router->post('/admin/certifications/detach-course', static function () use ($repo, $certEditUrl): void {
            Auth::requireAdmin();
            $certificationId = (int) ($_POST['certification_id'] ?? 0);
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $repo()->detachCertificationCourse($certificationId, $courseId);
            flash('info', 'Curso desvinculado.');
            header('Location: ' . $certEditUrl($certificationId, 'cursos'));
            exit;
        });

        $router->get('/admin/partners', static function () use ($repo): void {
            Auth::requireAdmin();
            $tab = trim((string) ($_GET['tab'] ?? 'partners'));
            if ($tab === 'niveles') {
                view('admin/tiers/index', [
                    'title' => 'Partners TR · Niveles',
                    'items' => $repo()->partnerTiers(),
                    'info' => flash('info'),
                    'error' => flash('error'),
                ]);
                return;
            }
            view('admin/partners/index', [
                'title' => 'Partners TR',
                'items' => $repo()->partners(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/partners/create', static function () use ($repo): void {
            Auth::requireAdmin();
            $tab = trim((string) ($_GET['tab'] ?? 'datos'));
            $allowedTabs = ['datos', 'envio', 'facturacion'];
            if (!in_array($tab, $allowedTabs, true)) {
                $tab = 'datos';
            }
            view('admin/partners/form', [
                'title' => 'Nuevo partner TR',
                'tab' => $tab,
                'item' => null,
                'tiers' => $repo()->partnerTiers(true),
                'error' => flash('error'),
                'info' => flash('info'),
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
            $tab = trim((string) ($_GET['tab'] ?? 'datos'));
            $allowedTabs = ['datos', 'envio', 'facturacion', 'historial'];
            if (!in_array($tab, $allowedTabs, true)) {
                $tab = 'datos';
            }
            view('admin/partners/form', [
                'title' => 'Editar partner TR',
                'tab' => $tab,
                'item' => $item,
                'tiers' => $repo()->partnerTiers(true),
                'history' => $repo()->partnerAssignmentHistory($id),
                'error' => flash('error'),
                'info' => flash('info'),
            ]);
        });

        $router->post('/admin/partners/save', static function () use ($repo, $partnerEditUrl): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $tierId = (int) ($_POST['partner_tier_id'] ?? 0);
            $firstName = trim((string) ($_POST['first_name'] ?? ''));
            $lastName = trim((string) ($_POST['last_name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
            $organization = trim((string) ($_POST['organization'] ?? '')) ?: null;
            $requiresInvoice = isset($_POST['requires_invoice']) ? 1 : 0;

            try {
                if ($tierId < 1) {
                    throw new \RuntimeException('Selecciona el nivel TR.');
                }
                $agreement = $repo()->currentAgreementForTier($tierId);
                if (!$agreement) {
                    throw new \RuntimeException('Ese nivel no tiene un convenio publicado. Crea y publica una versión en Convenios TR.');
                }

                $shippingLine = trim((string) ($_POST['shipping_address_line'] ?? ''));
                $shippingCity = trim((string) ($_POST['shipping_city'] ?? ''));
                if ($shippingLine === '' || $shippingCity === '') {
                    throw new \RuntimeException('La dirección de paquetería (calle y ciudad) es obligatoria.');
                }

                $existing = $id ? $repo()->partner($id) : null;
                if ($id && !$existing) {
                    throw new \RuntimeException('Partner no encontrado.');
                }

                $taxFile = $_FILES['tax_status'] ?? null;
                $hasTaxUpload = is_array($taxFile)
                    && (int) ($taxFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if ($requiresInvoice && !$hasTaxUpload && empty($existing['tax_status_path'])) {
                    throw new \RuntimeException('Si requiere factura, sube la Constancia de Situación Fiscal.');
                }

                $userRepo = new UserRepository();
                if ($id) {
                    $userId = (int) $existing['user_id'];
                    $userRepo->update($userId, [
                        'email' => $email !== '' ? $email : (string) $existing['email'],
                        'phone' => $phone,
                        'username' => (string) ($existing['username'] ?: explode('@', (string) $existing['email'], 2)[0]),
                        'first_name' => $firstName !== '' ? $firstName : (string) ($existing['first_name'] ?? 'Partner'),
                        'last_name' => $lastName !== '' ? $lastName : (string) ($existing['last_name'] ?? 'TR'),
                        'role' => 'partner',
                        'is_active' => (int) ($existing['user_active'] ?? 1),
                    ]);
                } else {
                    if ($firstName === '' || $lastName === '' || $email === '') {
                        throw new \RuntimeException('Nombre, apellidos y correo son obligatorios.');
                    }
                    $userId = $userRepo->createPartnerUser($email, $firstName, $lastName, $phone);
                }

                $admin = Auth::user();
                $tierChanged = $id && (int) ($existing['partner_tier_id'] ?? 0) !== $tierId;
                $savedId = $repo()->savePartner([
                    'user_id' => $userId,
                    'partner_tier_id' => $tierId,
                    // En alta / cambio de nivel la asignación con firma la hace PartnerAgreementService.
                    'current_agreement_id' => $id && !$tierChanged
                        ? ($existing['current_agreement_id'] ?? (int) $agreement['id'])
                        : null,
                    'organization' => $organization,
                    'phone' => $phone,
                    'shipping_address_line' => $shippingLine,
                    'shipping_address_line2' => trim((string) ($_POST['shipping_address_line2'] ?? '')) ?: null,
                    'shipping_neighborhood' => trim((string) ($_POST['shipping_neighborhood'] ?? '')) ?: null,
                    'shipping_city' => $shippingCity,
                    'shipping_state' => trim((string) ($_POST['shipping_state'] ?? '')) ?: null,
                    'shipping_postal_code' => trim((string) ($_POST['shipping_postal_code'] ?? '')) ?: null,
                    'shipping_country' => trim((string) ($_POST['shipping_country'] ?? 'México')) ?: 'México',
                    'signed_agreement_path' => $existing['signed_agreement_path'] ?? null,
                    'requires_invoice' => $requiresInvoice,
                    'tax_status_path' => $existing['tax_status_path'] ?? null,
                    'logo_path' => $existing['logo_path'] ?? null,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'skip_assignment_history' => true,
                ], $id, $admin ? (int) $admin['id'] : null);

                $subdir = 'partners/' . $savedId;
                $taxPath = $existing['tax_status_path'] ?? null;
                $logoPath = $existing['logo_path'] ?? null;
                $filesChanged = false;

                if ($hasTaxUpload) {
                    $taxPath = Uploader::store($taxFile, $subdir);
                    $filesChanged = true;
                }
                $logoFile = $_FILES['logo'] ?? null;
                if (is_array($logoFile) && (int) ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $logoPath = Uploader::storeImage($logoFile, $subdir, 600, 600);
                    $filesChanged = true;
                }

                if ($filesChanged) {
                    $repo()->savePartner([
                        'user_id' => $userId,
                        'partner_tier_id' => $tierId,
                        'tax_status_path' => $taxPath,
                        'logo_path' => $logoPath,
                        'requires_invoice' => $requiresInvoice,
                        'organization' => $organization,
                        'phone' => $phone,
                        'shipping_address_line' => $shippingLine,
                        'shipping_address_line2' => trim((string) ($_POST['shipping_address_line2'] ?? '')) ?: null,
                        'shipping_neighborhood' => trim((string) ($_POST['shipping_neighborhood'] ?? '')) ?: null,
                        'shipping_city' => $shippingCity,
                        'shipping_state' => trim((string) ($_POST['shipping_state'] ?? '')) ?: null,
                        'shipping_postal_code' => trim((string) ($_POST['shipping_postal_code'] ?? '')) ?: null,
                        'shipping_country' => trim((string) ($_POST['shipping_country'] ?? 'México')) ?: 'México',
                        'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                        'skip_assignment_history' => true,
                    ], $savedId, $admin ? (int) $admin['id'] : null);
                }

                if (!$id || $tierChanged) {
                    (new PartnerAgreementService($repo()))->assignCurrentOnPartnerCreate(
                        $savedId,
                        $tierId,
                        $admin ? (int) $admin['id'] : null
                    );
                }

                flash(
                    'info',
                    $id
                        ? ($tierChanged
                            ? 'Partner actualizado. Se asignó el convenio vigente del nuevo nivel (pendiente de firma).'
                            : 'Partner actualizado.')
                        : 'Partner creado. Debe firmar el convenio vigente en el portal TR. Correo: ' . $email
                            . ' · contraseña temporal: ' . UserRepository::PARTNER_DEFAULT_PASSWORD
                );
                header('Location: ' . $partnerEditUrl($savedId));
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? $partnerEditUrl($id) : '/admin/partners/create'));
                exit;
            }
        });

        $router->post('/admin/assets/upload', static function () use ($repo): void {
            Auth::requireAdmin();
            $repo()->ensureProductAssetsSchema();
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
                if (\App\Support\YoutubeUrl::isYoutubeAssetType($assetType)) {
                    $youtube = trim((string) ($_POST['youtube_url'] ?? ''));
                    if ($youtube === '') {
                        throw new \RuntimeException('Pega el enlace de YouTube del video.');
                    }
                    $path = \App\Support\YoutubeUrl::normalize($youtube);
                } else {
                    if (empty($_FILES['file']) || (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        throw new \RuntimeException('Selecciona un archivo.');
                    }
                    $path = Uploader::store($_FILES['file'], $ownerType);
                }
                $repo()->saveAsset([
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'asset_type' => $assetType,
                    'file_path' => $path,
                    'title' => trim((string) ($_POST['title'] ?? '')) ?: null,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                ]);
                flash('info', $assetType === 'youtube' ? 'Video de YouTube agregado.' : 'Archivo subido.');
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
                $path = (string) ($asset['file_path'] ?? '');
                if ($path !== '' && !\App\Support\YoutubeUrl::looksLikeUrl($path)) {
                    Uploader::delete($path);
                }
                flash('info', 'Asset eliminado.');
            } else {
                flash('error', 'Asset no encontrado.');
            }
            header('Location: ' . $redirect);
            exit;
        });

        $users = static fn (): UserRepository => new UserRepository();

        $router->get('/admin/users', static function () use ($users, $repo): void {
            Auth::requireAdmin();
            $filters = [
                'q' => trim((string) ($_GET['q'] ?? '')),
                'role' => (string) ($_GET['role'] ?? ''),
                'is_active' => $_GET['is_active'] ?? '',
            ];
            $items = $users()->list($filters);
            $ids = array_map(static fn (array $u): int => (int) $u['id'], $items);
            $caseSummary = $repo()->studentCaseSummaryByUserIds($ids);
            foreach ($items as &$item) {
                $item['case_summary'] = $caseSummary[(int) $item['id']] ?? null;
            }
            unset($item);
            view('admin/users/index', [
                'title' => 'Usuarios',
                'items' => $items,
                'filters' => $filters,
                'roles' => UserRepository::manageableRoles(),
                'roleLabels' => UserRepository::allRoleLabels(),
                'currentUserId' => (int) (Auth::user()['id'] ?? 0),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/users/create', static function (): void {
            Auth::requireAdmin();
            view('admin/users/form', [
                'title' => 'Nuevo usuario',
                'item' => null,
                'roles' => UserRepository::manageableRoles(),
                'error' => flash('error'),
                'info' => flash('info'),
            ]);
        });

        $router->get('/admin/users/edit', static function () use ($users): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $users()->find($id);
            if (!$item) {
                flash('error', 'Usuario no encontrado.');
                header('Location: /admin/users');
                exit;
            }
            view('admin/users/form', [
                'title' => 'Editar usuario',
                'item' => $item,
                'roles' => UserRepository::manageableRoles(),
                'currentUserId' => (int) (Auth::user()['id'] ?? 0),
                'error' => flash('error'),
                'info' => flash('info'),
            ]);
        });

        $router->post('/admin/users/save', static function () use ($users): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $payload = [
                'email' => (string) ($_POST['email'] ?? ''),
                'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
                'username' => (string) ($_POST['username'] ?? ''),
                'first_name' => (string) ($_POST['first_name'] ?? ''),
                'last_name' => (string) ($_POST['last_name'] ?? ''),
                'role' => (string) ($_POST['role'] ?? ''),
            ];

            try {
                if ($id) {
                    $existing = $users()->find($id);
                    if (!$existing) {
                        throw new \RuntimeException('Usuario no encontrado.');
                    }
                    $users()->update($id, $payload + ['is_active' => (int) $existing['is_active']]);
                    flash('info', 'Usuario actualizado.');
                    header('Location: /admin/users/edit?id=' . $id);
                } else {
                    $savedId = $users()->create($payload);
                    try {
                        $users()->sendWelcomeActivationEmail($savedId);
                        flash('info', 'Usuario creado. Se envió el correo de activación a ' . $payload['email'] . '.');
                    } catch (\Throwable $mailError) {
                        flash(
                            'info',
                            'Usuario creado, pero no se pudo enviar el correo: ' . $mailError->getMessage()
                            . ' Contraseña temporal: ' . UserRepository::DEFAULT_PASSWORD
                        );
                    }
                    header('Location: /admin/users/edit?id=' . $savedId);
                }
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/users/edit?id=' . $id : '/admin/users/create'));
                exit;
            }
        });

        $router->post('/admin/users/reset-password', static function () use ($users): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            $redirect = (string) ($_POST['redirect'] ?? '/admin/users');
            if (!str_starts_with($redirect, '/admin/users')) {
                $redirect = '/admin/users';
            }
            $emailAccess = isset($_POST['email_access']);
            try {
                $item = $users()->find($id);
                if (!$item) {
                    throw new \RuntimeException('Usuario no encontrado.');
                }
                if ($emailAccess || ($item['role'] ?? '') === 'student') {
                    $issued = Auth::issueTemporaryPasswordAndEmail(
                        $id,
                        'Restablecimos tu acceso a la plataforma Instituto DOCEO.'
                    );
                    flash('info', 'Nueva contraseña temporal enviada a ' . $issued['email'] . '.');
                } else {
                    $users()->resetToDefaultPassword($id);
                    flash('info', 'Contraseña restablecida a ' . UserRepository::DEFAULT_PASSWORD . '. El usuario deberá cambiarla al entrar.');
                }
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $redirect);
            exit;
        });

        $router->post('/admin/users/resend-activation', static function () use ($users): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            try {
                $item = $users()->find($id);
                if (!$item) {
                    throw new \RuntimeException('Usuario no encontrado.');
                }
                if (!empty($item['email_verified_at']) && (int) $item['is_active'] === 1) {
                    throw new \RuntimeException('La cuenta ya está activa.');
                }
                $users()->sendWelcomeActivationEmail($id);
                flash('info', 'Correo de activación reenviado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/users');
            exit;
        });

        $router->post('/admin/users/toggle-active', static function () use ($users): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            $actorId = (int) (Auth::user()['id'] ?? 0);
            $item = $users()->find($id);
            if (!$item) {
                flash('error', 'Usuario no encontrado.');
                header('Location: /admin/users');
                exit;
            }
            $enable = (int) $item['is_active'] !== 1;
            try {
                $users()->setActive($id, $enable, $actorId);
                flash('info', $enable ? 'Usuario habilitado.' : 'Usuario deshabilitado.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            $redirect = (string) ($_POST['redirect'] ?? '/admin/users');
            if (!str_starts_with($redirect, '/admin/users')) {
                $redirect = '/admin/users';
            }
            header('Location: ' . $redirect);
            exit;
        });

        $router->post('/admin/users/delete', static function () use ($users): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            $actorId = (int) (Auth::user()['id'] ?? 0);
            try {
                $repo = $users();
                $repo->delete($id, $actorId);
                $n = $repo->lastDeletedCasesCount();
                flash(
                    'info',
                    'Usuario eliminado'
                    . ($n > 0 ? ' junto con ' . $n . ' caso(s) de certificación.' : '.')
                );
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                $redirectFail = (string) ($_POST['redirect_fail'] ?? '');
                if (str_starts_with($redirectFail, '/admin/users')) {
                    header('Location: ' . $redirectFail);
                    exit;
                }
            }
            header('Location: /admin/users');
            exit;
        });

        $router->get('/admin/openpay', static function () use ($repo): void {
            Auth::requireAdmin();
            $repo()->ensureOpenPayWebhookEventsTable();
            $client = new \App\Integrations\OpenPayClient();
            $webhookUrl = \App\Integrations\OpenPayClient::publicWebhookUrl();
            $webhooks = [];
            $listError = null;
            try {
                $webhooks = $client->listWebhooks();
            } catch (\Throwable $e) {
                $listError = $e->getMessage();
            }

            $matched = null;
            foreach ($webhooks as $wh) {
                $url = rtrim((string) ($wh['url'] ?? ''), '/');
                if ($url === rtrim($webhookUrl, '/')) {
                    $matched = $wh;
                    break;
                }
            }

            view('admin/openpay', [
                'title' => 'OpenPay · Webhook',
                'webhookUrl' => $webhookUrl,
                'webhooks' => $webhooks,
                'matched' => $matched,
                'listError' => $listError,
                'verification' => $repo()->latestOpenPayVerificationCode(),
                'events' => $repo()->recentOpenPayWebhookEvents(25),
                'sandbox' => \App\Config\Env::getBool('OPENPAY_SANDBOX', true),
                'merchantId' => \App\Config\Env::get('OPENPAY_MERCHANT_ID'),
                'authUserConfigured' => \App\Config\Env::isFilled('OPENPAY_WEBHOOK_USER'),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/openpay/register-webhook', static function () use ($repo): void {
            Auth::requireAdmin();
            $repo()->ensureOpenPayWebhookEventsTable();
            try {
                $client = new \App\Integrations\OpenPayClient();
                $url = \App\Integrations\OpenPayClient::publicWebhookUrl();
                $user = trim((string) (\App\Config\Env::get('OPENPAY_WEBHOOK_USER', '') ?? ''));
                $pass = (string) (\App\Config\Env::get('OPENPAY_WEBHOOK_PASSWORD', '') ?? '');

                foreach ($client->listWebhooks() as $existing) {
                    if (rtrim((string) ($existing['url'] ?? ''), '/') === rtrim($url, '/')) {
                        $status = (string) ($existing['status'] ?? '');
                        flash(
                            'info',
                            'El webhook ya está registrado en OpenPay'
                            . ($status !== '' ? ' (estado: ' . $status . ')' : '')
                            . '. ID: ' . (string) ($existing['id'] ?? '—')
                        );
                        header('Location: /admin/openpay');
                        exit;
                    }
                }

                $created = $client->createWebhook(
                    $url,
                    $user !== '' ? $user : null,
                    $user !== '' ? $pass : null
                );
                $status = (string) ($created['status'] ?? '');
                flash(
                    'info',
                    'Webhook registrado en OpenPay'
                    . ($status !== '' ? ' · estado: ' . $status : '')
                    . '. ID: ' . (string) ($created['id'] ?? '—')
                    . '. Si quedó unverified, usa el código de verificación de abajo o “Reenviar código” en el dashboard.'
                );
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/openpay');
            exit;
        });

        $router->post('/admin/openpay/delete-webhook', static function (): void {
            Auth::requireAdmin();
            $webhookId = trim((string) ($_POST['webhook_id'] ?? ''));
            if ($webhookId === '') {
                flash('error', 'Falta el ID del webhook.');
                header('Location: /admin/openpay');
                exit;
            }
            try {
                $client = new \App\Integrations\OpenPayClient();
                $client->deleteWebhook($webhookId);
                flash('info', 'Webhook eliminado en OpenPay.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/openpay');
            exit;
        });

        $router->post('/admin/cases/moodle-enrol', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $result = (new \App\Integrations\MoodleEnrolService($repo()))->ensureAccessForCase(
                    $caseId,
                    $user ? (int) $user['id'] : null
                );
                if (!empty($result['skipped'])) {
                    flash('info', 'Sin acción Moodle: ' . ($result['reason'] ?? 'omitido'));
                } else {
                    $pass = \App\Integrations\MoodleEnrolService::defaultPassword();
                    flash(
                        'info',
                        (!empty($result['created_user']) ? 'Usuario Moodle creado' : 'Usuario Moodle existente')
                        . ' (' . ($result['username'] ?? '') . ') · clave ' . $pass
                        . ' · matriculado en ' . count($result['enrolled'] ?? []) . ' curso(s)'
                        . (!empty($result['access_mail']) ? ' · correo moodle_acceso enviado' : '')
                    );
                }
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/moodle-reset-password', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $result = (new \App\Integrations\MoodleEnrolService($repo()))->resetPasswordForCase(
                    $caseId,
                    true,
                    $user ? (int) $user['id'] : null
                );
                $msg = 'Contraseña Moodle restablecida a ' . $result['password']
                    . ' (usuario ' . $result['username'] . '). El alumno deberá cambiarla al entrar.';
                if (!empty($result['mailed'])) {
                    $msg .= ' Correo moodle_acceso enviado.';
                } else {
                    $msg .= ' No se pudo enviar el correo moodle_acceso.';
                }
                flash('info', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/assign-inventory', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $result = (new \App\Services\ExamFulfillmentService($repo()))->assignInventoryCode(
                    $caseId,
                    $user ? (int) $user['id'] : null
                );
                $inv = $result['inventory'] ?? [];
                $mail = $result['access_mail'] ?? null;
                $msg = 'Código asignado: ' . ($inv['exam_id'] ?? '');
                if (is_array($mail) && !empty($mail['sent'])) {
                    $msg .= ' · Correo “' . ($mail['template'] ?? '') . '” a ' . ($mail['to'] ?? '');
                } elseif (is_array($mail) && !empty($mail['error'])) {
                    $msg .= ' · Correo: ' . $mail['error'];
                }
                flash('info', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/resend-access', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $fields = [];
                foreach (['folio_id', 'access_key', 'zoom_url', 'prep_doc_url', 'access_doc_url'] as $key) {
                    if (array_key_exists($key, $_POST)) {
                        $fields[$key] = is_string($_POST[$key]) ? trim((string) $_POST[$key]) : $_POST[$key];
                    }
                }
                $mail = (new \App\Services\ExamFulfillmentService($repo()))->saveCredentialsAndSendAccessMail(
                    $caseId,
                    $fields,
                    $user ? (int) $user['id'] : null
                );
                $verb = !empty($mail['was_resend']) ? 'reenviado' : 'enviado';
                flash(
                    'info',
                    'Acceso ' . $verb . ' (“' . ($mail['template'] ?? '') . '”)'
                    . (!empty($mail['to']) ? ' a ' . $mail['to'] : '')
                    . (!empty($mail['saved']) ? ' · credenciales guardadas' : '')
                    . '.'
                );
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/fulfill', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            try {
                $result = (new \App\Services\ExamFulfillmentService($repo()))->fulfillAfterPayment(
                    $caseId,
                    $user ? (int) $user['id'] : null
                );
                $parts = [];
                $moodle = $result['moodle'] ?? null;
                if (is_array($moodle) && empty($moodle['skipped']) && empty($moodle['error'])) {
                    $parts[] = 'Moodle OK (' . ($moodle['username'] ?? '') . ')';
                } elseif (is_array($moodle) && !empty($moodle['error'])) {
                    $parts[] = 'Moodle: ' . $moodle['error'];
                } elseif (is_array($moodle) && !empty($moodle['skipped'])) {
                    $parts[] = 'Moodle omitido';
                }
                $inv = $result['inventory'] ?? null;
                if (is_array($inv) && !empty($inv['assigned'])) {
                    $parts[] = 'Código ' . ($inv['exam_id'] ?? '');
                } elseif (is_array($inv) && !empty($inv['error'])) {
                    $parts[] = 'Inventario: ' . $inv['error'];
                }
                $mail = $result['access_mail'] ?? null;
                if (is_array($mail) && !empty($mail['sent'])) {
                    $parts[] = 'Mail “' . ($mail['template'] ?? '') . '” a ' . ($mail['to'] ?? '');
                } elseif (is_array($mail) && !empty($mail['error'])) {
                    $parts[] = 'Mail acceso: ' . $mail['error'];
                }
                flash($parts === [] ? 'info' : 'info', $parts === [] ? 'Sin cambios.' : implode(' · ', $parts));
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/cases/exam-results', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $user = Auth::user();
            $action = trim((string) ($_POST['action'] ?? 'deliver'));
            try {
                $svc = new \App\Mail\CaseMailService($repo());
                $svc->ensureItepStudentResultTemplates();
                if ($action === 'invalidate') {
                    $result = $svc->invalidateExam(
                        $caseId,
                        (string) ($_POST['invalidation_reason'] ?? ''),
                        true,
                        $user ? (int) $user['id'] : null,
                        trim((string) ($_POST['template_code'] ?? 'itep_invalidado')) ?: 'itep_invalidado'
                    );
                    $msg = 'Examen marcado como invalidado.';
                } else {
                    $result = $svc->deliverExamResults(
                        $caseId,
                        (string) ($_POST['results_url'] ?? ''),
                        (string) ($_POST['score_url'] ?? ''),
                        (string) ($_POST['certificate_url'] ?? ''),
                        true,
                        $user ? (int) $user['id'] : null,
                        trim((string) ($_POST['template_code'] ?? 'itep_resultados')) ?: 'itep_resultados'
                    );
                    $msg = 'Resultados guardados.';
                }
                if (!empty($result['mailed'])) {
                    $msg .= ' Correo (“' . ($result['template'] ?? '') . '”) enviado'
                        . (!empty($result['to']) ? ' a ' . $result['to'] : '') . '.';
                }
                flash('info', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: ' . $caseViewUrl($caseId));
            exit;
        });

        $router->post('/admin/prorrogas/confirm', static function () use ($repo, $caseViewUrl): void {
            Auth::requireAdmin();
            $prorrogaId = (int) ($_POST['prorroga_id'] ?? 0);
            $user = Auth::user();
            $redirect = trim((string) ($_POST['redirect'] ?? ''));
            try {
                $prorroga = $repo()->courseProrroga($prorrogaId);
                if (!$prorroga) {
                    throw new \RuntimeException('Prórroga no encontrada.');
                }
                $method = trim((string) ($_POST['payment_method'] ?? $prorroga['payment_method'] ?? 'transfer'));
                if (!in_array($method, ['cash', 'transfer', 'openpay', 'other'], true)) {
                    $method = 'transfer';
                }
                $result = (new \App\Services\CourseProrrogaService($repo()))->confirmPaid(
                    $prorrogaId,
                    $method,
                    $user ? (int) $user['id'] : null
                );
                flash(
                    'info',
                    'Prórroga confirmada. Acceso Moodle hasta ' . ($result['access_ends_at'] ?? '') . '.'
                );
                $caseId = (int) ($prorroga['case_id'] ?? 0);
                if ($redirect !== '' && str_starts_with($redirect, '/admin/')) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: ' . $caseViewUrl($caseId));
                }
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($redirect !== '' && str_starts_with($redirect, '/admin/') ? $redirect : '/admin/pendientes'));
                exit;
            }
        });

        $router->post('/admin/moodle/expire-enrolments', static function () use ($repo): void {
            Auth::requireAdmin();
            try {
                $result = (new \App\Integrations\MoodleEnrolService($repo()))->suspendExpiredEnrolments(300);
                $msg = 'Matrículas vencidas suspendidas: ' . ($result['suspended'] ?? 0) . '.';
                if (!empty($result['errors'])) {
                    $msg .= ' ' . implode(' ', array_slice($result['errors'], 0, 3));
                }
                flash('info', $msg);
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/courses');
            exit;
        });

        $router->get('/admin/mail-templates', static function () use ($repo): void {
            Auth::requireAdmin();
            $audience = trim((string) ($_GET['audience'] ?? ''));
            $items = $repo()->mailTemplates(false);
            if ($audience !== '') {
                $items = array_values(array_filter(
                    $items,
                    static fn (array $t): bool => ($t['audience'] ?? '') === $audience
                ));
            }
            view('admin/mail_templates/index', [
                'title' => 'Plantillas de correo',
                'items' => $items,
                'audience' => $audience,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/mail-templates/create', static function (): void {
            Auth::requireAdmin();
            view('admin/mail_templates/form', [
                'title' => 'Nueva plantilla de correo',
                'item' => null,
                'tokens' => \App\Mail\CaseMailService::tokenHelp(),
                'error' => flash('error'),
            ]);
        });

        $router->get('/admin/mail-templates/edit', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $item = $repo()->mailTemplate($id);
            if (!$item) {
                flash('error', 'Plantilla no encontrada.');
                header('Location: /admin/mail-templates');
                exit;
            }
            view('admin/mail_templates/form', [
                'title' => 'Editar plantilla · ' . $item['code'],
                'item' => $item,
                'tokens' => \App\Mail\CaseMailService::tokenHelp(),
                'error' => flash('error'),
            ]);
        });

        $router->post('/admin/mail-templates/save', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            try {
                $savedId = $repo()->saveMailTemplate([
                    'code' => (string) ($_POST['code'] ?? ''),
                    'name' => (string) ($_POST['name'] ?? ''),
                    'audience' => (string) ($_POST['audience'] ?? 'student'),
                    'to_mode' => (string) ($_POST['to_mode'] ?? 'student'),
                    'to_fixed' => (string) ($_POST['to_fixed'] ?? ''),
                    'cc_mode' => (string) ($_POST['cc_mode'] ?? 'none'),
                    'cc_fixed' => (string) ($_POST['cc_fixed'] ?? ''),
                    'subject' => (string) ($_POST['subject'] ?? ''),
                    'body_html' => (string) ($_POST['body_html'] ?? ''),
                    'attach_export' => isset($_POST['attach_export']) ? 1 : 0,
                    'attach_regulation' => isset($_POST['attach_regulation']) ? 1 : 0,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ], $id);
                flash('info', 'Plantilla guardada.');
                header('Location: /admin/mail-templates/edit?id=' . $savedId);
                exit;
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                header('Location: ' . ($id ? '/admin/mail-templates/edit?id=' . $id : '/admin/mail-templates/create'));
                exit;
            }
        });

        $router->post('/admin/mail-templates/delete', static function () use ($repo): void {
            Auth::requireAdmin();
            $id = (int) ($_POST['id'] ?? 0);
            try {
                $repo()->deleteMailTemplate($id);
                flash('info', 'Plantilla eliminada.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /admin/mail-templates');
            exit;
        });

        $router->get('/admin/reglamentos-firmados', static function () use ($repo): void {
            Auth::requireAdmin();
            view('admin/regulations/signed', [
                'title' => 'Reglamentos firmados',
                'items' => $repo()->signedRegulations(),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });
    }
}
