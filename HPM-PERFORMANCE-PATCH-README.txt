HPM PERFORMANCE + DESKTOP MENU PATCH
=====================================

Changes:
1. Updated homepage hero background references in assets/css/style.css:
   - hpm-home-banner-1.png -> hpm-home-banner-1.webp
   - hpm-home-banner-2.png -> hpm-home-banner-2.webp
   - hpm-home-banner-3.png -> hpm-home-banner-3.webp
   The three WebP hero images were re-encoded and resized for web delivery.
2. Re-encoded 41 large raster image assets as WebP (including already-WebP
   images that were unnecessarily large), resized images wider/taller than
   2200px, and updated matching references in HTML/CSS/JS/PHP files.
3. Added assets/css/hpm-desktop-menu-fix.css and loaded it last on the
   website pages containing the HPM header. Desktop navigation is used at
   widths >=1200px; the hamburger trigger is hidden. Tablet/mobile trigger
   remains visible at <=1199px.

DEPLOYMENT:
1. Back up the current httpdocs directory and export the database.
2. Extract this archive and upload the contents of HPM/ into httpdocs,
   allowing files to be overwritten.
3. IMPORTANT: Preserve your current live config/config.php containing your
   real GoDaddy database password. Do not overwrite it with a copy from
   this archive unless you have verified the credentials in that copy.
4. In Plesk, clear any server/page cache if enabled.
5. In Chrome DevTools > Network, enable Disable cache and hard-refresh.
   Confirm the hero requests end in .webp, not .png.
6. Check the hamburger at desktop width 1440px and 1920px; check mobile
   width <=1199px to confirm the menu remains available.

NOTE:
This patch addresses the oversized image requests and desktop hamburger
visibility shown in the supplied screenshots. It does not guarantee a
particular live load time; the live server and cache must be tested after
deployment. The database and hosting settings are intentionally not changed.
