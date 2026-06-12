<?php
/**
 * Cotlas Admin SEO module bootstrap.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-seo-plugin-compatibility.php';
require_once __DIR__ . '/class-posttype-mapping.php';
require_once __DIR__ . '/class-schema-generator.php';
require_once __DIR__ . '/class-organization-schema.php';
require_once __DIR__ . '/class-article-schema.php';
require_once __DIR__ . '/class-webpage-schema.php';
require_once __DIR__ . '/class-website-schema.php';
require_once __DIR__ . '/class-breadcrumb-schema.php';
require_once __DIR__ . '/class-author-schema.php';
require_once __DIR__ . '/class-faq-schema.php';
require_once __DIR__ . '/class-localbusiness-schema.php';
require_once __DIR__ . '/class-opengraph.php';
require_once __DIR__ . '/class-schema.php';
require_once __DIR__ . '/class-settings.php';

new CotlasAdmin\SEO\OpenGraph();
new CotlasAdmin\SEO\Schema();
new CotlasAdmin\SEO\Settings();
CotlasAdmin\SEO\Breadcrumb_Schema::init();
