<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Auth;
use App\Catalog\CatalogRepository;
use App\Support\Uploader;

final class PartnerRoutes
{
    public static function register(Router $router): void
    {
        $repo = static fn (): CatalogRepository => new CatalogRepository();

        $router->get('/partner', static function () use ($repo): void {
            Auth::requirePartner();
            $user = Auth::user();
            $partner = $repo()->findPartnerByUserId((int) $user['id']);
            $filters = [
                'provider_id' => $_GET['provider_id'] ?? '',
                'q' => trim((string) ($_GET['q'] ?? '')),
                'cenni' => $_GET['cenni'] ?? '',
            ];
            $tierId = isset($partner['partner_tier_id']) ? (int) $partner['partner_tier_id'] : 0;
            view('partner/catalog', [
                'title' => 'Catálogo Teacher Referral',
                'user' => $user,
                'partner' => $partner,
                'canRegister' => Auth::partnerCanRegisterStudents($partner),
                'items' => $repo()->publishedCertificationsForPartner(
                    $tierId > 0 ? $tierId : null,
                    $filters
                ),
                'providers' => $repo()->providers(true),
                'filters' => $filters,
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->get('/partner/certificacion', static function () use ($repo): void {
            Auth::requirePartner();
            $user = Auth::user();
            $partner = $repo()->findPartnerByUserId((int) $user['id']);
            $slug = trim((string) ($_GET['slug'] ?? ''));
            $item = $slug !== '' ? $repo()->certificationBySlug($slug) : null;
            if (!$item || !(int) $item['is_published']) {
                http_response_code(404);
                echo 'Certificación no encontrada.';
                exit;
            }

            $partnerPrice = null;
            $tierId = isset($partner['partner_tier_id']) ? (int) $partner['partner_tier_id'] : 0;
            if ($tierId > 0) {
                $partnerPrice = $repo()->certificationTierPrice((int) $item['id'], $tierId);
            }

            $protocolSteps = [];
            if (!empty($item['protocol_id'])) {
                $protocolSteps = $repo()->protocolSteps((int) $item['protocol_id'], true);
            }

            view('partner/show', [
                'title' => $item['name'],
                'user' => $user,
                'partner' => $partner,
                'canRegister' => Auth::partnerCanRegisterStudents($partner),
                'item' => $item,
                'partnerPrice' => $partnerPrice,
                'protocolSteps' => $protocolSteps,
                'courses' => $repo()->certificationCourses((int) $item['id']),
                'assets' => $repo()->assets('certification', (int) $item['id']),
                'providerAssets' => $repo()->assets('provider', (int) $item['provider_id']),
            ]);
        });

        $router->get('/partner/convenio', static function () use ($repo): void {
            Auth::requirePartner();
            $user = Auth::user();
            $partner = $repo()->findPartnerByUserId((int) $user['id']);
            if (!$partner) {
                flash('error', 'Tu usuario aún no tiene ficha de partner.');
                header('Location: /partner');
                exit;
            }
            $assignment = $repo()->openAssignmentForPartner((int) $partner['id']);
            view('partner/convenio', [
                'title' => 'Convenio TR',
                'user' => $user,
                'partner' => $partner,
                'assignment' => $assignment,
                'canRegister' => Auth::partnerCanRegisterStudents($partner),
                'info' => flash('info'),
                'error' => flash('error'),
            ]);
        });

        $router->post('/partner/convenio/upload', static function () use ($repo): void {
            Auth::requirePartner();
            $user = Auth::user();
            $partner = $repo()->findPartnerByUserId((int) $user['id']);
            if (!$partner) {
                flash('error', 'Tu usuario aún no tiene ficha de partner.');
                header('Location: /partner');
                exit;
            }
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            try {
                $file = $_FILES['signed_pdf'] ?? null;
                if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    throw new \RuntimeException('Selecciona el PDF firmado.');
                }
                $path = Uploader::store($file, 'partners/' . (int) $partner['id'] . '/signed');
                $repo()->submitPartnerSignedAgreement((int) $partner['id'], $assignmentId, $path);
                flash('info', 'Convenio firmado enviado. Doceo lo revisará y te reactivará el acceso completo.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            header('Location: /partner/convenio');
            exit;
        });
    }
}
