<?php

namespace Theme\APIs;


use PHPShopify\Exception\ApiException;
use PHPShopify\Exception\CurlException;
use PHPShopify\ShopifySDK;

class Shopify
{

    /**
     * @var ShopifySDK
     */
    static $shopify;


    static $error;


    static $cacheTime = 24 * HOUR_IN_SECONDS;


    static private $useCache = true;



    /**
     * Find a discount code on Shopify and get it.
     *
     * If found return the price Rule and discountCodeID
     *
     * @param $code
     * @return array
     */
    public static function findDiscountCode($code)
    {
        $shopify = static::config();

        $discountCode = $shopify->DiscountCode();

        $priceRuleID = '';
        $discountCodeID = '';

        try {
            $discountCode->lookup(['code' => $code]);
        } catch (ApiException $e) {
        } catch (CurlException $e) {
            $location = isset($discountCode::$lastHttpResponseHeaders['location']) ? $discountCode::$lastHttpResponseHeaders['location'] : '';

            if ($location) {
                preg_match('/price_rules\/([0-9]*)\/discount_codes\/([0-9]*)/', $location, $matches);

                if (isset($matches[1])) {
                    $priceRuleID = $matches[1];
                }
                if (isset($matches[2])) {
                    $discountCodeID = $matches[2];
                }

            }
        }

        return [$priceRuleID, $discountCodeID];

    }



    public static function getCollections($limit = 100)
    {

        $graphQL = '
           {
              collections(first: ' . $limit . ') {
                edges {
                  node {
                    id
                    title
                    description
                    descriptionHtml
                    handle
                     seo {
                       description
                       title
                    }
                    image {
                      id
                      altText
                      originalSrc
                      transformedSrc
                    }
                  }
                }
              }
            }

            ';

        return static::runQuery($graphQL);

    }



    /**
     * Get a Discount Rule
     *
     * @param string $discountID
     * @return array|mixed
     * @throws ApiException
     * @throws CurlException
     */
    public static function getDiscountCode($discountID)
    {
        $shopify = static::config();

        $key = 'price_rule:' . md5(static::class . 'getDiscountCode' . $discountID);

        if (!static::$useCache || false === ($data = get_transient($key))) {

            $discountCode = $shopify->DiscountCode()->lookup(['code' => 'BV-Y6Z8KMMD']);
            static::wait();
            $data = (object)[
                'results' => json_decode(json_encode($discountCode)),
            ];

            set_transient($key, $data, static::$cacheTime);
        }
        return $data;

    }



    public static function getDiscountCodes($priceRuleID, $pageInfo = '')
    {
        $shopify = static::config();

        $params = [];
        if ($pageInfo) {
            $params['page_info'] = $pageInfo;
        }

        $key = 'discount_codes:' . md5(static::class . 'getDiscountCodes' . $priceRuleID . $pageInfo);

        if (!static::$useCache || false === ($data = get_transient($key))) {

            $discountCode = $shopify->PriceRule($priceRuleID)->DiscountCode();
            $result = $discountCode->get($params);
            static::wait();
            $data = (object)[
                'results'          => json_decode(json_encode($result)),
                'nextPageInfo'     => static::extractPageInfo($discountCode->getNextLink()),
                'previousPageInfo' => static::extractPageInfo($discountCode->getPrevLink()),

            ];

            set_transient($key, $data, static::$cacheTime);
        }
        return $data;

    }



    /**
     * Get a Price Rule
     *
     * @param string $priceRuleID
     * @return array|mixed
     * @throws ApiException
     * @throws CurlException
     */
    public static function getPriceRule($priceRuleID)
    {
        $shopify = static::config();

        $key = 'price_rule:' . md5(static::class . 'getPriceRule' . $priceRuleID);

        if (!static::$useCache || false === ($data = get_transient($key))) {

            $priceRule = $shopify->PriceRule($priceRuleID)->get();
            static::wait();
            $data = (object)[
                'results' => json_decode(json_encode($priceRule)),
            ];

            set_transient($key, $data, static::$cacheTime);
        }
        return $data;

    }



    /**
     * Get a Page oif Price Rules
     *
     * @param string $pageInfo
     * @return array|mixed
     * @throws ApiException
     * @throws CurlException
     */
    public static function getPriceRules($pageInfo = '')
    {
        $shopify = static::config();

        $params = [];
        if ($pageInfo) {
            $params['page_info'] = $pageInfo;
        }

        $key = 'price_rules:' . md5(static::class . 'getPriceRules' . $pageInfo);

        if (!static::$useCache || false === ($data = get_transient($key))) {

            $priceRule = $shopify->PriceRule();
            $result = $priceRule->get($params);
            static::wait();
            $data = (object)[
                'results'          => json_decode(json_encode($result)),
                'nextPageInfo'     => static::extractPageInfo($priceRule->getNextLink()),
                'previousPageInfo' => static::extractPageInfo($priceRule->getPrevLink()),
            ];

            set_transient($key, $data, static::$cacheTime);
        }
        return $data;

    }



    public static function getProduct($gid)
    {

        $graphQL = '
             {
              product(id: "' . $gid . '") {
                id
                hasOnlyDefaultVariant
                productType
                collections(first:10) {
                  edges {
                    node {
                      title
                    }
                  }
                }
                title
                tags
                descriptionHtml
                handle
                featuredImage {
                  id
                  src
                }
                options {
                    id
                    name
                    values
                }
                published: publishedOnPublication(publicationId:"gid://shopify/Publication/39897104493" )
                images(first: 2) {
                  edges {
                    node {
                      id
                      altText
                      originalSrc
                      transformedSrc
                    }
                  }
                }
                totalVariants
                tracksInventory
                seo {
                  description
                  title
                }
                gluten_free: metafield(namespace: "cote", key: "gluten_free") {
                  value
                }
                vegetarian: metafield(namespace: "cote", key: "vegetarian") {
                  value
                }
                home_freezing: metafield(namespace: "cote", key: "home_freezing") {
                  value
                }
                ingredients: metafield(namespace: "cote", key: "ingredients") {
                  value
                }
                variants(first: 30) {
                  edges {
                    node {
                      id
                      title
                      position
                      price
                      displayName
                      image {
                        id
                      }
                      optionValue: selectedOptions {
                        value
                      }
                      sku
                      barcode
                      compareAtPrice
                      weightUnit
                      weight
                      inventoryItem {
                        unitCost {
                          amount
                          currencyCode
                        }
                      }
                      image {
                        id
                        altText
                        originalSrc
                        transformedSrc
                      }
                    }
                  }
                }
              }
            }
        ';

        return static::runQuery($graphQL);

    }



    /**
     * Get all products
     *
     * @param string $cursor
     * @param int $limit
     * @return mixed
     * @throws ApiException
     * @throws CurlException
     */
    public static function getProducts($cursor = '', $limit = 250)
    {

        $after = $cursor ?  ', after: "'.$cursor.'"':'';

        $graphQL = '
            {
              products(first: ' . $limit . $after. ') {
                edges {
                  cursor
                  node {
                    id
                  }
                }
              }
            }
            ';

        return static::runQuery($graphQL);

    }



    public static function useCache($bool)
    {
        static::$useCache = $bool;
    }



    /**
     * @return ShopifySDK
     */
    protected static function config()
    {

        if (!static::$shopify) {

            $config = [
                'ShopUrl'     => SHOPIFY_URL,
                'ApiKey'      => SHOPIFY_APIKEY,
                'AccessToken' => SHOPIFY_PASSWORD,
                'Password'    => SHOPIFY_PASSWORD,
            ];

            static::$shopify = ShopifySDK::config($config);

        }

        return static::$shopify;
    }



    protected static function extractPageInfo($url = '')
    {

        if (!$url) {
            return '';
        }
        $parts = parse_url($url);
        parse_str($parts['query'], $query);
        return $query['page_info'];
    }



    /**
     * Run and cache a graphQL query
     *
     * @param $graphQL
     * @return mixed
     * @throws ApiException
     * @throws CurlException
     */
    protected static function runQuery($graphQL)
    {

        $key = md5($graphQL);

        if (false === ($data = get_transient($key))) {
            $result = static::config()->GraphQL->post($graphQL);
            $data = json_decode(json_encode($result['data']));
            set_transient($key, $data, static::$cacheTime);
        }

        return $data;
    }



    /**
     * Slow down our queries
     */
    protected static function wait()
    {
        usleep(500000);
    }


}
