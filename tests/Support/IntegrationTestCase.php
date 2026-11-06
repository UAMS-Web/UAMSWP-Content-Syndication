<?php

declare(strict_types=1);

namespace UAMS\ContentSyndication\Tests\Support;

use UAMS_Syndicate_Shortcode_News;
use WP_UnitTest_Factory;
use WP_UnitTestCase;

/**
 * Real WordPress test transactions, without a PHPUnit compatibility adapter.
 *
 * @command  tests/Integration/vendor/bin/phpunit -c tests/Integration/phpunit.xml
 */
abstract class IntegrationTestCase extends WP_UnitTestCase
{
    /**
     * Enable the pretty REST URLs the plugin constructs unconditionally.
     */
    public function set_up(): void
    {
        // Reset WordPress state before selecting the fixture permalink mode.
        parent::set_up();
        $this->set_permalink_structure('/%postname%/');
    }

    /**
     * Return the shared real WordPress factory.
     *
     * @return WP_UnitTest_Factory Fixture factory.
     */
    protected static function factory(): WP_UnitTest_Factory
    {
        return parent::factory();
    }

    /**
     * Retrieve the instance registered by the actual plugin init hook.
     *
     * @return UAMS_Syndicate_Shortcode_News Registered shortcode handler.
     */
    protected function news(): UAMS_Syndicate_Shortcode_News
    {
        global $shortcode_tags;

        return $shortcode_tags['uamswp_news'][0];
    }
}
