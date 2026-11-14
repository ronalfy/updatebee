<?php
/**
 * Temporary CI probe. This comment mentions ABSPATH and must not count as a guard.
 *
 * if ( ! defined( 'ABSPATH' ) ) { exit; }
 *
 * @package updatebee
 */

declare(strict_types=1);

namespace UpdateBee\CiProbe;

// defined( 'ABSPATH' ) || exit;

$updatebee_abspath_probe = 1;
