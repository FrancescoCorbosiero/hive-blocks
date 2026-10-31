# Roadmap

Hive Blocks takes over from golden-hive-blocks step by step. Each step is a
PR that can be tried on staging. Nothing changes on the store until the
switch-over (step 9).

## Decisions

- **Block names:** `hive/*`. The store's pages keep their `golden-hive/*`
  blocks until the migration in step 9 rewrites them.
- **One store:** Italian strings in the code, no translation layer.
- **Floors:** PHP 8.1, WordPress 6.8, WooCommerce 9.9.
- **CommerceKit goes, feature by feature.** Hive replaces the parts the
  store uses: Attribute Swatches, Order Bump, Product Gallery, Size Guides,
  Sticky Add to Cart, Stock Meter, Waitlist.
- **No animation engine in the base plugin.** A motion add-on can come
  later and enhance the same markup, so the fast version never pays for it.

## Steps

1. **Skeleton** (0.1.0, done): plugin core, settings (Hive → Aspetto),
   design tokens, per-block asset loading, build, tests, CI on a block theme
   and a classic one, and the Orari block as the pilot.
2. **Product card and its actions** (0.2.0, done): one card for every
   product list, WooCommerce's lists included (module Schede prodotto);
   add to cart on the card; Quick Add and Quick View, loaded on first use,
   fed by a cacheable REST read; the product rail block with the Store Hub's
   pin / exclude / fallback; size matching ("Taglia 42 · 180 €"); settings
   for the button colour, the size attribute and the WhatsApp number.
3. **Catalog** (0.3.0, done): the shop and its category, brand, tag and
   attribute pages drawn by Hive (module Pagine del negozio): between
   `get_header()` and `get_footer()` on classic themes, between the theme's
   header and footer parts on block themes. Filters as links to GET URLs,
   so every combination is a cacheable page, swapped in place without
   reloading; sizes matched on stock; counts cached per catalog generation.
   Sorting, pagination, sold-out last, category pages following their
   homepage rail, archive hero.
4. **Product page I, replacing CommerceKit** (0.4.0, done): the product
   page drawn by Hive (module Pagina prodotto), as the shop's pages are;
   gallery (Product Gallery) with CSS swiping, thumbnails or a grid, full
   screen with zoom; size buttons with price and sale mark over
   WooCommerce's own variation form (Attribute Swatches); sticky add-to-cart
   bar (Sticky Add to Cart); stock meter (Stock Meter); size guides by
   category, brand or product (Size Guides); add to cart without leaving the
   page, through WooCommerce's own form handler. WooCommerce's product hooks
   still fire, without the theme's callbacks.
5. **Product page II** (0.5.0, done): waitlist per size (Waitlist) and the
   size alerts as one list of people with double opt-in (module Avvisi e
   lista d'attesa); drops with release date, lock, countdown and calendar
   (module Drop); "Venduto N volte" and the social proof feed from real
   orders; WhatsApp, share, delivery estimate; the band under the product;
   colorways and recently viewed rails.
6. **Cart and checkout** (0.6.0, done): order bumps (Order Bump) above the
   payment methods, refreshed with WooCommerce's own order review; the bank
   details in a dialog with one-tap copy. Classic checkout first (as on the
   store); on the Checkout block the bump sits above it and adds through
   WooCommerce's cart store.
7. **Content blocks** (0.7.0, done): 27 of the other 28 blocks (the
   newsletter is step 8's) as 23: the four heroes into one Hero
   (Copertina, Diviso, Chi siamo), the two calls to action into one
   (Banner, Immagine e testo), the rest one to one. Maps,
   videos and third parties wait for the visitor; the FAQ's structured
   data comes from the server; the countdowns and "Acquisti recenti" are
   worked out in the browser, so cached pages stay true.
8. **Store Hub bridge, search, newsletter, preload:** the `wc-gh/v1` API
   unchanged at first; live search on its own endpoint (Relevanssi when
   present, without its Live Ajax Search add-on); newsletter; preload and
   speculation rules.
9. **Migration and switch-over:** a one-time tool, dry run first, that
   rewrites the `golden-hive/*` blocks and their attributes, moves the
   `ghb_*` options, the `_ghb_*` product meta and the `ghb_subscriber`
   posts, and imports CommerceKit's waitlist, size guides and order bumps.
   On staging: install, migrate, deactivate golden-hive-blocks and
   CommerceKit, check, then the same on the store.

## Public reads move off admin-ajax.php

golden-hive-blocks serves these through `admin-ajax.php`, which page caches
and CDNs pass through to PHP: Quick View, variations, filters, size rail,
recently viewed, social proof, the drop's .ics file, the search warm-up. In
Hive each becomes a REST GET with `Cache-Control: public, s-maxage,
stale-while-revalidate` and a generation number in the URL that changes
when products or orders do, so the CDN answers and a purge is just a new
number.

## Open questions

- **Store Hub app.** It reads block names in the `wc-gh/v1` payloads.
  After the migration, either the bridge keeps reporting the
  `golden-hive/*` names or the app learns the `hive/*` ones.
- **CommerceKit's data.** Where it keeps waitlist entries, size guides and
  order bump offers, to import them in step 9.
- **Checkout.** Hive's checkout parts are built for the classic checkout,
  as golden-hive-blocks', with a lighter path for the Checkout block.
  Confirm which one the store uses before the switch-over.
