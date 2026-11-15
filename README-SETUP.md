# Hari Pallet Machinery (HPM) — website handover

## Product catalogue structure

The catalogue is category-driven and uses MySQL relationships:

- `categories` stores category names, slugs, active status and display order.
- `products.category_id` links each product to one category.
- The public `products.php` groups published products by active category.
- Product cards show a compact image, name, short description and **View Details** only.
- Product details open in an accessible popup with description, specification bullets and an enquiry button linking to WhatsApp.
- Admin users can create, activate/deactivate and delete empty categories, and add/edit/publish/hide/delete products.

The four approved categories are preloaded from the HPM category document. No product records are preloaded. The source document says "19 Machines" in its heading but its listed category items total 20; confirm the approved final catalogue count with HPM before populating all products.

## Existing database migration (existing local or hosting database)

1. Export a full backup of `hpm_catalogue` in phpMyAdmin before proceeding.
2. Open phpMyAdmin, select the `hpm_catalogue` database, and import `database/migrate-category-catalogue.sql`.
3. **This migration intentionally truncates the existing `products` table. All existing product rows are permanently deleted and the product auto-increment counter is reset.** It preserves the admins and enquiries tables.
4. The migration creates the four category rows and changes the product relationship from the old text `category` column to `category_id`.
5. If you already manually changed the schema, do not run this migration blindly; compare the current schema first.

For a completely new database, import `database/hpm_catalogue.sql` instead. It creates the new schema and four categories without inserting products.

## Configuration

1. Copy `config/config.example.php` to `config/config.php` (or update the existing local config).
2. Enter the hosting MySQL hostname, database name, database username and password.
3. Set `whatsapp_number` to HPM's correct international WhatsApp number using digits only, including country code. The current local config value is a placeholder/example and must be verified.
4. Set the correct site URL and authorised sender mailbox.
5. Never upload a local XAMPP config containing `root` / blank password to production.

## Admin setup

- Open `/admin/login.php`.
- If no admin exists, create one using the CLI utility:
  `php admin/create-admin.php "HPM Administrator" admin@example.com "A-Strong-Unique-Password"`
- The first admin creation script is a setup utility; remove `admin/create-admin.php` from the production server after creating the account.
- Use HTTPS and a unique strong password. Do not use credentials from an example or a development database.
- Admin dashboard: `/admin/`
- Categories must be active before they are available in the product editor. A category containing products cannot be deleted until its products have been reassigned or removed.

## Add a product

1. Sign in and create/select an active category.
2. Choose **Add product**.
3. Enter the product name, category, short description and (if available) full description.
4. Add technical specifications one per line. They appear as a bullet list in the product details popup.
5. Upload a JPG, PNG or WebP image (maximum 5 MB), or provide a valid image path.
6. Add useful image alt text and SEO metadata.
7. Tick **Published / visible** to display the product publicly.
8. Save. The product appears automatically under its category on `products.php`.

## Files changed in this handover

- `admin/index.php` and `admin/admin.css`: responsive dashboard, category manager, catalogue summary and product table.
- `admin/category-action.php`: category create, activation and safe deletion actions.
- `admin/product-edit.php`: category dropdown, image upload and product form.
- `products.php`: category navigation and category-wise compact product cards.
- `api/product.php` and `assets/js/hpm-commerce.js`: product popup data and WhatsApp enquiry.
- `assets/css/hpm-catalogue.css`: responsive category and product detail styling.
- `includes/bootstrap.php`: WhatsApp number read from configuration.
- `database/hpm_catalogue.sql`: clean schema with four categories and no seeded products.
- `database/migrate-category-catalogue.sql`: destructive migration for an existing database.
- `assets/images/hpm-favicon.svg`: HPM favicon mark.
- `assets/images/hpm-logo.svg`: SVG wrapper containing the supplied final logo artwork.

## Handover / deployment checks

- Test login, category creation, category activation/deactivation and product create/edit/delete.
- Test product image upload permissions in `uploads/products/`.
- Test product details popup on desktop and mobile.
- Test WhatsApp button and confirm the configured number is correct.
- Confirm all machine photos, descriptions and specifications with HPM; no technical specifications have been invented or prefilled.
- Check contact form delivery, sitemap, canonical URLs and SSL after deployment.
- The original theme assets are retained because the static pages still depend on shared CSS, JavaScript, fonts and images. They have not been removed based only on filename assumptions; removing them without a full dependency and visual regression audit could break the remaining website pages.
