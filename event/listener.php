<?php
/**
 *
 * Sticky Ad. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019, toxyy, https://github.com/toxyy
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace toxyy\stickyad\event;

/**
 * Event listener
 */

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
    /** @var \phpbb\cache\service */
    protected $cache;

    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\template\template */
    protected $template;

    /** @var string phpBB root path */
    protected $phpbb_root_path;

    /** @var string phpEx */
    protected $php_ext;

    /**
     * Constructor
     */
    public function __construct(\phpbb\cache\service $cache, \phpbb\config\config $config, \phpbb\template\template $template, $phpbb_root_path, $php_ext)
    {
        $this->cache                              = $cache;
        $this->config                             = $config;
        $this->template                           = $template;
        $this->phpbb_root_path                    = $phpbb_root_path;
        $this->php_ext                            = $php_ext;
    }

    static public function getSubscribedEvents()
    {
        return [
            'core.viewtopic_modify_post_data'     => 'viewtopic_modify_post_data',
        ];
    }

    /*
     * parse all posts on a page for ebay/amazon/alibaba links
     * if any are found, check if its data is cached, if not get a trimmed mobile version of the listing and cache that data
     * add some template variables for the html event
     */
    public function viewtopic_modify_post_data($event)
    {
        $post_ary = array_column($event['rowset'], 'post_text');

        $ad_url = $ad_type = $ad_id = $title = $price = $img_src = $seller = $seller_feedback = $extra = '';
        // search for an ebay listing in this thread's posts, get its id
        while (empty($ad_url))
        {
            foreach ($post_ary as $post)
            {
                if (preg_match('~ebay\.com(?:/|(%2F))itm(?:(?:/|(%2F)).*)?(?:/|(%2F))(\d+)~i', $post, $match))
                {
                    $ad_type = 'ebay';
                    $ad_id = $match[4];
                    $ad_url = "https://www.ebay.com/itm/$ad_id";
                    break;
                }
                // amazon asin regex string from: https://gist.github.com/GreenFootballs/6731201fafc67ecc9322ccb4a7977018#file-amazon_regex-md
                $amazon_regex = "~
                            (?:(smile\.|www\.))?    # optionally starts with smile. or www.
                            ama?zo?n\.              # also allow shortened amzn.com URLs
                            (?:
                                com                 # match all Amazon domains
                                |
                                ca
                                |
                                co\.uk
                                |
                                co\.jp
                                |
                                de
                                |
                                fr
                            )
                            /
                            (?:                     # here comes the stuff before the ASIN
                                exec/obidos/ASIN/   # the possible components of an Amazon URL
                                |
                                o/
                                |
                                gp/product/
                                |
                                (?:                 # the dp/ format may contain a title
                                    (?:[^\"\'/]*)/   # anything but a slash or quote
                                )?                  # optional
                                dp/
                                |                   # if amzn.com format, nothing before the ASIN
                            )
                            ([A-Z0-9]{10})          # capture group $2 will contain the ASIN
                            (?:                     # everything after the ASIN
                                (?:/|\?|\#)         # starting with a slash, question mark, or hash
                                (?:[^\"\'\s]*)       # everything up to a quote or white space
                            )?                      # optional
                        ~isx";
                if (preg_match($amazon_regex, $post, $match))
                {
                    $ad_type = 'amazon';
                    $ad_id = $match[2];
                    $ad_url = "https://amazon.com/dp/$ad_id";
                    break;
                }
                if (preg_match('~alibaba.com/product-detail/.*?.html~i', $post, $match))
                {
                    $ad_type = 'alibaba';
                    $ad_id = $match[0];
                    $ad_url = "https://$ad_id";
                    break;
                }
                if (preg_match('~aliexpress.com/item/.*?.html~i', $post, $match))
                {
                    $ad_type = 'aliexpress';
                    $ad_id = $match[0];
                    $ad_url = "https://$ad_id";
                    break;
                }
            }

            // nothing to show, break while loop
            break;
        }

        $ad_data = [];
        // initial empty values
        $ad_data['title'] = $ad_data['price'] = $ad_data['img_src'] = $ad_data['seller'] = $ad_data['seller_feedback'] = $ad_data['extra'] = '';
        $show_ad = !empty($ad_url);
        $cache_id = "_toxyystickyad$ad_id";
        if ($show_ad && ($ad_data = $this->cache->get($cache_id)) === false)
        {
            // request sites as a mobile user to save bandwidth
            $opts = [
                'http'  =>  [//Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Mobile Safari/537.36
                    'header'    =>  "follow_location: false;\r\nContent-Security-Policy: script-src 'none';\r\nUser-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 7_0 like Mac OS X; en-us) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11A465 Safari/9537.53\r\n",
                ]
            ];
            // optimal page sizes to parse these mobile sites, no wasted data loaded. Increase limits if there is missing data
            $len = $ad_type == 'amazon' ? 700000 : ($ad_type == 'ebay' ? 100000 : ($ad_type == 'aliexpress' ? 2500000 : /*alibaba*/ 70000));
            $doc = new \DOMDocument;
            $context = stream_context_create($opts);
            $html = file_get_contents($ad_url,false, $context, 0, $len);

            if($ad_type === 'ebay')
            {
                $ad_expired = false;
                // unique response in the header array for non expired ads
                $match_length = strlen('Content-Length');
                foreach ($http_response_header as $key => $response)
                {
                    if(substr($response, 0, $match_length) === 'Content-Length')
                    {
                        $content_length = explode(': ', $response)[1];
                        if ($content_length != 0)
                        {
                            $ad_expired = true;
                            break;
                        }
                    }
                }

                if($ad_expired)
                {
                    preg_match('~ebay\.com(?:/|(%2F))(?:itm|p)(?:(?:/|(%2F)).*)?(?:/|(%2F))(\d+)~i', $html, $ad_url);
                    $ad_url = "https://$ad_url[0]";
                    $html = file_get_contents($ad_url,false, $context, 0, $len);
                }
            }

            $doc->loadHTML($html);
            $xpath = new \DOMXPath($doc);

            // the upcoming switch calls this in array path, so I don't have to write 5+ wrappers for each query, for each site
            $xquery = function ($query) use ($xpath) {
                return $xpath->query($query)->item(0)->nodeValue;
            };

            switch($ad_type)
            {
                case 'ebay':
                    $ad_data = array_map($xquery, [
                        'title'             =>  "//*[@class='vi-title__main' or @class='vi-product__main' or @class='product-title']",
                        'price'             =>  "//*[@class='vi-bin-primary-price__main-price' or @class='display-price']",
                        'img_src'           =>  "//img[@class='vi-image-gallery__image vi-image-gallery__image--absolute-center']/@data-src",
                        'seller'            =>  "//span[@class='app-sellerpresence__sellername']",
                        'seller_feedback'   =>  "//span[@class='app-sellerpresence__feedbackpercentage']",
                        //'extra'             =>  '',
                    ]);

                    if($ad_data['seller'] === null && $ad_data['seller_feedback'] === null) {
                        $patch_data = array_map($xquery, [
                            'seller'            =>  "//div[@class='seller-persona ']/span/a",
                            'seller_feedback'   =>  "//div[@class='seller-persona ']/descendant::span[@class='no-wrap'][2]",
                        ]);
                        $ad_data = $patch_data + $ad_data;
                    }
                    break;
                case 'amazon':
                    $ad_data = array_map($xquery, [
                        'title'             =>  "//span[@id='title']",
                        'price'             =>  "//div[@id='cerberus-data-metrics']/@data-asin-price",
                        'currency'          =>  "//div[@id='cerberus-data-metrics']/@data-asin-currency-code",
                        'img_src'           =>  "//img[@id='main-image']/@data-midres-replacement",
                        'seller'            =>  "//a[@id='bylineInfo']",
                        'seller_feedback'   =>  "//a[@id='acrCustomerReviewLink']/i/span",
                    ]);
                    // can only get currency symbol name (USD), need to convert it
                    $fmt = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
                    $ad_data['price'] = numfmt_format_currency($fmt, $ad_data['price'], $ad_data['currency']);
                    break;
                case 'alibaba':
                    $ad_data = array_map($xquery, [
                        'title'             =>  "//div[@class='product-title']",
                        'price'             =>  "//div[@class='price']",
                        'img_src'           =>  "//div[@id='banner-content']/img/@src",
                        'seller'            =>  "//h2[@class='line-1 company-title']",
                        'seller_feedback'   =>  "//img[@class='company-info-more-icon']/@alt",
                    ]);
                    $ad_data['price'] .= ' / Pieces';
                    break;
                case 'aliexpress':
                    $ad_data = array_map($xquery, [
                        'title'             =>  "//title",
                        'price'             =>  "//p[@class='current-price bold']",
                        'img_src'           =>  "//amp-carousel[@id='detail-carousel']/amp-img/@src",
                        'seller'            =>  "//section[@class='ms-block']/div/h3/a[@class='flex-1']",
                        'seller_feedback'   =>  "//ul[@class='store-summary flex justify-space-between']/li[@class='flex direction-column align-start']/span",
                    ]);
                    break;
            }
            // add ellipsis if title is too long
            $ad_data['title'] = mb_strimwidth($ad_data['title'], 0, 120, '...');

            // cache this ad's data for seven days
            $this->cache->put($cache_id, $ad_data, 604800);
        }
        $this->template->assign_vars([
            'STICKYAD_SHOW'         => $show_ad,
            'STICKYAD_JAVASCRIPT'   => false,
            'STICKYAD_TYPE'         => $ad_type,
            'STICKYAD_URL'          => $ad_url,
            'STICKYAD_TITLE'        => $ad_data['title'],
            'STICKYAD_PRICE'        => $ad_data['price'],
            'STICKYAD_IMG_SRC'      => $ad_data['img_src'],
            'STICKYAD_SELLER'       => $ad_data['seller'],
            'STICKYAD_FEEDBACK'     => $ad_data['seller_feedback'],
            'STICKYAD_EXTRA'        => $ad_data['extra'],
        ]);
    }
}
