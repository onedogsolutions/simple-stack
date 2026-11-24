<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * WordPress App SSTK_Cloud_Provider_Test.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SSTK_Cloud_Provider_Test
 *
 * Test double to avoid calling a real provider
 */
class SSTK_Cloud_Provider_Test extends CLOUD_PROVIDER_API {

    public function call()
    {
        return ['status' => 'done'];
    }
}