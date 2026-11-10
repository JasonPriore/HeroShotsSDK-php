<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Brand side of an agency relationship (brand API key or JWT, /api/v1/brand). */
class Brand extends AbstractResource
{
    /** @return array<string,mixed> POST /api/v1/brand/manager-invite/<token>/accept */
    public function acceptManagerInvite(string $token): array
    {
        return $this->httpPost("/api/v1/brand/manager-invite/{$token}/accept");
    }

    /** @return array<string,mixed> POST /api/v1/brand/manager-invite/<token>/reject */
    public function rejectManagerInvite(string $token): array
    {
        return $this->httpPost("/api/v1/brand/manager-invite/{$token}/reject");
    }

    /** @return array<string,mixed> GET /api/v1/brand/manager-relationship -> current managing agency, if any */
    public function managerRelationship(): array
    {
        return $this->httpGet('/api/v1/brand/manager-relationship');
    }

    /** @return array<string,mixed> GET /api/v1/brand/manager-relationships -> relationship history */
    public function managerRelationships(): array
    {
        return $this->httpGet('/api/v1/brand/manager-relationships');
    }

    /** @return array<string,mixed> POST /api/v1/brand/manager-relationship/termination */
    public function requestTermination(string $reason): array
    {
        return $this->httpPost('/api/v1/brand/manager-relationship/termination', ['json' => ['reason' => $reason]]);
    }

    /** @return array<string,mixed> POST /api/v1/brand/manager-relationship/termination/respond */
    public function respondTermination(bool $accept, ?string $reason = null): array
    {
        $body = self::compact(['accept' => $accept, 'response_reason' => $reason]);
        return $this->httpPost('/api/v1/brand/manager-relationship/termination/respond', ['json' => $body]);
    }

    /** @return array<string,mixed> POST /api/v1/brand/manager-relationship/termination/cancel */
    public function cancelTermination(): array
    {
        return $this->httpPost('/api/v1/brand/manager-relationship/termination/cancel');
    }
}
