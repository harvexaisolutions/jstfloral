=== Fleuriste ===
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
License: GPLv2 or later

A block theme for flower shops, built for WooCommerce.

== Occasions ==

Occasions are product tags: Birthday, Anniversary & Love, Sympathy and Get Well Soon (created
automatically). Add them to a product in Products → Edit → Product tags ("Choose from the most used
tags"); a product can have several. The "Shop by occasion" tiles on the home page open each tag's
page, listing every product with that occasion. Funeral products are tagged Sympathy.

== Contact page ==

Pages → Contact us shows the shop's contact details and a contact form. The details come from
Shop settings → Shop details (address, hours, phone, WhatsApp, email);
messages from the form are emailed to the company email. Shortcodes: [fleuriste_contact_details],
[fleuriste_contact_form].

== About us page ==

Pages → About us has starter text and photos (from your featured products) – replace them with the
shop's own story. The same layout is available as the "About us page" pattern in the block inserter.

== Logo ==

Set the logo in Appearance → Editor → Site logo (or Customizer → Site Identity). It replaces the
shop name in the header and, on a light badge, in the footer. Remove it to show the name as text again.

== Start here (admin manual) ==

The first menu item, "Start here", is a short guide: everyday tasks, "How do I…" answers and
what each menu is for, with a button to each page. Staff land on it after logging in.

== Where to set what ==

Shop settings → Shop details is the one place for the shop's name, address, phone, WhatsApp,
email, opening hours, logo and legal details (BRN, VAT). The header, footer, Contact page,
invoices and receipts, the pickup address, order emails and WooCommerce's store address all use it.
* Shop settings → Delivery & pickup: regions and fees, delivery/pickup days and times, pickup address
  (same as the shop by default). WooCommerce's Shipping tab doesn't need changing.
* Shop settings → Website: texts, photos and sections of the home page, product page and footer.
* WooCommerce → Settings: only for advanced options (currency, taxes, email sender address…).

== Getting started ==

1. Appearance → Themes → activate Fleuriste.
2. Settings → Permalinks → choose "Post name" and save (the menu links use /shop/ and /product-category/... addresses).
3. Create product categories with these slugs so the homepage tiles link correctly:
   bouquets, roses, plants, seasonal, luxury, sympathy, gifts,
   birthday, anniversary, get-well
4. Create pages called Contact, About, Delivery and Flower Care (slugs contact, about, delivery, flower-care).

== Admin menu ==

* Orders & Deliveries – daily work: Board, + New order, All orders, Customers, Drivers, Stock,
  Reports.
* Products – products, categories, attributes and reviews (WooCommerce).
* Shop settings – set up once: Shop details, Product options (sizes, vases, extras,
  delivery times, add-ons), Delivery fees & areas, Payment methods, WhatsApp alerts,
  Announcement bar, Customer accounts, Invoice printing, All WooCommerce settings.
* WooCommerce, Analytics, Marketing – WooCommerce's own pages (rarely needed day to day).

== Website settings ==

Shop settings → Website – everything on the website without the Site Editor:
* Home page: switch each section on/off, drag (or use the arrows) to change the order, and click
  a section to edit its texts, buttons (choose where they go), photos, and which products the
  grids show (best sellers, newest, featured or on sale). Empty an optional text (e.g. the badge
  or the second button) to hide it. "Reset this section" puts the original texts back.
* Product page: the reassurance lines under Add to cart, the four cards under the product,
  and the Add to cart bar at the bottom.
* Footer: about text, social links (Instagram, Facebook, TikTok, WhatsApp), help links,
  address, opening hours, phone, email and the copyright line. The "Shop" column lists your
  main categories automatically; address, hours, phone and WhatsApp come from Shop details.
Note: if the home page or footer was edited earlier in Appearance → Editor, WordPress keeps that
edited copy. Reset it there (Templates → Front page / Patterns → Footer → Reset) to use these settings.

== Editing ==

Everything is editable in Appearance → Editor:
* Templates → "Front page" is the homepage. Click any text or image to change it.
* Main menu: Home, All Products, then one item per product category (subcategories in a
  dropdown), then Contact Us. Categories appear automatically once they have products.
  Untick "Show in main menu" on a category (Products → Categories) to leave it out, and
  drag categories on that screen to change their order.
* Home page "Shop by category": built automatically from your main categories (those with
  products), in the order of the Categories screen, up to 8. Each tile uses the category's
  Thumbnail picture (Products → Categories → edit → Thumbnail); without one, the photo of the
  first product in that category. Untick "Show on the home page" on a category to leave it out.
* Header images: edit a category in Products → Categories and pick a "Header image"
  (subcategories without one use their parent's). For All Products, set the featured
  image of the Shop page (Pages → Shop).
* Patterns → Template parts → Header / Footer for the fixed menu items, address and opening hours.
* Shop settings → Announcement bar: the messages in the thin bar at the top, with optional dates,
  days of the week and times of day for each, plus colours and how fast they rotate.
  Times use the shop time zone (Settings → General → Timezone).
* Styles → change colours and fonts for the whole site.
* The blog ("Posts") is hidden from the admin. To use it, tick Settings → Reading → "Blog posts".
* The homepage product grids are Shortcode blocks, for example
  [products limit="8" columns="4" best_selling="true"].
  Change limit/columns, or use category="roses" to show a single category.
* Extra pages: pick the "Full width, no title" template and insert any Fleuriste pattern (Patterns → Fleuriste).

== Product page ==

Every product page shows a free gift card message above the Add to cart button, with a live total
(the delivery date and time are chosen once, at checkout). Customisable products also offer bouquet size, vase and extras.
The extra prices are added in the cart and the choices are saved on the order
(visible in the order screen, emails and My Account).

* Change the choices and prices: Shop settings → Product options.
  Write one choice per line as "Name | extra price".
* Which products are customisable: choose "Customisable categories" in the settings
  (Bouquets and Roses to start with), or set "Customisable" to Yes/No on a single product
  in Product data → General.
* Hide everything for one product (e.g. a gift card): Product data → General → "Hide all florist options".
* Flower care tab: default text in the settings, or per product in Product data → Advanced.
* In shop grids, customisable products show "Choose options" and open the product page;
  other products have a normal "Add to cart" button.

== Add-ons and "Frequently bought together" ==

Add-ons are tick-boxes above Add to cart ("Add a gift": chocolates, teddy bear, balloon…).
* Create each add-on as a normal simple product (e.g. in a Gifts category) with a price and photo.
* Shop settings → Product options → Add-ons: pick the default add-ons and
  whether they show on all products or only customisable ones (or leave empty for none).
* Per product (Product data → Linked products): choose its own add-ons, or tick "No add-ons".
* Ticked add-ons go into the cart as their own lines ("Add-on for: …") with their own stock.
  Removing the bouquet removes its add-ons. Out-of-stock add-ons are hidden.

Frequently bought together: a row of products under the product details.
* Per product (Product data → Linked products → Frequently bought together): pick them yourself.
* Left empty, the shop picks them from past orders (products bought in the same order most often),
  then WooCommerce cross-sells, then best sellers from the same category.
* Shop settings → Product options: turn it off, use only your own picks, change the heading
  or the number of products.

== Stock ==

Orders & Deliveries → Stock: every product with an in-stock switch (each colour/option of a variable
product too). Search and filter by category; "Only out of stock" shows what's off sale.
* Shortcuts: "Mark out of stock" under each product in Products, and a "Mark out of stock"
  button in the black admin bar when viewing a product on the site (handy on the phone).
* Products that count stock go to 0; switching them back on with no count left turns counting off.
* Orders already placed are not affected: they stay on the board, can be edited, and the buyer
  can still pay for them (payment link or a retried card payment).
* Out-of-stock products show "Out of stock" and can't be ordered. To hide them, tick
  "Hide out of stock items from the catalogue" in WooCommerce → Settings → Products → Inventory.

== Delivery & pickup ==

Shop settings → Delivery & pickup. At checkout customers choose Delivery or Pickup, then the day and time.
* Delivery areas and fees: one line per area with its fee (drag to reorder). Customers pick their
  area ("Region") in the delivery address and its fee is added. Optional free delivery over an amount.
* Delivery: time slots ("Name | extra price", e.g. "Evening (4pm – 7pm) | 100" adds Rs 100),
  same-day cut-off and days without delivery.
* Pickup from the shop: shop address and instructions, pickup time slots (free), same-day cut-off
  and days the shop is closed for pickup. Untick to offer delivery only.
* Booking window (how many days ahead) and holidays (no delivery and no pickup).
* Saving also sets up WooCommerce: "Delivery by area" in the Mauritius shipping zone (other flat
  rates there are switched off) and WooCommerce's Pickup option. No delivery plugin is needed –
  deactivate "Order Delivery Date and Time" if it is installed.
* The board marks pickup orders "Pickup at the shop" (no driver, not on run sheets); their steps are
  Preparing → Ready for pickup → Collected, and buyer messages and the tracking page say pickup.
* Orders taken on the board: choose the recipient's region and its fee is filled in; untick
  "Delivery applies" for a pickup.
* Change an order's delivery: "Delivery / pickup" box on the order screen (or "Change delivery" on the
  board). Switch to delivery to another region, pickup, or another method typed in by hand with its
  own fee; pick any date and a time from the list or type one in, with its charge. Click Update: the
  total is recalculated and a note is added to the order.
* The address asks for the region only (no town, no country – the country is always Mauritius).

== Checkout ==
* Customer accounts are off by default (Shop settings → Customer accounts): customers check out
  as guests, the account icon and "My Account" link are hidden, and the My Account page goes to
  the shop. Tracking links, invoices and receipts still work. Tick the box to switch accounts on.
* "All Products" always lists the products from every category (never category tiles).

* The delivery address (the recipient) and the billing address (the sender) are asked for separately;
  the customer can tick "My billing address is the same as the delivery address" when the flowers are for them.
* "Send anonymously" is a checkbox at checkout; it is saved on the order and shown in the order screen and emails.
* Tip: in Pages → Checkout, select the Checkout block and set the phone number to Required,
  so the driver can call the recipient.

== Deliveries dashboard ==

* New-order alerts (free): click "Turn on new-order alerts" on the board. While the board is open
  (e.g. on a tablet in the shop) it chimes and shows a notification for each new order.


WordPress admin → Deliveries (shop managers land here after logging in).
* Tiles: deliveries today, tomorrow, orders not started yet and overdue orders.
* Each order shows the recipient, address (with Map, Call and WhatsApp links), the sender,
  what to prepare (size, vase, extras) and the card message. "Anonymous" orders are flagged.
* One button moves an order to the next step: Mark as paid → Start preparing →
  Out for delivery → Mark delivered. Undo is there too.
* "Cancel order" asks for a reason and an optional note, and can tell the buyer by email and/or
  WhatsApp. Items go back into stock. Orders paid online still need refunding in the order screen
  (the board reminds you). Cancelled orders can be reopened from the "Delivered / cancelled" tab.
* "Print this list" prints the current view for the workshop or the driver.
* Two new order statuses, Preparing and Out for delivery, also appear in WooCommerce → Orders.

Drivers (Orders & Deliveries → Drivers):
* Add drivers with a name and phone. Deactivate a driver instead of deleting them.
* Pick a driver on each order on the board (or in the "Delivery driver" box on the order screen).
  Filter the board by driver and print a driver's run sheet.
* Every driver has a private link (copy it or send it by WhatsApp from the Drivers page).
  It opens their deliveries for today and tomorrow on their phone, with Call, WhatsApp and Map
  buttons, a "Collect payment" reminder for cash orders and a "Delivered" button.
  Create a new link if one is shared by mistake – the old one stops working.
* Run sheets: on the board, pick a date and a driver (or all drivers) and click "Print run sheets".
  Each driver gets a page with their stops in time order, phone numbers, items, cash to collect
  and a column to sign each delivery. The Drivers page has a "Print run sheet" link per driver.

WhatsApp alerts (Shop settings → WhatsApp alerts):
* Sends a WhatsApp message to one or more numbers for every new order (order number,
  delivery date and time, items, recipient's area, total, link to the board).
* Choose CallMeBot (free, for your own phone – each phone gets a key by messaging the bot once)
  or the official WhatsApp Business Cloud API (Meta account and an approved template needed).
* "Save and send a test" checks the setup. Each order gets a note saying whether the alert was sent.

== Orders taken by phone or in the shop ==

Orders & Deliveries → + New order (also a button at the top of the board).
* Type the buyer's phone number: a returning customer's name and email fill in, and their
  previous recipients appear as one-click buttons.
* Recipient (or "same as the buyer"), products with size / vase / extras, card message,
  delivery date and time, delivery fee and driver.
* Payment: cash on delivery, already paid in the shop, bank transfer (awaiting payment), or a
  payment link the buyer opens to pay online (e.g. by card with MiPS).
* Optionally email the buyer a confirmation (or the payment link) and send it on WhatsApp.
* The order then appears on the board like any website order.

Changing an order: click "Edit" on the order's card on the board (any order that isn't
delivered or cancelled, from the website or the phone).
* The same form opens filled in: change the buyer, recipient, products, options, card message,
  delivery date and time, fee, driver or payment, then "Save changes".
* Totals and stock are recalculated and a note lists what changed (items, total, date).
* If the buyer already paid online, the board says how much to collect or refund
  (refunds are made in the order screen).
* Optionally email the buyer the updated order and send it on WhatsApp.
* WooCommerce's own order screen can also change items on Processing, Preparing and
  Out for delivery orders.

Custom items, discounts and pick-up (on every order form: new, edit, duplicate, suborder):
* "+ Add a custom item": anything not in the shop, with your own name, quantity and price.
* Discount: an amount or a percentage of the items (shown as a "Discount" line).
* "Delivery applies": untick for pick-up in the shop – no delivery fee is charged.

Suborders (extras for an order already placed):
* Find the order with the search box on the board (order number, name or phone), then click
  "Add to order" on its card (also on the order screen and in WooCommerce → Orders).
* The buyer, recipient, delivery date/time and driver come from the original order. Add shop
  products and/or custom items, a discount, choose the payment (cash on delivery, paid,
  bank transfer or payment link) and whether delivery is charged (off by default – the extras
  go with the original delivery).
* Optionally email / WhatsApp the buyer the extra items with the payment link.
* The suborder is its own order (own total and payment, counted in reports). On the board the
  original order lists its extras ("Awaiting payment" until paid) and the suborder is marked
  "Extra for #… – deliver together".
* Paid / cash-on-delivery suborders move along with the original order (preparing, out for
  delivery, delivered). Unpaid ones stay put until paid.

Duplicating an order (copy, change, save): click "Duplicate" on any card on the board
(also on delivered and cancelled orders), in WooCommerce → Orders, or on the order screen.
* A new order form opens with everything from the original filled in: buyer, recipient,
  products and options, card message, delivery time and fee.
* The date moves to the earliest possible day if the original date has passed; no driver is
  set and payment starts as cash on delivery (or bank transfer / payment link if the
  original used those).
* Change what you need and click "Create order". The original order is not touched; both
  orders get a note linking them.

== Company details, invoices and receipts ==

Shop settings → Shop details: shop name, official company name (optional), BRN,
VAT registration number (optional), registered address, phone, email, website and logo.
These are printed on every invoice and receipt:
* under the shop details: the official company name, "BRN: …" and "VAT No: …";
* at the bottom: "SunlightFlowers is a trading name of Sunlight Trading Ltd · BRN … · VAT No …".

With the "Print Invoice & Delivery Notes" plugin active (as on this site), the plugin makes the
invoices, receipts and delivery notes (its own numbering, titles, footer and emails – set in
WooCommerce → Print Invoices & Delivery Notes); the theme adds the legal details above and uses
the Company page's name, address, phone, email and logo on them.
The Deliveries board shows "Invoice" and "Receipt" buttons on each order (receipt once paid),
the "order taken" message has them too (handy to print a receipt for a walk-in customer), and
the customer's tracking page links to them.

Without that plugin, the theme makes its own PDF invoices and receipts: numbering (prefix and
next number, e.g. to continue a paper book), titles (e.g. "VAT Invoice"), bank details for
unpaid invoices, a footer note, and optional email attachments (invoice with the confirmation,
receipt with the "completed" email) and customer downloads.

== Reports ==

Orders & Deliveries → Reports (shop managers and administrators).
* Pick a month (arrows, "This month", "Last month") or "Other dates" for any range.
* Tiles: orders received (what you should get), orders fulfilled (what you got), still to
  deliver, cancelled and the average order.
* Downloads, each as Excel or PDF:
  - Orders received: every order placed in the period with customer, recipient, delivery
    date, items, payment, paid or not, and amount, with the total and a total per payment
    method. Cancelled orders are on their own sheet / section and not counted.
  - Orders fulfilled: every order marked delivered in the period, with refunds taken off,
    the total you got, totals per payment method and per driver.
  The Excel files use real formulas (totals, per-method sums), have a "Products" sheet with
  one line per product sold, and open in Excel, Google Sheets or Numbers.
* Charts for the 12 months up to the chosen month: amount and number of orders, received vs
  fulfilled (hover a month for the figures), plus tables by payment method, by driver, best
  sellers and month by month.
* Received counts an order on the day it was placed; fulfilled counts it on the day it was
  marked delivered. Abandoned online checkouts are left out; phone orders waiting for a
  payment link are counted.

== Buyer notifications ==

* Every customer email (order received, on hold, completed…) has a "Track your order" button.
  It opens a private page with the order's progress (received → being prepared → out for
  delivery → delivered), delivery date and items. The same link is on the thank-you page
  and in My Account → Orders.
* Checkout has "Send me order updates on WhatsApp". With the WhatsApp Business Cloud API set up
  (Shop settings → WhatsApp alerts → Buyer updates template), those buyers get an automatic
  WhatsApp confirmation when they order.
* On the Deliveries board, "Notify buyer" opens a ready-written message for the order's current
  status (it opens by itself after you move an order to the next step). Edit it if you like,
  tick Email and/or WhatsApp, and Send. Without the Business API, WhatsApp opens on your phone
  or computer with the message ready – just press send. Each notification is saved in the order notes.

== Credits ==

Fonts: Cormorant Garamond and Jost, SIL Open Font License 1.1 (see assets/fonts).
Photos: supplied by the shop owner (Unsplash / Pexels licences).
