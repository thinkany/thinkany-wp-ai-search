# thinkany WP AI Search

## Description

thinkany WP AI Search is a WordPress plugin that enhances the default WordPress search functionality using OpenAI's powerful language models. The plugin generates related search terms based on the user's original query, expanding search results to include more relevant content that might otherwise be missed by traditional keyword matching.

## Features

- **AI-Enhanced Search**: Uses OpenAI to generate related search terms that expand search results beyond exact matches
- **Relevance Scoring**: Prioritizes search results based on a sophisticated relevance algorithm that considers:
  - Original search term matches in titles (highest priority)
  - AI-generated related term matches
  - Content and excerpt matches
- **Multiple OpenAI Models**: Supports various OpenAI models including GPT-3.5 Turbo, GPT-4, and GPT-4 Turbo
- **Secure API Key Management**: Encrypts API keys for secure storage
- **Admin Settings Panel**: Easy-to-use admin interface for configuration
- **Search Toggle**: Enable or disable AI-enhanced search as needed
- **Caching**: Caches AI-generated terms to improve performance and reduce API calls
- **Debug Mode**: Detailed logging for troubleshooting

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- OpenAI API key
- PHP OpenSSL extension (for API key encryption)

## Installation

1. Upload the `thinkany-wp-ai-search` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > ThinkAny AI Search to configure the plugin
4. Enter your OpenAI API key and select your preferred model
5. Save changes and start enjoying enhanced search results

## Configuration Options

### API Key
Enter your OpenAI API key. The key is encrypted before being stored in the database for security.

### Model Selection
Choose from available OpenAI models:
- GPT-3.5 Turbo (faster, more economical)
- GPT-4 (more advanced, better understanding)
- GPT-4 Turbo (latest model with improved capabilities)

### Enable/Disable
Toggle AI-enhanced search on or off without deactivating the plugin.

### Cache Duration
Set how long (in hours) to cache AI-generated terms to reduce API calls and improve performance.

### Debug Mode
Enable detailed logging for troubleshooting. Logs are written to the standard PHP log.

## How It Works

1. When a user performs a search, the plugin intercepts the search query
2. If enabled, the plugin calls the OpenAI API to generate related search terms
3. The search query is expanded to include these related terms
4. Results are ordered by relevance with the original search term given highest priority
5. Both posts and pages are included in search results
6. Pagination is preserved for navigating through larger result sets

## Key Features

### Relevance Scoring Algorithm

The plugin uses a sophisticated relevance scoring algorithm that prioritizes results in the following order:

1. Exact match of original search term in title
2. Title starts with original search term
3. Original search term appears anywhere in title
4. Title ends with original search term
5. Similar prioritization for AI-generated related terms
6. Content and excerpt matches (The plugin currently does NOT accessing ACF Fields directly - it's searching the page/post content field)

This ensures the most relevant content appears at the top of search results.

### Case-Insensitive Matching

All search term matching is case-insensitive, ensuring "Search Term" will match "search term" regardless of capitalization.

### Debug Logging

When debug mode is enabled, the plugin logs detailed information about:
- Original search queries
- AI-generated related terms
- SQL query modifications
- Search result counts and titles

## Troubleshooting

### No Enhanced Results

- Verify the plugin is enabled in the settings
- Check that a valid OpenAI API key has been entered
- Ensure your OpenAI account has available credits
- Try clearing the plugin's cache by setting the cache duration to 0 temporarily

### Performance Issues

- Increase the cache duration to reduce API calls
- Consider using GPT-3.5 Turbo instead of GPT-4 for faster response times
- Disable debug mode in production environments

## Credits

Developed by thinkany llc

## License

This plugin is licensed under the GPL v2 or later.

---

For support or feature requests, please contact the plugin developer.
