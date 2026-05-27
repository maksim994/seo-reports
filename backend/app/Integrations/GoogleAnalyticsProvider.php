<?php

namespace App\Integrations;

use App\Enums\IntegrationProvider;
use App\Models\Integration;

class GoogleAnalyticsProvider extends GoogleOAuthProvider
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::GoogleAnalytics;
    }

    protected function configKey(): string
    {
        return 'google_analytics';
    }

    public function listResources(Integration $integration): array
    {
        $token = $integration->credentials['access_token'] ?? null;
        if (! $token) {
            return [];
        }

        $data = $this->googleGet($token, 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries');
        $resources = [];

        foreach ($data['accountSummaries'] ?? [] as $account) {
            foreach ($account['propertySummaries'] ?? [] as $property) {
                $propertyId = $property['property'] ?? null;
                if (! $propertyId) {
                    continue;
                }

                $resources[] = [
                    'id' => $propertyId,
                    'label' => $property['displayName'] ?? $propertyId,
                    'meta' => [
                        'account' => $account['displayName'] ?? null,
                    ],
                ];
            }
        }

        return $resources;
    }
}
