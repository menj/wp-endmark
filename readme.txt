=== Endmark ===
Contributors: menj, colintemple
Tags: endmark, end mark, typography, article, symbol
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 4.2
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Adds a professional typographic endmark symbol to the end of your posts and pages.

== Description ==

Endmark adds a trailing symbol to the end of your posts and pages, providing that professional magazine-style finish to your content.

**Features:**

* Choose between a text symbol or custom image
* **Media Library integration** - upload images directly from WordPress
* Modern, intuitive settings interface with live preview
* Quick symbol picker with popular endmark characters
* Works with posts, pages, or both
* **Multiple placement modes** - last paragraph, before/after footnotes, append, or CSS selector
* **Exclusion rules** - by category, tag, or specific post IDs
* **Minimum word count** - only show on longer articles
* **Mobile and AMP support** - hide on mobile or disable for AMP pages
* **Manual placement** - use shortcode `[endmark]` or Gutenberg block
* **CSS variable support** - easy theme customization
* Compatible with popular footnote plugins
* Lightweight with minimal performance impact
* Fully translatable
* Clean uninstall

**Default Symbols:**

* ∎ (end of proof / tombstone)
* ◆ (black diamond)
* ❧ (rotated floral heart / hedera)
* ■ (black square)
* ● (black circle)
* ※ (reference mark)
* ❖ (black diamond minus white X)
* ◾ (black medium-small square)
* ▪ (black small square)
* Or any custom symbol or image

**Placement Modes:**

* **Last Paragraph** - Inserts at the end of the last paragraph (default)
* **Before Footnotes** - Inserts just before any detected footnotes
* **After Footnotes** - Appends after all content including footnotes
* **Append** - Simply appends to end of content
* **CSS Selector** - Inserts before a specified class or ID

== Installation ==

1. Upload the `endmark` folder to `/wp-content/plugins/`
2. Activate the plugin through 'Plugins' menu
3. Go to Settings → Endmark to configure

== Frequently Asked Questions ==

= Can I use an image instead of a symbol? =

Yes! Select "Image" as the endmark type, then click "Upload Image" to select from your Media Library.

= Where does the endmark appear? =

By default, at the end of the last paragraph. If using footnotes, it appears just before them. You can change this behavior in the Placement Mode settings.

= Can I manually place the endmark? =

Yes! Use the `[endmark]` shortcode or the Endmark Gutenberg block. When manually placed, automatic insertion is disabled for that post.

= Can I exclude certain posts or categories? =

Yes! In the Advanced Options, you can exclude specific categories, tags, or post IDs. You can also set a minimum word count.

= Does it work with footnote plugins? =

Yes! Endmark automatically detects footnotes from popular plugins and places the endmark before them (configurable).

= How do I customize the styling? =

If "Use CSS variables" is enabled, you can override the margins in your theme CSS:
`
:root {
    --endmark-margin-top: 0;
    --endmark-margin-left: 0.5em;
}
`

== Changelog ==

= 4.2 =
* Refactored admin CSS and JavaScript into separate asset files
* Improved code organization and maintainability
* Better browser caching for admin assets
* Modular JavaScript architecture with cleaner function structure
* Reduced main plugin file size by ~35%

= 4.1 =
* Redesigned settings page with modern tabbed interface
* New dark header design with gradient accents
* Added version badge and master toggle in header
* Reorganized settings into Appearance, Placement, Exclusions, and Advanced tabs
* Improved UI with smoother animations and transitions
* Updated typography using DM Sans font
* Enhanced form controls with better visual feedback
* Improved toggle switches and type selection cards
* Added info boxes with better visual styling
* Refined preview section layout
* Better responsive design for all screen sizes

= 4.0 =
* Complete rewrite with expanded feature set
* Added multiple placement modes (last paragraph, before/after footnotes, append, CSS selector)
* Added exclusion rules by category, tag, and post ID
* Added minimum word count setting
* Added mobile hide and AMP disable options
* Added shortcode `[endmark]` for manual placement
* Added Gutenberg block for manual placement
* Added CSS variable support for theme customization
* Added automatic duplicate detection and prevention
* Added hygiene prepass (cleans up trailing whitespace, empty paragraphs, repeated br tags)
* Added unsafe container detection (avoids inserting inside tables, lists, blockquotes, etc.)
* Improved footnote detection patterns
* Improved settings page UI with collapsible advanced options
* Full backward compatibility with v3.x settings

= 3.1 =
* Added WordPress Media Library integration
* Redesigned settings page with modern UI
* Added quick symbol picker
* Added live preview

= 3.0 =
* Complete code rewrite
* Added Settings API integration
* Added security improvements
* Added internationalization support

== Upgrade Notice ==

= 4.0 =
Major feature release with new placement modes, exclusion rules, and manual placement options. Your existing settings will be automatically migrated.

== Credits ==

Originally by Colin Temple. Maintained by MENJ.
