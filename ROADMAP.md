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
3. **Catalog:** the shop and category pages from the plugin's own template
   (`register_block_template()` on block themes; between `get_header()` and
   `get_footer()` on classic ones). Filters as GET URLs, so every
   combination is a cacheable page. Sorting, pagination, sold-out last,
   archive hero.
4. **Product page I, replacing CommerceKit:** gallery (Product Gallery),
   size swatches with price and sale badge (Attribute Swatches), sticky
   add-to-cart bar (Sticky Add to Cart), stock meter (Stock Meter), size
   guides (Size Guides).
5. **Product page II:** waitlist per size (Waitlist), merged with the size
   alerts; drops (release date, lock, countdown, calendar); social proof
   from real orders; WhatsApp, share, delivery estimate; colorways and
   recently viewed rails.
6. **Cart and checkout:** order bump (Order Bump), the bank details drawer.
7. **Content blocks:** the other 28 blocks, merged where they overlap (the
   hero blocks into one Hero with variations, the two CTAs into one).
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
- **Checkout.** golden-hive-blocks hooks into the classic checkout. Confirm
  the store still uses it before step 6 (the Checkout block needs a
  different integration).
