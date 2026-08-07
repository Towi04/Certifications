<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Auth;
use App\Catalog\CatalogRepository;
use App\Support\Str;

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
                'items' => $repo()->publishedCertificationsForPartner(
                    $tierId > 0 ? $tierId : null,
                    $filters
                ),
                'providers' => $repo()->providers(true),
                'filters' => $filters,
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
                'item' => $item,
                'partnerPrice' => $partnerPrice,
                'protocolSteps' => $protocolSteps,
                'courses' => $repo()->certificationCourses((int) $item['id']),
                'assets' => $repo()->assets('certification', (int) $item['id']),
                'providerAssets' => $repo()->assets('provider', (int) $item['provider_id']),
            ]);
        });
    }
}
