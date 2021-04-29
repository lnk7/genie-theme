<?php

namespace Theme;

use Theme\Commands\Commands;
use Theme\Commands\Referrals;
use Theme\Commands\Report;
use Theme\Commands\Roles;
use Theme\Parsers\RefererParser;
use Theme\Reports\StatsReport;
use Lnk7\Genie\AjaxHandler;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\RegisterApi;
use Lnk7\Genie\View;
use WP_CLI;

/**
 * Class Plugin
 *
 * @package CoteAtHome
 */
class Theme implements GenieComponent
{


    public static function setup()
    {

        if (defined('BROWSCAP') && BROWSCAP) {
            ini_set('browscap', BROWSCAP);
        }

        HookInto::action('wp_enqueue_scripts')
            ->run(function () {
                wp_enqueue_script('frontend-js', get_stylesheet_directory_uri() . '/dist/frontend.js', [], '', true);
                wp_enqueue_style('frontend-css', get_stylesheet_directory_uri() . '/dist/frontend.css');
            });

        HookInto::action('after_setup_theme')
            ->run(function () {
                show_admin_bar(false);
            });

        /**
         * Make sure we ignore SSL verification
         */
        HookInto::filter('https_ssl_verify')->returnFalse();

        /**
         * Add Migration CLI Commands
         */
        HookInto::action('cli_init')
            ->run(function () {
                //WP_CLI::add_command('migrate', Migration::class);
                WP_CLI::add_command('roles', Roles::class);
                WP_CLI::add_command('cah', Commands::class);
                WP_CLI::add_command('report', Report::class);
                WP_CLI::add_command('referrals', Referrals::class);
                WP_CLI::add_command('referer', RefererParser::class);
                WP_CLI::add_command('deploy', function () {
                    Deploy::deploy();
                });

            });

        /**
         * Set the cookie name for the session
         */
        HookInto::filter('genie_session_name')
            ->run(function ($name) {
                return 'cah_session';
            });


        HookInto::action('init', 1000)
            ->run(function () {
//                OrderReport::orderID(856927);
//                exit;
            });


        HookInto::Action('wp_head')
            ->run(function () {

                if (defined('GOOGLE_ANALYTICS_ID')) {
                    View::with('theme/wp_head.twig')
                        ->addVar('googleAnalyticsID', GOOGLE_ANALYTICS_ID)
                        ->addVar('useFacebookPixel', Theme::inProduction())
                        ->addVar('trackingEndpoint', AjaxHandler::generateUrl('tracking'))
                        ->addVar('exponeaTracking', defined('EXPONEA_TRACKING') && EXPONEA_TRACKING)
                        ->display();
                }

            });

        //Prevent deletion  of products in css
        HookInto::action('admin_head', 1)
            ->run(function () {
                if(get_post_type() == 'product'){
                    echo '<style> .submitdelete,div .row-actions .trash{display:none !important;} </style>';
                }
            });

        //Prevent mass deletion of products too
        HookInto::filter('bulk_actions-edit-product')
            ->run(function ($actions) {
                unset( $actions[ 'trash' ] );
                return $actions;
            });



        RegisterApi::get('stats-test')
            ->run(function () {

                if (Theme::inProduction()) {
                    return;
                }

                WooCommerce::abandonedCartCleanup();


            });

    }



    /**
     * Where do we store reports and other data?
     *
     * @return string
     */
    public static function getCahDataFolder()
    {
        return trailingslashit(dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'cah-data');
    }



    /**
     * get the version number
     * @return float
     */
    public static function getVersion()
    {
        $theme = wp_get_theme();
        return (float)$theme->get('Version');
    }



    /**
     * Are we in a development environment?
     *
     * @return bool
     */
    public static function inDevelopment()
    {
        return !static::inProduction();
    }



    /**
     * Are we in a production environment?
     *
     * @return bool
     */
    public static function inProduction()
    {
        return defined('COTE_ENVIRONMENT') && COTE_ENVIRONMENT === 'production';
    }

}
