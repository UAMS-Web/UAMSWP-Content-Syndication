<?php

class UAMS_Syndicate_Shortcode_News extends UAMS_Syndicate_Shortcode_Base
{
    /**
     * @var string Shortcode name.
     */
    public $shortcode_name = 'uamswp_news';

    public function __construct()
    {
        parent::construct();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_syndication_stylesheet']);
        // ShortcakeTest runs this branch in a separate process, which a coverage run cannot see, so
        // its mutants read as uncovered; that test pins each hook and asset call.
        // @pest-mutate-ignore: RemoveArrayItem, RemoveFunctionCall
        if (class_exists('UAMS_Shortcakes')) {
            add_action('admin_init', [$this, 'build_shortcake']);
            add_editor_style(plugins_url('/css/uams-syndication-admin.css', __DIR__));
            add_action('enqueue_shortcode_ui', function (): void {
                wp_enqueue_script('uams_syndications_editor_js', plugins_url('/js/uams-syndication-shortcake.js', __DIR__));
            });
        }
        // add_action( 'admin_init', array( $this, 'enqueue_syndication_stylesheet_admin' ) );
    }

    /**
     * Add the shortcode provided.
     */
    public function add_shortcode(): void
    {
        add_shortcode('uamswp_news', [$this, 'display_shortcode']);
    }

    /**
     * Enqueue styles specific to the network admin dashboard.
     */
    public function enqueue_syndication_stylesheet(): void
    {
        $post = get_post();
        if (isset($post->post_content) && has_shortcode($post->post_content, 'uamswp_news')) {
            wp_enqueue_style('uamswp-syndication-news-style', plugins_url('/css/uamswp-syndication-news.css', __DIR__), [], '');
        }
    }

    /**
     * Enqueue styles specific to the network admin dashboard.
     */
    // public function enqueue_syndication_stylesheet_admin() {
    // 	add_editor_style( 'uamswp-syndication-news-style-admin', plugins_url( '/css/uamswp-syndication-news.css', __DIR__ ), array(), '' );
    // }
    public function build_shortcake(): void
    {
        shortcode_ui_register_for_shortcode(

            /** Your shortcode handle */
            'uamswp_news',

            /** Your Shortcode label and icon */
            [

                /** Label for your shortcode user interface. This part is required. */
                'label' => esc_html__('News Syndication', 'uamswp_news'),

                /** Icon or an image attachment for shortcode. Optional. src or dashicons-$icon.  */
                'listItemImage' => 'dashicons-rss',

                /** Shortcode Attributes */
                'attrs' => [

                    /** Output format */
                    [
                        'label' => esc_html__('Format', 'uamswp_news'),
                        'attr' => 'output',
                        'type' => 'radio',
                        'options' => [
                            'headlines' => 'Headline',
                            'excerpts' => 'Excerpt',
                            'cards' => 'Card',
                            'full' => 'Full',
                        ],
                        'description' => 'Preferred output format',
                    ],

                    [

                        /** This label will appear in user interface */
                        'label' => esc_html__('Category', 'uamswp_news'),
                        'attr' => 'site_category_slug',
                        'type' => 'text',
                        'description' => 'Please enter the filter / category',
                    ],

                    /** Count - Number of articles */
                    [
                        'label' => esc_html__('Count', 'uamswp_news'),
                        'attr' => 'count',
                        'type' => 'number',
                        'description' => 'Number of news articles to display',
                        'meta' => [
                            'placeholder' => esc_html__('1'),
                            'min' => '1',
                            'step' => '1',
                        ],
                    ],

                    /** Offset - Number of articles to skip */
                    [
                        'label' => esc_html__('Offset', 'uamswp_news'),
                        'attr' => 'offset',
                        'type' => 'number',
                        'description' => 'Number of news articles to skip',
                        'meta' => [
                            'placeholder' => esc_html__('0'),
                            'min' => '0',
                            'step' => '1',
                        ],
                    ],

                    /** ID - ID of specific of articles */
                    [
                        'label' => esc_html__('Post ID', 'uamswp_news'),
                        'attr' => 'postid',
                        'type' => 'number',
                        'description' => 'Specific ID of article',
                    ],

                ],

                /** You can select which post types will show shortcode UI */
                'post_type' => ['post', 'page'],
            ]
        );
    }

    /**
     * Process the requested parameters for use with the WordPress JSON API and output
     * the response accordingly.
     *
     * @param  array<mixed>  $atts  {
     *                              Attributes passed with the shortcode.
     *
     * @type string $object                   The name of the JSON object to use when output is set to json.
     * @type string $output                   The type of output to display.
     *              - json           Output a JSON object to be used with custom Javascript.
     *              - headlines      Display an unordered list of headlines.
     *              - excerpts       Display only excerpt information in an unordered list.
     *              - cards       	 Display information in a card format.
     *              - full           Display full content for each item.
     * @type string $host                     The hostname to pull items from. Defaults to uamshealth.com.
     * @type string $site                     Overrides setting for host. Hostname and path to pull items from.
     * @type string $site_category_slug       The slug of a Site Category. Defaults to empty.
     * @type string $advanced_cat       	  The ids of a Site Category, including exclusions (-id). Defaults to empty.
     * @type string $tag                      The slug of a tag. Defaults to empty.
     * @type string $id                       The id of post. Defaults to empty.
     * @type string $style                    Adds additional styles to the wrapper. Defaults to empty.
     * @type string $query                    Allows for a custom WP-API query. Defaults as "posts". Any
     * @type int $local_count              The number of local items to merge with the remote results.
     * @type int $count                    The number of items to pull from a feed. Defaults to the
     *           posts_per_page setting of the remote site.
     * @type string $date_format              PHP Date format for the output of the item's date.
     * @type int $offset                   The number of items to offset when displaying. Used with multiple
     *           shortcode instances where one may pull in an excerpt and another
     *           may pull in the rest of the feed as headlines.
     * @type string $cache_bust               Any change to this value will clear the cache and pull fresh data.
     *              }
     *
     * @return string Data to output where the shortcode is used.
     */
    public function display_shortcode($atts): string
    {
        $atts = $this->process_attributes($atts);

        $site_url = $this->get_request_url($atts);
        if (! $site_url) {
            return '<!-- uamswp_news ERROR - an empty host was supplied -->';
        }

        // Retrieve existing content from cache if available.
        // $content = $this->get_content_cache( $atts, 'uamswp_news' );
        // if ( $content ) {
        // 	return apply_filters( 'uamswp_content_syndication_news', $content, $atts );
        // }

        $request = $this->build_initial_request($site_url, $atts);
        $request_url = $this->build_taxonomy_filters($atts, $request['url']);

        if (! empty($atts['offset'])) {
            $atts['count'] = $this->absint_att($atts, 'count') + $this->absint_att($atts, 'offset');
        }
        if ($atts['count']) {
            // At exactly 100 both branches normalize to the same request count.
            // @pest-mutate-ignore: GreaterToGreaterOrEqual, DecrementInteger
            $count = ($this->absint_att($atts, 'count') > 100)
                ? 100
                : $this->absint_att($atts, 'count');
            $request_url = add_query_arg([
                'per_page' => absint($count),
            ], $request_url);
        }
        $request_url = add_query_arg([
            '_embed' => '',
        ], $request_url);

        if ($atts['output'] === 'headlines') {
            $request_url = add_query_arg([
                '_fields[]' => 'title',
            ], $request_url);
            $request_url = add_query_arg([
                '_fields[]' => 'date',
            ], $request_url);
            $request_url = add_query_arg([
                '_fields[]' => 'link',
            ], $request_url);
        }

        if ($request['scheme'] === 'local') {
            $last_changed = wp_cache_get_last_changed('uamswp-content');
            $cache_key = md5($request_url).':'.$last_changed;
            $new_data = $this->posts(wp_cache_get($cache_key, 'uamswp-content'));
            if ($new_data === null) {
                $response = $this->dispatch_local_request($request_url);
                if ($response !== null && $response->get_status() === 200) {
                    $new_data = $this->process_local_posts($this->records($response->data), $atts);
                }
                wp_cache_set($cache_key, $new_data ?? false, 'uamswp-content');
            }
        } else {
            $new_data = $this->posts($this->get_content_cache($atts, 'uamswp_news'));
            if ($new_data === null) {
                $response = wp_remote_get($request_url);
                if (! is_wp_error($response) && wp_remote_retrieve_response_code($response) !== 404) {
                    $data = wp_remote_retrieve_body($response);
                    $new_data = $this->process_remote_posts($this->posts(json_decode($data)) ?? [], $atts);
                    // Store the built content in cache for repeated use.
                    $this->set_content_cache($atts, 'uamswp_news', $new_data);
                }
            }
        }

        if (! is_array($new_data)) {
            $new_data = [];
        }

        if ($this->absint_att($atts, 'local_count') !== 0) {
            $local_atts = [];
            foreach ($atts as $attribute => $value) {
                if (stripos($attribute, 'local_') === 0) {
                    $local_atts[substr($attribute, 6)] = $value;
                } else {
                    $local_atts[$attribute] = $value;
                }
            }
            $local_atts['count'] = $atts['local_count'];
            $local_url = $this->get_request_url($local_atts);
            if ($local_url !== false) {
                $request = $this->build_initial_request($local_url, $local_atts);
                $request_url = $this->build_taxonomy_filters($local_atts, $request['url']);
                // Keep the returned cap mutable, ignoring only the equivalent boundary.
                // @pest-mutate-ignore: GreaterToGreaterOrEqual, DecrementInteger
                $local_count = ($this->absint_att($local_atts, 'count') > 100)
                    ? 100
                    : $this->absint_att($local_atts, 'count');
                $request_url = add_query_arg([
                    'per_page' => absint($local_count),
                    '_embed' => '',
                ], $request_url);
                $last_changed = wp_cache_get_last_changed('uamswp-content');
                $cache_key = md5($request_url).':'.$last_changed;
                $local_data = $this->posts(wp_cache_get($cache_key, 'uamswp-content'));
                if ($local_data === null) {
                    $response = $this->dispatch_local_request($request_url);
                    $local_data = [];
                    if ($response !== null && $response->get_status() === 200) {
                        $local_data = $this->process_local_posts($this->records($response->data), $atts);
                    }
                    wp_cache_set($cache_key, $local_data, 'uamswp-content');
                }
                $new_data += $local_data;
            }
        } // End if().

        // Reverse sort the array of data by date.
        krsort($new_data);

        // Only provide a count to match the total count, the array may be larger if local
        // items are also requested.
        if ($atts['count']) {
            $new_data = array_slice($new_data, 0, $this->absint_att($atts, 'count'), false);
        }

        $content = apply_filters('uamswp_content_syndicate_news_output', false, $new_data, $atts);
        if ($content === false) {
            $content = $this->generate_shortcode_output($new_data, $atts);
        }

        return $this->to_string(apply_filters('uamswp_content_syndicate_news', $content, $atts));

    }

    /**
     * Generates the content to display for a shortcode.
     *
     * @since 1.1.0
     *
     * @param  array<int|string, stdClass>  $new_data  Data containing the posts to be displayed.
     * @param  array<string, mixed>  $atts  Array of options passed with the shortcode.
     * @return string Content to display for the shortcode.
     */
    private function generate_shortcode_output(array $new_data, array $atts): string|false
    {

        ob_start();
        // By default, we output a JSON object that can then be used by a script.
        if ($atts['output'] === 'json') {
            echo '<!-- UAMSWP Output JSON -->';
            // print_r ($new_data);
            echo '<script>var '.esc_js($this->string_att($atts, 'object')).' = '.wp_json_encode($new_data).';</script>';
        } elseif ($atts['output'] === 'headlines') {
            ?>
            <!-- UAMSWP Output Headlines -->
			<div class="uamswp-content-syndication-wrapper">
				<ul class="uamswp-content-syndication-list">
					<?php
                    $offset_x = 0;
            foreach ($new_data as $content) {
                if ($offset_x < $this->absint_att($atts, 'offset')) {
                    $offset_x++;

                    continue;
                }
                ?><li class="uamswp-content-syndication-item"><a href="<?php echo esc_url($this->to_string($content->link)); ?>"><?php echo esc_html($this->to_string($content->title)); ?></a></li><?php
            }
            ?>
				</ul>
			</div>
			<?php
        } elseif ($atts['output'] === 'excerpts') {
            ?>
            <!-- UAMSWP Output Excerpts -->
			<div class="uamswp-content-syndication-wrapper">
				<ul class="uamswp-content-syndication-excerpts">
					<?php
                    $offset_x = 0;
            foreach ($new_data as $content) {
                if ($offset_x < $this->absint_att($atts, 'offset')) {
                    $offset_x++;

                    continue;
                }
                ?>
						<li class="uamswp-content-syndication-item" itemscope itemtype="http://schema.org/NewsArticle">
							<meta itemscope itemprop="mainEntityOfPage"  itemType="https://schema.org/WebPage" itemid="<?php echo esc_url($this->to_string($content->link)); ?>"/>
							<a class="content-item-thumbnail" href="<?php echo esc_url($this->to_string($content->link)); ?>" itemprop="image" itemscope itemtype="https://schema.org/ImageObject"><?php if ($content->thumbnail) { ?><img src="<?php echo esc_url($this->to_string($content->thumbnail)); ?>" alt="<?php echo esc_html($this->to_string($content->thumbalt)); ?>" itemprop="url"><?php } ?></a>
							<span class="content-item-title" itemprop="headline"><a href="<?php echo esc_url($this->to_string($content->link)); ?>" class="news-link" itemprop="url"><?php echo esc_html($this->to_string($content->title)); ?></a></span>
							<span class="content-item-byline">
								<span class="content-item-byline-date" itemprop="datePublished" content="<?php echo esc_html(date('c', $this->timestamp($content->date))); ?>"><small><?php echo esc_html(date($this->string_att($atts, 'date_format'), $this->timestamp($content->date))); ?> | </small></span>
								<meta itemprop="dateModified" content="<?php echo esc_html(date('c', $this->timestamp($content->date))); ?>"/>
								<span class="content-item-byline-author" itemprop="author" itemscope itemtype="http://schema.org/Person"><small itemprop="name"><?php echo esc_html($this->to_string($content->author_name)); ?></small></span>
							</span>
							<span class="content-item-excerpt" itemprop="articleBody"><?php echo wp_kses_post($this->to_string($content->excerpt)); ?></span>
							<span itemprop="publisher" itemscope itemtype="http://schema.org/Organization">
								<meta itemprop="name" content="University of Arkansas for Medical Sciences"/>
								<span itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">
									<meta itemprop="url" content="http://web.uams.edu/wp-content/uploads/sites/51/2017/09/UAMS_Academic_40-1.png"/>
								    <meta itemprop="width" content="297"/>
								    <meta itemprop="height" content="40"/>
								</span>
							</span>
						</li>
						<?php
            }
            ?>
				</ul>
			</div>
			<?php
        } elseif ($atts['output'] === 'cards') {
            ?>
            <!-- UAMSWP Output Cards -->
			<div class="uamswp-content-syndication-wrapper">
				<div class="uamswp-content-syndication-cards">
					<?php
                    $offset_x = 0;
            foreach ($new_data as $content) {
                if ($offset_x < $this->absint_att($atts, 'offset')) {
                    $offset_x++;

                    continue;
                }
                ?>
					    <div class="default-card" itemscope itemtype="http://schema.org/NewsArticle">
					    	<meta itemscope itemprop="mainEntityOfPage"  itemType="https://schema.org/WebPage" itemid="<?php echo esc_url($this->to_string($content->link)); ?>"/>
					    	<div class="card-image" itemprop="image" itemscope itemtype="https://schema.org/ImageObject"><?php if ($content->image) { ?><img src="<?php echo esc_url($this->to_string($content->image)); ?>" alt="<?php echo esc_html($this->to_string($content->imagecaption)); ?>" itemprop="url"><?php } else { ?><img src="http://via.placeholder.com/560x350?text=Not%20Available" alt="" itemprop="url"><?php } ?></div>
							<div class="card-body">
					      		<span>
					      			<h3 itemprop="headline">
					                	<a href="<?php echo esc_url($this->to_string($content->link)); ?>" class="pic-title"><?php echo esc_html($this->to_string($content->title)); ?></a>
					              	</h3>
					              	<span itemprop="articleBody">
					      			<?php echo wp_kses_post($this->to_string($content->excerpt)); ?>
					      			</span>
					              	<a href="<?php echo esc_url($this->to_string($content->link)); ?>" class="pic-text-more uams-btn btn-sm btn-red" itemprop="url">Read more</a>
					              	<span class="content-item-byline-author" itemprop="author" itemscope itemtype="http://schema.org/Person"><meta itemprop="name" content="<?php echo esc_html($this->to_string($content->author_name)); ?>"/></span>
					              	<meta itemprop="datePublished" content="<?php echo esc_html(date('c', $this->timestamp($content->date))); ?>"/>
					              	<meta itemprop="dateModified" content="<?php echo esc_html(date('c', $this->timestamp($content->modified))); ?>"/>
					            </span>

							</div>
							<span itemprop="publisher" itemscope itemtype="http://schema.org/Organization">
								<meta itemprop="name" content="University of Arkansas for Medical Sciences"/>
								<span itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">
									<meta itemprop="url" content="http://web.uams.edu/wp-content/uploads/sites/51/2017/09/UAMS_Academic_40-1.png"/>
								    <meta itemprop="width" content="297"/>
								    <meta itemprop="height" content="40"/>
								</span>
							</span>
					    </div>
						<?php
            }
            ?>
				</div>
			</div>
			<?php
        } elseif ($atts['output'] === 'full') {
            ?>
            <!-- UAMSWP Output Full -->
			<div class="uamswp-content-syndication-wrapper">
				<div class="uamswp-content-syndication-container">
					<?php
                    $offset_x = 0;
            foreach ($new_data as $content) {
                if ($offset_x < $this->absint_att($atts, 'offset')) {
                    $offset_x++;

                    continue;
                }
                ?>
						<div class="uamswp-content-syndication-full" itemscope itemtype="http://schema.org/NewsArticle">
							<meta itemscope itemprop="mainEntityOfPage"  itemType="https://schema.org/WebPage" itemid="<?php echo esc_url($this->to_string($content->link)); ?>"/>
							<div class="content-item-thumbnail" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">
								<?php if ($content->image) { ?><img src="<?php echo esc_url($this->to_string($content->image)); ?>" alt="<?php echo $this->to_string($content->imagealt); ?>"><?php } else { ?><meta itemprop="url" content="http://www.uams.edu/_images/blank.gif"/><?php } ?>
								<?php echo $content->imagecaption ? '<span class="wp-caption-text">'.$this->to_string($content->imagecaption).'</span>' : ''; ?>
							</div>
							<span class="content-item-title"><a href="<?php echo esc_url($this->to_string($content->link)); ?>" itemprop="url"><?php echo '<h2 itemprop="headline">'.esc_html($this->to_string($content->title)).'</h2>'; ?></a></span>
							<div class="content-item-byline">
								<!-- <?php if ($content->date) { ?><span class="content-item-byline-date"><?php echo esc_html(date($this->string_att($atts, 'date_format'), $this->timestamp($content->date))); ?></span> | <?php } ?>-->
								<?php if ($content->author_name) { ?><span class="content-item-byline-author" itemprop="author" itemscope itemtype="http://schema.org/Person"><span itemprop="name"><?php echo esc_html($this->to_string($content->author_name)); ?></span></span><?php } ?>
								<meta itemprop="datePublished" content="<?php echo esc_html(date('c', $this->timestamp($content->date))); ?>"/>
					            <meta itemprop="dateModified" content="<?php echo esc_html(date('c', $this->timestamp($content->modified))); ?>"/>
							</div>
							<div class="content-item-content" itemprop="articleBody">
								<?php
                            if ($content->fullcontent) {
                                echo do_shortcode(wp_kses_post($this->to_string($content->fullcontent)));
                            } else {
                                echo do_shortcode(wp_kses_post($this->to_string($content->content)));
                            }
                ?>
								<hr size="1" width="75%"/>
							</div>
							<span itemprop="publisher" itemscope itemtype="http://schema.org/Organization">
								<meta itemprop="name" content="University of Arkansas for Medical Sciences"/>
								<span itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">
									<meta itemprop="url" content="http://web.uams.edu/wp-content/uploads/sites/51/2017/09/UAMS_Academic_40-1.png"/>
								    <meta itemprop="width" content="297"/>
								    <meta itemprop="height" content="40"/>
								</span>
							</span>
						</div>
						<?php
            }
            ?>
				</div>
			</div>
			<?php
        } // End if().
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Process REST API results received remotely through `wp_remote_get()`
     *
     * @since 0.9.0
     *
     * @param  array<int|string, stdClass>|null  $data  List of post data.
     * @param  array<string, mixed>  $atts  Attributes passed with the original shortcode.
     * @return array<int|string, stdClass> Array of objects representing individual posts.
     */
    public function process_remote_posts($data, $atts): array
    {
        if (empty($data)) {
            return [];
        }

        $new_data = [];

        foreach ($data as $post) {
            $subset = new StdClass;

            // Only a subset of data is returned for a headlines request.
            if ($atts['output'] === 'headlines') {
                $subset->link = $this->value_at($post, 'link');
                $subset->date = $this->value_at($post, 'date');
                $subset->title = $this->value_at($post, 'title', 'rendered');
            } else {
                $subset->ID = $this->value_at($post, 'id');
                $subset->date = $this->value_at($post, 'date'); // In time zone of requested site
                $subset->link = $this->value_at($post, 'link');
                $subset->modified = $this->value_at($post, 'modified'); // Added for schema

                // These fields all provide a rendered version when the response is generated.
                $subset->title = $this->value_at($post, 'title', 'rendered');
                $subset->content = $this->value_at($post, 'content', 'rendered');
                $subset->excerpt = $this->value_at($post, 'excerpt', 'rendered');

                if ($atts['output'] === 'full') {
                    $subset->fullcontent = $this->value_at($post, 'fullcontent');
                }

                // If a featured image is assigned (int), the full data will be in the `_embedded` property.
                $media = $this->value_at($post, '_embedded', 'wp:featuredmedia', 0);
                if (! empty($this->value_at($post, 'featured_media')) && $media !== null) {
                    $subset->thumbnail = $this->value_at($media, 'source_url');
                    foreach (['post-thumbnail', 'thumbnail'] as $size) {
                        if ($this->value_at($media, 'media_details', 'sizes', $size) !== null) {
                            $subset->thumbnail = $this->value_at($media, 'media_details', 'sizes', $size, 'source_url');

                            break;
                        }
                    }
                    $subset->thumbalt = $this->value_at($media, 'alt_text');
                    $subset->thumbcaption = $this->value_at($media, 'caption', 'rendered');

                    // Add Medium Image
                    if ($this->value_at($media, 'media_details', 'sizes', 'uams_news') !== null) {
                        $subset->image = $this->value_at($media, 'media_details', 'sizes', 'uams_news', 'source_url');
                        $subset->imagealt = $this->value_at($media, 'alt_text');
                        $subset->imagecaption = $this->value_at($media, 'caption', 'rendered');
                    } else {
                        $subset->image = false;
                    }
                } else {
                    $subset->thumbnail = false;
                }

                // If an author is available, it will be in the `_embedded` property.
                $author = $this->value_at($post, '_embedded', 'author', 0);
                if ($author !== null) {
                    $subset->author_name = $this->value_at($author, 'name');
                } else {
                    $subset->author_name = '';
                }

                // We've always provided an empty value for terms. @todo Implement terms. :)
                $subset->terms = [];

            } // End if().

            /**
             * Filter the data stored for an individual result after defaults have been built.
             *
             * @since 0.7.10
             *
             * @param  stdClass  $subset  Data attached to this result.
             * @param  stdClass  $post  Data for an individual post retrieved via `wp-json/posts` from a remote host.
             * @param  array  $atts  Attributes originally passed to the `uamswp_news` shortcode.
             */
            $subset = apply_filters('uams_content_syndication_host_data', $subset, $post, $atts);

            // An undated post, or a date strtotime() cannot read, takes the current time as its key: a false
            // key would collide with the next one while `$subset_key++` leaves false unchanged.
            $post_date = $this->to_string($this->value_at($post, 'date'));
            $subset_key = $post_date ? (strtotime($post_date) ?: time()) : time();

            while (array_key_exists($subset_key, $new_data)) {
                $subset_key++;
            }
            $new_data[$subset_key] = $subset;
        } // End foreach().

        return $new_data;
    }

    /**
     * Keep the post objects from a decoded or cached list.
     *
     * @param  mixed  $list  Decoded JSON or a cache read.
     * @return array<int|string, stdClass>|null Null when the value is not a list, as for a cache miss.
     */
    private function posts(mixed $list): ?array
    {
        if (! is_array($list)) {
            return null;
        }

        $posts = [];
        foreach ($list as $key => $post) {
            if ($post instanceof stdClass) {
                $posts[$key] = $post;
            }
        }

        return $posts;
    }

    /**
     * Keep the records from a REST response body.
     *
     * @param  mixed  $list  Data of a REST response.
     * @return list<array<mixed>> The array records, in order.
     */
    private function records(mixed $list): array
    {
        $records = [];
        if (is_array($list)) {
            foreach ($list as $record) {
                if (is_array($record)) {
                    $records[] = $record;
                }
            }
        }

        return $records;
    }

    /**
     * Dispatch an internal REST request for a URL on this install.
     *
     * @param  string  $url  Full REST API URL, query string included.
     * @return WP_REST_Response|null Null when the URL cannot be turned into a request.
     */
    private function dispatch_local_request(string $url)
    {
        $request = WP_REST_Request::from_url($url);
        if ($request === false) {
            return null;
        }

        return rest_do_request($request);
    }

    /**
     * Process REST API results received locally through `rest_do_request()`
     *
     * @since 0.9.0
     *
     * @param  list<array<mixed>>|null  $data  Array of post data.
     * @param  array<string, mixed>  $atts  Attributes passed with the original shortcode.
     * @return array<int|string, stdClass> Array of objects representing individual posts.
     */
    public function process_local_posts($data, $atts): array
    {
        if (empty($data)) {
            return [];
        }

        $new_data = [];

        foreach ($data as $post) {
            $subset = new stdClass;

            // Only a subset of data is returned for a headlines request.
            if ($atts['output'] === 'headlines') {
                $subset->link = $this->value_at($post, 'link');
                $subset->date = $this->value_at($post, 'date');
                $subset->title = $this->value_at($post, 'title', 'rendered');
            } else {
                $subset->ID = $this->value_at($post, 'id');
                $subset->date = $this->value_at($post, 'date'); // In time zone of requested site
                $subset->link = $this->value_at($post, 'link');
                $subset->modified = $this->value_at($post, 'modified'); // Added for schema

                // These fields all provide a rendered version when the response is generated.
                $subset->title = $this->value_at($post, 'title', 'rendered');
                $subset->content = $this->value_at($post, 'content', 'rendered');
                $subset->excerpt = $this->value_at($post, 'excerpt', 'rendered');
                if ($atts['output'] === 'full') {
                    $subset->fullcontent = $this->value_at($post, 'fullcontent');
                }

                $media_response = null;
                if (! empty($post['featured_media']) && ! empty($this->value_at($post, '_links', 'wp:featuredmedia'))) {
                    $media_response = $this->dispatch_local_request($this->to_string($this->value_at($post, '_links', 'wp:featuredmedia', 0, 'href')));
                }

                if ($media_response !== null) {
                    $media = $media_response->data;

                    if ($this->value_at($media, 'media_details', 'sizes', 'post-thumbnail') !== null) {
                        $subset->thumbnail = $this->value_at($media, 'media_details', 'sizes', 'post-thumbnail', 'source_url');
                    } elseif ($this->value_at($media, 'media_details', 'sizes', 'thumbnail') !== null) {
                        $subset->thumbnail = $this->value_at($media, 'media_details', 'sizes', 'thumbnail', 'source_url');
                    } else {
                        $subset->thumbnail = $this->value_at($media, 'source_url');
                    }
                } else {
                    $subset->thumbnail = false;
                }

                $subset->author_name = '';

                if (! empty($post['author']) && ! empty($this->value_at($post, '_links', 'author'))) {
                    $author_response = $this->dispatch_local_request($this->to_string($this->value_at($post, '_links', 'author', 0, 'href')));
                    if ($author_response !== null && $this->value_at($author_response->data, 'name') !== null) {
                        $subset->author_name = $this->value_at($author_response->data, 'name');
                    }
                }

                // We've always provided an empty value for terms. @todo Implement terms. :)
                $subset->terms = [];
            } // End if().

            /**
             * Filter the data stored for an individual result after defaults have been built.
             *
             * @since 0.7.10
             *
             * @param  stdClass  $subset  Data attached to this result.
             * @param  array<mixed>  $post  Data for an individual post retrieved through `rest_do_request()` on this site.
             * @param  array<string, mixed>  $atts  Attributes originally passed to the `uamswp_news` shortcode.
             */
            $subset = apply_filters('uams_content_syndication_host_data', $subset, $post, $atts);

            // An undated post, or a date strtotime() cannot read, takes the current time as its key: a false
            // key would collide with the next one while `$subset_key++` leaves false unchanged.
            $post_date = $this->to_string($this->value_at($post, 'date'));
            $subset_key = $post_date ? (strtotime($post_date) ?: time()) : time();

            while (array_key_exists($subset_key, $new_data)) {
                $subset_key++;
            }
            $new_data[$subset_key] = $subset;
        } // End foreach().

        return $new_data;
    }
}
