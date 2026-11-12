# Subtract

Switch off what your WordPress site never uses.

One file. No settings page, no database tables, nothing to configure. Every removal is explained in the code, in plain English, and any of them can be kept with one line in `wp-config.php`.

## What it removes

| Block | What goes | Why |
| --- | --- | --- |
| `comments` | Comments, pingbacks and trackbacks, everywhere, plus the comment feeds and the Comments menu | Bots post straight to `wp-comments-post.php` and the comments API even when your theme shows no form. Closing comments in Settings leaves old posts open. |
| `xmlrpc` | XML-RPC and the pingback header | A favourite target for password guessing: one request can try hundreds of passwords. The block editor and the WordPress apps use the REST API instead. |
| `emoji` | The emoji script and styles on every page, in the dashboard and in emails | Every browser and phone has drawn emoji natively for years. Emoji you type keep working. |
| `embeds` | The discovery links and script that let other sites embed your posts as cards | Few sites want it, and the script loads everywhere. Embedding YouTube, Vimeo and others in your own posts keeps working. |
| `head` | The WordPress version tag, Windows Live Writer, shortlinks, previous and next post links | The version tag tells scanners which sites run old, vulnerable releases. The rest serve tools nobody uses any more. |
| `users` | `/?author=1`, the users API and the users sitemap, for visitors who are not logged in | They hand out the login names that password-guessing bots need. Author archives at `/author/name/` still work. |
| `dashboard` | The Welcome panel and the WordPress Events and News box | The first thing you see after logging in, and neither is about your site. |
| `forms` | Spam through Elementor Forms and Contact Form 7: a hidden field bots fill in, a signed time stamp, a check that a person actually used the page, and four rules for the message itself | Closing comments moves the spam to the contact form. Refused: messages posted without opening the page, sent within three seconds, sent without anyone pressing a key, tapping or clicking, mostly in another script on a Latin-alphabet site, or carrying three or more links, a .ru link, a short link or an email address on your own domain. Nothing is stored and no outside service is asked. |
| `editor` | Editing theme and plugin code from the dashboard | Anyone who gets into an admin account can use it to run code on your server, and a typo there has no undo. |

Nothing is deleted. Existing comments stay in your database, hidden. Deactivate the plugin and everything is back as it was.

## Install

**As a plugin.** Download `subtract.php`, put it in `wp-content/plugins/subtract/`, then activate **Subtract** under Plugins.

**As a must-use plugin** (recommended, it cannot be switched off by accident). Put `subtract.php` in `wp-content/mu-plugins/`. Create the folder if it is not there. Must-use plugins run automatically.

Requires WordPress 6.0 and PHP 7.4 or later.

## Keep something

Name the blocks you want to keep in `wp-config.php`, above the line that says *That's all, stop editing!*:

```php
define('SUBTRACT_KEEP', 'comments, xmlrpc');
```

Keep `xmlrpc` if you use Jetpack or publish from an old desktop or mobile app. Keep `comments` if your site takes comments. Keep `users` if a tool reads your users from the REST API without logging in. Keep `forms` if your forms take messages in a script your site's language doesn't use, or links are normal in them.

## See what it does on your site

On the Plugins screen, **View details** under Subtract opens a page listing every block, whether it is removed or kept on that site, why, and how to keep any of it.

## Check it worked

Open your site, view the page source and search for `wp-emoji` or `generator`: both should be gone. Then try these, logged out:

- `https://your-site/xmlrpc.php` answers *XML-RPC is off.*
- `https://your-site/wp-json/wp/v2/users` answers *No route was found*
- `https://your-site/?author=1` takes you to the home page
- View the source of a page with a contact form and search for `subtract_t`: it should be there, just before `</form>`

## Why we made it

We are [Subtraction](https://subtraction.co), a technology and product studio in Cape Town. We remove what is not needed so that what remains works better. Most WordPress sites we look after had the same features switched on and unused, and one of them had the comment spam, and then the form spam, to show for it. This is the file we now add to every site.

Issues and pull requests are welcome. A new block should earn its place: something most sites do not use, that costs speed, security or attention, and that can be removed without breaking anything else.

## Licence

MIT. See [LICENSE](LICENSE).
