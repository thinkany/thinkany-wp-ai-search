=== thinkany WP AI Search ===
Contributors: thinkany
Tags: search, ai, openai, gpt, artificial intelligence, search enhancement
Requires at least: 6.0
Tested up to: 6.5 < 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enhances WordPress search using OpenAI to provide more relevant search results.

== Description ==

thinkany WP AI Search is a WordPress plugin that enhances the default WordPress search functionality using OpenAI's powerful language models. The plugin generates related search terms based on the user's original query, expanding search results to include more relevant content that might otherwise be missed by traditional keyword matching.

= Features =

* **AI-Enhanced Search**: Uses OpenAI to generate related search terms that expand search results beyond exact matches
* **Relevance Scoring**: Prioritizes search results based on a sophisticated relevance algorithm
* **Multiple OpenAI Models**: Supports various OpenAI models including GPT-3.5 Turbo, GPT-4, and GPT-4 Turbo
* **Secure API Key Management**: Encrypts API keys for secure storage
* **Admin Settings Panel**: Easy-to-use admin interface for configuration
* **Search Toggle**: Enable or disable AI-enhanced search as needed
* **Caching**: Caches AI-generated terms to improve performance and reduce API calls
* **Debug Mode**: Detailed logging for troubleshooting
* **Token Info Logging**: Track API usage and token consumption separately from general debugging
* **Admin-Safe**: Only enhances frontend searches, leaving admin search functionality untouched

= How It Works =

1. When a user performs a search, the plugin intercepts the search query
2. If enabled, the plugin calls the OpenAI API to generate related search terms
3. The search query is expanded to include these related terms
4. Results are ordered by relevance with the original search term given highest priority
5. Both posts and pages are included in search results
6. Pagination is preserved for navigating through larger result sets

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher
* OpenAI API key
* PHP OpenSSL extension (for API key encryption)

== Installation ==

1. Upload the `thinkany-wp-ai-search` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > ThinkAny WP AI Search to configure the plugin
4. Enter your OpenAI API key and select your preferred model
5. Save changes and start enjoying enhanced search results

== Frequently Asked Questions ==

= Do I need an OpenAI API key? =

Yes, you need an OpenAI API key to use this plugin. You can get one by signing up at [OpenAI's website](https://platform.openai.com/).

= Is my API key secure? =

Yes, your API key is encrypted before being stored in the WordPress database using WordPress's built-in encryption functions.

= Which OpenAI model should I use? =

For most websites, GPT-3.5 Turbo provides a good balance of performance and cost. If you need more advanced understanding of search queries, GPT-4 or GPT-4 Turbo may provide better results but at a higher cost.

= Will this plugin increase my OpenAI API costs? =

The plugin uses caching to minimize API calls. You can adjust the cache duration in the settings to balance between freshness of results and API usage. You can also enable Token Info Logging to monitor your API usage.

= Can I disable the AI search temporarily? =

Yes, you can toggle the AI search on or off in the plugin settings without deactivating the plugin.

= Does this plugin affect WordPress admin searches? =

No, the plugin only enhances frontend searches. Admin searches remain untouched to ensure optimal performance and compatibility with WordPress core functionality.

== Screenshots ==

1. Admin settings page
2. AI Enhanced search results
3. Search results without API enabled

== Changelog ==

= 1.0.1 =
* Added a Token Info Logging option to separately track API usage
* Improved admin interface with toggle switches for settings
* Added safeguard to prevent the plugin from affecting admin searches

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.1 =
This update adds Token Info Logging, improves the admin interface, and ensures the plugin doesn't affect admin searches.

= 1.0.0 =
Initial release

== Privacy Policy ==

This plugin connects to the OpenAI API to enhance search functionality. No personal data from your users is sent to OpenAI - only the search terms they enter. The plugin caches search results to improve performance, which are stored in your WordPress database as transients. Please ensure your site's privacy policy reflects this data usage if required by applicable regulations.
