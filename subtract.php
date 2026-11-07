<?php
/**
 * Plugin Name:       Subtract
 * Plugin URI:        https://github.com/subtractionco/subtract
 * Description:       Switches off what most WordPress sites never use. One file, no settings. Each removal is explained below and can be kept with one line in wp-config.php.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Subtraction
 * Author URI:        https://subtraction.co
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Update URI:        https://github.com/subtractionco/subtract
 */

/*
 * How it works
 * ------------
 * Every removal below is one block with a name. All of them are on.
 * To keep a feature, name its block in wp-config.php, above "That's all, stop editing!":
 *
 *     define('SUBTRACT_KEEP', 'comments, xmlrpc');
 *
 * The names: comments, xmlrpc, emoji, embeds, head, users, dashboard, editor.
 * Nothing is deleted from your database. Deactivate the plugin and everything is back as it was.
 */

if (!defined('ABSPATH')) exit;

/** True when a block is on, that is, not named in SUBTRACT_KEEP. */
function subtract_on(string $block): bool {
    static $keep = null;
    if ($keep === null) {
        $keep = defined('SUBTRACT_KEEP') ? array_filter(array_map('trim', explode(',', strtolower((string) SUBTRACT_KEEP)))) : [];
    }
    return !in_array($block, $keep, true);
}

/** Stops the request with a short plain-text answer, before WordPress does any more work. */
function subtract_refuse(string $message): void {
    status_header(403);
    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

/** The file part of the requested path, e.g. "xmlrpc.php". */
function subtract_script(): string {
    return basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
}


/* ---------------------------------------------------------------------------------------------
 * comments
 * Comments, pingbacks and trackbacks, everywhere.
 *
 * Why: most business sites never show a comment form, yet WordPress still accepts comments sent
 * straight to wp-comments-post.php and the comments API. That is where comment spam comes from.
 * Each post also keeps its own "allow comments" setting, so closing them in Settings does not
 * close old posts. This closes all of them in code.
 * Existing comments stay in the database, hidden. Keep "comments" to bring them back.
 * ------------------------------------------------------------------------------------------- */
if (subtract_on('comments')) {
    add_filter('comments_open', '__return_false', 99);
    add_filter('pings_open', '__return_false', 99);
    add_filter('comments_array', '__return_empty_array', 99);
    add_filter('get_comments_number', '__return_zero', 99);

    add_action('init', function () {
        foreach (get_post_types() as $type) {
            remove_post_type_support($type, 'comments');
            remove_post_type_support($type, 'trackbacks');
        }
        if (in_array(subtract_script(), ['wp-comments-post.php', 'wp-trackback.php'], true)) {
            subtract_refuse('Comments are closed.');
        }
    }, 0);

    // The comments API is closed to visitors who are not logged in.
    add_filter('rest_endpoints', function ($endpoints) {
        if (is_user_logged_in()) return $endpoints;
        foreach (array_keys($endpoints) as $route) {
            if (strpos($route, '/wp/v2/comments') === 0) unset($endpoints[$route]);
        }
        return $endpoints;
    });

    // No comment feeds.
    add_filter('feed_links_show_comments_feed', '__return_false');
    add_action('template_redirect', function () {
        if (is_comment_feed()) { wp_safe_redirect(home_url('/'), 301); exit; }
    });

    // Nothing in the dashboard that suggests comments are a thing here.
    add_action('admin_menu', function () {
        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    });
    add_action('admin_bar_menu', function ($bar) { $bar->remove_node('comments'); }, 99);
    add_action('wp_dashboard_setup', function () { remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal'); });
}


/* ---------------------------------------------------------------------------------------------
 * xmlrpc
 * XML-RPC, the remote publishing interface from before the REST API.
 *
 * Why: it is a favourite target for password guessing, because one request can try hundreds of
 * passwords, and for pingback spam. The block editor, the WordPress apps and most tools now use
 * the REST API instead.
 * Keep "xmlrpc" if you use Jetpack, or publish from an old desktop or mobile app.
 * ------------------------------------------------------------------------------------------- */
if (subtract_on('xmlrpc')) {
    add_filter('xmlrpc_enabled', '__return_false');
    add_filter('xmlrpc_methods', '__return_empty_array');
    add_filter('wp_headers', function ($headers) { unset($headers['X-Pingback']); return $headers; });
    add_filter('pings_open', '__return_false', 99);
    remove_action('wp_head', 'rsd_link');
    add_action('init', function () {
        if ((defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) || subtract_script() === 'xmlrpc.php') subtract_refuse('XML-RPC is off.');
    }, 0);
}


/* ---------------------------------------------------------------------------------------------
 * emoji
 * The emoji script and styles WordPress adds to every page.
 *
 * Why: every browser and phone has shown emoji natively for years. The script still loads on
 * every page, in the dashboard and in emails, to swap emoji for images.
 * Emoji you type keep working; they are drawn by the visitor's device instead.
 * ------------------------------------------------------------------------------------------- */
if (subtract_on('emoji')) {
    // Twice, because WordPress adds the dashboard copies of these hooks after "init".
    $subtract_emoji = function () {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('embed_head', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
        remove_action('admin_enqueue_scripts', 'wp_enqueue_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    };
    add_action('init', $subtract_emoji);
    add_action('admin_init', $subtract_emoji);
    add_filter('tiny_mce_plugins', function ($plugins) {
        return is_array($plugins) ? array_values(array_diff($plugins, ['wpemoji'])) : $plugins;
    });
    // The emoji images were fetched from s.w.org; stop asking the browser to look it up.
    add_filter('wp_resource_hints', function ($urls, $type) {
        if ($type !== 'dns-prefetch') return $urls;
        return array_values(array_filter($urls, function ($u) {
            return strpos(is_array($u) ? (string) ($u['href'] ?? '') : (string) $u, 's.w.org') === false;
        }));
    }, 10, 2);
}


/* ---------------------------------------------------------------------------------------------
 * embeds
 * Other sites embedding your posts as cards.
 *
 * Why: WordPress adds discovery links and a script to every page so that other WordPress sites
 * can show your posts as embedded cards. Few sites want that, and the script loads everywhere.
 * Embedding others in your posts (YouTube, Vimeo, Spotify and so on) keeps working.
 * ------------------------------------------------------------------------------------------- */
if (subtract_on('embeds')) {
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
    add_action('wp_enqueue_scripts', function () { wp_dequeue_script('wp-embed'); }, 100);
}


/* ---------------------------------------------------------------------------------------------
 * head
 * Lines in every page's <head> that nothing reads.
 *
 * Why: the generator tag announces your exact WordPress version to anyone scanning for old,
 * vulnerable sites. The rest (Windows Live Writer, shortlinks, previous and next post links)
 * belong to tools and browsers that stopped using them long ago.
 * Your RSS feed, canonical links and the REST API are untouched.
 * ------------------------------------------------------------------------------------------- */
if (subtract_on('head')) {
    remove_action('wp_head', 'wp_generator');
    add_filter('the_generator', '__return_empty_string');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_shortlink_wp_head', 10);
    remove_action('template_redirect', 'wp_shortlink_header', 11);
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
}


/* ---------------------------------------------------------------------------------------------
 * users
 * Lists of your usernames, open to anyone.
 *
 * Why: /?author=1 redirects to the first user's archive, and /wp-json/wp/v2/users lists every
 * author. Both hand out the login names that password-guessing bots need. The users sitemap
 * does the same. Visitors who are not logged in no longer get any of them.
 * Author archives at /author/name/ still work. Keep "users" if a tool reads users from the API
 * without logging in.
 * ------------------------------------------------------------------------------------------- */
if (subtract_on('users')) {
    add_action('template_redirect', function () {
        if (!is_user_logged_in() && isset($_GET['author'])) { wp_safe_redirect(home_url('/'), 301); exit; }
    }, 1);   // before WordPress turns ?author=1 into /author/name/
    add_filter('rest_endpoints', function ($endpoints) {
        if (is_user_logged_in()) return $endpoints;
        foreach (array_keys($endpoints) as $route) {
            if (strpos($route, '/wp/v2/users') === 0) unset($endpoints[$route]);
        }
        return $endpoints;
    });
    add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
        return $name === 'users' ? false : $provider;
    }, 10, 2);
}


/* ---------------------------------------------------------------------------------------------
 * dashboard
 * The Welcome panel and the WordPress Events and News box.
 *
 * Why: they are the first thing you see after logging in, every time, and neither is about
 * your site. The Events box also asks wordpress.org for meetups near you.
 * ------------------------------------------------------------------------------------------- */
if (subtract_on('dashboard')) {
    // On "admin_init", because WordPress adds the Welcome panel after plugins load.
    add_action('admin_init', function () { remove_action('welcome_panel', 'wp_welcome_panel'); });
    add_action('wp_dashboard_setup', function () { remove_meta_box('dashboard_primary', 'dashboard', 'side'); });
}


/* ---------------------------------------------------------------------------------------------
 * editor
 * Editing theme and plugin code from the dashboard.
 *
 * Why: anyone who gets into an administrator account can use it to run their own code on your
 * server, and a typo there can take the site down with no undo. Code changes belong in version
 * control or at least an FTP client, where they can be reversed.
 * ------------------------------------------------------------------------------------------- */
if (subtract_on('editor') && !defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}
