# थिम फाइल प्याच शीट — फाइल-दर-फाइल (कपी-पेस्ट)

यहाँ अडिटमा औंल्याइएका प्रत्येक फाइलको काम छ। सबै परिवर्तन **cPanel → File Manager → Edit** बाट गर्न मिल्छ।

**सम्पादन अघि नियम:**
1. ब्याकअप लिएको हुनुपर्छ (गाइडको चरण ०)।
2. एक पटकमा एउटा फाइल — Save गरेपछि साइट खोलेर जाँच्नुहोस्।
3. `<?php` लाइन नबिर्सनुहोस्; encoding UTF-8 (BOM बिना)।
4. लाइन नम्बर अडिटको अनुमानित हो — फाइलको आकार फरक भए सन्दर्भ वाक्य (जस्तै `get_cat_ID`) कै आधारमा खोज्नुहोस्।

**सामग्री खोज्ने सजिलो तरिका:** टुलकिटको रिपोर्टमा "फाइल — लाइन — कोड" तालिका छ; त्यही लाइन नम्बरमा File Manager को Edit मा पुग्नुहोस् (Ctrl+F चल्छ, कहिलेकाहीँ Ctrl+G पनि)।
cPanel → **Terminal** छ भने यी आदेशले पनि भेट्छ:

```bash
cd ~/public_html/wp-content/themes/palikalive
grep -rn "get_cat_ID\|get_category_link" .          # १.१
grep -rn "href=\"\"" .                              # खाली लिङ्क
grep -rn "add_action( *'wp_head'\|add_action(\"wp_head\"" .   # SEO होक्स
grep -rn "sandesh_track_views\|update_post_meta.*view" .      # भ्यु काउन्टर
grep -rn "logo.png\|lazy.png" .                     # asset बाटो
grep -Ln "ABSPATH" $(find . -name "*.php")          # guard नभएका फाइल
```

---

## १. `arthapalika.php` — "अर्थ पालिका" सेक्सनको खाली लिङ्क (लाइन ~७६–७७)

**खोज्नुहोस्:** `$cat1` र `get_cat_ID` भएको भाग।

```php
// ❌ पहिले
$cat1 = 'अर्थ पालिका';                                     // वा जुनसुकै मान लेखिएको छ
$cat1_link = get_category_link( get_cat_ID( $cat1 ) );
```

```php
// ✅ पछि — slug प्रयोग गर्ने, कहिल्यै खाली नहुने
$cat1 = 'economy';                                         // अर्थ पालिका को slug
$cat1_link = get_term_link( $cat1, 'category' );
if ( is_wp_error( $cat1_link ) ) {
	$cat1_term = get_term_by( 'name', $cat1, 'category' );
	$cat1_link = $cat1_term ? get_term_link( $cat1_term ) : '';
}
if ( is_wp_error( $cat1_link ) || '' === $cat1_link ) {
	$cat1_link = home_url( '/' );
}
```

**जाँच:** होमपेजको "अर्थ पालिका" शीर्षक र "सबै" दुवै `/content/economy/` मा जानुपर्छ।

---

## २. `palikabarta.php` — "पालिका वार्ता" सेक्सन (लाइन ~७४)

यहाँ **जड यही हो:** होमपेज शीर्षक `पालिका वार्ता` (व) तर श्रेणीको नाम `पालिका बार्ता` (ब) — नाम नमिल्दा `get_cat_ID()` ले `0` दियो, अनि लिङ्क खाली भयो।

```php
// ✅ पछि
$cat1 = 'interview';                                       // पालिका वार्ता (श्रेणी नाम अझै 'बार्ता' छ) को slug
$cat1_link = get_term_link( $cat1, 'category' );
if ( is_wp_error( $cat1_link ) ) {
	$cat1_term = get_term_by( 'name', $cat1, 'category' );
	$cat1_link = $cat1_term ? get_term_link( $cat1_term ) : '';
}
if ( is_wp_error( $cat1_link ) || '' === $cat1_link ) {
	$cat1_link = home_url( '/' );
}
```

**साथै सिफारिस:** श्रेणीको नाम नै ठीक गर्नुहोस् — Posts → Categories → "पालिका बार्ता" → नाम `पालिका वार्ता` (slug `interview` जस्तै राख्नुहोस्, URL फेरिँदैन)। यसपछि शीर्षक र श्रेणी एउटै देखिन्छ।

---

## ३. `5prasna.php` — "५ प्रश्न" सेक्सन (लाइन ~७०)

होमपेज शीर्षक `५ प्रश्न`, श्रेणीको नाम `हाम्रो प्रश्न` — मिलेन।

```php
// ✅ पछि
$cat1 = '5-questions';                                     // हाम्रो प्रश्न को slug
$cat1_link = get_term_link( $cat1, 'category' );
if ( is_wp_error( $cat1_link ) ) {
	$cat1_term = get_term_by( 'name', $cat1, 'category' );
	$cat1_link = $cat1_term ? get_term_link( $cat1_term ) : '';
}
if ( is_wp_error( $cat1_link ) || '' === $cat1_link ) {
	$cat1_link = home_url( '/' );
}
```

**नोट:** श्रेणी नाम `हाम्रो प्रश्न` जस्तै रहन दिनुहोस् वा मेनु/साइटमा एकरूपता ल्याउन `५ प्रश्न` बनाउनुहोस् — slug फेरिँदैन।

---

## ४. `janapratinidhi-5prasna.php` (लाइन ~७३–७४)

यो सेक्सन होमपेजमा छैन, तर त्यही बग छ (भविष्यमा प्रयोग गर्दा देखिने)। त्यसैले यहाँ पनि माथिको ढाँचा लगाउनुहोस् — यो सेक्सन कुन श्रेणी देखाउने हो त्यही slug राख्नुहोस् (प्रायः `5-questions`)।

**छिटो समाधान:** यी चारै फाइलमा `get_cat_ID(` र `get_category_link(` भेटिएको सबै लाइनलाई माथिको ढाँचाले बदल्नुहोस्। यसपछि जाँच्नुहोस्:

```bash
grep -rn "get_cat_ID" .     # केही देखिनु हुँदैन
```

> **वैकल्पिक (० सम्पादन):** mu-plugin को `empty_href` ले खाली लिङ्क घरपृष्ठमा पुर्‍याउँछ — तर सही श्रेणी पेजमा पुर्‍याउन थिम सम्पादन नै चाहिन्छ।

---

## ५. `front-page.php` — होमपेजको h1 (लाइन: पहिलो सेक्सन अघि)

```php
<?php get_header(); ?>

<!-- ✅ यो थप्नुहोस् -->
<h1 class="palika-home-h1">Palika Live — स्थानीय तह, सुशासन र ताजा समाचार</h1>

<div class="home-sections">
	<?php get_template_part( 'headline' ); ?>
	...
```

CSS (Appearance → Customize → Additional CSS):

```css
.palika-home-h1{margin:12px 0 16px;padding:9px 14px;font-size:17px;line-height:1.5;font-weight:700;background:#f5f5f5;border-left:4px solid #c8102e}
```

**जाँच:** View Source मा `<h1` ठ्याक्कै १।

---

## ६. `theme-function.php` — भ्यु काउन्टर र पुरानो Open Graph

**६.१ भ्यु काउन्टर (`sandesh_track_views`):** यो फंक्सन `wp_head` मा जोडिएको छ र हरेक भ्युमा `update_post_meta` चलाउँछ।

```php
// ❌ पहिले
add_action( 'wp_head', 'sandesh_track_views' );
function sandesh_track_views() {
	if ( is_single() ) {
		$views = (int) get_post_meta( get_the_ID(), 'post_views_count', true );
		update_post_meta( get_the_ID(), 'post_views_count', $views + 1 );
	}
}
```

```php
// ✅ पछि — दुईमध्ये एउटा छान्नुहोस्
// (क) थिमको कोड हटाएर mu-plugin को beacon चलाउने (सिफारिस)
//     — माथिको add_action र function पूरै हटाउनुहोस्, अनि mu-plugin मा:
//       'views_beacon' => true, 'views_meta_key' => 'post_views_count',

// (ख) थिमकै कोड राख्नैपर्ने भए कम्तीमा bot र दोहोरो भ्रमण रोक्नुहोस्:
add_action( 'wp_head', 'sandesh_track_views' );
function sandesh_track_views() {
	if ( ! is_single() || is_preview() || wp_doing_ajax() ) {
		return;
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( $_SERVER['HTTP_USER_AGENT'] ) : '';
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|monitor|headless|lighthouse/', $ua ) ) {
		return; // bot/preview गन्दैन
	}
	$post_id = get_the_ID();
	$key     = 'viewed_' . $post_id;
	if ( ! empty( $_COOKIE[ $key ] ) ) {
		return; // एउटै पाठकले दोहोरो गनिएन
	}
	setcookie( $key, '1', time() + 6 * HOUR_IN_SECONDS, '/' );
	update_post_meta( $post_id, 'post_views_count', (int) get_post_meta( $post_id, 'post_views_count', true ) + 1 );
}
```

**६.२ पुरानो Open Graph कोड:** यही फाइलमा `og:title`, `og:description` जस्ता ट्याग छाप्ने कोड छ भने — Rank Math सक्रिय भएकोले यसलाई बन्द गर्नुहोस्:

```php
if ( ! palika_seo_plugin_active() ) {   // helper mu-plugin बाट आउँछ
	// ... थिमको पुरानो OG कोड यहीँ भित्र ...
}
```

---

## ७. `functions.php` — सेक्शन १०–१७ (SEO दोहोरो)

थिम यहाँ पनि meta/canonical/OG/schema छाप्छ (priority 4–5) — `inc/custom-field.php` (priority 1–2) सँग दोहोरो।

**कदम १:** ढाँचा एकै बनाउनुहोस् — `functions.php` मा सेक्शन १०–१७ का SEO फंक्सन/होक्स **पूरै** बेर्नुहोस्:

```php
if ( ! palika_seo_plugin_active() ) {

	// ... यहाँ पहिलेजस्तै सबै SEO add_action/add_filter लाइनहरू ...

}
```

**कदम २:** फाइलको माथिल्लो भागमा (वा थिममा कतै एकै ठाउँमा) यो helper सुरक्षित राख्नुहोस् (mu-plugin छ भने यो पनि आउँछ, दुई पटक नराख्नुहोस्):

```php
if ( ! function_exists( 'palika_seo_plugin_active' ) ) {
	function palika_seo_plugin_active() {
		return ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' )
			|| defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath\\Helper' ) );
	}
}
```

**कदम ३ (कोड नछोई छिटो):** mu-plugin सेटिङमा हटाउनुपर्ने फंक्सनका नाम राख्नुहोस् (टुलकिटले नाम देखाउँछ):

```php
'head_callbacks_to_remove' => 'palika_seo_meta, sandesh_og_tags',
```

**जाँच:** लेखमा View Source → `description`, `canonical`, `NewsArticle`, `og:title` प्रत्येक **१ मात्र**।

---

## ८. `inc/custom-field.php`

**८.१ Meta box र दोहोरो guard:** फाइल अन्त्यको `class_exists("RankMath")` जाँचलाई साझा helper ले बदल्नुहोस्; साथै SEO फंक्सनको सुरुमा:

```php
if ( palika_seo_plugin_active() ) {
	return;
}
```

**८.२ Schema fallback लोगो (फाइल छैन):** `assets/images/logo.png` भन्ने फाइल थिममा नै छैन — भाँचिएको बाटो:

```php
// ❌ पहिले
$logo = get_template_directory_uri() . '/assets/images/logo.png';

// ✅ पछि
$custom = get_theme_mod( 'custom_logo' );
$logo   = $custom ? wp_get_attachment_image_url( $custom, 'full' ) : 'https://palikalive.com/wp-content/uploads/2026/06/logo_.png';
```

**८.३ PDF अपलोड जाँच (लाइन ~११११):** फाइलनामको extension मात्र हेर्ने तरिका कमजोर छ।

```php
// ❌ पहिले: फाइलनामको अन्त्य हेरेर मात्र
$ext = strtolower( pathinfo( $_FILES['field']['name'], PATHINFO_EXTENSION ) );
if ( 'pdf' !== $ext ) {
	wp_die( 'PDF मात्र' );          // ⚠️ wp_die ले सम्पादकको सामग्री हराउँछ
}

// ✅ पछि: सामग्री (content) पनि जाँच्ने
$allowed = array( 'pdf' => 'application/pdf' );
$check   = wp_check_filetype_and_ext( $_FILES['field']['tmp_name'], $_FILES['field']['name'], $allowed );
if ( empty( $check['ext'] ) || 'pdf' !== strtolower( $check['ext'] ) ) {
	// error लाई notice मा राख्ने, wp_die नगर्ने
	set_transient( 'palika_pdf_error', 'PDF फाइल मान्य भएन।', 60 );
	return;
}
```
> सबैभन्दा राम्रो: WordPress को Media Library नै प्रयोग गर्नुहोस् (`media_handle_upload()`) — WP आफैंले जाँच्छ।

**८.४ Meta box अन्योल:** Rank Math सक्रिय रहेसम्म थिमको SEO meta box का फिल्ड प्रयोग हुँदैनन्। सम्पादकले गलत बक्स भर्नु नपरोस् भनेर थिमको meta box लुकाउनु राम्रो:

```php
if ( palika_seo_plugin_active() ) {
	remove_action( 'add_meta_boxes', 'your_theme_seo_metabox' );   // थिमको वास्तविक फंक्सन नाम
}
```

---

## ९. `skip.php` — इन्टरस्टिसियल विज्ञापन

फाइलको सुरुमा (`<?php` पछि) थप्नुहोस्:

```php
// १) लेख हेर्दा पूरा-पर्दा विज्ञापन नदेखाउने
if ( is_singular( 'post' ) ) {
	return;
}

// २) एउटै सेसनमा एकै पटक मात्र
if ( ! empty( $_COOKIE['palika_intro_seen'] ) ) {
	return;
}
setcookie( 'palika_intro_seen', '1', time() + 12 * HOUR_IN_SECONDS, '/' );
```

साथै **a11y:** `role="dialog" aria-modal="true"` राखिएको छ भने इन्टरस्टिसियल खुल्दा बन्द बटनमा focus पुर्‍याउनुहोस् र Esc ले बन्द हुने बनाउनुहोस्।

---

## १०. `header.php` — getElementById override र AbortError निल्ने कोड

**समस्या:** थिमले `document.getElementById` आफैं बदलेर नभएका element बनाइदिन्छ र सबै `AbortError` चुपचाप निल्छ — यसले असली बग लुकाउँछ।

```javascript
// ❌ यस्तो खण्ड हटाउनुहोस्
var originalGetById = document.getElementById;
document.getElementById = function (id) {
	if (!originalGetById.call(document, id)) {
		var fake = document.createElement('div');
		fake.id = id;
		document.body.appendChild(fake);
		return fake;
	}
	return originalGetById.call(document, id);
};
window.addEventListener('unhandledrejection', function (e) {
	if (e.reason && e.reason.name === 'AbortError') { e.preventDefault(); }
});
```

**असली समाधान (`assets/js/custom.js`):**
1. जुन element खोजिँदैछ त्यो वास्तवमै छ कि छैन पहिले जाँच्नुहोस्:

```javascript
var el = document.getElementById('my-element');
if (el) {
	// काम
}
```
2. `AbortError` अाएको ठाउँमा सोही request को `AbortController` प्रयोग गरी इरादा अनुसार मात्र निल्नुहोस्:

```javascript
fetch(url, { signal: controller.signal })
	.catch(function (err) {
		if (err.name === 'AbortError') { return; }   // इरादा अनुसार रद्द भएको
		console.error(err);                          // बाँकी error लुकाउनु हुँदैन
	});
```

---

## ११. `video.php`, `single-old.php`, `single-writters.php`

- **`video.php`:** "delete after fixing" भनिएको admin debug भाग (जस्तै `if ( current_user_can('manage_options') ) { echo '<pre>'; print_r(...); }`) हटाउनुहोस्।
- **`single-old.php`** (३२ KB, प्रयोगमा छैन): फिर्ता ल्याउनु नपर्ने पक्का भएपछि हटाउनुहोस्।
- **`single-writters.php`** (नाममै टाइपो): नाम `single-writers.php` बनाउनुहोस् र कोडमा `get_template_part( 'single-writters' )` भएको ठाउँ अद्यावधिक गर्नुहोस्:

```bash
grep -rn "single-writters" .    # कहाँ प्रयोग भएको छ हेर्ने
```

---

## १२. फन्ट र लाइब्रेरी (गति)

**फन्ट (७ → २):** `fonts.googleapis.com` भएको लाइन खोज्नुहोस् (प्रायः `header.php`):

```html
<!-- ❌ पहिले: ७ परिवार -->
<link href="https://fonts.googleapis.com/css?family=Arya|Ek+Mukta|Kalam|Khand|Mukta|Niramit|Roboto" rel="stylesheet">

<!-- ✅ पछि: Mukta (मुख्य पाठ) + एउटा डिस्प्ले फन्ट -->
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700&family=Khand:wght@500;700&display=swap" rel="stylesheet">
```

**लाइब्रेरी सर्तभित्र:** Bootstrap, Popper, Owl, Magnific, Colorbox, Datepicker — टुलकिटको "इन्क्युइ गरिएका script/style" सूचीबाट फंक्सन नाम लिएर:

```php
if ( is_singular( 'post' ) ) {
	wp_enqueue_style( 'colorbox' );
	wp_enqueue_script( 'colorbox' );
}
```

**Lazy tस्बिर (JS नचल्दा पनि देखिने):** LiteSpeed Cache → Lazy Load ON गरिसकेपछि mu-plugin सेटिङ:

```php
'native_lazy' => true,
```

---

## १३. सामान्य जाँच सूची (परिवर्तनपछि)

- [ ] होमपेज खुल्छ, चारै "सबै" लिङ्क सही श्रेणीमा
- [ ] होमपेजमा `<h1` ठ्याक्कै १
- [ ] लेखमा `description`, `canonical`, `NewsArticle` प्रत्येक १
- [ ] लेखको फिचर्ड इमेज + preload
- [ ] इन्टरस्टिसियल: लेखमा देखिँदैन, होममा एकै पटक
- [ ] मोबाइलमा मेनु, टिकर, कार्ड सबै ठीक
- [ ] `grep -rn "get_cat_ID" .` → खाली
- [ ] View Source मा PHP error/warning छैन
