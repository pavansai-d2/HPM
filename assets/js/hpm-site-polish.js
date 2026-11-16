/*
 * HPM shared menu behaviour - intentionally empty.
 *
 * This file used to contain a hard-coded list of the first four product
 * categories and built a second "Products" dropdown from it. That list
 * covered the live category dropdown, so any category added later in the
 * admin panel (for example "Repairing & Recycling Machines") was hidden on
 * laptop/desktop screens.
 *
 * Product categories now come ONLY from the database through
 * assets/js/hpm-global-navigation.js (api/categories.php).
 * The file is kept so existing <script> tags keep working.
 */
