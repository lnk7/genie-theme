<?php

namespace Theme;

use Theme\Forms\ContactForm;
use Theme\Objects\DeliveryArea;
use Theme\Objects\ProductComponent;
use Theme\Utils\File;
use Lnk7\Genie\Cache;
use Lnk7\Genie\Options;
use WP_CLI;

/**
 * Class Deploy
 *
 * @package Cote
 */
class Deploy
{


    /**
     * Run after a deployment.
     */
    public static function deploy()
    {

        set_time_limit(0);

        do_action('before_deploy');
        WP_CLI::log('Deployment started');

        // Make our folders
        File::maybeCreateFolder(ContactForm::getContactFormFolder());
        File::maybeCreateFolder(Theme::getCahDataFolder());
        File::maybeCreateFolder(Log::logPath());

        // Clear Cache
        WP_CLI::log('Clearing cache');
        Cache::clearCache();

        // Handle any releases
        WP_CLI::log('Loading releases');
        static::loadReleases();

        // Process our Tables
        WP_CLI::log('Processing tables');
        static::processTables();

        // Just to make sure we're up to date
        WP_CLI::log('Rebuilding postcode lookup');
        DeliveryArea::rebuildPostcodeLookup();

        // Update ProductComponents
        WP_CLI::log('Updating product component titles');
        ProductComponent::updateTitles();

        // We have to do this as dompdf assumes all your fonts are in the vendor directory... nice.
        WP_CLI::log('Installing Fonts');
        FontLoader::installFonts();

        // sort out our paths
        WP_CLI::log('Flushing rewrite rules');
        flush_rewrite_rules(true);

        do_action('after_deploy');
        WP_CLI::log('Deployment Complete');

    }


    /**
     * process any classes in the Releases folder
     */
    protected static function loadReleases()
    {

        $releasesTracking = Options::get('releases', []);

        $files = glob(get_stylesheet_directory() . "/src/php/Releases/*.php");
        foreach ($files as $file) {
            $className = 'CoteAtHome\\Releases\\' . pathinfo($file, PATHINFO_FILENAME);

            if (!in_array($className, $releasesTracking) or $className::$runOnce === false) {
                if (method_exists($className, 'run')) {
                    $result = $className::run();
                    WP_CLI::log('...'.$className . ': ' . $result);
                    $releasesTracking[$className] = $className;
                }
            }
        }
        Options::set('releases', $releasesTracking);

    }


    /**
     * Load all our tables
     */
    protected static function processTables()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $sqlStatements = apply_filters('deploy_tables', []);
        foreach ($sqlStatements as $sqlStatement) {
            dbDelta($sqlStatement);
        }
    }

}
