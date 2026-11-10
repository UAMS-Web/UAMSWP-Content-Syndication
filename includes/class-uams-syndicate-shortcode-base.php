<?php

declare(strict_types=1);
/**
 * A base class for UAMS syndicate shortcodes.
 *
 * Class UAMS_Syndicate_Shortcode_Base
 */
class UAMS_Syndicate_Shortcode_Base
{
    /**
     * Default path used to consume the REST API from an individual site.
     *
     * @var string
     */
    public $default_path = 'wp-json/wp/v2/';

    /**
     * Default attributes applied to all shortcodes that extend this base.
     *
     * @var array<string, mixed>
     */
    // The mutation run reports these initializers as uncovered although the defaults test pins every value:
    // changing one default fails that test. The run cannot attribute a property initializer to a test.
    // @pest-mutate-ignore: RemoveArrayItem, EmptyStringToNotEmpty, DecrementInteger, IncrementInteger, FalseToTrue
    public $defaults_atts = [
        'object' => 'json_data',
        'output' => 'json',
        'host' => 'news.uams.edu', // originally 'uamshealth.com'
        'scheme' => 'https',
        'site' => '',
        'site_category_slug' => '',
        'advanced_cat' => '',
        'tag' => '',
        'style' => '',
        'query' => 'posts',
        'local_count' => 0,
        'count' => false,
        'date_format' => 'F j, Y',
        'offset' => 0,
        'cache_bust' => '',
        'id' => '',
    ];

    /**
     * Defaults for individual base attributes can be overridden for a
     * specific shortcode.
     *
     * @var array<string, mixed>
     */
    public $local_default_atts = [];

    /**
     * Defaults can be extended with additional keys by a specific shortcode.
     *
     * @var array<string, mixed>
     */
    public $local_extended_atts = [];

    /**
     * @var string The shortcode name.
     */
    // Uncovered for the same reason as `$defaults_atts`; the defaults test pins the empty name.
    // @pest-mutate-ignore: EmptyStringToNotEmpty
    public $shortcode_name = '';

    /**
     * A common constructor that initiates the shortcode.
     */
    public function construct(): void
    {
        $this->add_shortcode();
    }

    /**
     * Required to add a shortcode definition.
     */
    public function add_shortcode(): void {}

    /**
     * Required to display the content of a shortcode.
     *
     * @param  array<string, mixed>  $atts  A list of attributes assigned to the shortcode.
     * @return string Final output for the shortcode.
     */
    public function display_shortcode($atts): string
    {
        return '';
    }

    /**
     * Enqueue the scripts and styles when a page with the proper shortcode tag is being displayed.
     */
    // public function enqueue_syndication_scripts() {
    // 	// if ( ! is_singular() ) {
    // 	// 	return;
    // 	// }

    // 	$post = get_post();
    // 	if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'uamswp_news' ) ) {
    // 		//wp_register_style( 'uamswp-syndication-news', plugins_url('/css/uamswp-syndication-news.css', __FILE__ ) );
    // 	}
    // }

    /**
     * Process passed attributes for a shortcode against arrays of base defaults,
     * local defaults, and extended local defaults.
     *
     * @param  array<string, mixed>  $atts  Attributes passed to a shortcode.
     * @return array<string, mixed> Fully populated list of attributes expected by the shortcode.
     */
    public function process_attributes($atts)
    {
        $defaults = apply_filters('uamswp_content_syndicate_default_atts', $this->defaults_atts);

        $defaults = shortcode_atts($defaults, $this->local_default_atts);
        $defaults = array_merge($defaults, $this->local_extended_atts);

        $local_defaults = [];
        // Allow for different attribute values to be passed when results from the
        // local site are merged into results from a remote site.
        foreach ($defaults as $attribute => $value) {
            // The core default attributes should stay the same
            if (array_key_exists($attribute, $this->defaults_atts)) {
                continue;
            }

            $local_defaults['local_'.$attribute] = $value;
        }

        $defaults = array_merge($defaults, $local_defaults);

        return shortcode_atts($defaults, $atts, $this->shortcode_name);
    }

    /**
     * Create a hash of all attributes to use as a cache key. If any attribute changes,
     * then the cache will regenerate on the next load.
     *
     * @param  array<string, mixed>  $atts  List of attributes used for the shortcode.
     * @param  string  $shortcode  Shortcode being displayed.
     * @return mixed False if cache is not available or expired. The stored value if available.
     */
    public function get_content_cache($atts, $shortcode)
    {
        $atts_key = md5(serialize($atts)); // @codingStandardsIgnoreLine

        $content = wp_cache_get($atts_key, $shortcode);

        return $content;
    }

    /**
     * Store generated content from the shortcode in cache.
     *
     * @param  array<string, mixed>  $atts  List of attributes used for the shortcode.
     * @param  string  $shortcode  Shortcode being displayed.
     * @param  mixed  $content  Generated content after processing the shortcode.
     */
    public function set_content_cache($atts, $shortcode, $content): void
    {
        $atts_key = md5(serialize($atts)); // @codingStandardsIgnoreLine

        wp_cache_set($atts_key, $content, $shortcode, 600);
    }

    /**
     * Processes a given site URL and shortcode attributes into data to be used for the
     * request.
     *
     * @since 0.10.0
     *
     * @param  array<string, mixed>  $site_url  Contains host and path of the requested URL.
     * @param  array<string, mixed>  $atts  Contains the original shortcode attributes.
     * @return array<string, mixed> List of request information.
     */
    public function build_initial_request($site_url, $atts): array
    {
        $url_scheme = 'https';
        $local_site_id = false;
        // Account for a previous version that allowed "local" as a manual scheme.
        if ($atts['scheme'] === 'local') {
            $atts['scheme'] = 'http';
        }

        $home_url_data = wp_parse_url(trailingslashit(get_home_url()));
        if (isset($home_url_data['host'], $home_url_data['path'], $home_url_data['scheme']) && $home_url_data['host'] === $site_url['host'] && $home_url_data['path'] === $site_url['path']) {
            $local_site_id = 1;
            $url_scheme = $home_url_data['scheme'];
            // Local is assigned as a scheme only if the requesting site is the requested site.
            $atts['scheme'] = 'local';
        } elseif (is_multisite()) {
            $local_site = get_blog_details([
                'domain' => $site_url['host'],
                'path' => $site_url['path'],
            ], false);
            if ($local_site) {
                $local_site_id = $local_site->blog_id;
                $local_home_url = get_home_url((int) $local_site_id);
                $url_scheme = wp_parse_url($local_home_url, PHP_URL_SCHEME);
                $atts['scheme'] = $url_scheme;
            }
        }

        $request_url = esc_url($url_scheme.'://'.$site_url['host'].$site_url['path'].$this->default_path).$atts['query'];

        return [
            'url' => $request_url,
            'scheme' => $atts['scheme'],
            'site_id' => $local_site_id,
        ];
    }

    /**
     * Determine what the base URL should be used for REST API data.
     *
     * @param  array<string, mixed>  $atts  List of attributes used for the shortcode.
     * @return array<string, mixed>|false host and path if available, false if not.
     */
    public function get_request_url($atts)
    {
        // If a site attribute is provided, it overrides the host attribute.
        if (! empty($atts['site'])) {
            $site_url = trailingslashit(esc_url($atts['site']));
        } else {
            $site_url = trailingslashit(esc_url($atts['host']));
        }

        $site_url = wp_parse_url($site_url);
        if (empty($site_url['host'])) {
            return false;
        }

        return $site_url;
    }

    /**
     * Add proper filters to a given URL to handle lookup by University taxonomies and
     * built in WordPress taxonomies.
     *
     * @param  array<string, mixed>  $atts  List of attributes used for the shortcode.
     * @param  string  $request_url  REST API URL being built.
     * @return string Modified REST API URL.
     */
    public function build_taxonomy_filters($atts, $request_url)
    {

        $request_url = apply_filters('uamswp_content_syndicate_taxonomy_filters', $request_url, $atts, $request_url);

        if (! empty($atts['advanced_cat'])) {
            // $terms = $this->sanitized_terms( $atts['university_location_slug'] );
            $terms = $this->sanitized_ids($atts['advanced_cat']);
            $request_url = add_query_arg([
                'filter[cat]' => $terms,
            ], $request_url);
        }

        if (! empty($atts['site_category_slug'])) {
            $terms = $this->sanitized_terms($atts['site_category_slug']);
            $request_url = add_query_arg([
                'filter[category_name]' => $terms,
            ], $request_url);
        }

        if (! empty($atts['tag'])) {
            $terms = $this->sanitized_terms($atts['tag']);
            $request_url = add_query_arg([
                'filter[tag]' => $terms,
            ], $request_url);
        }

        if (! empty($atts['id'])) {
            $terms = $this->sanitized_ids($atts['id']);
            $request_url = add_query_arg([
                'filter[p]' => $terms,
            ], $request_url);
        }

        return $request_url;
    }

    /**
     * Explode comma-separated terms into an array, sanitize each term, implode into a string.
     *
     * @since 0.10.0
     *
     * @param  string  $terms  Comma separated list of terms.
     * @return string Sanitized comma separated list of terms.
     */
    public function sanitized_terms($terms): string
    {
        $term_array = explode(',', $terms);
        $sanitize_term_array = array_map(sanitize_key(...), $term_array);

        return implode(',', $sanitize_term_array);
    }

    /**
     * Explode comma-separated ids into an array, sanitize each id, implode into a string.
     *
     * @since 0.10.0
     *
     * @param  string  $terms  Comma separated list of ids.
     * @return string Sanitized comma separated list of ids.
     */
    public function sanitized_ids($terms): string
    {
        $term_array = explode(',', $terms);
        $sanitize_term_array = array_map(intval(...), $term_array);

        return implode(',', $sanitize_term_array);
    }
}
