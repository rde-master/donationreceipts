<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Attribute;

use Attribute;

/**
 * Marks a controller that AccessMiddleware must not restrict to the allowed
 * group. Only for controllers that are already guarded otherwise (the admin
 * settings controller requires a Nextcloud admin).
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AccessGroupExempt {
}
