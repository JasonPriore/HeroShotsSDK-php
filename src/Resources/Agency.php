<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/**
 * Agency: managed brands (agency API key or JWT, /api/v1/agency).
 *
 * To generate *as* a managed brand, set the managed user on the client
 * ($client->withManagedUser(...)) and call the normal resources.
 */
class Agency extends AbstractResource
{
    private const BASE = '/api/v1/agency/managed-clients';

    /** @return array<string,mixed> GET /api/v1/agency/managed-clients -> {clients: [...]} */
    public function clients(): array
    {
        return $this->httpGet(self::BASE);
    }

    /** @return array<string,mixed> GET /api/v1/agency/managed-clients/<id> -> relationship, wallet, generations */
    public function client(int|string $relationshipId): array
    {
        return $this->httpGet(self::BASE . "/{$relationshipId}");
    }

    /**
     * POST /api/v1/agency/managed-clients/invite -> invite a brand by email.
     *
     * Optional markup terms: markup_terms or the flat image_fixed, image_pct,
     * video_short_fixed... fields.
     *
     * @param array<string,mixed> $terms
     * @return array<string,mixed>
     */
    public function inviteClient(string $inviteEmail, array $terms = []): array
    {
        return $this->httpPost(self::BASE . '/invite', ['json' => array_merge(['invite_email' => $inviteEmail], $terms)]);
    }

    /**
     * POST /api/v1/agency/managed-clients/direct -> create a brand without invite.
     *
     * @param array<string,mixed> $legalConsents
     * @param array<string,mixed> $terms
     * @return array<string,mixed>
     */
    public function createClient(string $companyName, string $companyCountry, array $legalConsents, ?string $vatNumber = null, array $terms = []): array
    {
        $body = self::compact(array_merge([
            'company_name' => $companyName,
            'company_country' => $companyCountry,
            'legal_consents' => $legalConsents,
            'vat_number' => $vatNumber,
        ], $terms));
        return $this->httpPost(self::BASE . '/direct', ['json' => $body]);
    }

    /** @return array<string,mixed> PATCH /api/v1/agency/managed-clients/<id> ($accessMode: autonomous | read_only) */
    public function updateClient(int|string $relationshipId, string $accessMode): array
    {
        return $this->httpPatch(self::BASE . "/{$relationshipId}", ['json' => ['access_mode' => $accessMode]]);
    }

    /** @return array<string,mixed> POST /api/v1/agency/managed-clients/<id>/resend-invite */
    public function resendInvite(int|string $relationshipId): array
    {
        return $this->httpPost(self::BASE . "/{$relationshipId}/resend-invite");
    }

    /** @return array<string,mixed> POST /api/v1/agency/managed-clients/<id>/claim-invite -> portal access link */
    public function claimInvite(int|string $relationshipId, string $inviteEmail): array
    {
        return $this->httpPost(self::BASE . "/{$relationshipId}/claim-invite", ['json' => ['invite_email' => $inviteEmail]]);
    }

    /** @return array<string,mixed> POST /api/v1/agency/managed-clients/<id>/topup -> move EUR from the agency wallet */
    public function topup(int|string $relationshipId, float $amount): array
    {
        return $this->httpPost(self::BASE . "/{$relationshipId}/topup", ['json' => ['amount' => $amount]]);
    }

    /** @return array<string,mixed> POST /api/v1/agency/managed-clients/<id>/termination */
    public function requestTermination(int|string $relationshipId, string $reason): array
    {
        return $this->httpPost(self::BASE . "/{$relationshipId}/termination", ['json' => ['reason' => $reason]]);
    }

    /** @return array<string,mixed> POST /api/v1/agency/managed-clients/<id>/termination/respond */
    public function respondTermination(int|string $relationshipId, bool $accept, ?string $reason = null): array
    {
        $body = self::compact(['accept' => $accept, 'response_reason' => $reason]);
        return $this->httpPost(self::BASE . "/{$relationshipId}/termination/respond", ['json' => $body]);
    }

    /** @return array<string,mixed> POST /api/v1/agency/managed-clients/<id>/termination/cancel */
    public function cancelTermination(int|string $relationshipId): array
    {
        return $this->httpPost(self::BASE . "/{$relationshipId}/termination/cancel");
    }
}
