<?php

namespace Theme\Commands;

use Theme\APIs\Shopify;
use Theme\Media;
use Theme\Objects\Product;
use Theme\Objects\Redirection;
use Theme\Theme;
use Theme\WooCommerce;
use Lnk7\Genie\Utilities\HookInto;
use WC_Cache_Helper;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_CLI;
use function WP_CLI\Utils\make_progress_bar;

class Migration
{

    public static $recommendations = [
        'beef-bourguignon'                                  => 'minted-peas,french-beans',
        'poulet-grille'                                     => 'minted-peas,french-beans',
        'poulet-breton'                                     => 'minted-peas,french-beans',
        'haddock-and-salmon-fishcake'                       => 'frites',
        'chicken-and-bacon-parmentier'                      => 'minted-peas,french-beans',
        'haddock-and-salmon-parmentier'                     => 'minted-peas,french-beans',
        'lamb-parmentier'                                   => 'minted-peas,french-beans',
        'dry-aged-cote-de-boeuf-400g'                       => 'roquefort-butter-2-x-30g-1,garlic-butter-2-x-30g,peppercorn-sauce-1-x-50g',
        'fillet-steak-2-x-7oz'                              => 'roquefort-butter-2-x-30g-1,garlic-butter-2-x-30g,peppercorn-sauce-1-x-50g',
        'ribeye-steak-2-x-8oz'                              => 'roquefort-butter-2-x-30g-1,garlic-butter-2-x-30g,peppercorn-sauce-1-x-50g',
        'sirloin-steak-2-x-8oz'                             => 'roquefort-butter-2-x-30g-1,garlic-butter-2-x-30g,peppercorn-sauce-1-x-50g',
        'smoked-salmon-200g'                                => 'demi-baguette',
        'chicken-liver-parfait'                             => 'demi-baguette,cornichons-80g',
        'smoked-mackerel-rillettes'                         => 'demi-baguette',
        'pork-rillettes'                                    => 'demi-baguette,cornichons-80g',
        'chateaubriand-1-x-1kg'                             => 'roquefort-butter-2-x-30g-1,garlic-butter-2-x-30g,peppercorn-sauce-1-x-50g,gravieres,gratin-potato,creamed-spinach',
        'trimmed-pork-fillet'                               => 'pinot-noir-les-mougeottes,roasted-peppers',
        'marinated-chicken-supreme-2-x-400g'                => 'garlic-butter-2-x-30g,fleurie-la-bonne-dame,haricot-beans-with-spinach',
        'duck-confit'                                       => 'merlot-chemin-de-marquiere,gratin-potato,haricot-beans-with-spinach',
        'lamb-chops-4-x-150g'                               => 'roquefort-butter-2-x-30g-1,garlic-butter-2-x-30g,peppercorn-sauce-1-x-50g,chateau-la-croix-bordeaux,potato-puree,minted-peas',
        'en-famille-meat-box'                               => 'roquefort-butter-2-x-30g-1,garlic-butter-2-x-30g,peppercorn-sauce-1-x-50g,chateau-treviac,haricot-beans-with-spinach,frites',
        'prestige-steak-selection'                          => 'roquefort-butter-2-x-30g-1,garlic-butter-2-x-30g,peppercorn-sauce-1-x-50g,gravieres,gratin-potato,creamed-spinach',
        'salmon-with-ratatouille'                           => 'cuvee-laborie,frites',
        'roasted-seabream-with-asparagus'                   => 'chateau-du-poyet-muscadet,potato-puree',
        'risotto-vert'                                      => 'chardonnay-maison-laiglon,roasted-peppers',
        'hachis-parmentier'                                 => 'merlot-chemin-de-marquiere,minted-peas',
        'vegetarian-sausages'                               => 'chateau-treviac,ratatouille-with-spinach',
        'prestige-burger-2-x-170g'                          => 'gravieres,',
        'floury-baps'                                       => 'prestige-burger-2-x-170g,back-bacon,cumberland-sausages-500g,beurre-demi-sel-aop-charentes-poitou-la-conviette',
        'sourdough-boule-de-meule'                          => 'beurre-demi-sel-aop-charentes-poitou-la-conviette,beurre-le-saunier-guerande-250g,beurre-echire-mini-motte-doux-salted-250g,beurre-echire-mini-motte-doux-unsalted-250g,confiture-de-myrtilles-blueberry-jam,confiture-de-fraise-strawberry-jam,confiture-d-abricots-apricot-jam,piquant-mixed-olives-120g,saucisson-sec-enrobe-au-poivre-180g,saucisson-sec-enrobe-aux-herbes-de-provence-180g',
        'sourdough-seeded-batard'                           => 'beurre-demi-sel-aop-charentes-poitou-la-conviette,beurre-le-saunier-guerande-250g,beurre-echire-mini-motte-doux-salted-250g,beurre-echire-mini-motte-doux-unsalted-250g,confiture-de-myrtilles-blueberry-jam,confiture-de-fraise-strawberry-jam,confiture-d-abricots-apricot-jam,piquant-mixed-olives-120g,saucisson-sec-enrobe-au-poivre-180g,saucisson-sec-enrobe-aux-herbes-de-provence-180g',
        'croissant'                                         => 'beurre-demi-sel-aop-charentes-poitou-la-conviette,beurre-le-saunier-guerande-250g,beurre-echire-mini-motte-doux-salted-250g,beurre-echire-mini-motte-doux-unsalted-250g,confiture-de-myrtilles-blueberry-jam,confiture-de-fraise-strawberry-jam,confiture-d-abricots-apricot-jam',
        'demi-baguette'                                     => 'beurre-demi-sel-aop-charentes-poitou-la-conviette,beurre-le-saunier-guerande-250g,chicken-liver-parfait,smoked-mackerel-rillettes,pork-rillettes,piquant-mixed-olives-120g,saucisson-sec-enrobe-au-poivre-180g,saucisson-sec-enrobe-aux-herbes-de-provence-180g',
        'boned-and-rolled-leg-of-lamb'                      => 'gratin-potato,french-beans,gravieres',
        'bone-in-leg-of-lamb'                               => 'gratin-potato,french-beans,chateau-haut-pezat',
        'lamb-rack'                                         => 'frites,creamed-spinach,roquefort-butter-2-x-30g-1,fleurie-la-bonne-dame',
        'lamb-t-bones'                                      => 'frites,ratatouille-with-spinach,garlic-butter-2-x-30g,pinot-noir-les-mougeottes',
        'fougasse'                                          => 'piquant-mixed-olives-120g,saucisson-sec-enrobe-au-poivre-180g,saucisson-sec-enrobe-aux-herbes-de-provence-180g',
        'prawn-gratinee'                                    => 'fougasse,demi-baguette,beurre-le-saunier-guerande-250g,cotes-de-provence-rose',
        'marinated-heritage-beetroot'                       => 'fougasse,demi-baguette,beurre-le-saunier-guerande-250g,',
        'chicken-and-walnut-salad'                          => 'fougasse,demi-baguette,beurre-le-saunier-guerande-250g,frites,cuvee-laborie',
        'chargrilled-lamb-brochette'                        => 'frites,gratin-potato',
        'prawn-linguine'                                    => 'fougasse,cotes-de-provence-rose',
        'breaded-chicken-with-fennel'                       => 'frites,gratin-potato',
        'roasted-seabream-with-norfolk-potatoes'            => 'french-beans,creamed-spinach,sancerre-rose',
        'beurre-demi-sel-aop-charentes-poitou-la-conviette' => 'floury-baps,sourdough-boule-de-meule,sourdough-seeded-batard,croissant,demi-baguette',
        'pain-pochon'                                       => 'beurre-demi-sel-aop-charentes-poitou-la-conviette,beurre-le-saunier-guerande-250g,beurre-echire-mini-motte-doux-salted-250g,beurre-echire-mini-motte-doux-unsalted-250g,confiture-de-myrtilles-blueberry-jam,confiture-de-fraise-strawberry-jam,confiture-d-abricots-apricot-jam,piquant-mixed-olives-120g,saucisson-sec-enrobe-au-poivre-180g,saucisson-sec-enrobe-aux-herbes-de-provence-180g',
        'artichoke-with-garlic-butter'                      => 'fougasse,demi-baguette',
        'wild-mushroom-tagliatelle'                         => 'fougasse,demi-baguette,pinot-noir-les-mougeottes',
        'swordfish-piperade'                                => 'frites,roasted-norfolk-new-potatoes,cotes-de-provence-rose',
        'roasted-chicken-supreme'                           => 'creamed-spinach,minted-peas,macon-villages-les-preludes',
        'roasted-rump-of-lamb'                              => 'gravieres,french-beans',
        'wild-mushroom-soup'                                => 'fougasse,demi-baguette',
    ];


    public static $users = [
        ['email' => 'sunil@cote.co.uk', 'first_name' => 'Sunil', 'last_name' => 'Kumar', 'role' => 'administrator', 'password' => 'Ume{t$2ZLWQV9!gG(ahj'],
        ['email' => 'adelle@cote-restaurants.co.uk', 'first_name' => 'Adelle', 'last_name' => 'Taylor', 'role' => 'shop_user', 'password' => '$U2Xu3%]JWfQR^vhsgL='],
        ['email' => 'allan.gray@cote.co.uk', 'first_name' => 'Allan', 'last_name' => 'Gray', 'role' => 'shop_user', 'password' => 't=meEfSJ?[Z*Q3-7LbdW'],
        ['email' => 'daniel@cote-restaurants.co.uk', 'first_name' => 'Daniel', 'last_name' => 'Wonfor', 'role' => 'shop_user', 'password' => 'QMC8GEqrLcYBd%=zw-H7'],
        ['email' => 'darja.ljahhova@cote-restaurants.co.uk', 'first_name' => 'Daria', 'last_name' => 'Ljahhova', 'role' => 'shop_user_plus', 'password' => 'w.cN-Ed$*m{R%(35Qa9V'],
        ['email' => 'felicia@cote-restaurants.co.uk', 'first_name' => 'Felicia', 'last_name' => 'Holst', 'role' => 'shop_user', 'password' => 'XFbv5tph8kHA!dP@C]$j'],
        ['email' => 'athomereports@cote.co.uk', 'first_name' => 'Finance', 'last_name' => 'Reports', 'role' => 'shop_admin', 'password' => 'Y6vmeScs.ygLHG>{n7Nx'],
        ['email' => 'genevieve@cote.co.uk', 'first_name' => 'Genevieve', 'last_name' => 'Sparrow', 'role' => 'shop_admin', 'password' => 'zu[v-x8NchX7!Hs>+/gT'],
        ['email' => 'iulia@cote-restaurants.co.uk', 'first_name' => 'Iulia', 'last_name' => 'Maltev', 'role' => 'shop_user_plus', 'password' => '4!F_c>fsh[aw.9*=7q{2'],
        ['email' => 'jan@cote-restaurants.co.uk', 'first_name' => 'Jan', 'last_name' => 'Baylon', 'role' => 'shop_user', 'password' => '8^&r]@?CZUNvn>G}Tj/L'],
        ['email' => 'jo@cote-restaurants.co.uk', 'first_name' => 'Joanna', 'last_name' => 'Van den Bussche', 'role' => 'shop_user_plus', 'password' => 'MJ/DrQSw[tPGm{]9RYV8'],
        ['email' => 'josie@cote.co.uk', 'first_name' => 'Josie', 'last_name' => 'Price', 'role' => 'shop_admin', 'password' => 'Ps)5Q8_6^3ABhqyz[/a$'],
        ['email' => 'kdrake@cote.co.uk', 'first_name' => 'Katrina', 'last_name' => 'Drake', 'role' => 'administrator', 'password' => 'F+rA$2P3%*tDq/YSZ!Ca'],
        ['email' => 'kelly@cote-restaurants.co.uk', 'first_name' => 'Kelly', 'last_name' => 'Ashenden-Wadham', 'role' => 'shop_user_plus', 'password' => 'b2(/>Rh7x5C9HMuLa-^d'],
        ['email' => 'lesley@cote.co.uk', 'first_name' => 'Lesley', 'last_name' => 'Turner', 'role' => 'shop_user_plus', 'password' => '&Aq=zhgtF3+5^.s6xDXc'],
        ['email' => 'm@lnk7.com', 'first_name' => 'Mark', 'last_name' => 'Buhagiar', 'role' => 'administrator', 'password' => 'z_+g$!7uUNR*XkY}a]%m'],
        ['email' => 'mark.durham@cote.co.uk', 'first_name' => 'Mark', 'last_name' => 'Durham', 'role' => 'shop_user', 'password' => '/8T_FAbtCd(uUaV]}.5n'],
        ['email' => 'mark@cote-restaurants.co.uk', 'first_name' => 'Mark', 'last_name' => 'Gudgin', 'role' => 'shop_user', 'password' => '/6?-Krf=&3_{Gzwtx(U7'],
        ['email' => 'martina@cote.co.uk', 'first_name' => 'Martina', 'last_name' => 'Melandri', 'role' => 'shop_user_plus', 'password' => '[?5nU6c8AL&G@.J!=hwH'],
        ['email' => 'mathieu@cote-restaurants.co.uk', 'first_name' => 'Mathieu', 'last_name' => 'Stein', 'role' => 'shop_user', 'password' => 'ahNEH5.}r7x($APp9[nB'],
        ['email' => 'michele@cote.co.uk', 'first_name' => 'Michele', 'last_name' => 'Bacon', 'role' => 'shop_user', 'password' => '*2_LQK/}nc(z$bP.?7jM'],
        ['email' => 'nasra@cote.co.uk', 'first_name' => 'Nasra', 'last_name' => 'Abdille', 'role' => 'shop_user', 'password' => '^ahvEcRxMHZBkQ7pL=w&'],
        ['email' => 'nicola@cote-restaurants.co.uk', 'first_name' => 'Nicola', 'last_name' => 'Smale', 'role' => 'shop_user_plus', 'password' => 'X9}D>hM]z2V-K^B$S{Q&'],
        ['email' => 'nicoletta@cote-restaurants.co.uk', 'first_name' => 'Nicoletta', 'last_name' => 'Spartaco', 'role' => 'shop_user', 'password' => 'hb(S3pC>!gm{vrXQPxG)'],
        ['email' => 'niki@cote-restaurants.co.uk', 'first_name' => 'Nikola', 'last_name' => 'Kocarova', 'role' => 'shop_user_plus', 'password' => 'djayAx[JmLQ@ZC2RfeN7'],
        ['email' => 'olga.voronova@cote-restaurants.co.uk', 'first_name' => 'Olga', 'last_name' => 'Voronova', 'role' => 'shop_user', 'password' => 'SMr5vGWJ$3V8>P7a^xq/'],
        ['email' => 'paul@cote-restaurants.co.uk', 'first_name' => 'Paul', 'last_name' => 'Livesey', 'role' => 'shop_user', 'password' => '35x_QbrL{}2(@?%e9-Bz'],
        ['email' => 'becky@cote-restaurants.co.uk', 'first_name' => 'Rebecca', 'last_name' => 'Pearce', 'role' => 'shop_user_plus', 'password' => '@X9}3dRw$xf2%ULZr(hJ'],
        ['email' => 'rebecca@cote-restaurants.co.uk', 'first_name' => 'Rebecca', 'last_name' => 'Tooth', 'role' => 'shop_admin', 'password' => '=[Cp+B(J4!)}VLK>F2n?'],
        ['email' => 'sarah.perkins@cote-restaurants.co.uk', 'first_name' => 'Sarah', 'last_name' => 'Perkins', 'role' => 'shop_user', 'password' => '5=V/&xs{DKt)Y4F]er2.'],
        ['email' => 'silvia.gerthoux@cote-restaurants.co.uk', 'first_name' => 'Silvia', 'last_name' => 'Gerthoux', 'role' => 'shop_user', 'password' => 'my&UZ{?T>rS.kd2MN4+R'],
        ['email' => 'simona@cote-restaurants.co.uk', 'first_name' => 'Simona', 'last_name' => 'Viciute', 'role' => 'shop_user_plus', 'password' => '*Qa]V9pe2C[7xWA}5gT.'],
        ['email' => 'sophie@cote-restaurants.co.uk', 'first_name' => 'Sophie', 'last_name' => 'Hawe', 'role' => 'shop_user_plus', 'password' => '$78ayG>+X?[H4=Wt-Vxw'],
        ['email' => 'sharrison@cote-restaurants.co.uk', 'first_name' => 'Stephanie', 'last_name' => 'Harrison-Wood', 'role' => 'shop_user', 'password' => '$jwv[K>tc7eb3r@H^afZ'],
        ['email' => 'steve@cote-restaurants.co.uk', 'first_name' => 'Steve', 'last_name' => 'Seager', 'role' => 'shop_user', 'password' => 'hw(}jH.&@P%upRantme7'],
        ['email' => 'strahan@cote-restaurants.co.uk', 'first_name' => 'Strahan', 'last_name' => 'Wilson', 'role' => 'shop_admin', 'password' => 'UD&+eXj}umz%Hy4S?{w!'],
        ['email' => 'encio.valfer@cote-restaurants.co.uk', 'first_name' => 'Valfer', 'last_name' => 'Encio', 'role' => 'shop_user', 'password' => 'sLeDC^.k+mP=79vuMUWj'],
        ['email' => 'yovana@cote-restaurants.co.uk', 'first_name' => 'Yovana', 'last_name' => 'Veerabudren', 'role' => 'shop_user_plus', 'password' => '4JL-6H!+8$epRnyxAwKD'],
    ];



    /**
     * Copied from woo commerce Docs.
     *
     * @param string $raw_name Name of attribute to create.
     * @param array(string) $terms          Terms to create for the attribute.
     * @return array
     * @since 2.3
     */
    public static function createAttribute($raw_name = 'size', $terms = ['small'])
    {

        global $wc_product_attributes;
        // Make sure caches are clean.
        delete_transient('wc_attribute_taxonomies');
        WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');

        // These are exported as labels, so convert the label to a name if possible first.
        $attribute_labels = wp_list_pluck(wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name');
        $attribute_name = array_search($raw_name, $attribute_labels, true);
        if (!$attribute_name) {
            $attribute_name = wc_sanitize_taxonomy_name($raw_name);
        }

        $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_name);

        if (!$attribute_id) {
            $taxonomy_name = wc_attribute_taxonomy_name($attribute_name);
            // Deregister taxonomy which other tests may have created...
            unregister_taxonomy($taxonomy_name);
            $attribute_id = wc_create_attribute(
                [
                    'name'         => $raw_name,
                    'slug'         => $attribute_name,
                    'type'         => 'select',
                    'order_by'     => 'menu_order',
                    'has_archives' => 0,
                ]
            );
            // Register as taxonomy.
            register_taxonomy(
                $taxonomy_name,
                apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, ['product']),
                apply_filters(
                    'woocommerce_taxonomy_args_' . $taxonomy_name,
                    [
                        'labels'       => [
                            'name' => $raw_name,
                        ],
                        'hierarchical' => false,
                        'show_ui'      => false,
                        'query_var'    => true,
                        'rewrite'      => false,
                    ]
                )
            );
            // Set product attributes global.
            $wc_product_attributes = [];
            foreach (wc_get_attribute_taxonomies() as $taxonomy) {
                $wc_product_attributes[wc_attribute_taxonomy_name($taxonomy->attribute_name)] = $taxonomy;
            }
        }
        $attribute = wc_get_attribute($attribute_id);

        $return = [
            'attribute_name'     => $attribute->name,
            'attribute_taxonomy' => $attribute->slug,
            'attribute_id'       => $attribute_id,
            'term_ids'           => [],
        ];
        foreach ($terms as $term) {
            $result = term_exists($term, $attribute->slug);
            if (!$result) {
                $result = wp_insert_term($term, $attribute->slug);
                $return['term_ids'][] = $result['term_id'];
            } else {
                $return['term_ids'][] = $result['term_id'];
            }
        }
        return $return;
    }



    /**
     *
     * ## OPTIONS
     * [--reset]
     * : reset the wordpress database
     *
     * [--limit]
     * : limit of products to import per page.
     *
     * ## EXAMPLES
     *
     *     wp migrate products --limit=100
     *     wp migrate products --limit=100 --reset
     *
     * @alias products
     */
    public function products($args = [], $assoc_args = [])
    {


        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'limit' => 250,
                'reset' => '',
            ]
        );

        //Reset the import
        if (!empty($arguments->reset)) {
            static::resetProducts();
            WP_CLI::log('Product Reset Complete');
        }

        static::syncCollections();

        WP_CLI::log("Initiating product import...");

        $ids = [];
        $last = '';

        $data = Shopify::getProducts();
        foreach ($data->products->edges as $productID) {

            $last = $productID->cursor;
            $ids[$productID->node->id] = $productID->node->id;
        }

        if (count($ids) === 250) {
            $data = Shopify::getProducts($last);
            foreach ($data->products->edges as $productID) {
                $ids[$productID->node->id] = $productID->node->id;
            }

        }
        if (empty($ids)) {
            WP_CLI::success("Nothing To Import");
            return;
        }

        $productCount = count($ids);
        $progress = make_progress_bar('Importing Products', $productCount);

        // Go through all products
        foreach ($ids as $productID) {
            static::syncProduct($productID);
            $progress->tick();
        }

        $progress->finish();
        WP_CLI::success($productCount . ' products imported successfully.');

    }



    public function recommendations()
    {

        foreach (static::$recommendations as $slug => $slugs) {

            $product_id = Product::findIdBySlug($slug);
            if (!$product_id) {
                continue;
            }

            $recommendedSlugs = explode(',', $slugs);
            $ids = [];
            foreach ($recommendedSlugs as $recommendedSlug) {
                $recommended_id = Product::findIdBySlug($recommendedSlug);

                if ($recommended_id) {
                    $ids[] = $recommended_id;
                }
            }

            WP_CLI::log("Recommendations for $product_id  ($slug): " . implode(',', $ids));

            update_field('field_product_recommended_product_ids', $ids, $product_id);
        }

    }



    public function seo()
    {

        $redirects = [];

        $categories = get_terms(['taxonomy' => 'product_cat']);

        foreach ($categories as $category) {
            $redirects['collections/' . $category->slug] = $category->slug;
        }


        $ids = get_posts([
            'post_type'   => 'product',
            'post_status' => 'any',
            'fields'      => 'ids',
            'numberposts' => -1,
        ]);


        foreach ($ids as $product_id) {
            $product = wc_get_product($product_id);
            $category_ids = $product->get_category_ids();
            foreach ($category_ids as $category_id) {
                $term = get_term($category_id, 'product_cat');
                $redirects['collections/' . $term->slug . '/products/' . $product->get_slug()] = 'collections/' . $term->slug . '/' . $product->get_slug();
            }
        }

        foreach ($redirects as $from => $to) {
            Redirection::updateOrNew($from, $to);
        }


    }



    /**
     * Simple command to test if the cli is working
     */
    public function test()
    {
        if (Theme::inDevelopment()) {
            static::resetProducts();
            static::syncCollections();
            static::syncProduct('gid://shopify/Product/4758928523373');
        }

    }



    public function users()
    {
        global $wpdb;


        $wpdb->query("update $wpdb->users set user_pass = ''");

        HookInto::filter('send_password_change_email')
            ->orFilter('send_email_change_email')
            ->returnFalse();

        foreach (static::$users as $user) {

            $fullName = $user['first_name'] . ' ' . $user['last_name'];

            $data = [

                'user_login'    => $user['email'],
                'user_nicename' => sanitize_title($fullName),
                'user_email'    => $user['email'],
                'display_name'  => $fullName,
                'first_name'    => $user['first_name'],
                'last_name'     => $user['last_name'],
                'role'          => $user['role'],
            ];

            $wpUser = get_user_by('email', $user['email']);
            if ($wpUser) {
                $data['ID'] = $wpUser->ID;
            }

            $user_id = wp_insert_user($data);

            wp_update_user([
                'ID'        => $user_id,
                'user_pass' => $user['password'],
            ]);

        }
        WP_CLI::log("User Migration Complete");

    }



    /**
     * Remove all Products so we can sync with a fresh start.
     *
     */
    protected static function resetProducts()
    {

        // Delete Tags and Categories
        foreach (['product_cat', 'product_tag', 'shopify_type'] as $term) {

            $ids = get_terms($term, ['fields' => 'ids', 'hide_empty' => false]);
            foreach ($ids as $id) {
                wp_delete_term($id, $term);
            }
        }

        // Delete Posts
        $posts = get_posts([
            'post_type'      => ['product', 'product_variation'],
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);

        foreach ($posts as $id) {
            wp_delete_post($id, true);
        }

        // Delete Attributes
        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $attribute) {
            wc_delete_attribute($attribute->attribute_id);
        }

    }



    /**
     * Sync Collections from Shopify
     * These are mapped to product_cat
     */
    protected function syncCollections()
    {

        WP_CLI::log("Syncing Collections...");
        $data = Shopify::getCollections();
        $collections = $data->collections->edges;

        $progress = make_progress_bar('Importing Collections', count($collections));

        foreach ($collections as $collectionNode) {

            $collectionObject = $collectionNode->node;

            $term = get_term_by('name', $collectionObject->title, 'product_cat', ARRAY_A);

            // Create or update the term.
            if (!is_array($term)) {
                $term = wp_insert_term($collectionObject->title, 'product_cat', [
                    'description' => $collectionObject->description,
                ]);
                update_term_meta($term['term_id'], '_shopify_id', $collectionObject->id);
            } else {

                wp_update_term($term['term_id'], 'product_cat', [
                    'description' => $collectionObject->description,
                ]);
            }

            if ($collectionObject->image) {

                $image = $collectionObject->image;
                $attachmentID = Media::findImageIDByPhotoID($image->id);

                if (is_null($attachmentID)) {

                    $attachmentID = Media::createImageFromURL([
                        'url'      => $image->originalSrc,
                        'title'    => $collectionObject->title,
                        'photo_id' => $image->id,
                    ]);

                    if (!$attachmentID) {
                        WP_CLI::log("Could not get image $image->id");

                    }
                }
                if ($attachmentID) {
                    update_term_meta($term['term_id'], 'thumbnail_id', $attachmentID);
                }
            }
            $progress->tick();
        }
        $progress->finish();
    }



    /**
     * Sync one product from Shopify to WooCommerce
     *
     * @param $gid
     */
    protected static function syncProduct($gid)
    {

        // Grab from Shopify
        $product = Shopify::getProduct($gid)->product;

        // What type of product is this?
        $variableProduct = ($product->totalVariants > 1);

        //Options
        $wooProduct = null;
        $productAttribute = null;
        $attributeOptions = [];

        // Go through Each variant and add them one by one.
        foreach ($product->variants->edges as $index => $edge) {

            $variant = $edge->node;

            // Create the main product
            if (!$wooProduct) {

                $productID = WooCommerce::findProductIDByShopifyID($product->id);

                $class = $variableProduct ? WC_Product_Variable::class : WC_Product_Simple::class;
                $wooProduct = new $class($productID);

                //Add our Attributes if this is a variable product
                if ($variableProduct) {

                    $options = $product->options[0]->values;

                    $productAttribute = self::createAttribute($product->options[0]->name, $options);

                    $terms = get_terms([
                        'taxonomy'   => $productAttribute['attribute_taxonomy'],
                        'hide_empty' => false,
                    ]);

                    // Just grab the term_id for the attribute that are being used.
                    $termIDs = [];
                    foreach ($terms as $term) {
                        if (in_array($term->name, $options)) {
                            $termIDs[] = $term->term_id;
                            $attributeOptions[$term->name] = $term->slug;
                        }
                    }

                    $wooProductAttribute = new WC_Product_Attribute();
                    $wooProductAttribute->set_id($productAttribute['attribute_id']);
                    $wooProductAttribute->set_name($productAttribute['attribute_taxonomy']);
                    $wooProductAttribute->set_options($termIDs);
                    $wooProductAttribute->set_visible(true);
                    $wooProductAttribute->set_position(1);
                    $wooProductAttribute->set_variation(true);

                    $wooProduct->set_attributes([$wooProductAttribute]);

                }

                $wooProduct->set_name($product->title);
                $wooProduct->set_description($product->descriptionHtml);
                $wooProduct->set_regular_price($variant->price);
                $wooProduct->set_price($variant->price);

                if ($variant->compareAtPrice) {
                    $wooProduct->set_sale_price($variant->compareAtPrice);
                }
                $wooProduct->set_manage_stock(false);
                $wooProduct->set_stock_status('instock');

                if ($variant->weight) {
                    $wooProduct->set_weight($variant->weight);
                }

                $galleryImages = [];

                foreach ($product->images->edges as $index => $imageEdge) {

                    $image = $imageEdge->node;

                    $attachmentID = Media::findImageIDByPhotoID($image->id);

                    if (is_null($attachmentID)) {

                        $attachmentID = Media::createImageFromURL([
                            'url'      => $image->originalSrc,
                            'title'    => $wooProduct->get_name(),
                            'photo_id' => $image->id,
                        ]);

                        if (!$attachmentID) {
                            WP_CLI::log("Product {$product->title} could not get image $image->id");
                            continue;
                        }
                    }

                    if ($product->featuredImage->id === $image->id) {
                        $wooProduct->set_image_id($attachmentID);
                    } else {
                        $galleryImages[] = $attachmentID;
                    }
                }

                $wooProduct->set_gallery_image_ids($galleryImages);


                // Add Tags
                $tagTermIDs = [];
                $labelTermIDs = [];
                foreach ($product->tags as $tag) {

                    $taxonomy = strpos($tag, '__label') === 0 ? Product::labelTaxonomy : 'product_tag';

                    $tag = str_replace('__label:', '', $tag);

                    $term = get_term_by('name', $tag, $taxonomy, ARRAY_A);
                    if (!is_array($term)) {
                        $term = wp_insert_term($tag, $taxonomy);
                    }

                    if (is_array($term) && !empty($term)) {
                        if ($taxonomy === Product::labelTaxonomy) {
                            $labelTermIDs[] = $term['term_id'];
                        } else {
                            $tagTermIDs[] = $term['term_id'];
                        }
                    }

                }
                $wooProduct->set_tag_ids($tagTermIDs);

                $wooProduct->set_status($product->published ? 'publish' : 'draft');

                // Collections
                $collections = $product->collections->edges;
                $categoryIDs = [];
                foreach ($collections as $collectionObject) {

                    $title = $collectionObject->node->title;

                    $term = get_term_by('name', $title, 'product_cat', ARRAY_A);
                    if (is_array($term)) {
                        $categoryIDs[] = $term['term_id'];
                    }
                }
                $wooProduct->set_category_ids($categoryIDs);

                // Now save the product
                $wooProduct->save();

                //set labels
                wp_set_post_terms($wooProduct->get_id(), $labelTermIDs, Product::labelTaxonomy);

                if ($product->productType) {
                    wp_set_post_terms($wooProduct->get_id(), $product->productType, 'shopify_type', true);
                }


                //Barcode
                $barcode = '';
                if ($variant->barcode) {
                    $barcode = $variant->barcode;
                }

                if (!$barcode && $variant->sku) {
                    $barcode = $variant->sku;
                }


                // Non WooCommerce Stuff we need to save.
                wp_update_post([
                    'ID'          => $wooProduct->get_id(),
                    'post_name'   => $product->handle,
                    'post_author' => 1,
                    'meta_input'  => [
                        '_shopify_id' => $product->id,
                        '_barcode'    => $barcode,
                    ],
                ]);


                try {
                    if ($variant->inventoryItem->unitCost) {
                        $cost = $variant->inventoryItem->unitCost->amount;
                        update_field('field_product_cost', $cost, $wooProduct->get_id());
                    }

                    update_field('field_product_gluten_free', $product->gluten_free->value ?? 0, $wooProduct->get_id());
                    update_field('field_product_vegetarian', $product->vegetarian->value ?? 0, $wooProduct->get_id());
                    update_field('field_product_home_freezing', $product->home_freezing->value ?? 0, $wooProduct->get_id());
                    update_field('field_product_ingredients', $product->ingredients->value ?? 0, $wooProduct->get_id());

                } catch (\Exception $e) {
                }
            }

            if (!$variableProduct) {
                continue;
            }

            //Add our variants

            $productVariantID = WooCommerce::findProductVariationIDByShopifyID($variant->id);
            $wooProductVariation = new WC_Product_Variation($productVariantID);

            $wooProductVariation->set_parent_id($wooProduct->get_id());

            try {
                $optionValue = $variant->optionValue[0]->value;

                if ($optionValue and !empty($attributeOptions) and array_key_exists($optionValue, $attributeOptions)) {
                    $wooProductVariation->set_attributes([
                        $productAttribute['attribute_taxonomy'] => $attributeOptions[$optionValue],

                    ]);
                } else {
                    WP_CLI::log("$variant->id : Cannot add attribute value: $optionValue to {$productAttribute['attribute_taxonomy']} ");
                }

            } catch (\Exception $e) {
                WP_CLI::log("$variant->id : Cannot find  attribute value: to add to {$productAttribute['attribute_taxonomy']} ");
            }

            $wooProductVariation->set_name($variant->displayName);

            $wooProductVariation->set_regular_price($variant->price);
            $wooProductVariation->set_price($variant->price);


            if ($variant->compareAtPrice) {
                $wooProductVariation->set_sale_price($variant->compareAtPrice);
            }
            $wooProductVariation->set_manage_stock(false);
            $wooProductVariation->set_stock_status('instock');

            if ($variant->weight) {
                $wooProductVariation->set_weight($variant->weight);
            }

            if ($variant->image) {
                //$wooProductVariation->set_image_id();
            }

            // Now save the product
            $wooProductVariation->save();

            update_post_meta($wooProductVariation->get_id(), '_shopify_id', $variant->id);

            $barcode = '';
            if ($variant->barcode) {
                $barcode = $variant->barcode;
            }

            if (!$barcode && $variant->sku) {
                $barcode = $variant->sku;
            }

            update_post_meta($wooProductVariation->get_id(), '_barcode', $barcode);

        }
    }


}
