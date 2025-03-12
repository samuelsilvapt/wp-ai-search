=== AI Search ===
Contributors: samuelsilvapt  
Tags: search, AI, OpenAI, WordPress  
Tested up to: 6.7 
Stable tag: 1.4
Requires PHP: 8.0 
License: GPLv2
Replaces the default search with an intelligent search system.
---

== Description ==

AI Search for WordPress enhances the search experience by:
- Replacing the default WordPress search with an AI-powered intelligent search system.
- Generating embeddings for posts using OpenAI’s `text-embedding-ada-002` model.
- Contextualizing search queries for better relevance and precision.
- Ranking posts based on similarity using cosine similarity metrics.


## Features

- **Intelligent Search**: Enhances default search functionality with AI-powered context inference.
- **Embeddings Table**: Stores AI-generated embeddings for all posts in the database.
- **Admin Settings**: Easily configure the plugin via the WordPress admin panel.
- **Seamless Integration**: Automatically updates embeddings when posts are saved or updated.
- Cached results for better performance

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > AI Search** to configure your OpenAI API key.
4. Enjoy smarter search functionality on your site!


## Usage

- Simply use the default WordPress search, and AI Search will enhance it.
- Manage your OpenAI API key and plugin settings via the **Settings > AI Search** menu.


== Frequently Asked Questions ==

### What is required to use AI Search for WordPress?
You need an OpenAI API key to use this plugin. You can get one from the [OpenAI website](https://openai.com/).

### How does this plugin work?
The plugin uses OpenAI’s `text-embedding-ada-002` model to generate embeddings for your posts. When users search, it matches the query embedding with post embeddings in the database for more relevant results.

### What happens if the OpenAI API fails?
The plugin handles API errors by logging them and reverting to the default search query.

== External Service ==

This plugin connects with Completions API from Open AI to get the embedding of the products. It also connects to the Embeddings endpoint.

Please read more here: 
https://platform.openai.com/docs/guides/completions
https://platform.openai.com/docs/guides/embeddings

== Changelog ==

= 1.4 = 
- **Settings UI Revamp**: Introduced **tabbed navigation** to separate "General Settings" and "Generate Embeddings" for better organization.
- **Batch Embedding Generation**: Users can now trigger embedding generation **for up to 50 posts** at a time.
- **Custom Post Type Selection**: A dropdown was added, allowing users to **choose which CPT** they want to generate embeddings for.
- **Embedding Optimization**: Now only processes posts that **do not yet have embeddings** to **avoid redundant API calls** and **improve performance**.
- **Security Enhancements**: Added **nonce verification** to protect settings updates.

This update significantly **improves usability**, **optimizes performance**, and **gives users more control** over AI-powered search embeddings. 

= 1.3 = 
- **Similarity Threshold Control**: Added a **range input field** (0-1) in settings to allow users to adjust the similarity threshold dynamically.

= 1.2 =
- Cache Search embeddings, saved in transients for one day

= 1.1 = 
- text-davinci-003 (deprecated) has been replaced bytext-embedding-ada-002 model
- db manipulation has been replaced by custom_post_meta approach

= 1.0 =
- Initial release, adding the settings page and the search filter.
