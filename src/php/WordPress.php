<?php

namespace Theme;

use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;

class WordPress implements GenieComponent
{


    public static function setup()
    {

        // Remove Dashboard Widgets
        HookInto::Action('admin_init', 20)
            ->run(function () {
                remove_meta_box('woocommerce_dashboard_status', 'dashboard', 'normal');
                remove_meta_box('e-dashboard-overview', 'dashboard', 'normal');
                remove_meta_box('woocommerce_dashboard_recent_reviews', 'dashboard', 'normal');
                remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
                remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
                remove_meta_box('dashboard_primary', 'dashboard', 'normal');
                remove_meta_box('dashboard_secondary', 'dashboard', 'normal');
                remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
                remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
                remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
                remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
                remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
                remove_meta_box('dashboard_activity', 'dashboard', 'normal');
                remove_meta_box('dashboard_site_health', 'dashboard', 'normal');

            });
    }

}
