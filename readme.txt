=== Simple Popular Posts for GA4 ===
Contributors: tonogata
Tags: popular posts, google analytics, ga4, ranking, widget
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple popular posts widget that retrieves ranking data from Google Analytics 4 (GA4).

== Description ==

**Simple Popular Posts for GA4** is a lightweight WordPress plugin that generates a "Popular Posts" ranking based on actual page views from **Google Analytics 4 (GA4)**.

This plugin fetches aggregated ranking data directly from the GA4 API. This approach minimizes database usage by avoiding the need to store individual page views, ensuring high stability and performance regardless of traffic volume.

**Features**

*   **GA4 API Integration**: Generates rankings based on accurate page view data.
*   **Low Server Load**: Fetches data only once a day (or specified interval) from Google, ensuring zero impact on site performance during page views.
*   **Google Cloud Setup Required**: Users must provide their own Google Cloud Platform Service Account JSON key to verify ownership and access GA4 data.
*   **Versatile Display Options**:
    *   **Widget**: Drag and drop into your sidebar.
    *   **Shortcode**: Embed anywhere using `[spp_ga4_ranking]`.
    *   **Gutenberg Block**: Place and configure intuitively with a dedicated block.
*   **Advanced Filtering**:
    *   Time Range (7 days / 30 days)
    *   Filter by Category or Tag ID
    *   Exclude specific Post IDs (Max 100)
    *   Filter by Publish Date (e.g., posts published within the last year)
*   **Performance**:
    *   Fetches data daily via Cron and caches it in the database.
    *   Uses Transient cache for rendering to ensure fast page loads.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/simple-popular-posts-for-ga4` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Use the **Settings->Simple Popular Posts** screen to configure the Google Cloud Platform Service Account JSON.
4.  (Optional) Setup the manual fetch to verify connection.
5.  (Optional) For enhanced security, define a custom secret key in `wp-config.php`.

== Security Configuration (Optional) ==

To further harden the encryption of your JSON key, you can define a custom secret key in your `wp-config.php` file.
This adds an extra layer of security known only to you (the administrator) and not present in the plugin's source code.

`define( 'SPP_GA4_CUSTOM_KEY', 'your-very-long-random-secret-string' );`

**Note:** The plugin encrypts your JSON key using your WordPress salt keys (`wp_salt()`). If you migrate your site, change your `wp-config.php` salt keys, or change the `SPP_GA4_CUSTOM_KEY` constant, the stored key can no longer be decrypted. In such cases, you **must re-save** the JSON key in the plugin settings to generate a new valid encryption.

== Configuration ==

**1. Google Cloud Platform (GCP) Setup**

This plugin uses a GCP "Service Account" to communicate directly with Google servers.
If you are new to GCP, follow these steps to obtain the configuration file (JSON key).

1.  **Prepare a Project**: Select "New Project" in GCP Console.
2.  **Enable API**: Search for "**Google Analytics Data API**" in Library and enable it.
3.  **Create Service Account**: Go to IAM & Admin > Service Accounts > Create.
4.  **Issue JSON Key**: Go to Keys tab of the service account > Add Key > Create new key > JSON.
5.  **Add User to GA4**: Copy the service account email, go to GA4 Admin > Property Access Management, and add it as "Viewer".

**2. Plugin Settings**

1.  In WordPress Admin, go to **Settings** > **Simple Popular Posts**.
2.  **Service Account JSON Key**: Paste the entire content of the downloaded JSON file.
3.  **GA4 Property ID**: Enter the "Property ID" (numbers).
4.  **Auto-Fetch Schedule**: Set the time for data fetching.

== Usage ==

**Widget**
Go to **Appearance** > **Widgets** and add the "Simple Popular Posts (GA4)" widget.

**Shortcode**
Insert the following shortcode into any post or page:

`[spp_ga4_ranking limit="5" range="30d" show_thumb="1" cat_id=""]`

**Parameters:**
*   `limit`: Number of posts to display (Default: 5)
*   `range`: Time range (`7d` or `30d`)
*   `show_thumb`: Display thumbnail (`1` or `0`)
*   `show_date`: Display date (`1` or `0`)
*   `cat_id`: Filter by Category ID (Includes sub-categories. Single ID only)
*   `tag_id`: Filter by Tag ID (Single ID only)
*   `exclude_ids`: Exclude specific Post IDs (e.g. `123,456`)
*   `filter_days`: Filter by publish date in days (e.g. `365` for within 1 year. 0 to disable)
*   `title_tag`: HTML tag for the title (e.g. `h2`, `h3`, `h4`, `span`, `none`). Default: `h2`

**Block Editor**
Search for the "Simple Popular Posts (GA4)" block in the editor and place it. You can adjust various options from the sidebar settings panel.

== Frequently Asked Questions ==

= Does it support sub-categories? =
Yes, specifying a Category ID automatically includes posts from its sub-categories.

= Can I specify multiple Category IDs? =
No, currently only a single Category ID is supported per widget/block.

= What happens if I set both Category ID and Tag ID? =
If both are set, the logic is an **AND** condition (Intersection). Only posts that match *both* the Category ID and Tag ID will be displayed. If either ID is left empty, that specific filter is ignored (targeting all categories/tags).

= How can I customize the design? =
You can disable the default CSS in the plugin settings ("Disable Default CSS"). Then, you can write your own CSS in your theme's `style.css` or Customizer targeting the `.ga4-rank-list` class.

= Is the Google Cloud Platform (GCP) free? =
The Google Analytics Data API has a generous free quota which is usually sufficient for most sites. However, please check the official GCP documentation for the latest pricing and quota limits as they are subject to change by Google.

= What is SPP_GA4_CUSTOM_KEY? =
It is an optional security constant you can define in your `wp-config.php` file. By setting a unique secret string, you add an extra layer of encryption to your stored JSON key, making it unique to your server environment.


== Changelog ==

= 0.1.2 =
* Refactored code structure and updated translation mechanisms.
* Added missing Japanese translations and improved English descriptions.
* Embedded security notifications and optimized uninstall routine.

= 0.1.0 =
Initial public release.

== Screenshots ==

1.  **Widget Settings**: The widget configuration panel allows you to set the title, limit, range, and design presets.
2.  **Plugin Settings**: Configure your Google Cloud Service Account JSON and GA4 Property ID here.
3.  **Front-end Display**: An example of how the popular posts list looks on your site (List Style).

