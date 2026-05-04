=== Yoast Schema Override ===
Contributors: rpeacan
Requires at least: 6.5
Requires Plugins:  wordpress-seo
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Override the Yoast SEO schema output on a per-page or per-post basis using a guided field builder or raw JSON.

== Description ==

Yoast Schema Override lets you replace the schema.org data that Yoast SEO automatically generates for any individual page or post — without touching any code.

**Two modes:**

* **Simple Builder** — Fill in individual fields (name, description, image, dates, author, etc.) using a clean form. Fields are pre-populated with sensible defaults pulled from the post itself, so you only need to change what's different.
* **Advanced (Paste JSON)** — Paste a raw schema JSON object for full control.

**How it works:**

The plugin intercepts Yoast's `wpseo_schema_graph` filter and swaps the primary content node (WebPage, Article, etc.) with your custom data. All other nodes Yoast outputs — BreadcrumbList, Organization, WebSite — are left intact. The original node's `@id` is preserved so Yoast's internal graph links continue to work correctly.

**Supported post types:** Pages and Posts.
Custom post types are not yet supported in simple mode; advanced (JSON) mode works on any singular post type if you add the toggle manually.

== Requirements ==

* WordPress 6.5 or higher
* PHP 7.4 or higher
* [Yoast SEO](https://yoast.com/wordpress/plugins/seo/) (free or premium), version 14.0 or higher

== Installation ==

1. Upload the `yoast-schema-override` folder to `/wp-content/plugins/`.
2. Make sure Yoast SEO is installed and activated.
3. Activate **Yoast Schema Override** from the Plugins screen.

== Usage ==

1. Open any Page or Post in the WordPress editor.
2. Scroll down to the **Schema Override** metabox (below the editor).
3. Check **"Override the schema data for this page"**.
4. Choose a mode:
   - **Simple Builder** — fill in the fields presented. Anything left blank uses the default value shown in the field description.
   - **Advanced: Paste JSON** — paste a valid schema JSON object (no `<script>` tag, no `@context`).
5. Save the post.

The next time the page is visited, the custom schema will appear in the `<script type="application/ld+json">` block in place of Yoast's auto-generated data.

== Simple Mode Fields ==

**Pages (WebPage)**

* Page Type — WebPage, AboutPage, ContactPage, FAQPage, or CollectionPage
* Page Name
* Description
* Page URL
* Primary Image
* Date Published
* Date Modified
* Language

**Posts (Article)**

* Article Type — Article, BlogPosting, or NewsArticle
* Headline
* Description
* Article URL
* Primary Image
* Date Published
* Date Modified
* Author Name
* Author URL
* Language
* Keywords

== Frequently Asked Questions ==

= Does this replace ALL of Yoast's schema output? =

No — only the primary content node (WebPage or Article). Yoast's other nodes such as BreadcrumbList, Organization, and WebSite are left exactly as Yoast generates them.

= What happens if I leave a Simple Builder field blank? =

The plugin falls back to the post's own data — post title, permalink, featured image, published date, etc. You only need to fill in fields where the default is incorrect.

= Does this work with Yoast Premium? =

Yes. The plugin uses the same `wpseo_schema_graph` filter available in both free and premium versions.

= Is the JSON validated before it's saved? =

Yes. The plugin decodes the JSON on save and rejects it if the syntax is invalid, preserving the previously saved value. The metabox also provides live feedback in the browser before you save.

= Can I use this on custom post types? =

The Simple Builder metabox is currently shown only on Pages and Posts. Advanced (JSON) mode will work on any singular post type if you use the filter hook `yso_supported_post_types` to extend support (developer feature).

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
