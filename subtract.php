<?php
/**
 * Plugin Name:       Subtract
 * Plugin URI:        https://github.com/subtractionco/subtract
 * Description:       Switches off what most WordPress sites never use. One file, no settings. Each removal is explained below and can be kept with one line in wp-config.php.
 * Version:           1.1.0
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
 * The names: comments, xmlrpc, emoji, embeds, head, users, dashboard, forms, editor.
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
 * forms
 * Spam sent through your contact forms (Elementor Forms and Contact Form 7).
 *
 * Why: closing comments moves the spam to the contact form. Most of it comes from bots that post
 * straight to the form's address without opening the page, or fill in every field they find.
 * The rest is pasted in by people working through lists of sites, usually in another script
 * with a link to a shop. Each form gets two invisible checks, and every message three rules:
 *   - A hidden field people never see. Bots fill it in.
 *   - A signed time stamp. A message sent without one (the page was never opened), within three
 *     seconds of the page loading, or from a page more than 30 days old is refused.
 *   - On a site whose language uses the Latin alphabet, a message mostly in another script is
 *     refused, as is one with three or more links or a link to a .ru or .su address.
 * Nothing is stored and no outside service is asked. A refused visitor is told the message could
 * not be sent and asked to get in touch another way. Keep "forms" if a check gets in your way.
 * ------------------------------------------------------------------------------------------- */
if (subtract_on('forms')) {
    /** The two hidden fields, added to every form. */
    function subtract_form_fields(): string {
        $t = (string) time();
        return '<span aria-hidden="true" style="position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden">'
            . '<label>Leave this empty <input type="text" name="subtract_hp" value="" tabindex="-1" autocomplete="off"></label></span>'
            . '<input type="hidden" name="subtract_t" value="' . esc_attr($t . '.' . hash_hmac('sha256', $t, wp_salt('nonce'))) . '">';
    }

    /** Why a submission is spam, or an empty string when it is not. */
    function subtract_form_spam(array $values): string {
        if (!empty($_POST['subtract_hp'])) return 'hidden field filled in';
        $parts = explode('.', (string) ($_POST['subtract_t'] ?? ''), 2);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], wp_salt('nonce')), $parts[1])) return 'no time stamp';
        $age = time() - (int) $parts[0];
        if ($age < 3) return 'sent too fast';
        if ($age > 30 * DAY_IN_SECONDS) return 'page too old';

        $text = implode("\n", array_map(function ($v) { return is_array($v) ? implode(' ', $v) : (string) $v; }, $values));
        if (preg_match_all('#(https?://|www\.)#i', $text) >= 3) return 'too many links';
        if (preg_match('#(https?://|www\.)[^\s/]+\.(ru|su|xn--p1ai)(/|\s|$)#i', $text)) return 'link to a .ru or .su address';
        // On an English, Afrikaans, French (and so on) site, a message mostly in another script is not for you.
        $latin_site = !preg_match('/^(ru|uk|bg|sr|mk|be|kk|zh|ja|ko|ar|fa|he|th|hi|el)/', get_locale());
        $letters = preg_match_all('/\p{L}/u', $text);
        if ($latin_site && $letters >= 20 && preg_match_all('/\p{Latin}/u', $text) / $letters < 0.5) return 'written in another script';
        return '';
    }

    $subtract_sorry = 'Sorry, your message could not be sent. Please get in touch another way.';

    // Elementor Forms
    add_filter('elementor/widget/render_content', function ($content, $widget) {
        if (!is_object($widget) || !method_exists($widget, 'get_name') || $widget->get_name() !== 'form') return $content;
        return preg_replace('#</form>#i', subtract_form_fields() . '</form>', (string) $content, 1);
    }, 10, 2);
    add_action('elementor_pro/forms/validation', function ($record, $ajax) use ($subtract_sorry) {
        $fields = (array) $record->get('fields');
        $why = subtract_form_spam(array_map(function ($f) { return $f['value'] ?? ''; }, $fields));
        if ($why === '') return;
        do_action('subtract_form_refused', $why, 'elementor');
        $first = array_key_first($fields);
        $ajax->add_error($first !== null ? $first : 'subtract', $subtract_sorry);
        $ajax->add_error_message($subtract_sorry);
    }, 10, 2);

    // Contact Form 7
    add_filter('wpcf7_form_elements', function ($html) { return $html . subtract_form_fields(); });
    add_filter('wpcf7_spam', function ($spam, $submission = null) {
        if ($spam) return $spam;
        $data = is_object($submission) && method_exists($submission, 'get_posted_data') ? (array) $submission->get_posted_data() : [];
        $why = subtract_form_spam($data);
        if ($why === '') return false;
        do_action('subtract_form_refused', $why, 'cf7');
        if (is_object($submission) && method_exists($submission, 'add_spam_log')) $submission->add_spam_log(['agent' => 'subtract', 'reason' => $why]);
        return true;
    }, 10, 2);
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
