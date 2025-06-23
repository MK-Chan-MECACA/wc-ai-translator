=== AI Product Title Translator – Bahasa Malaysia ===

Contributors: mk-chan\
Donate link: coff.ee/mkchan\
Tags: translation, ai, product-titles, bahasa-malaysia, ecommerce\
Requires at least: 6.0\
Tested up to: 6.7\
Requires PHP: 7.4.0\
Stable tag: 1.0.1\
License: GPLv2 or later\
License URI: https://www.gnu.org/licenses/gpl-2.0.html\
Translate WooCommerce product titles to Bahasa Malaysia using AI providers like OpenAI, Claude, Gemini, and Mesolitica.

== Description ==

AI Product Title Translator – Bahasa Malaysia is a powerful plugin that automatically translates your WooCommerce product titles to Bahasa Malaysia using advanced AI translation services. Perfect for Malaysian e-commerce stores looking to localize their product catalogs.

= Key Features =

* **Multiple AI Providers**: Support for OpenAI GPT, Anthropic Claude, Google Gemini, and Mesolitica
* **Individual Translation**: Translate single product titles from the product edit page
* **Bulk Translation**: Process multiple products at once with a beautiful progress interface
* **Real-time Progress**: Visual feedback during bulk translations with success/failure tracking
* **API Testing**: Built-in connection testing for all supported AI providers
* **User-friendly Interface**: Intuitive admin interface with clear instructions
* **Error Handling**: Comprehensive error handling and user feedback
* **Mobile Responsive**: Works perfectly on all devices

= Supported AI Providers =

* **OpenAI**: Using the powerful gpt-4o-mini model
* **Anthropic Claude**: Using claude-3-haiku-20240307 model
* **Google Gemini**: Using the gemini-pro model
* **Mesolitica**: Specialized for Malay language translation

= How It Works =

1. Configure your preferred AI provider and API key
2. Test the connection to ensure everything works
3. Translate individual products or use bulk translation
4. Monitor progress with real-time feedback
5. Review results and enjoy your localized product titles

= Perfect For =

* Malaysian e-commerce stores
* International retailers targeting Malaysia
* Multilingual WooCommerce sites
* Store owners who want to localize their product catalogs

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wc-ai-translator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to WooCommerce > AI Title Translator to configure your settings.
4. Select your preferred AI provider and enter your API key.
5. Test the connection to ensure everything is working properly.
6. Start translating your product titles!

= Manual Installation =

1. Download the plugin zip file
2. Extract the files to your `/wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin panel
4. Configure your AI provider settings

== Frequently Asked Questions ==

= What AI providers are supported? =

The plugin supports OpenAI (GPT), Anthropic Claude, Google Gemini, and Mesolitica. Each provider offers different strengths, with Mesolitica being specifically optimized for Malay language translation.

= Do I need an API key? =

Yes, you'll need an API key from your chosen AI provider. The plugin includes instructions on how to obtain these keys.

= Can I translate products in bulk? =

Absolutely! The plugin includes a powerful bulk translation feature that processes products in batches with real-time progress tracking.

= What happens if a translation fails? =

The plugin includes comprehensive error handling. Failed translations are tracked and reported, allowing you to retry or manually review problematic products.

= Is there a limit to how many products I can translate? =

The limit depends on your AI provider's API limits and pricing. The plugin processes translations in batches to respect rate limits and prevent timeouts.

= Can I undo translations? =

Currently, translations directly modify the product titles. We recommend backing up your database before performing bulk translations.

= Which AI provider gives the best results for Bahasa Malaysia? =

Mesolitica is specifically designed for Malay language and may provide more culturally appropriate translations. However, other providers like OpenAI and Claude also produce excellent results.

= Does this work with variable products? =

Yes, the plugin works with all WooCommerce product types including simple, variable, grouped, and external products.

== Screenshots ==

1. Settings page with AI provider configuration
2. Individual product translation button on edit page
3. Bulk translation progress interface
4. Product list with translate buttons
5. Success notification after bulk translation

== Changelog ==

= 1.0.0 =
* Initial release
* Support for OpenAI, Claude, Gemini, and Mesolitica
* Individual and bulk translation features
* Real-time progress tracking
* API connection testing
* Comprehensive error handling
* Mobile-responsive interface

== Upgrade Notice ==

= 1.0.0 =
Initial release of WooCommerce AI Product Title Translator. Get started with AI-powered product title translation today!

== Developer Information ==

= Hooks and Filters =

The plugin provides several hooks for developers:

* `wcait_before_translation` - Action fired before translation
* `wcait_after_translation` - Action fired after translation
* `wcait_translation_result` - Filter to modify translation results
* `wcait_supported_providers` - Filter to add custom AI providers

= API Integration =

The plugin follows WordPress coding standards and includes:

* Proper data sanitization and validation
* Nonce verification for all AJAX requests
* Capability checks for admin functions
* Internationalization support
* Error logging and debugging options

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 3.0 or higher
* PHP 7.4 or higher
* cURL extension enabled
* SSL certificate (required for API calls)

== Privacy and Data ==

This plugin sends product titles to third-party AI services for translation. Please review the privacy policies of your chosen AI provider:

* OpenAI Privacy Policy: https://openai.com/privacy/
* Anthropic Privacy Policy: https://www.anthropic.com/privacy
* Google Privacy Policy: https://policies.google.com/privacy
* Mesolitica Privacy Policy: https://mesolitica.com/privacy/

No personal customer data is transmitted, only product titles for translation purposes.

== Support ==

For support, feature requests, or bug reports, please visit our support forum or contact us through our website.

= Contributing =

This plugin is open source. Contributions are welcome on our GitHub repository: https://github.com/MK-Chan-MECACA/wc-ai-translator
