HPM WEBSITE — NAVIGATION, BANNER & BREADCRUMB FIX
==================================================

WHAT WAS FIXED
1. Removed the duplicate mobile submenu click handler.
   hpm-global-navigation.js is now the only controller for Services and
   Products plus/minus buttons. hpm-products-navigation.js only manages
   product-category selection.

2. Made desktop and mobile product category lists database-driven on all
   public pages through api/categories.php.

3. Removed PHP foreach fragments from static .html navigation markup.
   The browser now receives empty category lists and JavaScript fills them
   using the shared API.

4. Added the shared navigation JavaScript to products.php.

5. Normalized the Products desktop dropdown markup across public pages.

6. Replaced fragile mobile close and plus/minus Font Awesome icons with
   stable text glyphs. Replaced fa-x-twitter with the bundled fa-twitter icon.

7. Added hpm-final-responsive-fix.css after the existing theme styles.
   Desktop/current menu items stay navy; hover/focus uses HPM orange.

8. Rebuilt breadcrumbs as compact full-width CSS sections with centered
   headings and orange geometric accents. This avoids stretching the mostly
   white aboutus.png artwork into a large blank panel.

9. Reworked the homepage hero sizing to preserve the original 2.4:1 banner
   image ratio and prevent forced tall mobile slides.

INSTALLATION
============
1. Make a backup copy of your existing HPM folder first.
2. Extract this ZIP.
3. Copy the contents of the included HPM folder into your existing HPM folder.
   Allow the updated files to replace the old versions.
4. Keep your existing config/config.php, database credentials, uploads,
   and any production-only files if they differ from this development ZIP.
5. Ensure api/categories.php is reachable at:
   http://localhost/HPM/api/categories.php
6. Open the API URL directly. It should return JSON with:
   {"success":true,"categories":[...]}
7. If the API returns an error, check includes/bootstrap.php, the DB
   connection, and that the categories table contains active categories.
8. Hard-refresh the browser (Ctrl + Shift + R) after copying the files.

IMPORTANT
=========
- The API uses the existing database bootstrap and categories table.
- Product categories are loaded asynchronously on every page.
- Static .html pages cannot execute PHP loops; those loops were removed
  from the shared navigation and replaced by the API-driven list.
- The homepage banner images are raster artwork containing text. At narrow
  widths, preserving the full image also means the baked-in text becomes
  smaller. A separate mobile banner image would be needed for larger text
  without cropping.
- Test the API and navigation on localhost before uploading to production.
