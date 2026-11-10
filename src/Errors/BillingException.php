<?php

declare(strict_types=1);

namespace HeroShots\Errors;

/**
 * The charge could not be made: no payment method, or billing/VAT data missing.
 *
 * Code "wallet_vat_validation_required" means the account must complete its
 * billing details (VAT number + VIES check, or the private-person flag) in the portal.
 */
class BillingException extends ApiException
{
}
