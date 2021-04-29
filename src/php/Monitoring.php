<?php


namespace Theme;


use Theme\Log;

/*
 * Monitoring cron task for coteathome, failure sends out alert emails.
 *
 */
class Monitoring
{
    public static function checkBrowscap()
    {
        if (!file_exists(BROWSCAP_LOCAL_DESINATION) || ini_get('browscap') == false || !get_browser(null, true)) {
            log::error('Monitoring error: Browscap not found');
        }
    }

    public static function selfCheck()
    {
        self::checkBrowscap();
    }
}


