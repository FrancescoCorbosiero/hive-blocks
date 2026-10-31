# Switching the store over to Hive

From golden-hive-blocks and CommerceKit to Hive Blocks, on the live store,
by hand. The theme can stay as it is (Shoptimizer included): Hive doesn't
depend on it, so changing theme is a separate step, whenever you like.

Do it on a staging copy first, then on the store, at a quiet hour.

## Before

1. A full backup of the database (and the uploads), restorable.
2. WordPress 6.8+, WooCommerce 9.9+, PHP 8.1+.
3. Install Hive and activate it **next to** golden-hive-blocks and
   CommerceKit. Nothing changes on the pages yet: Hive only adds its own
   blocks.

## The pages

`pages/` has the store's pages made for Hive: `homepage.html`,
`chi-siamo.html` and `contatti.html`. They are golden-hive-blocks' pages
with each block as its Hive block: the same texts, links and pictures.
For each one:

1. Open the page in the editor.
2. ⋮ (top right) → **Editor di codice**.
3. Select everything, paste the file's content, **Esci dall'editor di
   codice**, **Aggiorna**.

WordPress keeps the page before as a revision.

- The size-guide pages (`guida-taglie-*`) use WordPress's own blocks: they
  stay as they are.
- Contatti's hero button wrote to a placeholder WhatsApp number
  (`39INSERISCINUMERO`): the file has the store's.
- Pictures are by URL, as on the store. Picking one again from the media
  library (the block's sidebar) gives it its smaller sizes for phones.
- The heroes have no alignment, as before. **Larghezza piena** in the block
  toolbar makes one edge to edge.

## By hand

- **Hive → Impostazioni**: the WhatsApp number, corners, the filters'
  layout, sold-out last, the product page extras, the delivery estimate
  (cutoff time, days) and, under **Velocità**, the files to preload: what
  golden-hive-blocks had in the Customizer and in Tools → GH Preload.
- **Size guides** (CommerceKit's go): Hive → Guide alle taglie, one per
  guide page (`guida-taglie-*`). Copy the page's content into a new guide,
  choose its categories and brands (Hive picks the most specific one for
  each product), publish. The product page's "Guida alle taglie" opens it.
- **Order bump** (CommerceKit's goes): Hive → Offerte al checkout, the
  product to offer and the carts' categories.
- **Drops**: a product with a release date in golden-hive-blocks gets it
  again in Product data → General (**Drop: uscita**, **Blocca fino al
  drop**).
- **People**: golden-hive-blocks' newsletter and size-alert subscribers,
  and CommerceKit's waitlist, stay in those plugins' records. Hive starts
  its own list (Hive → Iscritti).
- **Code Snippets**: look through the active snippets for golden-hive-blocks'
  functions (`ghb_…`) or CommerceKit's (`commercekit…`): they break once
  those plugins are off. A snippet that adds to WooCommerce's product lists
  (`woocommerce_…_shop_loop_item…` hooks) shows nothing on Hive's cards and
  shop pages, which don't run those hooks.
- **Additional CSS** (Customizer or Site Editor) written for
  golden-hive-blocks' `gh-` / `ghb-` classes: Hive's markup doesn't have
  them, so rewrite those rules on the `hive-` classes or drop them.

## What looks different

On purpose, so you know what to expect when you check:

- **No animation engine.** Reveal on scroll, parallax, mouse tracking,
  particles, magnetic buttons and the carousel's crossfade and slow zoom
  are gone (a motion add-on can come later). A hero's video starts once
  the page has loaded, never with reduced motion or data saving, and has
  a pause button. The trust badges' "carousel" is a band that scrolls by
  hand on phones.
- **Maps** always wait for "Mostra la mappa": no Google request, and no
  Google cookie, before.
- **Colours** come from Hive → Aspetto (accent and buttons). On dark
  surfaces (a cover hero, the carousel, the banner) the main button is
  light, so it shows. The floating WhatsApp button takes the accent.
- **Default texts** that were English or misspelled are Italian and
  correct where a block never had its own ("Autenticità certificata",
  "Autenticità"); a Legit check button pointing nowhere (`#`) isn't drawn.
- **A section around a shortcode** that prints nothing is not drawn at
  all (golden-hive-blocks drew its heading anyway).
- **Prodotto in evidenza** shows a real product, its price and its add to
  cart button, instead of a typed price and link.
- **Popup promozionale** shows once per visitor; **Acquisti recenti** a
  few times per browser session, never in the cart or the checkout.

## After

1. Deactivate **golden-hive-blocks**, **CommerceKit** and **Relevanssi
   Live Ajax Search** (Plugins), once the snippets above are seen to.
2. Hive → Impostazioni: check everything, then the modules you use.
3. Purge the page cache and the CDN.
4. Check, logged out and on a phone:
   - the home page and the pages with blocks
   - the catalog and its filters, a category, a brand, the search
   - a product: gallery, sizes, add to cart, sticky bar, size guide,
     waitlist on a sold-out size, a drop
   - cart, checkout (the offer, the bank details), an order
   - the newsletter: sign up, the confirmation email, the link
   - the Store Hub app: rails and previews
5. Filtered and sorted URLs don't change (both use WooCommerce's own
   parameters), so links and search results keep working.

## The store's other plugins

| Plugin | With Hive |
| --- | --- |
| CommerceKit | Off: Hive does the parts the store used (gallery, swatches, sticky bar, stock meter, size guides, waitlist, order bump). |
| Relevanssi | Keep: Hive's live search searches its index. |
| Relevanssi Live Ajax Search | Off: Hive's live search replaces it, on an address the CDN can cache (it asks WordPress at every keystroke, past the caches). |
| WP Super Cache | Keep, set up as below. Hive empties it when it must (a design setting, a drop out, a Store Hub edit). |
| Modern Image Formats | Keep: it makes the photos AVIF/WebP and Hive shows them as WordPress gives them. Its `<picture>` option is fine too (Hive's layout ignores the wrapper). Photos uploaded before it get modern versions when their sizes are regenerated. |
| Abandoned Cart Lite | Keep: carts go through WooCommerce's own add to cart, and the checkout stays WooCommerce's, so it sees them as before. Its reminder emails need WP-Cron (below). |
| Code Snippets | Keep; look through the snippets as above. |
| Hustle | Keep for now (the homepage embeds its form). Hive's Popup promozionale and Newsletter cover a popup with a coupon and the signup; the rest is planned (ROADMAP). |

The theme's search field can open Hive's live search: name it in a snippet,
for example `add_filter('hive_search_trigger_selector', fn() =>
'.site-search');` (the theme's own class for its search box).

### WP Super Cache and WP-Cron

Cached pages are served without WordPress, so WP-Cron, which runs when
WordPress does, runs less often: a drop's release (emptying the caches,
the size alerts), the alerts' emails, Abandoned Cart's reminders and
WooCommerce's scheduled sales wait for an uncached visit. Give the site a
real cron, every minute, and turn off the one that waits for visits:

```php
define('DISABLE_WP_CRON', true); // wp-config.php
```

```bash
* * * * * curl -s 'https://YOUR-SITE/wp-cron.php?doing_wp_cron' > /dev/null
```

A late cron never leaves a visitor on a drop's countdown: at the release
the product page reloads once, past the cache (`?hive_released=`), and
WordPress shows the product on sale. In WP Super Cache's settings:

- **Caching on**, Simple or Expert delivery; no caching for logged-in
  visitors (the default).
- Clear the cache **when a post or page is published or updated**: a
  product edited refreshes the shop's pages.
- **Cache timeout** an hour or so: stock sold through orders shows on the
  cached lists within it (WooCommerce refuses a sold-out size anyway).
- Filtered and sorted shop pages have a query (`?filter_taglia=42`): WP
  Super Cache doesn't serve those from its files, so they come from
  WordPress (Hive caches their counts) or from the CDN if it keeps them.

## Going back

Reactivate golden-hive-blocks and CommerceKit, and put each page back to
its revision from before (the page's **Revisioni**).
