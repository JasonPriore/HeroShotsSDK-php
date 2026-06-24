<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Billing & wallet (portal JWT, /api/auth/billing|wallet|transactions|stripe). */
class Billing extends AbstractResource
{
    /** @return array<string,mixed> */
    public function paymentMethods(): array
    {
        return $this->httpGet('/api/auth/billing');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function addPaymentMethod(array $fields): array
    {
        return $this->httpPost('/api/auth/billing', ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function deletePaymentMethod(string $pmId): array
    {
        return $this->httpDelete("/api/auth/billing/{$pmId}");
    }

    /** @return array<string,mixed> */
    public function getBillingInfo(): array
    {
        return $this->httpGet('/api/auth/billing/info');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function updateBillingInfo(array $fields): array
    {
        return $this->httpPut('/api/auth/billing/info', ['json' => $fields]);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function topup(array $fields): array
    {
        return $this->httpPost('/api/auth/billing/topup', ['json' => $fields]);
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function walletMovements(array $query = []): array
    {
        return $this->httpGet('/api/auth/wallet/movements', $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> */
    public function getAutoRecharge(): array
    {
        return $this->httpGet('/api/auth/wallet/auto-recharge');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function setAutoRecharge(array $fields): array
    {
        return $this->httpPost('/api/auth/wallet/auto-recharge', ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function disableAutoRecharge(): array
    {
        return $this->httpDelete('/api/auth/wallet/auto-recharge');
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function transactions(array $query = []): array
    {
        return $this->httpGet('/api/auth/transactions', $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> */
    public function invoice(string $txnId): array
    {
        return $this->httpPost("/api/auth/transactions/{$txnId}/invoice");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function stripeSetupIntent(array $fields = []): array
    {
        return $this->httpPost('/api/auth/stripe/setup-intent', ['json' => $fields]);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function stripeConfirmPaymentMethod(array $fields): array
    {
        return $this->httpPost('/api/auth/stripe/confirm-payment-method', ['json' => $fields]);
    }
}
