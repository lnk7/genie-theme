<?php

namespace Theme\Objects;

use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateTaxonomy;

class FAQ extends CustomPost
{

    static $postType = 'faq';

    static $taxonomy = 'faq_category';

    protected static $singular = 'FAQ';

    protected static $plural = 'FAQs';



    static public function setup()
    {

        parent::setup();

        CreateTaxonomy::Called(static::$taxonomy)
            ->register();

        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-admin-comments')
            ->addTaxonomy(static::$taxonomy)
            ->backendOnly()
            ->removeSupportFor(['thumbnail'])
            ->register();

    }

}

