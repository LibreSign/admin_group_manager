<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AdminGroupManager\Controller\Attribute;

use Attribute;

/**
 * Attribute for controller methods to restrict access by IP address
 *
 * @since 30.0.0
 */
#[Attribute]
class RestrictIp {
}
