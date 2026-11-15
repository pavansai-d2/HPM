<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$categories = [];
$productsByCategory = [];
$dbError = '';
$pageTitle = 'Pallet Making & Wood Processing Machines | Hari Pallet Machinery';
$pageDescription = 'Explore HPM pallet making machines, wood cutting and processing equipment, heat treatment systems and material handling machinery. View machine details and enquire directly with our team.';
try {
  $pdo = db();
  $categories = $pdo->query("SELECT id,name,slug FROM categories WHERE is_active=1 ORDER BY sort_order ASC,name ASC")->fetchAll();
  $st = $pdo->query("SELECT p.id,p.name,p.slug,p.short_description,p.image,p.alt_text,p.is_featured,c.id AS category_id,c.name AS category_name,c.slug AS category_slug
    FROM products p INNER JOIN categories c ON c.id=p.category_id
    WHERE p.is_active=1 AND c.is_active=1
    ORDER BY c.sort_order ASC,p.is_featured DESC,p.name ASC");
  foreach ($st->fetchAll() as $product) {
    $productsByCategory[(int)$product['category_id']][] = $product;
  }
} catch (Throwable $ex) {
  error_log('HPM products database error: ' . $ex->getMessage());
  $dbError = 'The product catalogue is temporarily unavailable. Please check the HPM database connection.';
}
?>
<!doctype html>
<html lang="en">

<head>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= e($pageDescription) ?>">
  <!-- Place favicon.ico in the root directory -->
  <link rel="icon" type="image/svg+xml" href="assets/images/hpm-logo.svg" />
  <title><?= e($pageTitle) ?></title>
  <link rel="canonical" href="https://haripalletmachinery.com/products.php" />
  <meta property="og:title" content="<?= e($pageTitle) ?>" />
  <meta property="og:description" content="<?= e($pageDescription) ?>" />
  <meta property="og:type" content="website" />

  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300..900&display=swap"
    onload="
        this.onload = null;
        this.rel = 'stylesheet';
      " />
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Exo:ital,wght@0,100..900&display=swap"
    onload="
        this.onload = null;
        this.rel = 'stylesheet';
      " />
  <noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300..900&display=swap" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Exo:ital,wght@0,100..900&display=swap" />
  </noscript>

  <!-- fontawesome css -->
  <link rel="preload" href="assets/css/plugins/fontawesome.css" as="style" onload="this.rel = 'stylesheet'" />
  <link rel="preload" href="assets/css/plugins/swiper.css" as="style" onload="this.rel = 'stylesheet'" />
  <!-- <link rel="preload" href="assets/css/plugins/aos.css" as="style" onload="this.rel='stylesheet'"> -->
  <link rel="preload" href="assets/css/plugins/unicons.css" as="style" onload="this.rel = 'stylesheet'" />
  <link rel="preload" href="assets/css/plugins/metismenu.css" as="style" onload="this.rel = 'stylesheet'" />
  <link rel="preload" href="assets/css/plugins/magnific-popup.css" as="style" onload="this.rel = 'stylesheet'" />
  <link rel="preload" href="assets/css/plugins/hover-revel.css" as="style" onload="this.rel = 'stylesheet'" />
  <link rel="preload" href="assets/css/plugins/timepickers.min.css" as="style" onload="this.rel = 'stylesheet'" />
  <link rel="preload" href="assets/css/plugins/odometer.css" as="style" onload="this.rel = 'stylesheet'" />
  <!-- bootstrap css -->
  <link rel="preload" href="assets/css/vendor/bootstrap.min.css" as="style" onload="this.rel = 'stylesheet'" />
  <link rel="preload" as="image" href="assets/images/banner/12.webp" />

  <!-- main css -->
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/hpm-commerce.css" />
  <link rel="stylesheet" href="assets/css/hpm-product-viewer.css?v=20261109-spec-bullets2" />
  <link rel="stylesheet" href="assets/css/hpm-catalogue.css" />
  <link rel="stylesheet" href="assets/css/hpm-site-polish.css" />
  <link rel="stylesheet" href="assets/css/hpm-navigation-responsive.css" />
  <link rel="stylesheet" href="assets/css/hpm-navigation-breadcrumb-overrides.css" />
  <link rel="stylesheet" href="assets/css/hpm-final-responsive-fix.css?v=20261115-dynamic-categories" />
  <meta name="robots" content="index,follow,max-image-preview:large">
<link rel="stylesheet" href="assets/css/hpm-desktop-menu-fix.css" />
  <link rel="stylesheet" href="assets/css/hpm-performance-design.css">
</head>

<body class="home-five home-green">
  <!-- Header Start -->
  
  <!-- header area start -->
  <header class="header-one header-seven inner header--sticky header-sticky-smooth">
    <!-- header top -->
    <div class="header-top overflow-visible">
      <div class="row">
        <div class="col-lg-12">
          <div class="header-top-wrapper">
            <!-- left area header top -->
            <div class="left">
              <!-- single map area -->
              <div class="single-component info-con">
                <div class="icon">
                  <i class="fal fa-clock"></i>
                </div>
                <div class="info">
                  <span>Mon - Sat (9:00 AM - 6:00 PM)</span>
                </div>
              </div>
              <!-- single map area -->
              <!-- single map area -->
              <div class="single-component info-con">
                <div class="icon">
                  <i class="fal fa-phone"></i>
                </div>
                <div class="info">
                  <span><a href="tel:+971542188600">+971 542188600</a></span>
                </div>
              </div>
              <!-- single map area -->
              <!-- single map area -->
              <div class="single-component info-con">
                <div class="icon">
                  <i class="fal fa-map-marker-alt"></i>
                </div>
                <div class="info">
                  <span>Near Sharjah Gas Plant, Al Sajja Industrial Area,
                    Sharjah, UAE</span>
                </div>
              </div>
              <!-- single map area -->
            </div>
            <!-- right area strat -->
            <div class="right">
              <ul class="social-wrapper-1 hover-bg-white">
                <li class="single">
                  <a target="_blank" href="https://www.facebook.com/"><i class="fab fa-facebook-f"></i></a>
                </li>
                <li class="single">
                  <a target="_blank" href="https://x.com/"><i class="fab fa-twitter"></i></a>
                </li>
                <li class="single">
                  <a target="_blank" href="https://www.instagram.com/"><i class="fab fa-instagram"></i></a>
                </li>
                <li>
                  <a target="_blank" href="https://www.linkedin.com/"><i class="fa-brands fa-linkedin-in"></i></a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- header bottom -->
    <!-- header style hear -->
    <div class="header-main header-main-2">
      <div class="row">
        <div class="col-lg-12">
          <div class="header-mid-one-wrapper wrapper-two">
            <div class="left d-flex align-items-center">
              <div class="logo-area shape-none">
                <a href="index.html">
                  <img decoding="async" src="assets/images/hpm-logo.svg" alt="logo" />
                </a>
              </div>
              <div class="nav-area">
                <ul class="mainmenu">
                  <li class="main-nav"><a href="index.html">Home</a></li>

                  <li class="main-nav">
                    <a href="about.html">About Us</a>
                  </li>

                  <li class="main-nav has-dropdown has-menu-child-item">
                    <a href="services.html">Services</a>
                    <ul class="submenu parent-nav">
                      <li><a href="automation-services.html">Automation Services</a></li>
                      <li><a href="metal-fabrication-works.html">Metal Fabrication Works</a></li>
                      <li><a href="installation-and-commissioning.html">Installation & Commissioning</a></li>
                      <li><a href="repair-and-maintenance.html">Repair and Maintenance</a></li>
                      <li><a href="spare-parts-and-nailing-equipment.html">Spare Parts and Nailing Equipment</a></li>
                      <li><a href="warehouse-solutions.html">Warehouse Solutions</a></li>
                      <li><a href="heat-treatment-plant-and-machine-customisation.html">Heat Treatment Plant and Machine
                          Customisation</a></li>
                      <li><a href="pallet-product-line-solutions.html">Pallet Product line solutions</a></li>
                      <li><a href="technical-support.html">Technical Support</a></li>
                    </ul>
                  </li>

                  <li class="main-nav has-dropdown has-menu-child-item active hpm-products-menu">
                    <a href="products.php">Products</a>
                    <ul class="submenu parent-nav hpm-products-dropdown"></ul>
                  </li>
                  <li class="main-nav">
                    <a href="contact.html">Contact Us</a>
                  </li>
                </ul>
              </div>
            </div>
            <div class="header-right">
              <div class="single-component">

                <a href="https://wa.me/971542188600" target="_blank" rel="noopener noreferrer"
                  class="tmp-btn btn-primary hover-icon-reverse hpm-whatsapp-quote" data-sal="slide-up-large"
                  data-sal-duration="1000" data-sal-delay="600">

                  <span class="icon-reverse-wrapper">

                    <!-- WhatsApp + Text Group -->
                    <span class="hpm-quote-content">
                      <i class="fa-brands fa-whatsapp hpm-whatsapp-icon"></i>
                      <span class="btn-text">Request a Quote</span>
                    </span>

                    <!-- Arrow Animation -->
                    <span class="btn-icon">
                      <i class="fa-solid fa-arrow-right"></i>
                    </span>

                    <span class="btn-icon">
                      <i class="fa-solid fa-arrow-right"></i>
                    </span>

                  </span>
                </a>

                <div class="menu-area d-block d-xl-none" id="menu-btn">
                  <div class="icon radius-small">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="16" viewBox="0 0 18 16" fill="none">
                      <rect width="18" height="2" fill="#0C0A0A"></rect>
                      <rect y="7" width="18" height="2" fill="#0C0A0A"></rect>
                      <rect y="14" width="18" height="2" fill="#0C0A0A"></rect>
                    </svg>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- header style hearm end -->
  </header>
  <!-- header area end -->

  <!-- Header End -->
  <!-- Breadcrumb Start -->
  <!-- tmp breadcrumba area start -->
  <div class="tmp-bread-crumb-area bg-breadcrumb bg-image-breadcrumb bg_image hpm-page-breadcrumb">
    <div class="container-3">
      <div class="row">
        <div class="col-lg-12">
          <!-- bread crumb inner wrapper -->
          <div class="breadcrumb-inner ptb--100 ptb_sm--100 text-center">
            <h1 class="title">Products</h1>
            <div class="meta">
              <a href="index.html" class="prev"><i class="fa-solid fa-house"></i> Home</a>
              <span><i class="fa-regular fa-chevron-right"></i></span>
              <a href="products.php" class="next">Products</a>
            </div>
          </div>
          <!-- bread crumb inner wrapper end -->
        </div>
      </div>
    </div>
  </div>
  <!-- tmp breadcrumba area end -->
  <!-- Breadcrumb End -->
  <main class="hpm-products-page">
    <section class="hpm-catalogue-intro">
      <div class="container-3">
        <span class="hpm-eyebrow">HARI PALLET MACHINERY</span>
        <h1>Our Machinery & Equipment</h1>
        <p>Explore dependable machinery for pallet manufacturing, wood processing, treatment and material handling.</p>
      </div>
    </section>
    <?php if ($dbError !== ''): ?>
      <div class="container-3">
        <div class="hpm-catalogue-alert" role="alert"><?= e($dbError) ?></div>
      </div>
    <?php elseif (!$categories): ?>
      <div class="container-3">
        <div class="hpm-catalogue-empty">
          <h2>Our product catalogue is being updated</h2>
          <p>Please contact our team for current machinery details.</p>
        </div>
      </div>
    <?php else: ?>
      <section class="container-3 hpm-catalogue-layout" aria-label="Product categories">
        <aside class="hpm-category-nav" aria-label="Browse categories">
          <h2>Categories</h2>
          <?php foreach ($categories as $i => $cat): ?>
            <a href="products.php#category-<?= rawurlencode((string)$cat['slug']) ?>" class="<?= $i === 0 ? 'is-current' : '' ?>" data-hpm-category-link>
              <span><?= e($cat['name']) ?></span><small><?= count($productsByCategory[(int)$cat['id']] ?? []) ?></small>
            </a>
          <?php endforeach; ?>
        </aside>
        <div class="hpm-category-content">
          <?php foreach ($categories as $i => $cat): $items = $productsByCategory[(int)$cat['id']] ?? []; ?>
            <section class="hpm-category-section" id="category-<?= e($cat['slug']) ?>" data-category-slug="<?= e($cat['slug']) ?>" <?= $i === 0 ? "" : "hidden" ?>>
              <div class="hpm-category-heading">
                <div><span class="hpm-eyebrow">HPM PRODUCT RANGE</span>
                  <h2><?= e($cat['name']) ?></h2>
                </div><span class="hpm-category-count"><?= count($items) ?> <?= count($items) === 1 ? 'machine' : 'machines' ?></span>
              </div>
              <?php if ($items): ?>
                <div class="hpm-product-grid">
                  <?php foreach ($items as $p): ?>
                    <article class="hpm-product-card">
                      <button class="hpm-product-image" type="button" data-product-id="<?= (int)$p['id'] ?>" aria-label="View <?= e($p['name']) ?> details">
                        <img loading="lazy" src="<?= e($p['image'] ?: 'assets/images/usage/product-19.webp') ?>" alt="<?= e($p['alt_text'] ?: $p['name']) ?>" onerror="this.onerror=null;this.src='assets/images/usage/product-19.webp'">
                      </button>
                      <div class="hpm-product-body">
                        <span class="hpm-category"><?= e($cat['name']) ?></span>
                        <h3><?= e($p['name']) ?></h3>
                        <p><?= e($p['short_description']) ?></p>
                        <div class="hpm-product-actions"><button type="button" class="hpm-details-btn" data-product-id="<?= (int)$p['id'] ?>">View Details <span aria-hidden="true">→</span></button></div>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="hpm-category-empty">Products in this category will be added soon. Please contact HPM for assistance.</div>
              <?php endif; ?>
            </section>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <!-- tmp footer area start -->
  <footer class="tmp-footer-area footer-one footer-five footer-bg hpm-footer-layout-fix">
    <div class="container-3">
      <!-- Footer CTA -->
      <div class="footer-cta-area cta-two">
        <div class="cta-image-area">
          <img loading="lazy" decoding="async" src="assets/images/cta/02.webp" width="390" alt="" />
          <img loading="lazy" decoding="async" src="assets/images/cta/cta-round2.svg" alt="" />
          <img loading="lazy" decoding="async" src="assets/images/cta/solar1.webp" alt="" />
          <img loading="lazy" decoding="async" src="assets/images/cta/solar2.webp" alt="" />
        </div>

        <div class="cta-area-inner">
          <h3 class="cta-title">
            Join the Future of Construction <br />
            Contact Us Now
          </h3>

          <div class="cta-button-area">
            <a href="contact.html" class="tmp-btn btn-primary bg-white button-flip btn-md">
              <span data-text="GET A QUOTE">GET A QUOTE</span>
            </a>
          </div>
        </div>

        <div class="shape-area">
          <img loading="lazy" decoding="async" src="assets/images/cta/cta-round.svg" alt="" />
          <img loading="lazy" decoding="async" src="assets/images/cta/cta-round.svg" alt="" />
        </div>
      </div>

      <!-- Footer Main Content -->
      <div class="row">
        <div class="col-lg-12 p-0">
          <div class="main-footer-one-area">
            <!-- Footer Column 1: Logo, Description and Pallet Animation -->
            <div class="single-footer-inner logo-area">
              <div class="body one">
                <div class="logo mb--20">
                  <a href="index.html">
                    <img decoding="async" src="assets/images/hpm-logo-white.webp" alt="Hari Pallet Machinery" />
                  </a>
                </div>

                <p class="desc">
                  We provide reliable pallet machinery, precision engineering
                  and industrial solutions built for performance.
                </p>

                <ul class="social-wrapper-1 hover-bg-white">
                  <li>
                    <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                      <i class="fab fa-facebook-f"></i>
                    </a>
                  </li>
                  <li>
                    <a href="https://x.com/" target="_blank" rel="noopener noreferrer" aria-label="X">
                      <i class="fab fa-twitter"></i>
                    </a>
                  </li>
                  <li>
                    <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer"
                      aria-label="Instagram">
                      <i class="fab fa-instagram"></i>
                    </a>
                  </li>
                  <li>
                    <a href="https://www.linkedin.com/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                      <i class="fab fa-linkedin-in"></i>
                    </a>
                  </li>
                </ul>

                <!-- HPM Footer Contact Details START -->
                <div class="hpm-footer-contact-details">

                  <!-- Working Hours -->
                  <div class="hpm-footer-contact-item">
                    <span class="hpm-footer-contact-icon">
                      <i class="fa-regular fa-clock"></i>
                    </span>

                    <div class="hpm-footer-contact-content">
                      <span class="hpm-footer-contact-label">Working Hours</span>
                      <p>Mon - Sat (9:00 AM - 6:00 PM)</p>
                    </div>
                  </div>

                  <!-- Phone Number -->
                  <div class="hpm-footer-contact-item">
                    <span class="hpm-footer-contact-icon">
                      <i class="fa-solid fa-phone"></i>
                    </span>

                    <div class="hpm-footer-contact-content">
                      <span class="hpm-footer-contact-label">Call Us</span>
                      <p>
                        <a href="tel:+971542188600">+971 542188600</a>
                      </p>
                    </div>
                  </div>

                  <!-- Address -->
                  <div class="hpm-footer-contact-item">
                    <span class="hpm-footer-contact-icon">
                      <i class="fa-solid fa-location-dot"></i>
                    </span>

                    <div class="hpm-footer-contact-content">
                      <span class="hpm-footer-contact-label">Our Address</span>
                      <p>
                        <a href="https://www.google.com/maps/search/?api=1&query=Near+Sharjah+Gas+Plant%2C+Al+Sajja+Industrial+Area%2C+Sharjah%2C+UAE"
                          target="_blank" rel="noopener noreferrer">
                          Near Sharjah Gas Plant, Al Sajja Industrial Area,
                          Sharjah, UAE
                        </a>
                      </p>
                    </div>
                  </div>
                </div>
                <!-- HPM Footer Contact Details END -->

              </div>
            </div>

            <!-- Column 2: HPM Company -->
            <div class="single-footer-inner company">
              <div class="body two">

                <h3 class="footer-title">Company</h3>

                <div class="use-full-link-wrapper hpm-footer-company-links">

                  <a href="about.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <span>About Us</span>
                  </a>

                  <a href="services.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-gears" aria-hidden="true"></i>
                    <span>Services</span>
                  </a>

                  <a href="products.php" class="single hover-moving-primary">
                    <i class="fa-solid fa-industry" aria-hidden="true"></i>
                    <span>Products</span>
                  </a>

                  <a href="contact.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    <span>Contact Us</span>
                  </a>

                </div>
              </div>
            </div>
            <!-- Company Column End -->

            <!-- Column 3: HPM Services -->
            <div class="single-footer-inner service">
              <div class="body three">
                <h3 class="footer-title">Services</h3>

                <div class="use-full-link-wrapper hpm-footer-service-links">

                  <a href="automation-services.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-gears" aria-hidden="true"></i>
                    <span>Automation Services</span>
                  </a>

                  <a href="metal-fabrication-works.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-industry" aria-hidden="true"></i>
                    <span>Metal Fabrication Works</span>
                  </a>

                  <a href="installation-and-commissioning.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                    <span>Installation &amp; Commissioning</span>
                  </a>

                  <a href="repair-and-maintenance.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-toolbox" aria-hidden="true"></i>
                    <span>Repair &amp; Maintenance</span>
                  </a>

                  <a href="spare-parts-and-nailing-equipment.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-screwdriver" aria-hidden="true"></i>
                    <span>Spare Parts &amp; Nailing Equipment</span>
                  </a>

                  <a href="warehouse-solutions.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-warehouse" aria-hidden="true"></i>
                    <span>Warehouse Solutions</span>
                  </a>

                  <a href="heat-treatment-plant-and-machine-customisation.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-temperature-high" aria-hidden="true"></i>
                    <span>Heat Treatment Plant &amp; Machine Customisation</span>
                  </a>

                  <a href="technical-support.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                    <span>Technical Support</span>
                  </a>

                  <a href="pallet-product-line-solutions.html" class="single hover-moving-primary">
                    <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                    <span>Pallet Product Line Solutions</span>
                  </a>

                </div>
              </div>
            </div>
            <!-- Services Column End -->

            <!-- Column 4: Location Map (body four) -->

            <!-- HPM FOOTER LOCATION START -->
            <div class="single-footer-inner contact footer-contact-map">
              <div class="body four">

                <h3 class="footer-title">Our Location</h3>

                <div class="footer-map-wrapper">
                  <div class="footer-map">

                    <iframe
                      src="https://www.google.com/maps/embed?pb=!1m13!1m8!1m3!1d7212.87267239118!2d55.6700168!3d25.3231353!3m2!1i1024!2i768!4f13.1!3m2!1m1!2s!5e0!3m2!1sen!2sin!4v1794015337682!5m2!1sen!2sin"
                      title="Hari Pallet Machinery - Sharjah, UAE" width="600" height="450" style="border:0;"
                      allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin">
                    </iframe>

                  </div>
                </div>

                <!-- GET DIRECTIONS BUTTON -->
                <a href="https://www.google.com/maps/dir/?api=1&destination=25.3231353,55.6700168"
                  class="hpm-footer-directions" target="_blank" rel="noopener noreferrer">

                  <i class="fa-solid fa-location-arrow" aria-hidden="true"></i>

                  <span>Get Directions</span>

                  <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>

                <!-- WORKER AND PALLET JACK ANIMATION -->
                <div class="footer-truck-strip footer-map-truck" role="img" aria-label="Worker moving a pallet jack">

                  <div class="footer-truck-track">
                    <div class="footer-truck-unit">

                      <i class="fa-solid fa-person-walking footer-worker" aria-hidden="true"></i>

                      <i class="fa-solid fa-dolly footer-pallet-truck" aria-hidden="true"></i>

                    </div>
                  </div>

                  <div class="footer-truck-floor"></div>

                </div>

              </div>
            </div>
            <!-- HPM FOOTER LOCATION END -->
          </div>
        </div>
      </div>

      <!-- Copyright -->
      <div class="copyright-area-one">
        <div class="row justify-content-between">
          <div class="col-lg-6 col-md-12 col-sm-12">
            <div class="wrapper text-start text-md-center text-lg-start">
              <p>
                &copy; Copyright <span id="year"></span> by
                <a class="hover-moving-primary" href="index.html">
                  HPM Packing & Packaging Machines Assembling Co.LLC
                </a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </footer>
  <!-- tmp footer area end -->

  <!-- side bar for desktop -->
  <div id="side-bar" class="side-bar hpm-mobile-drawer" aria-label="Mobile navigation">
    <div class="hpm-mobile-drawer-header">
      <a class="hpm-mobile-drawer-logo" href="index.html" aria-label="Hari Pallet Machinery home">
        <img decoding="async" src="assets/images/hpm-logo.svg" alt="Hari Pallet Machinery">
      </a>
      <button type="button" class="close-icon-menu hpm-mobile-close" aria-label="Close navigation menu">
        <span class="hpm-close-glyph" aria-hidden="true">×</span></button>
    </div>

    <div class="mobile-menu d-block d-xl-none">
      <nav class="nav-main mainmenu-nav hpm-mobile-nav" aria-label="Mobile main navigation">
        <ul class="hpm-mobile-menu-list" id="hpm-mobile-menu">
          <li><a href="index.html" class="hpm-mobile-main-link">Home</a></li>
          <li><a href="about.html" class="hpm-mobile-main-link">About Us</a></li>

          <li class="hpm-mobile-has-submenu">
            <div class="hpm-mobile-menu-row">
              <a href="services.html" class="hpm-mobile-main-link">Services</a>
              <button type="button" class="hpm-mobile-submenu-toggle" aria-expanded="false" aria-label="Open Services submenu">
                <span class="hpm-submenu-glyph" aria-hidden="true">+</span>
              </button>
            </div>
            <ul class="hpm-mobile-submenu" hidden>
              <li><a href="automation-services.html">Automation Services</a></li>
              <li><a href="metal-fabrication-works.html">Metal Fabrication Works</a></li>
              <li><a href="installation-and-commissioning.html">Installation & Commissioning</a></li>
              <li><a href="repair-and-maintenance.html">Repair and Maintenance</a></li>
              <li><a href="spare-parts-and-nailing-equipment.html">Spare Parts and Nailing Equipment</a></li>
              <li><a href="warehouse-solutions.html">Warehouse Solutions</a></li>
              <li><a href="heat-treatment-plant-and-machine-customisation.html">Heat Treatment Plant and Machine Customisation</a></li>
              <li><a href="technical-support.html">Technical Support</a></li>
              <li><a href="pallet-product-line-solutions.html">Pallet Product Line Solutions</a></li>
            </ul>
          </li>

          <li class="hpm-mobile-has-submenu">
            <div class="hpm-mobile-menu-row">
              <a href="products.php" class="hpm-mobile-main-link">Products</a>
              <button type="button" class="hpm-mobile-submenu-toggle" aria-expanded="false" aria-label="Open Products categories">
                <span class="hpm-submenu-glyph" aria-hidden="true">+</span>
              </button>
            </div>
            <ul class="hpm-mobile-submenu hpm-mobile-product-categories" hidden></ul>
          </li>

          <li><a href="contact.html" class="hpm-mobile-main-link">Contact Us</a></li>
        </ul>
      </nav>

      <section class="hpm-mobile-contact" aria-labelledby="hpm-mobile-contact-title">
        <h3 id="hpm-mobile-contact-title">Contact Information</h3>
        <a href="tel:+971542188600"><i class="fa-solid fa-phone" aria-hidden="true"></i><span>+971 54 218 8600</span></a>
        <a href="mailto:info@haripalletmachinery.com"><i class="fa-regular fa-envelope" aria-hidden="true"></i><span>info@haripalletmachinery.com</span></a>
        <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Near Sharjah Gas Plant, Al Sajja Industrial Area, Sharjah, UAE</span></p>
        <p><i class="fa-regular fa-clock" aria-hidden="true"></i><span>Monday – Saturday: 9:00 AM – 6:00 PM</span></p>
      </section>

      <div class="hpm-mobile-social">
        <span>Find With Us</span>
        <div class="hpm-mobile-social-links">
          <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="https://x.com/" target="_blank" rel="noopener noreferrer" aria-label="X"><i class="fa-brands fa-twitter"></i></a>
          <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="https://www.linkedin.com/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
        </div>
      </div>
    </div>
  </div>
  <!-- mobile navigation drawer end -->

  <!-- side bar for desktop -->
  <div id="side-bar2" class="side-bar side-bar2">
    <!-- inner menu area desktop start -->
    <div class="inner-main-wrapper-desk style-one-sidebar d-none d-lg-block">
      <div class="header-area-wrapper">
        <a href="index.html" class="logo">
          <img decoding="async" src="assets/images/hpm-logo.svg" alt="logo">
        </a>
        <a href="index.html" class="logo logo-yellow">
          <img decoding="async" src="assets/images/hpm-logo.svg" alt="logo">
        </a>
        <button class="close-icon-menu" aria-label="close"><i class="far fa-times"></i></button>
      </div>
      <div class="header-bottom-qhick-action">
        <p class="disc">
          HPM is a Pallet Machinery manufacturing company based in Dubai, UAE. We provide reliable pallet machinery,
          precision engineering and industrial solutions built for performance.
        </p>
        <div class="info-list-wrapper">
          <ul>
            <li><a href="tel:+971542188600"><i class="fa-solid fa-phone"></i><span
                  class="hover-moving-primary">+971 542188600</span></a></li>
            <li><a href="mailto:info@haripalletmachinery.com"><i class="fa-regular fa-envelope"></i><span
                  class="hover-moving-primary">info@haripalletmachinery.com</span></a></li>
          </ul>
          <ul class="mt--20">
            <li><a href="#"><i class="fa-solid fa-location-crosshairs"></i><span class="hover-moving-primary">Near
                  Sharjah
                  Gas Plant Al sajja Industrial Area Sharjah,</span></a></li>
            <li><a href="#"><i class="fa-regular fa-clock-three"></i>(Mon–Sat: 9:00–6:00)</a></li>
          </ul>
        </div>

        <nav class="side-bar-nav">
          <ul class="navbar-nav">
            <li>
              <a class="hover-moving-primary" href="index.html">Home</a>
            </li>
            <li>
              <a class="hover-moving-primary" href="about.html">About Us</a>
            </li>
            <li>
              <a class="hover-moving-primary" href="services.html">Services</a>
            </li>
            <li>
              <a class="hover-moving-primary" href="products.html">Products</a>
            </li>
            <li>
              <a class="hover-moving-primary" href="contact.html">Contact Us</a>
            </li>
          </ul>
        </nav>

        <div class="social-share-wrapper-1">
          <span class="short-title">Find With Us</span>
          <ul class="social-stylle-one justify-content-start mt--20">
            <li><a href="#"><i class="fa-brands fa-facebook-f"></i></a></li>
            <li><a href="#"><i class="fa-brands fa-twitter"></i></a></li>
            <li><a href="#"><i class="fa-brands fa-instagram"></i></a></li>
            <li><a href="#"><i class="fa-brands fa-linkedin-in"></i></a></li>
          </ul>
        </div>

      </div>
    </div>
  </div>

  <div class="mouse-cursor cursor-outer"></div>
  <div class="mouse-cursor cursor-inner"></div>

  <div id="anywhere-home" class=""></div>

  
  <div id="elevate-load">
    
  </div>
  

  <div class="scrollbar-v show-on-scroll"></div>
  <div class="float-text show-on-scroll">
    <span><a href="#">Scroll to top</a></span>
  </div>

 <!-- HPM Floating Contact Actions -->
  <div class="hpm-floating-contact-left" aria-label="whatsapp Hari Pallet Machinery">
    <!-- Left: WhatsApp -->
    <a class="hpm-whatsapp" href="mailto:info@haripalletmachinery.com" target="_blank" rel="noopener noreferrer"
      aria-label="WhatsApp Hari Pallet Machinery" title="mail">
      <i class="fa fa-envelope" aria-hidden="true"></i>
    </a>
  </div>

  <div class="hpm-floating-contact-right" aria-label="Contact Hari Pallet Machinery">
    <!-- Right: Call -->
    <a class="hpm-call" href="tel:+971542188600" aria-label="Call Hari Pallet Machinery" title="Call us">
      <i class="fas fa-phone" aria-hidden="true"></i>
    </a>
  </div>
  <!-- HPM Floating Contact Actions -->

  <!-- jquery js -->
  <script src="assets/js/plugins/jquery-3.7.1.min.js" defer></script>
  <!-- jquery ui -->

  <script src="assets/js/vendor/jqueryui.js" defer></script>

  <!-- swiper js -->
  <script src="assets/js/plugins/swiper.js" defer></script>
  <!-- metismenu js -->
  <script src="assets/js/plugins/metismenu.js" defer></script>
  <!-- waypoint js -->
  <script src="assets/js/vendor/waypoint.js" defer></script>
  <!-- waw -->
  <script src="assets/js/vendor/waw.js" defer></script>
  <!-- jquery ui js -->

  <!-- magnifying ui js -->
  <script src="assets/js/plugins/magnific-popup.js" defer></script>
  <!-- sal animation -->
  <script src="assets/js/vendor/sal.min.js" defer></script>
  <!-- bootstrap JS -->
  <script src="assets/js/plugins/bootstrap.min.js" defer></script>
  <!-- contact form js -->
  <script src="assets/js/plugins/contact-form.js" defer></script>
  <!-- Smooth Scroll js -->
  <script src="assets/js/plugins/smooth-scroll.js" defer></script>


  <!-- gsap js -->
  <!-- <script src="assets/js/plugins/gsap.min.js" defer></script>
    <script src="assets/js/plugins/scrolltigger.js" defer></script>
    <script src="assets/js/plugins/split-text.js" defer></script> -->


  <script src="assets/js/plugins/scroll-paralax.js" defer></script>

  <!-- odometer js -->
  <script src="assets/js/plugins/odometer.js" defer></script>
  <!-- main js -->
  <script src="assets/js/main.js" defer></script>

  <!-- header style two End -->

  <div class="hpm-modal" id="productModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="hpm-modal-backdrop" data-close-modal></div>
    <div class="hpm-modal-panel"><button type="button" class="hpm-modal-close" aria-label="Close product details" data-close-modal>×</button>
      <div id="modalContent">
        <p>Loading product details…</p>
      </div>
    </div>
  </div>
  <script>
    window.HPM_PRODUCT_API = 'api/product.php';
  </script>
  <script src="assets/js/hpm-commerce.js?v=20261109-spec-bullets2" defer></script>
  <script src="assets/js/hpm-site-polish.js" defer></script>
  <script src="assets/js/hpm-products-navigation.js" defer></script>
  <script src="assets/js/hpm-global-navigation.js?v=20261115-dynamic-categories" defer></script>
</body>

</html>