-- =====================================================================
-- Palika Live — cleanup of fake share numbers and leftover ShareThis
-- blocks. READ THE WHOLE FILE BEFORE RUNNING ANYTHING.
--
-- Table prefix: these examples assume the prefix is  wp_  (so the tables
-- are wp_posts / wp_postmeta). If your wp-config.php $table_prefix is
-- different (e.g. wppl_), replace every wp_ below with your prefix.
--
-- ALWAYS take a database backup first (cPanel → Backup, or
-- "mysqldump", or UpdraftPlus). Run on staging first if possible.
--
-- The WordPress CLI (WP-CLI) versions are safer and are listed at the
-- bottom; use them instead if your host provides WP-CLI.
-- =====================================================================


-- ---------------------------------------------------------------------
-- STEP A. See which articles contain the leftover ShareThis block
-- ---------------------------------------------------------------------
SELECT ID, post_title
FROM wp_posts
WHERE post_type = 'post'
  AND post_status = 'publish'
  AND post_content LIKE '%sharethis-inline-share-buttons%';


-- ---------------------------------------------------------------------
-- STEP B. Remove the leftover ShareThis block from all articles.
-- The stored block currently looks like this (note the spacing):
--   <div style="margin-top: 0px; margin-bottom: 0px;" class="sharethis-inline-share-buttons" ></div>
-- It may also appear with slightly different attributes in older posts,
-- so two REPLACE statements are provided.
-- ---------------------------------------------------------------------
UPDATE wp_posts
SET post_content = REPLACE(
    post_content,
    '<div style="margin-top: 0px; margin-bottom: 0px;" class="sharethis-inline-share-buttons" ></div>',
    ''
)
WHERE post_type = 'post'
  AND post_content LIKE '%sharethis-inline-share-buttons%';

-- Catch any other attribute variations (MySQL 8.0+ / MariaDB 10.0.5+):
UPDATE wp_posts
SET post_content = REGEXP_REPLACE(
    post_content,
    '<div[^>]*class="sharethis-inline-share-buttons"[^>]*>[[:space:]]*</div>',
    ''
)
WHERE post_type = 'post'
  AND post_content LIKE '%sharethis-inline-share-buttons%';


-- ---------------------------------------------------------------------
-- STEP C. Find the meta key behind the fake "Shares" counter.
-- Replace 130321 with any news article's post ID (look at the article
-- URL via ?p=ID or the editor URL post.php?post=130321).
-- Look for a key named anything like: share_count, _share_count,
-- shares, post_shares, _post_shares, total_shares, views, _views...
-- ---------------------------------------------------------------------
SELECT meta_id, meta_key, meta_value
FROM wp_postmeta
WHERE post_id = 130321
ORDER BY meta_id;

-- Articles with the largest fake values (helps spot the key):
-- replace 'PUT_META_KEY_HERE' with the key you found.
-- SELECT post_id, meta_value FROM wp_postmeta
-- WHERE meta_key = 'PUT_META_KEY_HERE'
-- ORDER BY CAST(meta_value AS UNSIGNED) DESC
-- LIMIT 50;


-- ---------------------------------------------------------------------
-- STEP D. Reset/delete the fake counter values.
-- Use ONE of the two statements below.
--   * DELETE removes the key entirely (recommended if the template
--     tolerates a missing value).
--   * UPDATE sets every count to 0.
-- Replace PUT_META_KEY_HERE first.
-- ---------------------------------------------------------------------
-- DELETE FROM wp_postmeta WHERE meta_key = 'PUT_META_KEY_HERE';
-- UPDATE wp_postmeta SET meta_value = '0' WHERE meta_key = 'PUT_META_KEY_HERE';


-- ---------------------------------------------------------------------
-- STEP E. Clear cached pages after everything:
--   * Purge Cloudflare cache (Cloudflare dashboard → Caching →
--     Configuration → Purge Everything), and
--   * Purge your WordPress cache plugin / LiteSpeed cache /
--     WP Super Cache, otherwise old pages with fake numbers remain.
-- ---------------------------------------------------------------------


-- =====================================================================
-- SAFER WP-CLI ALTERNATIVES (run from the site root over SSH)
-- =====================================================================
--
-- # 1) Remove the ShareThis block from every post (serialization safe):
-- wp search-replace \
--   '<div style="margin-top: 0px; margin-bottom: 0px;" class="sharethis-inline-share-buttons" ></div>' \
--   '' wp_posts --precise --all-tables
--
-- # 2) Dry-run first, then delete the fake meta (replace the key):
-- wp post meta list 130321
-- wp db query "DELETE FROM wp_postmeta WHERE meta_key='PUT_META_KEY_HERE';"
--
-- # 3) Flush caches:
-- wp cache flush
-- wp litespeed-purge all   # only if LiteSpeed Cache is used
