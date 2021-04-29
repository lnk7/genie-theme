<?php

namespace Theme\Objects;

use Theme\Utils\Time;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Fields\DateTimeField;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\SelectField;
use Lnk7\Genie\Fields\TabField;
use Lnk7\Genie\Fields\TextAreaField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\Where;

/**
 * Class Redirection
 * @package Cote\PostTypes
 *
 * @property $from
 * @property $to
 * @property $type
 * @property $description
 * @property $hits
 * @property $last_hit
 *
 */
class Redirection extends CustomPost
{

    static $postType = 'redirection';



    public static function setup()
    {

        Parent::setup();

        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-update')
            ->set('supports', false)
            ->backendOnly()
            ->set('capabilities', [
                'edit_post'          => 'shop_admin',
                'edit_posts'         => 'shop_admin',
                'edit_others_posts'  => 'shop_admin',
                'publish_posts'      => 'shop_admin',
                'read_post'          => 'shop_admin',
                'read_private_posts' => 'shop_admin',
                'delete_post'        => 'shop_admin',
            ])
            ->register();

        CreateSchema::Called('Redirection')
            ->instructionPlacement('field')
            ->withFields([
                TabField::Called('details'),
                TextField::Called('from')
                    ->required(true)
                    ->prepend('/')
                    ->wrapperWidth(33),
                TextField::Called('to')
                    ->prepend('/')
                    ->instructions('Leave blank to redirect to the home page')
                    ->wrapperWidth(33),
                SelectField::Called('type')
                    ->wrapperWidth(33)
                    ->returnFormat('value')
                    ->choices([
                        '301' => '301',
                        '302' => '302',
                        '307' => '307',
                    ])
                    ->default('307'),
                TextAreaField::Called('description')
                    ->rows(3),
                TabField::Called('stats'),
                NumberField::Called('hits'),
                DateTimeField::Called('last_hit')
                    ->displayFormat('d/m/y g:i:a')
                    ->returnFormat('Y-m-d H:i:s'),
            ])
            ->shown(Where::field('post_type')->equals(static::$postType))
            ->attachTo(static::class)
            ->register();


        HookInto::action('acf/save_post', 30)
            ->run(function ($post_id) {
                global $post;

                if (!$post or $post->post_type != static::$postType) {
                    return;
                }

                //Cleanup !
                $redirection = new static($post_id);
                if (!$redirection->hits) {
                    $redirection->hits = 0;
                }
                $redirection->save();

            });

        HookInto::action('template_redirect')->run(function () {

            // Do not run if we're in CLI mode.
            if (defined('WP_CLI')) {
                return;
            }

            // Do we need to redirect ?
            $url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $parts = parse_url($url);
            $path = sanitize_text_field(static::cleanPath($parts['path']));

            if (!$path) {
                return;
            }

            $redirection = static::getByTitle($path);

            if (!$redirection) {
                return;
            }

            $queryParts = [];
            if (isset($parts['query'])) {
                $queryParts['query'] = $parts['query'];
            }

            $url = trailingslashit(home_url($redirection->to));

            $url = http_build_url(
                $url,
                $queryParts,
                HTTP_URL_STRIP_AUTH | HTTP_URL_JOIN_PATH | HTTP_URL_JOIN_QUERY
            );

            $redirection->hits++;
            $redirection->last_hit = Time::now()->format('Y-m-d H:i:s');
            $redirection->save();

            wp_redirect($url, $redirection->type);
            exit;

        });

    }



    public function beforeSave()
    {

        $this->from = static::cleanPath($this->from);
        $this->to = static::cleanPath($this->to);
        $this->post_title = $this->from;
    }



    public function setDefaults()
    {
        parent::setDefaults();
        $this->hits = 0;

    }



    /**
     * @param string $from
     * @param string $to
     * @param int $type
     * @return $this|bool|Redirection
     */
    public static function updateOrNew(string $from, string $to, int $type = 307)
    {
        $from = static::cleanPath($from);
        $to = static::cleanPath($to);

        $redirection = static::getByTitle($from);

        if (!$redirection) {
            $redirection = new static();
            $redirection->from = $from;
        }

        $redirection->to = $to;
        $redirection->type = $type;
        $redirection->save();

        return $redirection;


    }



    /**
     * remove beginniong and ending /
     *
     * @param $path
     * @return string
     */
    protected static function cleanPath($path)
    {
        return ltrim(untrailingslashit($path), '/');
    }


}
