# Theme Snippets — copy/paste code (English)

यी स्निपेट **चरण ६** मा प्रयोग हुन्छन् (पहिले `palikalive-fixes.php` चलाएर साइट स्थिर भएपछि)। प्रत्येक स्निपेटमा — **किन**, **कुन फाइल**, **कोड**, **जाँच** — चार कुरा छन्।

**सम्पादन गर्ने तरिका:** cPanel → File Manager → फाइलमा राइट-क्लिक → Edit → Save Changes। एक पटकमा एउटा स्निपेट, Save गरेपछि साइट खोलेर जाँच्नुहोस्।

---

## S1. Section links — the real fix for the empty "सबै" links

**किन:** `get_cat_ID()` matches a category **name**. The Nepali heading text and the real category name differ (e.g. heading `पालिका वार्ता` vs category `पालिका बार्ता`), so it returns `0` and `get_category_link(0)` returns an empty string. Using the **slug** removes the mismatch forever.

**फाइलहरू:** `arthapalika.php` (~line 76), `palikabarta.php` (~74), `5prasna.php` (~70), `janapratinidhi-5prasna.php` (~73)

**Before**
```php
$cat1      = 'अर्थ पालिका';                                  // category NAME - fragile
$cat1_link = get_category_link( get_cat_ID( $cat1 ) );       // empty string when it fails
```

**After** (same pattern in all four files)
```php
$cat1      = 'economy';                                      // category SLUG - stable
$cat1_link = get_term_link( $cat1, 'category' );

if ( is_wp_error( $cat1_link ) ) {
	$cat1_term = get_term_by( 'name', $cat1, 'category' );
	$cat1_link = $cat1_term ? get_term_link( $cat1_term ) : '';
}
if ( is_wp_error( $cat1_link ) || '' === $cat1_link ) {
	$cat1_link = home_url( '/' );                            // never leave an empty href
}
```

| File | Section heading | Slug to use |
|---|---|---|
| `arthapalika.php` | अर्थ पालिका | `economy` |
| (विचार section file) | विचार | `opinion` |
| `palikabarta.php` | पालिका वार्ता | `interview` |
| `5prasna.php` | ५ प्रश्न | `5-questions` |
| `janapratinidhi-5prasna.php` | (not on homepage) | `5-questions` |

**जाँच:** `grep -rn "get_cat_ID" .` → कुनै नतिजा नआउनुपर्छ। होमपेजमा चारै "सबै" मा राइट-क्लिक → Copy link → `/content/economy/`, `/content/opinion/`, `/content/interview/`, `/content/5-questions/` देखिनुपर्छ।

---

## S2. Homepage H1 — theme version (optional)

**किन:** A homepage needs one H1. The mu-plugin adds it already; use this only if you prefer the markup in the theme itself (then turn `home_h1` off in the mu-plugin).

**फाइल:** `front-page.php` — right after `get_header();`
```php
<?php get_header(); ?>

<h1 class="palika-home-h1">Palika Live — स्थानीय तह, सुशासन र ताजा समाचार</h1>
```
**CSS** (Appearance → Customize → Additional CSS):
```css
.palika-home-h1{margin:12px 0 16px;padding:9px 14px;font-size:17px;line-height:1.5;font-weight:700;background:#f5f5f5;border-left:4px solid #c8102e}
```
**जाँच:** View Source → `<h1` ठ्याक्कै एक पटक।

---

## S3. Interstitial ad — no ad on articles, once per session

**किन:** A full-screen ad 10 seconds on every page hurts engagement and risks Google's intrusive-interstitial policy. This keeps the revenue on section pages but protects article readers.

**फाइल:** `skip.php` — सुरुमा, `<?php` पछि
```php
// 1) Never show the full-screen ad on article pages.
if ( is_singular( 'post' ) ) {
	return;
}

// 2) Show it only once per browser session.
if ( ! empty( $_COOKIE['palika_intro_seen'] ) ) {
	return;
}
setcookie( 'palika_intro_seen', '1', time() + 12 * HOUR_IN_SECONDS, '/' );
```
**जाँच:** नयाँ incognito window → होमपेजमा एकै पटक; अर्को सेक्सनमा जाँदा देखिँदैन; लेखमा कहिल्यै नदेखिने। साथै: बन्द बटनमा focus र Esc ले बन्द हुने बनाउनुहोस् (a11y)।

---

## S4. PDF upload — validate content, not just the file name

**किन:** Checking the extension alone can be bypassed. `wp_check_filetype_and_ext()` checks the real content. Also: `wp_die()` inside `save_post` throws away everything the editor typed — use a notice instead.

**फाइल:** `inc/custom-field.php` (~line 1111)

**Before**
```php
$ext = strtolower( pathinfo( $_FILES['your_field']['name'], PATHINFO_EXTENSION ) );
if ( 'pdf' !== $ext ) {
	wp_die( 'Only PDF allowed' );        // editor loses the whole post
}
```

**After**
```php
$allowed = array( 'pdf' => 'application/pdf' );
$check   = wp_check_filetype_and_ext(
	$_FILES['your_field']['tmp_name'],
	$_FILES['your_field']['name'],
	$allowed
);

if ( empty( $check['ext'] ) || 'pdf' !== strtolower( $check['ext'] ) ) {
	set_transient( 'palika_pdf_error', 'PDF फाइल मान्य भएन।', 60 );
	return;                               // keep the editor's work
}
```
**जाँच:** `.txt` फाइललाई `.pdf` नाम दिएर अपलोड गर्दा अस्वीकार हुनुपर्छ; राम्रो PDF जानुपर्छ।

---

## S5. Missing logo fallback + OG image

**किन:** `assets/images/logo.png` does not exist in the theme, so schema/OG fall back to a broken path. Use the site logo set in WordPress, and set a proper 1200×630 Open Graph image.

**फाइल:** `inc/custom-field.php` (जहाँ `logo.png` छ)
```php
// Before: get_template_directory_uri() . '/assets/images/logo.png'
$custom_logo_id = get_theme_mod( 'custom_logo' );
$logo_url       = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
$logo_url       = $logo_url ? $logo_url : 'https://palikalive.com/wp-content/uploads/2026/06/logo_.png';
```
**WordPress admin:** Rank Math → Titles & Meta → Global Meta → **OpenGraph Thumbnail** → 1200×630 को फोटो राख्नुहोस्।
**जाँच:** View Source मा `logo.png` भन्ने भाँचिएको बाटो नदेखिनुपर्छ।

---

## S6. Fonts — 7 families down to 2

**किन:** 7 families / ~30 weights = ठूलो download; प्रायः Mukta मात्र प्रयोग हुन्छ।

**फाइल:** `header.php` (वा जहाँ `fonts.googleapis.com` छ)
```html
<!-- Before -->
<link href="https://fonts.googleapis.com/css?family=Arya|Ek+Mukta|Kalam|Khand|Mukta|Niramit|Roboto" rel="stylesheet">

<!-- After -->
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700&family=Khand:wght@500;700&display=swap" rel="stylesheet">
```
**जाँच:** पूरा होमपेज र एउटा लेख स्क्रोल गरी लेआउट/फन्ट पहिलेजस्तै छ कि छैन हेर्नुहोस्। कुनै हेडिङको फन्ट फरक देखिए (Khand चाहिँदैन भने) त्यो परिवार मात्र फिर्ता राख्नुहोस्।

---

## S7. Load libraries only where they are used

**किन:** Bootstrap, Popper, Owl, Magnific, Colorbox, datepicker सबै पेजमा झिकिइन्छ — पहिलो लोड ढिलो हुन्छ।

**फाइल:** `functions.php` (जहाँ `wp_enqueue_script` लाइनहरू छन्)
```php
// Before: everywhere
wp_enqueue_style( 'colorbox' );
wp_enqueue_script( 'colorbox' );

// After: only where it is needed
if ( is_singular( 'post' ) || is_page_template( 'gallery.php' ) ) {
	wp_enqueue_style( 'colorbox' );
	wp_enqueue_script( 'colorbox' );
}
```
कुन लाइब्रेरी कुन पेजमा चाहिन्छ आफैं हेर्नुहोस्:
| Library | कहाँ चाहिन्छ (सामान्यतः) |
|---|---|
| Colorbox / Magnific | लेखभित्र फोटो जुम गर्ने, ग्यालरी पेज |
| Owl Carousel | होमपेज/सेक्सन स्लाइडर जहाँ छ |
| Datepicker | मात्र फारम/अपोइन्टमेन्ट पेज |
| Bootstrap JS | dropdown/modal/tab भएका पेज |
**जाँच:** टेस्ट गरेको पेजमा सबै काम गर्छ कि (स्लाइडर चल्ने, फोटो जुम हुने) हेर्नुहोस्; टुट्यो भने त्यो लाइब्रेरीको सर्त फिर्ता थप्नुहोस्।

---

## S8. Remove the `getElementById` override (hides real bugs)

**किन:** थिमले `document.getElementById` बदलेर नभएका element बनाइदिन्छ र सबै `AbortError` निल्छ — यसले असली बग लुकाउँछ।

**फाइल:** `header.php` — यो जस्तो खण्ड **हटाउनुहोस्**
```javascript
// DELETE this block
var originalGetById = document.getElementById;
document.getElementById = function (id) { /* fabricates fake elements */ };
window.addEventListener('unhandledrejection', function (e) {
	if (e.reason && e.reason.name === 'AbortError') { e.preventDefault(); }
});
```
**असली समाधान — `assets/js/custom.js`**
```javascript
// 1) Guard the element you are looking for
var node = document.getElementById('my-element');
if (node) {
	node.addEventListener('click', handler);
}

// 2) Only swallow an AbortError you caused yourself
fetch(url, { signal: controller.signal })
	.catch(function (error) {
		if (error.name === 'AbortError') { return; }   // cancelled on purpose
		console.error(error);                          // never hide real errors
	});
```
**जाँच:** ब्राउजर Console खोलेर होमपेज र लेख चलाउनुहोस् — लाल error देखिए त्यो असली बग हो, हटाउनुहोस् (अब लुक्दैन)।

---

## S9. Remove the debug block and unused files

**फाइल:** `video.php`
```php
// DELETE block like this:
if ( current_user_can( 'manage_options' ) ) {
	echo '<pre>';
	print_r( $some_variable );
	echo '</pre>';
}
```
**Terminal आदेश** (cPanel → Terminal, या File Manager बाट Delete/Rename)
```bash
cd ~/public_html/wp-content/themes/palikalive

# Is single-old.php used anywhere?
grep -rn "single-old" .            # केही नदेखिए delete गर्न सकिन्छ

# Rename the misspelled file, then update every reference
grep -rn "single-writters" .
mv single-writters.php single-writers.php
```
**जाँच:** फाइल हटाए/नाम फेरेपछि सम्बन्धित पेज खोलेर 500 error आउँदैन भनी पुष्टि गर्नुहोस्।

---

## S10. Article cards — alt text + named links (accessibility)

**किन:** Lighthouse "links do not have a discernible name" — तस्बिर मात्र भएको लिङ्कले स्क्रिन-रिडरमा कुनै नाम पाउँदैन। (mu-plugin ले पनि यही काम गर्छ, तर टेम्प्लेटमै यो शुद्ध तरिका हो।)

**फाइल:** `content.php` / `headline.php` (कार्ड लूप)
```php
<?php if ( has_post_thumbnail() ) : ?>
	<a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
		<?php
		the_post_thumbnail(
			'medium',
			array(
				'alt'     => esc_attr( get_the_title() ),
				'loading' => 'lazy',
			)
		);
		?>
	</a>
<?php endif; ?>
```
**जाँच:** Chrome → F12 → Lighthouse → Accessibility चलाउनुहोस् — "link-name" समस्या हट्नुपर्छ।

---

## S11. Share buttons and footer badges

**किन:** Viber बटनको लिङ्क खाली छ; Android/iOS ब्याज होमपेजमा गएको छ।

**फाइल:** single post template + `footer.php`
```php
<?php
$page_url  = rawurlencode( get_permalink() );
$page_name = rawurlencode( get_the_title() );
?>
<!-- Viber -->
<a class="share-viber" target="_blank" rel="noopener nofollow"
	href="<?php echo esc_url( 'viber://forward?text=' . $page_name . '%20' . $page_url ); ?>">Viber</a>

<!-- X / Twitter -->
<a class="share-x" target="_blank" rel="noopener nofollow"
	href="<?php echo esc_url( 'https://twitter.com/intent/tweet?text=' . $page_name . '&url=' . $page_url ); ?>">X</a>
```
**Footer app badges:** एप छैन भने ब्याज हटाउनुहोस्; छ भने `href` मा Play Store / App Store को सही URL राख्नुहोस्।
```html
<a href="https://play.google.com/store/apps/details?id=YOUR_APP_ID" target="_blank" rel="noopener">Android</a>
<a href="https://apps.apple.com/app/idYOUR_APP_ID" target="_blank" rel="noopener">iOS</a>
```

---

## S12. `wp-config.php` hardening

**किन:** लाइभ साइटमा debug बन्द, admin बाट PHP सम्पादन बन्द, र Edit/Install लाई सीमित गर्ने।

**फाइल:** `public_html/wp-config.php` (`/* That's all, stop editing! */` भन्दा माथि)
```php
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );      // turns off theme/plugin file editor
define( 'WP_POST_REVISIONS', 10 );         // limits revisions, keeps the database lighter
define( 'AUTOMATIC_UPDATER_DISABLED', false ); // keep auto minor updates ON (safer default)
```
**जाँच:** admin → Appearance → Theme File Editor हराउनुपर्छ; साइट सामान्य चल्नुपर्छ।

---

## S13. `functions.php` — one SEO guard (if you do not use the mu-plugin)

**किन:** थिमले Rank Math/Yoast सँगै meta, canonical, OG र schema छाप्दा दोहोरिन्छ। (mu-plugin को `seo_dedupe` ले यो स्वचालित गर्छ — यो हातैले गर्ने विकल्प हो।)

**फाइल:** `functions.php` (माथिल्लो भागमा helper, अनि सेक्शन १०–१७ भित्र)
```php
// Helper - once, at the top of the file
if ( ! function_exists( 'palika_seo_plugin_active' ) ) {
	function palika_seo_plugin_active() {
		return ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' )
			|| class_exists( 'RankMath' ) || class_exists( 'WPSEO_Frontend' ) );
	}
}

// Wrap the theme's SEO output
if ( ! palika_seo_plugin_active() ) {

	// ... existing add_action( 'wp_head', 'theme_seo_meta' ); etc. stay inside ...

}
```
**जाँच:** लेखमा View Source → `description`, `canonical`, `NewsArticle`, `og:title` प्रत्येक **१ मात्र**।

---

## S14. `theme-function.php` — stop the per-view database write (if you do not use the mu-plugin)

**फाइल:** `theme-function.php`
```php
// DELETE the old hook entirely:
// add_action( 'wp_head', 'sandesh_track_views' );
// function sandesh_track_views() { ... update_post_meta( ... ); }

// The mu-plugin now counts views - keep only this helper if other code needs the number:
function sandesh_get_views( $post_id = 0 ) {
	$post_id  = $post_id ? $post_id : get_the_ID();
	$meta_key = get_option( 'palika_detected_views_meta_key', 'post_views_count' );
	return (int) get_post_meta( $post_id, $meta_key, true );
}
```
**जाँच:** एउटा लेख खोल्नुहोस् → phpMyAdmin → `wp_postmeta` मा त्यो `post_id` को `post_views_count` ५ सेकेन्डपछि +१ हुनुपर्छ (र पेज क्यास गरिए पनि)।
