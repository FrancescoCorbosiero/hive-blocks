# Switching the store over to Hive

From golden-hive-blocks and CommerceKit to Hive Blocks, on the live store.
The theme can stay as it is (Shoptimizer included): Hive doesn't depend on
it, so changing theme is a separate step, whenever you like.

Do it on a staging copy first, then on the store, at a quiet hour.

## Before

1. A full backup of the database (and the uploads), restorable.
2. WordPress 6.8+, WooCommerce 9.9+, PHP 8.1+.
3. Install Hive and activate it **next to** golden-hive-blocks and
   CommerceKit. Until you migrate, the pages still use the old blocks: Hive
   only adds its own.

## The migration

**Hive → Migrazione**, or from the server:

```bash
wp hive migrate --dry-run
```

```bash
wp hive migrate
```

The dry run (**Prova**) changes nothing and lists what would change. Read
it, then **Migra**. You can run it again: what's done stays as it is.

| What | Becomes |
| --- | --- |
| `golden-hive/*` blocks in pages, posts, templates, template parts, synced patterns, block widgets | the matching `hive/*` blocks, attributes carried over (each page keeps a revision of before) |
| `[gh_product_rail]`, `[carousel_section]`, `[product_carousel]`, `[bestsellers]`, `[new_arrivals]`, `[on_sale]`, `[featured_products]` | `[hive_product_rail]` with the same choice of products |
| `[gh_size_rail]`, `[gh_bank_details]` | `[hive_size_rail]`, `[hive_bank_details]` |
| `[gh_filters]`, `[bfl_filters]` | removed: the shop's pages have their filters (Hive → Impostazioni → Negozio) |
| Customizer and GH Preload settings (WhatsApp number, shape, filters layout, sold out last, product page extras, delivery estimate, preload) | Hive → Impostazioni, for settings you haven't already changed there |
| Drops (`_ghb_drop_*`) | Hive's drops, release scheduled |
| Newsletter and size-alert subscribers | Hive → Iscritti, with their confirmations, sizes and brands; links in emails already sent keep working |
| CommerceKit waitlist | Hive's waitlist, still active (see below) |
| CommerceKit size guides | drafts in Hive → Guide alle taglie |
| CommerceKit order bumps | drafts in Hive → Offerte al checkout |

What's left to do by hand is in the list: a block with no Hive
counterpart, a table it couldn't read, additional CSS (Customizer or Site
Editor) written for golden-hive-blocks' `gh-` / `ghb-` classes, which
Hive's markup doesn't have.

### CommerceKit

Its data isn't documented, so the migration looks at what's in the
database and the dry run says what it found: tables, settings, product
data. It brings over:

- **Waitlist**: each request not answered yet, as a Hive wait on the same
  product and size. These people asked to be told, so their waits are
  active without a confirmation email, as with CommerceKit. Addresses that
  switched Hive's alerts off are left alone.
- **Size guides**: as drafts. Give each the categories and brands it is for
  (Hive picks the most specific guide), then publish.
- **Order bumps**: as drafts. Choose the carts' categories, then publish.

Swatches, the gallery, the sticky bar and the stock meter are settings in
Hive (Hive → Impostazioni → Pagina prodotto). Nothing to move.

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
  Heroes and calls to action keep exactly the texts they showed.
- **A section around a shortcode** that prints nothing is not drawn at
  all (golden-hive-blocks drew its heading anyway).
- **Prodotto in evidenza** shows a real product, its price and its add to
  cart button, instead of a typed price and link: the migration finds the
  product from the old link or the exact name, or tells you to pick it.
- **Slider categorie** shows the store's categories, each with its own
  picture (WooCommerce → Prodotti → Categorie → Miniatura), or a product's
  photo; the migration matches the typed list to them and names what it
  couldn't match. **Brand in scorrimento** takes the logos from the media
  library.
- **Popup promozionale** shows once per visitor; **Acquisti recenti** a
  few times per browser session, never in the cart or the checkout.

## After

1. Deactivate **golden-hive-blocks** and **CommerceKit** (Plugins).
2. Hive → Impostazioni: check everything, then the modules you use.
3. Hive → Guide alle taglie and Hive → Offerte al checkout: publish the
   imported drafts.
4. Purge the page cache and the CDN.
5. Check, logged out and on a phone:
   - the home page and the pages with blocks
   - the catalog and its filters, a category, a brand, the search
   - a product: gallery, sizes, add to cart, sticky bar, size guide,
     waitlist on a sold-out size, a drop
   - cart, checkout (the offer, the bank details), an order
   - the newsletter: sign up, the confirmation email, the link
   - the Store Hub app: rails and previews
6. Filtered and sorted URLs don't change (both use WooCommerce's own
   parameters), so links and search results keep working.

## Going back

Reactivate golden-hive-blocks and CommerceKit. Pages can go back to their
revision from before the migration, but subscribers and drops have moved
to Hive's records: for a full return, restore the backup.
