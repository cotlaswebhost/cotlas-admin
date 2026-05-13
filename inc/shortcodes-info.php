<?php
/**
 * Admin page listing all shortcodes and GenerateBlocks dynamic tags registered by cotlas-admin.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

add_action('admin_menu', function () {
    add_menu_page(
        'Shortcodes Info',
        'Shortcodes Info',
        'manage_options',
        'cotlas-shortcodes-info',
        'cotlas_render_shortcodes_info_page',
        'dashicons-editor-code',
        99
    );
});

/**
 * cotlas_render_shortcodes_info_page.
 */
function cotlas_render_shortcodes_info_page() {

    echo '<style>
        .cotlas-sc-wrap { max-width:1200px; }
        .cotlas-sc-wrap h1 { margin-bottom:4px; }
        .cotlas-sc-wrap .sc-subtitle { color:#666; margin-bottom:24px; font-size:13px; }
        .cotlas-sc-wrap h2 { border-bottom:1px solid #dcdcde; padding-bottom:6px; margin-top:32px; }
        .cotlas-sc-wrap h3 { margin:0 0 6px; font-size:14px; }
        .cotlas-sc-wrap table code { background:#f0f0f1; padding:2px 5px; border-radius:3px; font-size:12px; }
        .cotlas-sc-wrap .usage-block { background:#fff; border:1px solid #dcdcde; border-radius:4px; padding:16px 20px; margin-bottom:16px; }
        .cotlas-sc-wrap .usage-block p { margin:0 0 8px; color:#555; font-size:13px; }
        .cotlas-sc-wrap .usage-block ul { margin:4px 0 0; padding-left:18px; }
        .cotlas-sc-wrap .usage-block ul li { font-size:13px; margin-bottom:4px; line-height:1.6; }
        .cotlas-sc-wrap .usage-block ul li code { background:#f0f0f1; padding:1px 4px; border-radius:3px; }
        .cotlas-sc-wrap .tag-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:12px; }
        .cotlas-sc-wrap .gb-tag-card { background:#fff; border:1px solid #dcdcde; border-radius:4px; padding:14px 16px; }
        .cotlas-sc-wrap .gb-tag-card h4 { margin:0 0 6px; font-size:13px; color:#1d2327; }
        .cotlas-sc-wrap .gb-tag-card p { margin:0 0 6px; font-size:12px; color:#666; }
        .cotlas-sc-wrap .gb-tag-card ul { margin:0; padding-left:16px; }
        .cotlas-sc-wrap .gb-tag-card ul li { font-size:12px; color:#555; margin-bottom:2px; }
        .cotlas-sc-wrap .notice-tip { background:#f0f6fc; border-left:4px solid #2271b1; padding:10px 14px; border-radius:0 3px 3px 0; margin-bottom:20px; font-size:13px; }
    </style>';

    echo '<div class="wrap cotlas-sc-wrap">';
    echo '<h1>Shortcodes Info</h1>';
    echo '<p class="sc-subtitle">All shortcodes and GenerateBlocks dynamic tags registered by the <strong>cotlas-admin</strong> plugin. Site settings are managed under <strong>Settings &rarr; Site Settings</strong>.</p>';

    // ── 1. Plugin Shortcodes ─────────────────────────────────────────────────
    $plugin_shortcodes = [
        ['tag' => 'gp_nav',                 'desc' => 'Renders the GeneratePress navigation menu. No attributes.'],
        ['tag' => 'first_category',         'desc' => 'Shows the first category of the current post as a linked badge with icon. No attributes.'],
        ['tag' => 'yoast_primary_category', 'desc' => 'Displays Yoast primary category (or first category fallback). Supports: show_link, class, text_class, fallback.'],
        ['tag' => 'social_share',           'desc' => 'Social share buttons for the current post. Supports: class, size, show_names, networks.'],
        ['tag' => 'author_social_links',    'desc' => 'Author social profile links from their WP profile. Supports: class, size, show_names, networks.'],
        ['tag' => 'cotlas_social',          'desc' => 'Site social icons from Site Settings. Supports: class, size, show_names, networks.'],
        ['tag' => 'audio_player',           'desc' => 'HTML5 audio player for Audio-format posts. Requires _audio_file_url meta. Supports: style, width, height, autoplay, loop.'],
        ['tag' => 'cotlas_search',          'desc' => 'Accessible search form. Supports: placeholder, button_label, input_label, post_types.'],
        ['tag' => 'local_datetime',         'desc' => "Visitor's local date/time updating live via JS. Supports: class, date_format, time_format."],
        ['tag' => 'cotlas_comments',        'desc' => 'Styled threaded comments section. Supports: post_id, title.'],
        ['tag' => 'cotlas_logout_link',     'desc' => 'Logout link — only renders when user is logged in. No attributes.'],
        ['tag' => 'cotlas_login_link',      'desc' => 'Login link — only renders when user is logged out. No attributes.'],
        ['tag' => 'category_info',          'desc' => "Output any category's name, description, or URL. Supports: id, slug, field, link."],
        ['tag' => 'post_marquee',           'desc' => 'Scrolling headline ticker for recent posts. Supports: count, category, speed.'],
        ['tag' => 'trending_categories',    'desc' => 'List of trending categories by post activity. Cached 1 hour. Supports: count, label.'],
        ['tag' => 'most_read',              'desc' => 'Most-viewed posts (requires Post Views Counter plugin). Cached 1 hour. Supports: count.'],
        ['tag' => 'human_date',             'desc' => 'Relative date ("3 hours ago"). Falls back to formatted date after 24 h. Supports: type, id.'],
        ['tag' => 'focused_categories',     'desc' => 'Horizontal scrollable focus bar of tagged categories. Mark categories via Posts → Categories. Supports: label, highlight, orderby, order, class.'],
    ];

    echo '<h2>Plugin Shortcodes (cotlas-admin)</h2>';
    echo '<table class="widefat striped"><thead><tr><th style="width:240px">Shortcode</th><th>Description</th></tr></thead><tbody>';
    foreach ($plugin_shortcodes as $sc) {
        printf('<tr><td><code>[%s]</code></td><td>%s</td></tr>', esc_html($sc['tag']), esc_html($sc['desc']));
    }
    echo '</tbody></table>';

    // ── 2. Site Settings Shortcodes ──────────────────────────────────────────
    $settings_shortcodes = [
        ['tag' => 'company_name',        'desc' => 'Company/site name'],
        ['tag' => 'company_tagline',     'desc' => 'Company tagline or slogan'],
        ['tag' => 'company_address',     'desc' => 'Company address (line breaks preserved)'],
        ['tag' => 'company_phone',       'desc' => 'Company phone number'],
        ['tag' => 'company_email',       'desc' => 'Company email address'],
        ['tag' => 'company_short_intro', 'desc' => 'Short company introduction paragraph (HTML allowed)'],
        ['tag' => 'company_whatsapp',    'desc' => 'WhatsApp number'],
        ['tag' => 'social_facebook',     'desc' => 'Facebook page URL'],
        ['tag' => 'social_twitter',      'desc' => 'Twitter/X profile URL'],
        ['tag' => 'social_youtube',      'desc' => 'YouTube channel URL'],
        ['tag' => 'social_instagram',    'desc' => 'Instagram profile URL'],
        ['tag' => 'social_linkedin',     'desc' => 'LinkedIn page URL'],
        ['tag' => 'social_threads',      'desc' => 'Threads profile URL'],
    ];

    echo '<h2>Site Settings Shortcodes</h2>';
    echo '<p style="color:#555;font-size:13px;margin-top:6px;">Output values saved in <strong>Settings &rarr; Site Settings</strong>. These shortcodes take no attributes — they simply return the saved value.</p>';
    echo '<table class="widefat striped"><thead><tr><th style="width:240px">Shortcode</th><th>Description</th></tr></thead><tbody>';
    foreach ($settings_shortcodes as $sc) {
        printf('<tr><td><code>[%s]</code></td><td>%s</td></tr>', esc_html($sc['tag']), esc_html($sc['desc']));
    }
    echo '</tbody></table>';

    // ── 3. GenerateBlocks Dynamic Tags ───────────────────────────────────────
    echo '<h2>GenerateBlocks Dynamic Tags</h2>';
    echo '<div class="notice-tip">These tags appear as <strong>dropdown selections</strong> inside the GenerateBlocks editor (Dynamic Content &amp; Dynamic Link panels). No manual key entry needed &mdash; just pick from the dropdown.</div>';

    $gb_native_tags = [
        [
            'tag'     => 'Company Info',
            'tag_id'  => 'company_info',
            'type'    => 'Option value (global setting)',
            'use_for' => 'Dynamic Content — any text block',
            'desc'    => 'Outputs any company detail from Site Settings.',
            'options' => ['Company Name', 'Company Tagline', 'Company Address', 'Company Phone', 'Company Email', 'Company Short Intro', 'Company WhatsApp'],
        ],
        [
            'tag'     => 'Company Social URL',
            'tag_id'  => 'company_social',
            'type'    => 'Option value (global setting)',
            'use_for' => 'Dynamic Link — icon or button blocks',
            'desc'    => 'Outputs a social network URL from Site Settings. WhatsApp auto-generates a wa.me/... link.',
            'options' => ['Facebook', 'Twitter/X', 'Instagram', 'LinkedIn', 'YouTube', 'Threads', 'WhatsApp'],
        ],
        [
            'tag'     => 'Yoast Primary Category',
            'tag_id'  => 'yoast_primary_category',
            'type'    => 'Post-based (query loop)',
            'use_for' => 'Dynamic Content or Dynamic Link in query loops',
            'desc'    => 'Primary Yoast category of the current post. Falls back to first category.',
            'options' => ['Category name', 'Linked category name (full HTML)', 'Category URL', 'Category slug', 'Category ID'],
        ],
        [
            'tag'     => 'Human Date (Relative)',
            'tag_id'  => 'human_date',
            'type'    => 'Post-based (query loop)',
            'use_for' => 'Dynamic Content — date display blocks',
            'desc'    => 'Relative date string ("3 hours ago"). Falls back to formatted date after 24 hours. Supports Dynamic Link (links to post).',
            'options' => ['Published date (default)', 'Modified date'],
        ],
        [
            'tag'     => 'Post Views Count',
            'tag_id'  => 'post_views',
            'type'    => 'Post-based (query loop)',
            'use_for' => 'Dynamic Content — view count display',
            'desc'    => 'Returns the raw view count number for the current post. Requires Post Views Counter plugin. Outputs plain number, no HTML.',
            'options' => ['Current post (default)', 'Specific post via source picker'],
        ],
        [
            'tag'     => 'Primary Category',
            'tag_id'  => 'primary_category',
            'type'    => 'Post-based (query loop)',
            'use_for' => 'Dynamic Content or Dynamic Link — category label',
            'desc'    => 'Yoast SEO primary category of the current post. Falls back to first assigned category. Supports Dynamic Link (wraps in <a> to category archive).',
            'options' => ['Category name (plain text)', 'Linked category name — enable via Dynamic Link → Term'],
        ],
        [
            'tag'     => 'Term Display',
            'tag_id'  => 'term_display',
            'type'    => 'Term-based (taxonomy archive or query loop)',
            'use_for' => 'Dynamic Content or Dynamic Link — any term data',
            'desc'    => 'Outputs any field from a category/taxonomy term. Works on archive pages and post loops. Key option controls what is returned.',
            'options' => ['term_title — term name', 'term_desc — term description', 'term_image — category featured image URL', 'term_count — number of posts in term', 'term_url — term archive URL'],
        ],
        [
            'tag'     => 'Term / Category Image',
            'tag_id'  => 'term_image',
            'type'    => 'Post-based (uses primary category)',
            'use_for' => 'Dynamic Image src or Dynamic Link — category image blocks',
            'desc'    => 'Returns the featured image set on the category (via Categories screen). Resolves via Yoast primary category inside post loops, or queried term on archives.',
            'options' => ['key:url — image URL (default)', 'key:id — attachment post ID', 'key:alt — image alt text', 'size: — any registered image size (e.g. medium, large)'],
        ],
    ];

    echo '<div class="tag-grid">';
    foreach ($gb_native_tags as $t) {
        echo '<div class="gb-tag-card">';
        printf(
            '<h4>%s &nbsp;<small style="color:#888;font-weight:400;">tag: <code>%s</code></small></h4>',
            esc_html($t['tag']),
            esc_html($t['tag_id'])
        );
        printf('<p><strong>Type:</strong> %s<br><strong>Use for:</strong> %s</p>', esc_html($t['type']), esc_html($t['use_for']));
        printf('<p>%s</p>', esc_html($t['desc']));
        echo '<ul>';
        foreach ($t['options'] as $opt) {
            printf('<li>%s</li>', esc_html($opt));
        }
        echo '</ul></div>';
    }
    echo '</div>';

    // ── 3b. Legacy post-meta keys ────────────────────────────────────────────
    echo '<h3 style="margin-top:24px;">Legacy post-meta Keys (manual entry)</h3>';
    echo '<p style="color:#555;font-size:13px;">Alternatively, set <em>Dynamic Content Type</em> to <strong>post-meta</strong> in GenerateBlocks and type one of these keys manually into the <em>Meta Field Name</em> field. The native dropdown tags above are the preferred method.</p>';

    $gb_keys = [
        ['key' => 'cotlas_company_name',        'desc' => 'Company name'],
        ['key' => 'cotlas_company_tagline',     'desc' => 'Company tagline'],
        ['key' => 'cotlas_company_address',     'desc' => 'Company address'],
        ['key' => 'cotlas_company_phone',       'desc' => 'Company phone'],
        ['key' => 'cotlas_company_email',       'desc' => 'Company email'],
        ['key' => 'cotlas_company_short_intro', 'desc' => 'Company short intro'],
        ['key' => 'cotlas_company_whatsapp',    'desc' => 'WhatsApp number'],
        ['key' => 'cotlas_social_facebook',     'desc' => 'Facebook URL'],
        ['key' => 'cotlas_social_twitter',      'desc' => 'Twitter/X URL'],
        ['key' => 'cotlas_social_youtube',      'desc' => 'YouTube URL'],
        ['key' => 'cotlas_social_instagram',    'desc' => 'Instagram URL'],
        ['key' => 'cotlas_social_linkedin',     'desc' => 'LinkedIn URL'],
        ['key' => 'cotlas_social_threads',      'desc' => 'Threads URL'],
    ];

    echo '<table class="widefat striped"><thead><tr><th style="width:280px">Key (Meta Field Name)</th><th>Description</th></tr></thead><tbody>';
    foreach ($gb_keys as $k) {
        printf('<tr><td><code>%s</code></td><td>%s</td></tr>', esc_html($k['key']), esc_html($k['desc']));
    }
    echo '</tbody></table>';

    // ── 4. Detailed Usage & Attributes ───────────────────────────────────────
    echo '<h2>Shortcode Usage &amp; Attributes</h2>';

    // [gp_nav]
    echo '<div class="usage-block">';
    echo '<h3>[gp_nav]</h3>';
    echo '<p>Renders the GeneratePress navigation menu exactly as it appears in the theme. Useful inside custom layout elements or widget areas where the nav is not automatically inserted by the theme.</p>';
    echo '<p><em>No attributes.</em></p><p>Example: <code>[gp_nav]</code></p>';
    echo '</div>';

    // [first_category]
    echo '<div class="usage-block">';
    echo '<h3>[first_category]</h3>';
    echo '<p>Outputs the first category of the current post as a linked badge with an SVG icon. Designed for post headers and cards inside query loops.</p>';
    echo '<p><em>No attributes.</em></p><p>Example: <code>[first_category]</code></p>';
    echo '</div>';

    // [yoast_primary_category]
    echo '<div class="usage-block">';
    echo '<h3>[yoast_primary_category]</h3>';
    echo '<p>Displays the Yoast SEO primary category of the current post. Falls back to the first assigned category if no primary is set.</p>';
    echo '<ul>';
    echo '<li><strong>show_link</strong>: <code>true</code> (default) | <code>false</code> &mdash; wrap the category name in a link. Example: <code>[yoast_primary_category show_link="false"]</code></li>';
    echo '<li><strong>class</strong>: CSS class for the wrapper &lt;p&gt; element. Default: <code>gp-post-category</code>. Example: <code>[yoast_primary_category class="post-badge"]</code></li>';
    echo '<li><strong>text_class</strong>: CSS class for the inner &lt;span&gt;. Default: <code>gp-post-category-text</code>.</li>';
    echo '<li><strong>fallback</strong>: <code>first</code> (default, show first category if no Yoast primary) | <code>none</code> (show nothing). Example: <code>[yoast_primary_category fallback="none"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [cotlas_comments]
    echo '<div class="usage-block">';
    echo '<h3>[cotlas_comments]</h3>';
    echo '<p>Renders a styled threaded comments section with a reply form. Automatically targets the current post when used inside the loop.</p>';
    echo '<ul>';
    echo '<li><strong>post_id</strong>: Target a specific post by ID. Default: current post. Example: <code>[cotlas_comments post_id="42"]</code></li>';
    echo '<li><strong>title</strong>: Heading text shown above the comments. Default: <code>Comments</code>. Example: <code>[cotlas_comments title="Reader Comments"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [social_share]
    echo '<div class="usage-block">';
    echo '<h3>[social_share]</h3>';
    echo '<p>Renders social share buttons for the current post. Typically placed at the top and/or bottom of post content.</p>';
    echo '<ul>';
    echo '<li><strong>class</strong>: CSS classes for the wrapper element. Example: <code>[social_share class="cotlas-social-share cotlas-social-share-top"]</code></li>';
    echo '<li><strong>size</strong>: Icon size in pixels. Default: <code>24</code>. Example: <code>[social_share size="20"]</code></li>';
    echo '<li><strong>show_names</strong>: <code>true</code> | <code>false</code> (default) &mdash; show platform label next to icon. Example: <code>[social_share show_names="true"]</code></li>';
    echo '<li><strong>networks</strong>: Comma-separated platforms to include. Default: all. Allowed values: <code>facebook, twitter, linkedin, whatsapp, telegram, pinterest, reddit, threads, print</code>. Example: <code>[social_share networks="facebook,twitter,whatsapp"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [author_social_links]
    echo '<div class="usage-block">';
    echo '<h3>[author_social_links]</h3>';
    echo '<p>Shows the post author\'s social profile links. Values are pulled from the author\'s WordPress user profile fields. Best used on single-post or author archive templates.</p>';
    echo '<ul>';
    echo '<li><strong>class</strong>: Wrapper CSS class. Default: <code>cotlas-author-social-links</code>. Example: <code>[author_social_links class="author-social compact"]</code></li>';
    echo '<li><strong>size</strong>: Icon size in pixels. Default: <code>24</code>. Example: <code>[author_social_links size="20"]</code></li>';
    echo '<li><strong>show_names</strong>: <code>true</code> | <code>false</code> (default). Example: <code>[author_social_links show_names="true"]</code></li>';
    echo '<li><strong>networks</strong>: Comma-separated platforms. Allowed: <code>facebook, twitter, instagram, linkedin, youtube, pinterest, email</code>. Use <code>x</code> as alias for Twitter. Example: <code>[author_social_links networks="facebook,x,instagram"]</code></li>';
    echo '<li>Profile fields read from user meta: <code>facebook, twitter, instagram, linkedin, youtube, pinterest</code>. Email is read from the WP account email.</li>';
    echo '</ul>';
    echo '</div>';

    // [cotlas_social]
    echo '<div class="usage-block">';
    echo '<h3>[cotlas_social]</h3>';
    echo '<p>Renders site-wide social icon links. URLs are pulled from <strong>Settings &rarr; Site Settings</strong>. Only platforms that have a saved URL are rendered.</p>';
    echo '<ul>';
    echo '<li><strong>class</strong>: Wrapper CSS class. Example: <code>[cotlas_social class="footer-social"]</code></li>';
    echo '<li><strong>size</strong>: Icon size in pixels. Default: <code>24</code>. Example: <code>[cotlas_social size="20"]</code></li>';
    echo '<li><strong>show_names</strong>: <code>true</code> | <code>false</code> (default). Example: <code>[cotlas_social show_names="true"]</code></li>';
    echo '<li><strong>networks</strong>: Limit to specific platforms. Allowed: <code>facebook, twitter, instagram, youtube, linkedin, threads, whatsapp</code>. Use <code>x</code> as alias for Twitter. Example: <code>[cotlas_social networks="facebook,x,instagram,youtube"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [cotlas_search]
    echo '<div class="usage-block">';
    echo '<h3>[cotlas_search]</h3>';
    echo '<p>Accessible styled search form that submits to the standard WordPress search results page.</p>';
    echo '<ul>';
    echo '<li><strong>placeholder</strong>: Input placeholder text. Default: <code>Type to search...</code>. Example: <code>[cotlas_search placeholder="Search news..."]</code></li>';
    echo '<li><strong>button_label</strong>: Aria-label for the submit button (for screen readers). Default: <code>Submit search</code>. Example: <code>[cotlas_search button_label="Go"]</code></li>';
    echo '<li><strong>input_label</strong>: Aria-label for the search input (for screen readers). Default: <code>Search our website</code>. Example: <code>[cotlas_search input_label="Search articles"]</code></li>';
    echo '<li><strong>post_types</strong>: Comma-separated post types to restrict results. Default: <code>post,page</code>. Example: <code>[cotlas_search post_types="post"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [audio_player]
    echo '<div class="usage-block">';
    echo '<h3>[audio_player]</h3>';
    echo '<p>Renders a styled HTML5 audio player. Requires the post\'s <em>Post Format</em> set to <strong>Audio</strong> and a custom field named <code>_audio_file_url</code> containing the audio file URL.</p>';
    echo '<ul>';
    echo '<li><strong>style</strong>: Visual theme. <code>modern</code> (default) | <code>minimal</code> | <code>dark</code>. Example: <code>[audio_player style="dark"]</code></li>';
    echo '<li><strong>width</strong>: CSS width of the player. Default: <code>100%</code>. Example: <code>[audio_player width="320px"]</code></li>';
    echo '<li><strong>height</strong>: CSS height of the player. Default: <code>50px</code>. Example: <code>[audio_player height="40px"]</code></li>';
    echo '<li><strong>autoplay</strong>: <code>yes</code> | <code>no</code> (default). Example: <code>[audio_player autoplay="yes"]</code></li>';
    echo '<li><strong>loop</strong>: <code>yes</code> | <code>no</code> (default). Example: <code>[audio_player loop="yes"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [local_datetime]
    echo '<div class="usage-block">';
    echo '<h3>[local_datetime]</h3>';
    echo '<p>Shows the visitor\'s local date and time, updating every second via JavaScript. No server request — the browser fills in the time using the visitor\'s own timezone.</p>';
    echo '<ul>';
    echo '<li><strong>class</strong>: CSS class for the output span. Default: <code>cotlas-local-datetime</code>. Example: <code>[local_datetime class="header-clock"]</code></li>';
    echo '<li><strong>date_format</strong>: Intl date style. <code>full</code> | <code>long</code> (default) | <code>medium</code> | <code>short</code>. Example: <code>[local_datetime date_format="short"]</code></li>';
    echo '<li><strong>time_format</strong>: <code>12</code> (default, AM/PM) | <code>24</code> (24-hour). Example: <code>[local_datetime time_format="24"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [category_info]
    echo '<div class="usage-block">';
    echo '<h3>[category_info]</h3>';
    echo '<p>Outputs a specific field from any category. Identify the category by its WordPress ID or slug.</p>';
    echo '<ul>';
    echo '<li><strong>id</strong>: Category ID. Example: <code>[category_info id="5" field="name"]</code></li>';
    echo '<li><strong>slug</strong>: Category slug — used when <code>id</code> is not set. Example: <code>[category_info slug="sports"]</code></li>';
    echo '<li><strong>field</strong>: What to output. <code>name</code> (default) | <code>description</code> | <code>link</code> (returns the category archive URL). Example: <code>[category_info slug="sports" field="link"]</code></li>';
    echo '<li><strong>link</strong>: <code>true</code> | <code>false</code> (default) &mdash; when <code>field="name"</code>, wraps the name in an &lt;a&gt; tag linking to the category archive. Example: <code>[category_info slug="sports" link="true"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [post_marquee]
    echo '<div class="usage-block">';
    echo '<h3>[post_marquee]</h3>';
    echo '<p>A CSS-animated scrolling ticker showing recent post headlines with links to each post.</p>';
    echo '<ul>';
    echo '<li><strong>count</strong>: Number of posts to include. Default: <code>5</code>. Example: <code>[post_marquee count="8"]</code></li>';
    echo '<li><strong>category</strong>: Restrict to a specific category by slug. Default: all categories. Example: <code>[post_marquee category="cricket"]</code></li>';
    echo '<li><strong>speed</strong>: Animation loop duration in seconds. Lower = faster scroll. Default: <code>20</code>. Example: <code>[post_marquee speed="35"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [trending_categories]
    echo '<div class="usage-block">';
    echo '<h3>[trending_categories]</h3>';
    echo '<p>Displays a list of popular categories ranked by number of published posts. Results are cached for 1 hour.</p>';
    echo '<ul>';
    echo '<li><strong>count</strong>: Number of categories to show. Min: 1, Max: 20. Default: <code>6</code>. Example: <code>[trending_categories count="8"]</code></li>';
    echo '<li><strong>label</strong>: Optional heading text shown above the category list. Default: none. Example: <code>[trending_categories label="Popular Topics"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [most_read]
    echo '<div class="usage-block">';
    echo '<h3>[most_read]</h3>';
    echo '<p>Shows the most-viewed posts on the site. Requires the <strong>Post Views Counter</strong> plugin to be active for accurate view data. Results are cached for 1 hour.</p>';
    echo '<ul>';
    echo '<li><strong>count</strong>: Number of posts to show. Min: 1, Max: 10. Default: <code>3</code>. Example: <code>[most_read count="5"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [human_date]
    echo '<div class="usage-block">';
    echo '<h3>[human_date]</h3>';
    echo '<p>Outputs a relative date string like <em>"5 minutes ago"</em> or <em>"2 days ago"</em>. Within the last 24 hours it shows elapsed time; beyond that it shows the formatted publish/modified date.</p>';
    echo '<ul>';
    echo '<li><strong>type</strong>: <code>published</code> (default) | <code>modified</code>. Example: <code>[human_date type="modified"]</code></li>';
    echo '<li><strong>id</strong>: Post ID to target. Default: current post in loop. Example: <code>[human_date id="42"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [cotlas_logout_link] & [cotlas_login_link]
    echo '<div class="usage-block">';
    echo '<h3>[cotlas_logout_link] &amp; [cotlas_login_link]</h3>';
    echo '<p><code>[cotlas_logout_link]</code> renders a styled logout link with icon. <strong>Only visible when a user is logged in.</strong> Redirects to the homepage after logout.</p>';
    echo '<p><code>[cotlas_login_link]</code> renders a &ldquo;Sign in&rdquo; link. <strong>Only visible when no user is logged in.</strong> Links to <code>/login</code>.</p>';
    echo '<p><em>Neither shortcode accepts attributes.</em></p>';
    echo '</div>';

    // [focused_categories]
    echo '<div class="usage-block">';
    echo '<h3>[focused_categories]</h3>';
    echo '<p>A horizontally scrollable pill-style navigation bar showing categories marked as <strong>Focused</strong>. One category can be marked <strong>Highlighted</strong> to display with a coloured pill and appear first. Manage flags under <strong>Posts &rarr; Categories</strong> (Focused / Highlighted dropdowns).</p>';
    echo '<ul>';
    echo '<li><strong>label</strong>: Left-side label text. Default: <code>फोकस</code>. Example: <code>[focused_categories label="Focus"]</code></li>';
    echo '<li><strong>highlight</strong>: Category slug to force as the highlighted pill. Default: the category with the Highlighted flag set, or the first item. Example: <code>[focused_categories highlight="cricket"]</code></li>';
    echo '<li><strong>orderby</strong>: <code>name</code> (default) | <code>count</code> | <code>id</code> | <code>term_order</code>. Example: <code>[focused_categories orderby="count" order="DESC"]</code></li>';
    echo '<li><strong>order</strong>: <code>ASC</code> (default) | <code>DESC</code>.</li>';
    echo '<li><strong>class</strong>: Extra CSS class on the wrapper element. Example: <code>[focused_categories class="compact-bar"]</code></li>';
    echo '</ul>';
    echo '<p><em>Returns empty output if no categories have the Focused flag set.</em></p>';
    echo '</div>';

    // ── 5. GenerateBlocks Dynamic Tag Reference ───────────────────────────────
    echo '<h2>GenerateBlocks Dynamic Tag Usage</h2>';

    // post_views
    echo '<div class="usage-block">';
    echo '<h3>post_views <small style="color:#888;font-weight:400;">tag: <code>post_views</code></small></h3>';
    echo '<p>Returns the raw view count number for the current (or specified) post. Requires the <strong>Post Views Counter</strong> plugin. Outputs a plain number — no icon or HTML wrapper.</p>';
    echo '<ul>';
    echo '<li><strong>source</strong>: Pick via the source selector in the GB editor — <em>Current post</em> (default) or a specific post.</li>';
    echo '<li>Use in a GB Text block as Dynamic Content to display a number like <code>4,821</code>.</li>';
    echo '<li>Combine with a static icon/SVG block and this tag to build a &ldquo;views&rdquo; counter display.</li>';
    echo '</ul>';
    echo '</div>';

    // primary_category
    echo '<div class="usage-block">';
    echo '<h3>Primary Category <small style="color:#888;font-weight:400;">tag: <code>primary_category</code></small></h3>';
    echo '<p>Outputs the Yoast SEO primary category of the current post. Falls back to the first assigned category when no primary is set. Works in query loops.</p>';
    echo '<ul>';
    echo '<li><strong>Dynamic Content:</strong> Outputs the plain category name. Set Dynamic Content type to <em>Dynamic Tag</em> &rarr; <em>Primary Category</em>.</li>';
    echo '<li><strong>Dynamic Link &rarr; Term:</strong> Wraps the output in an <code>&lt;a href&gt;</code> linking to the category archive page.</li>';
    echo '<li><strong>source:</strong> Current post (default) or choose a specific post via the source picker.</li>';
    echo '<li>Similar to <code>yoast_primary_category</code> but with broader link support via the GB link panel.</li>';
    echo '</ul>';
    echo '</div>';

    // term_display
    echo '<div class="usage-block">';
    echo '<h3>Term Display <small style="color:#888;font-weight:400;">tag: <code>term_display</code></small></h3>';
    echo '<p>Outputs any field from a category or taxonomy term. Works on taxonomy archive pages and inside post query loops. Resolves the term automatically — via the queried object on archives, or the Yoast primary category inside loops.</p>';
    echo '<ul>';
    echo '<li><strong>key: term_title</strong> &mdash; the term name. Example: <code>term_display id:33 tax:category key:term_title</code></li>';
    echo '<li><strong>key: term_desc</strong> &mdash; the term description (HTML preserved).</li>';
    echo '<li><strong>key: term_image</strong> &mdash; URL of the category featured image (set via the Categories screen). Example: use as Image block src.</li>';
    echo '<li><strong>key: term_count</strong> &mdash; number of published posts in the term.</li>';
    echo '<li><strong>key: term_url</strong> &mdash; the term archive URL. Example: use as Dynamic Link on a button or image.</li>';
    echo '<li><strong>id:</strong> Explicit term ID. Optional — omit to auto-resolve from context.</li>';
    echo '<li><strong>tax:</strong> Taxonomy slug. Default: <code>category</code>.</li>';
    echo '</ul>';
    echo '<p><strong>Tip:</strong> To link an Image block to the category archive, set <em>Image src</em> to <code>term_display key:term_image</code> and <em>Dynamic Link</em> to <code>term_display key:term_url</code>.</p>';
    echo '</div>';

    // term_image
    echo '<div class="usage-block">';
    echo '<h3>Term / Category Image <small style="color:#888;font-weight:400;">tag: <code>term_image</code></small></h3>';
    echo '<p>Returns the featured image set on a category (uploaded via the Categories admin screen). Inside post loops it uses the Yoast primary category; on archive pages it uses the queried term.</p>';
    echo '<ul>';
    echo '<li><strong>key:url</strong> (default) &mdash; returns the full image URL.</li>';
    echo '<li><strong>key:id</strong> &mdash; returns the WordPress attachment post ID.</li>';
    echo '<li><strong>key:alt</strong> &mdash; returns the image alt text.</li>';
    echo '<li><strong>size:</strong> Any registered image size. Default: <code>full</code>. Example: <code>term_image id:12 size:medium</code></li>';
    echo '<li><strong>id:</strong> Explicit term ID. Omit to auto-resolve from the current post or archive context.</li>';
    echo '</ul>';
    echo '<p><strong>How to set a category image:</strong> Go to <em>Posts &rarr; Categories</em>, edit any category, and use the <em>Category Image</em> upload field.</p>';
    echo '</div>';

    // ── 6. GenerateBlocks Query Loop Parameters ───────────────────────────────
    echo '<h2>GenerateBlocks Query Loop Parameters</h2>';
    echo '<div class="notice-tip">These are custom parameters added to GenerateBlocks Query Loop blocks via the <strong>Query Parameters</strong> panel in the block editor. They extend the standard query options.</div>';

    $query_params = [
        [
            'param'   => 'featuredPosts',
            'desc'    => 'Filter query results by the Featured Post flag set in the block editor sidebar.',
            'values'  => [
                '<code>only</code> — show only posts marked as featured',
                '<code>exclude</code> — hide all featured posts, show the rest',
                '(empty) — no filter, show all posts (default)',
            ],
            'note'    => 'The Featured Post toggle appears in the block editor sidebar under a "Featured Post" panel. It saves the <code>_is_featured</code> post meta.',
        ],
        [
            'param'   => 'popularPosts',
            'desc'    => 'Order query results by view count (most viewed first). Requires the Post Views Counter plugin.',
            'values'  => [
                '<code>1</code> or any truthy value — enable ordering by views descending',
            ],
            'note'    => 'When active, overrides the Order By setting and sets orderby to post_views with suppress_filters disabled.',
        ],
    ];

    foreach ($query_params as $qp) {
        echo '<div class="usage-block">';
        printf('<h3>%s</h3>', esc_html($qp['param']));
        printf('<p>%s</p>', esc_html($qp['desc']));
        echo '<ul>';
        foreach ($qp['values'] as $v) {
            echo '<li>' . wp_kses($v, ['code' => []]) . '</li>';
        }
        echo '</ul>';
        printf('<p><em>Note: %s</em></p>', esc_html($qp['note']));
        echo '</div>';
    }

    echo '</div>'; // .cotlas-sc-wrap
}
/**
 * Custom GenerateBlocks Dynamic Tag: Post Views Count
 *
 * Returns the raw view count number for the current (or specified) post,
 * from the Post Views Counter plugin. No HTML, no icon — just the number.
 *
 * Usage in a GB Text block:
 *   {{post_views}}            – view count of the current post in a loop
 *   {{post_views id:42}}      – view count of a specific post by ID
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_loaded', 'gpc_register_post_views_tag' );
/**
 * gpc_register_post_views_tag.
 */
function gpc_register_post_views_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
