# जनप्रतिनिधि (People's Representatives) homepage block is empty

## Problem (confirmed 2026-09-11)
- Homepage block "जनप्रतिनिधि" renders empty (white gap above the भिडियो block).
- Its "सबै" link `https://palikalive.com/content/on-behalf-of-our-people`
  redirects to `https://palikalive.com/on-behalf-of-our-people/` and shows
  **Page Not Found**.
- REST API check: category **जनप्रतिनिधि**, ID **4923**, slug
  `on-behalf-of-our-people`, **count = 0** — it is the ONLY category on the
  site with zero posts.
- The theme (1) shows no cards in a category block when the category has no
  posts, and (2) serves the 404 template for an empty category archive.

## Fix (no code — content fix)
Assign at least one published post to the category.

### A. Assign existing posts (fastest, bulk)
1. WP Admin → **Posts → All Posts**.
2. Tick the checkbox of each article about जनप्रतिनिधि (mayor/chair/ward
   representatives etc.).
3. **Bulk actions → Edit → Apply**.
4. In the bulk-edit panel, under **Categories** tick **जनप्रतिनिधि**
   (do NOT untick the posts' other categories).
5. Click **Update**.

### B. On a new post
- In the post editor's right sidebar → **Categories** panel → tick
  **जनप्रतिनिधि** → Publish/Update.

## After assigning
1. Purge cache: caching plugin (e.g. WP Super Cache / LiteSpeed) **Delete Cache**,
   then **Cloudflare → Caching → Purge Everything** (or Development Mode ON).
2. Hard refresh homepage with **Ctrl+F5**. The जनप्रतिनिधि block now fills
   automatically (latest posts from category 4923).
3. Open `https://palikalive.com/on-behalf-of-our-people/` — it must list the
   articles instead of "Page Not Found".
4. If it still shows 404 after posts exist: **Settings → Permalinks →
   Save Changes** (no need to change anything) to flush rewrite rules.

## If there is no जनप्रतिनिधि news yet
Hide the empty block instead of showing a blank gap:
**Appearance → Customize** (or the theme's Homepage/Front Page options) →
find the homepage section set to category जनप्रतिनिधि /
`on-behalf-of-our-people` → remove/disable that section → Publish. Re-enable
it once articles exist.

## Verification via API
```
https://palikalive.com/wp-json/wp/v2/categories/4923
```
`count` must be greater than 0.
