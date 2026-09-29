# Ad Engine समीक्षा — पहिलो खोज (अधुरो कोडको आधारमा)

तपाईंले पठाउनुभएको `PalikaLive Master Ad Engine (v46)` को **पहिलो दुई सेक्सन मात्र** आएको छ — `.pl-header-ad-flex img {` भन्ने CSS बीचमै रोकिएको छ। तलको समीक्षा **देखिने भागमा** आधारित छ।

---

## ⏱ आजै गर्ने तीन काम (जाँच २ मिनेट + सुधार १० मिनेट)

**(१) Live छ कि छैन पक्का गर्ने — तपाईंले "थाहा छैन" भन्नुभयो:**
`mu-plugins` फोल्डर भित्रको **हरेक `.php` फाइल स्वतः सक्रिय हुन्छ** (कुनै activation चाहिँदैन)। त्यसैले:
cPanel → File Manager → `wp-content/mu-plugins/` → त्यहाँ "Master Ad Engine" भएको फाइल छ भने **पक्का live छ**।

प्रमाण (२ मिनेट): Chrome → F12 → **Network** → पेज रिलोड → पहिलो (डकुमेन्ट) अनुरोधमा क्लिक → **Headers**:
- `cache-control: no-cache, must-revalidate, max-age=0` → क्यास बन्द छ (यो plugin चलिरहेको छ)
- `x-litespeed-cache: miss` (हरेक पटक) → उही कुरा
साथै WordPress → **LiteSpeed Cache → Dashboard** मा hit rate ~०% देखिन्छ।

**(२) सेक्सन १ को ब्लकलाई तलको "विकल्प B" कोडले बदल्ने** (फाइलको `.bak` कपी राखेर)। यसले सामान्य पाठकका लागि क्यास खोल्छ, ad परीक्षणका लागि `?pklv_ads_debug=1` छोड्छ।

**(३) LiteSpeed → Cache → TTL: Public Cache TTL = 3600 (१ घण्टा)** → ad/campaign प्रति घण्टा ताजा हुन्छ, तर क्यासको गति पनि पाइन्छ। त्यसपछि **Purge All** र फेरि Headers जाँच्नुहोस् (`x-litespeed-cache: hit` आउनुपर्छ)। कुनै ad पुरानो देखिए तुरुन्तै **Purge All** (१ क्लिक)।

> यही तीन काम v47 भन्दा अघि पनि पूरा सुरक्षित छन्, किनभने सेक्सन १ को कोड पूरै देखिएको छ।

---

## 🔴 समस्या १ (गम्भीर): यो plugin ले सबै पाठकका लागि LiteSpeed क्यास बन्द गर्छ

**कोड (सेक्सन १):**
```php
add_action( 'template_redirect', function() {
    if ( is_admin() || is_user_logged_in() ) return;
    if ( is_front_page() || is_home() || is_archive() || is_single() ) {
        do_action( 'litespeed_control_set_nocache', 'PalikaLive fresh ad delivery' );
        nocache_headers();
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
    }
}, 1 );
```

**यसले के गर्छ (नेपालीमा):** लगइन नगरेका **जोसुकै पाठक** का लागि होम, लेख, श्रेणी — सबै पेजको क्यास बन्द गरिदिन्छ। अर्थात् हरेक पेज हेराइमा WordPress ले सुरुदेखि PHP चलाएर पूरा पेज फेरि बनाउँछ।

**किन यो हानिकारक छ:**
- LiteSpeed Cache को सारा फाइदा खत्तम हुन्छ (मेरो चेकलिस्टको चरण ७ मा cache ON गर्नु भनेको यो plugin चलिरहेसम्म अर्थहीन हुन्छ)।
- मोबाइलमा पहिलो लोड ढिलो → Core Web Vitals (LCP/TTFB) बिग्रन्छ; Google रैंकिङमा असर।
- साझा होस्टिङमा CPU/RAM भार बढ्छ → 508/limit error को जोखिम।
- CDN/ExactDN तहमा पनि `nocache_headers()` ले क्यास रोक्छ।

**किन लेखिएको होला:** विज्ञापन "ताजा" देखाउन (हरेक लोडमा फरक ad)। तर यसको सही उपाय **क्यास बन्द गर्नु होइन** — तलका दुई विकल्पमध्ये एउटा प्रयोग गर्नुहोस्।

---

### ✅ विकल्प A (सिफारिस): क्यास खुला राख्ने + ad पाठकको ब्राउजरमै घुमाउने

क्यास भए पनि **प्रत्येक पेज लोडमा फरक ad** देखिन्छ, किनभने छनोट JavaScript ले गर्छ।

**गर्नुहोस्:** माथिको सेक्सन १ को पूरा `add_action( 'template_redirect', ... )` ब्लक **हटाउनुहोस्**।

अनि ad स्लटमा सबै सिर्जनात्मक (creatives) राखेर यो स्क्रिप्ट थप्नुहोस्:

```html
<!-- Ad slot: put every creative of this slot inside, JS picks one per page load -->
<div class="pl-banner-box" data-pklv-rotate>
    <a href="https://advertiser-a.com" data-pklv-ad><img src="/ads/a.jpg" alt=""></a>
    <a href="https://advertiser-b.com" data-pklv-ad><img src="/ads/b.jpg" alt=""></a>
    <a href="https://advertiser-c.com" data-pklv-ad><img src="/ads/c.jpg" alt=""></a>
</div>
```
```html
<script>
(function () {
  document.querySelectorAll('[data-pklv-rotate]').forEach(function (box) {
    var ads = box.querySelectorAll('[data-pklv-ad]');
    if (ads.length < 2) { return; }
    var pick = Math.floor(Math.random() * ads.length);
    ads.forEach(function (ad, index) {
      ad.style.display = (index === pick) ? '' : 'none';
    });
  });
}());
</script>
```

**फाइदा:** क्यास १००% चल्छ, ad प्रत्येक लोडमा फरक, सर्भरमा भार शून्य। नोट: क्लिक गणना/रिपोर्टिङ सर्भरमा हुनुपर्ने भए अलग क्लिक-ट्र्याकिङ लिङ्क (`/go/ad-id`) प्रयोग गर्नुहोस् — त्यो लिङ्कको पेज मात्र nocache हुनुपर्ने हो, पूरै साइट होइन।

---

### 🔧 विकल्प B: आजै लागू गर्न मिल्ने सुरक्षित संस्करण (नामसहित function + debug-only बाइपास)

अहिलेको ब्लकलाई यसैले बदल्नुहोस्। **क्यास सामान्य पाठकका लागि खुल्छ**; ad परीक्षण गर्दा मात्र बन्द हुन्छ।

```php
/**
 * Set true only while testing ad delivery (see ?pklv_ads_debug=1 below).
 */
if ( ! defined( 'PKLV_ADS_NOCACHE' ) ) {
	define( 'PKLV_ADS_NOCACHE', false );
}

/**
 * Invalidate page cache only for the ad tester, never for normal readers.
 */
function pklv_ads_cache_guard() {

	if ( is_admin() ) {
		return;
	}

	$is_ad_tester = isset( $_GET['pklv_ads_debug'] ) && current_user_can( 'manage_options' );

	if ( ! PKLV_ADS_NOCACHE && ! $is_ad_tester ) {
		return; // Normal readers: keep LiteSpeed cache working.
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	do_action( 'litespeed_control_set_nocache', 'PalikaLive ads debug' );
	nocache_headers();
}
add_action( 'template_redirect', 'pklv_ads_cache_guard', 1 );
```

**प्रयोग:**
- ad परीक्षण: `https://palikalive.com/?pklv_ads_debug=1` (admin लगइन अवस्थामा) → क्यास बन्द हुन्छ, ताजा ad देखिन्छ
- आपत्कालमा पूरै साइटको क्यास बन्द गर्नुपरे: `wp-config.php` मा `define( 'PKLV_ADS_NOCACHE', true );`
- `litespeed_control_set_nocache` नै मुख्य काम गर्ने API हो — `DONOTCACHEPAGE` यहाँ सहयोगी मात्र (timing नमिले पनि अप्सन A/B मा फरक पर्दैन)।

---

## 🟡 समस्या २: anonymous closure हरू — "Zero-Collision" तथापि नियन्त्रण गर्नै नसकिने

`add_action( 'template_redirect', function() { ... } )` जस्ता नाम-नभएका function लाई **कुनै अरू कोडले हटाउन सक्दैन** (नाम नै छैन)। त्यसैले "zero-collision" भनिए पनि आफ्नै फाइलबाट बाहिरबाट बन्द गर्ने उपाय छैन — समस्या आए `Edit` गर्नुको विकल्प छैन।

**सुधार:** नामसहित, प्रिफिक्स भएका function प्रयोग गर्नुहोस् (जस्तै `pklv_ad_*`) र व्यवहार नियन्त्रण गर्न constant राख्नुहोस्। विकल्प B को कोड नै यसको उदाहरण हो — `PKLV_ADS_NOCACHE` ले एक लाइनबाट व्यवहार फेर्छ।

**थप (v47 मा गर्ने):** पहिलो लाइनमै kill-switch राख्नुहोस्, जसले आपत्कालमा सबै ad बन्द गर्न सकियोस्:
```php
if ( ! defined( 'PKLV_ADS_ENABLED' ) ) {
	define( 'PKLV_ADS_ENABLED', true ); // wp-config.php मा false गर्दा सबै ad बन्द
}
```

---

## 🟡 समस्या ३: `nocache_headers()` पछि अरूले फेरि बदल्न सक्छन्

क्यास बन्द गर्ने निर्णय `template_redirect` (priority 1) मा भए पनि अन्य plugin ले पछि हेडर फेर्न सक्छन्। त्यसैले **परीक्षण गरेर पुष्टि गर्नुहोस्**:

**कसरी जाँच्ने:** ब्राउजर → F12 → **Network** → पेज रिलोड → पहिलो डकुमेन्ट अनुरोधमा क्लिक → **Headers** हेर्नुहोस्:
| हेडर | क्यास ON | क्यास OFF (अहिलेको अवस्था) |
|---|---|---|
| `x-litespeed-cache` | `hit` | `miss` हरेक पटक |
| `cache-control` | `public, max-age=...` | `no-cache, must-revalidate, max-age=0` |
साथै WordPress admin → **LiteSpeed Cache → Dashboard** मा hit rate हेर्नुहोस् (क्यास बन्द भए ~०%).

---

## अझै समीक्षा गर्न बाँकी (पूरा फाइल चाहिन्छ)

यी भाग आएपछि जाँच्नेछु:
- Header ad injection (`wp_head`/`header` hook), Article ads, Category slots, **Skip Ad (full-screen interstitial — अडिट १.५)**
- कुनै `admin-ajax.php` वा REST endpoint — **nonce + capability** जाँच छ कि छैन
- कुनै `$_GET`/`$_POST` सिधै SQL/echo मा गएको छ कि (SQL injection / XSS)
- `echo $ad_html` जस्ता ठाउँमा escaping (`esc_url`, `wp_kses_post`) छ कि छैन
- Ad block को settings/CSS/JS — केही पुरानो `getElementById` override जस्तो workaround छ कि छैन

**पठाउनुपर्ने:** `fixes/` मा राखिएको जस्तै — फाइलको बाँकी भाग। धेरै लामो भए **२ भागमा** पठाउनुहोस् (भाग १: सेक्सन १–५, भाग २: बाँकी), वा यी लाइनहरू भएको भाग मात्र: `add_action(`, `add_filter(`, `add_shortcode(`, `wp_ajax_`, `$_POST`, `$_GET`, `echo`, `<script`, `<style`, "Skip".

---

## यो फाइलसँग जोडिने दुई कुरा (मेरो किट)

1. **चरण ७ (LiteSpeed ON)** यो plugin चलिरहेसम्म काम गर्दैन — पहिले विकल्प A/B लागू गर्नुहोस्।
2. **Skip Ad** (पूरा पर्दा ओगट्ने इन्टरस्टिसियल) यही plugin बाट आउँछ भने `fixes/theme-snippets.md` को **S3** (थिमको `skip.php`) ले काम गर्ने छैन — त्यो नीति **यही Ad Engine भित्र** लागू गर्नुपर्छ, वा mu-plugin को `'interstitial_once' => true` + सही `interstitial_selectors` प्रयोग गर्नुपर्छ।

> ⚠️ परिवर्तन गर्नुअघि फाइलको कपी राख्नुहोस् (जस्तै `palikalive-ad-engine.php.bak-2026-09-29`)।

---

## 🟢 खण्ड २ समीक्षा — डाटाबेस इन्जिन, hooks र shortcodes (सेक्सन २ अन्त्य–५)

**सामान्य मूल्याङ्कन: राम्रो कोड।** यहाँ कुनै SQL injection छैन (raw SQL छैन, `WP_Query` प्रयोग भएको छ), escaping पनि ठीक छ (`esc_url` लिङ्क/तस्बिरमा, `esc_attr` class/align/alt मा), `wp_reset_postdata()` पनि बोलाइएको छ, `function_exists()` guard ले थिमको आफ्नै function नबिगार्ने, र `noopener noreferrer` पनि छ। तलका ७ बुँदा सुधार गर्नुपर्ने छन् — **v47 फाइलमा सबै समाधान भइसकेको छ**।

| # | फेला परेको | किन महत्त्वपूर्ण | v47 मा |
|---|---|---|---|
| १ | `'orderby' => 'rand'` + अब क्यास खुल्दा → **ad क्यास TTL सम्म स्थिर** (घुम्दैन) | ad "ताजा" नहुने — क्यास बन्द गर्नुको असली कारण यही थियो | नयाँ `$count` प्यारामिटर: एउटै स्लटमा ३ creative राख्दा **ब्राउजरले हरेक पेज लोडमा फरक ad** देखाउँछ (क्यास खुलै) |
| २ | ad लिङ्कमा `rel="sponsored"` **छैन** | Google को नीति — तिरेको विज्ञापन लिङ्क `sponsored`/`nofollow` नभए SEO जोखिम (अडिटले थिमका ad मा यो राम्रो भनेको थियो) | `rel="sponsored noopener noreferrer"` |
| ३ | हेडर ad पनि `loading="lazy"` | माथि देखिने ad lazy भए LCP ढिलो हुन्छ | नयाँ `$eager` प्यारामिटर (`palika_display_ad('header','center',1,true)`) |
| ४ | `$align` whitelist बिना `style` भित्र | `[palika_ad align="..."]` मार्फत CSS injection सम्भव (लेखक-स्तरका प्रयोगकर्ता) | `left\|center\|right` मात्र स्वीकार |
| ५ | `palika_ad` / `palika_display_ad` hooks मा **anonymous closure** | नाम नभएकोले बाहिरबाट हटाउन/बन्द गर्न सकिँदैन | नामसहित `pklv_ads_do_action()` |
| ६ | `'no_found_rows'` छैन | हरेक ad स्लटमा थप `COUNT(*)` query (५ स्लट = ५ फाल्तु query) | `no_found_rows => true` |
| ७ | **क्लिक/इम्प्रेसन गणना कतै छैन** (हेर्ने भागमा) | — | **यो राम्रो खबर:** क्यास खोल्दा कुनै गणना बिग्रँदैन, त्यसैले विकल्प A/B निर्धो लागू गर्न सकिन्छ |

### तपाईंले सोध्नुभएको थियो: "क्लिक/इम्प्रेसन गणना छ?"
हेर्न पाएको भागमा **छैन** — ad छानिन्छ `orderby => rand` ले, देखाइन्छ, अनि बस्। गणना भएको भए क्यास खोल्दा तथ्याङ्क बिग्रिन्थ्यो; अहिले त्यो समस्या छैन।
**भविष्यमा थप्नुपरे:** पूरै साइटको क्यास बन्द **नगर्नुहोस्** — क्लिक लिङ्कलाई `/go/ad-12/` जस्तो एउटा मात्र नो-क्यास पेजमा पठाउनुहोस्, त्यो पेजले गन्छ र `wp_redirect()` गर्छ।

### `suppress_filters => true` र `lang => ''` बारे
यी लाइन WPML/Polylang को फिल्टर हटाउन राखिएका हुन् — जानाजान राखिएको देखिन्छ, त्यसैले v47 मा जस्ताको तस्तै राखेको छु। एउटा साइड-इफेक्ट जान्नुहोस्: यो query मा अरू plugin ले पनि फिल्टर लगाउन पाउँदैन (जस्तै कुनै ad-rotation plugin)। तपाईंको नियन्त्रणमा सबै छ भने ठीकै छ।

### एउटा सुझाव (आवश्यक होइन)
`get_the_post_thumbnail_url( $id, 'full' )` ले **पूरै साइजको** तस्बिर ल्याउँछ। ब्यानर जहाँ जहाँ ~११००px भन्दा सानो स्थानमा देखिन्छ, त्यहाँ ठूलो फाइल अनावश्यक झर्छ। `add_image_size( 'pl-banner', 1200, 0, false )` बनाएर `'pl-banner'` प्रयोग गर्नुहोस् — तस्बिर मोटो भए स्पष्ट फरक देखिन्छ।

---


---

## 🟠 खण्ड ३ समीक्षा — सेक्सन ६ (लेखभित्रको ad) र सेक्सन ७ (हेडर)

### 🔴 सेक्सन ६ मा असली HTML बग — लेखको markup बिग्रन्छ

```php
$p = explode( '</p>', $content );   // </p> सबै हट्छन्
$p[1] .= '</p>' . $ad;
$content = implode( '', $p );       // delimiter बिना जोडिन्छ
```
`explode('</p>')` ले सबै `</p>` हटाउँछ; `implode('')` ले ती फिर्ता राख्दैन। नतिजा (मैले चलाएर जाँचें):

| | नतिजा |
|---|---|
| v46 को आउटपुट | `<p>A<p>B</p>[AD]<p>C` — **३ खुल्ला `<p>`, १ मात्र `</p>`** |
| v47 को आउटपुट | `<p>A</p><p>B</p>[AD]<p>C</p>` — **सन्तुलित ✅** |

**किन महत्त्वपूर्ण:** यही HTML Google मा जान्छ; schema, AMP, सामाजिक पूर्वावलोकन र पाठक मोड सबैले यही पढ्छन्। ब्राउजर आफैं ट्याग मिलाउँछ, त्यसैले **आँखाले देखिँदैन** — यो "लुकेको" बग हो।
v47 ले `preg_replace_callback` बाट दोस्रो `</p>` पछि मात्र ad राख्छ, कुनै ट्याग हराउँदैन; २ भन्दा कम अनुच्छेद भएमा अन्त्यमा थप्छ।

### सेक्सन ६ का थप सुधार
- `is_feed()` / `is_embed()` guard (v46 मा थिएन — फिड/embed मा ad घुस्न सक्थ्यो)
- `pklv_master_get_ad('in-between','center',2)` — **२ creative राख्नुहोस्**, अनि क्यास खुलै रहँदा पनि ad हरेक लोडमा फेरिन्छ
- नामसहित closure हटाई `pklv_ads_inject_in_article()` (परिवर्तन/बन्द गर्न सकिने)

### 🟡 सेक्सन ७ (हेडर) मा ३ सुधार — मैले नै विकास गर्दा भेटेको बग सहित
1. **`json_encode` → `wp_json_encode(..., JSON_HEX_TAG | JSON_HEX_AMP)`.** v46 मा ad HTML भित्र कतै `</script>` वा `&` आयो भने पूरै स्क्रिप्ट/पेज बिग्रन्छ। (मेरो पहिलो v47 ड्राफ्टमा यो कमी थियो — अहिले ठीक।)
2. **Rotation script अब `DOMContentLoaded` मा पनि चल्छ.** हेडर ad पछि JS ले घुसाउने भएकोले, पुरानो स्क्रिप्ट (तुरुन्तै चल्ने) ले हेडर स्लट घुमाउँदैनथ्यो — अब `pklvRotateAds()` injection पछि फेरि बोलाइन्छ।
3. **inline style सहितको rotation markup ठीक भयो.** मेरो पहिलो ड्राफ्टमा प्रत्येक creative छुट्टै बक्समा जान्थ्यो, त्यसैले JS ले घुमाउन सक्दैनथ्यो। अब **एउटै बक्सभित्र सबै creative** (पहिलो देखिने, बाँकी `display:none !important`)।

### ➕ नयाँ: header स्लट सर्भर-साइड राख्ने विकल्प (सिफारिस)
JS ले हेडरमा ad घुसाउँदा **layout shift (CLS)** हुन्छ र JavaScript बन्द भए ad कहिल्यै देखिँदैन। अब दुई बाटो छ:
```php
// header.php मा, लोगोको छेउमा:
<div class="pl-header-ad-flex"><?php echo pklv_header_ad_html(); ?></div>
```
```php
// wp-config.php मा (थिमले आफैं छाप्न थालेपछि JS fallback बन्द):
define( 'PKLV_ADS_HEADER_JS', false );
```
सर्भर-साइड राख्दा: layout shift हुँदैन, JS नचले पनि ad देखिन्छ, र पेज क्यास पनि सामान्य चल्छ।

### ✅ सुरक्षा (यो खण्डमा)
`the_content` filter मा `in_the_loop() + is_main_query()` जाँच राम्रो ✔ · लिङ्क/तस्बिर `esc_url`, class/align `esc_attr` ✔ · `logo.after()` जस्ता DOM API प्रयोग ठीक ✔ · **कुनै `$_GET`/`$_POST` दुरुपयोग भेटिएन** ✔ · क्लिक/इम्प्रेसन गणना अझै भेटिएन (हेर्न बाँकी सेक्सनमा छ कि हेर्नुछ)।

---

## 📦 v48 — तयार (एउटै फाइल, उही नाम)

**फाइल:** `fixes/wp-content/mu-plugins/palikalive-ads.php` → सर्भरमा `wp-content/mu-plugins/palikalive-ads.php`

> **v48 मा के फेरियो (v47 भन्दा):** लेखभित्रको ad अब घुम्दैन (rotation बन्द)। दोस्रो अनुच्छेदपछि **तीनवटा ad एउटै पंक्तिमा** देखिन्छन् — तीनै बराबर वर्गाकार बक्स, जतिसुकै ठूलो/फरक साइजको creative भए पनि तलमाथि सर्दैन; माथि अंग्रेजीमा **ADVERTISEMENT** लेबल। बढीमा ३ — चौथो ad त्यो ठाउँमा राखे पनि देखिँदैन; कुन देखिने भने `in-between` का **नयाँ ३**। हेडर र `[palika_ad]` shortcode अझै rotation गर्छन्।

यसमै सेक्सन १–७ सबै छन् (स्थिर CSS, इन्जिन, rotation, hooks, shortcodes, लेखभित्रको ad, हेडर) + थप:

| थपिएको | किन |
|---|---|
| `PKLV_ADS_ENABLED` kill switch | आपत्कालमा `wp-config.php` बाट एक लाइनले सबै ad बन्द |
| Cache policy | क्यास खुला; `?pklv_ads_debug=1` (admin) वा `PKLV_ADS_NOCACHE` मा मात्र बाइपास |
| Rotation (`count` प्यारामिटर) | क्यास खुलै रहँदा पनि हरेक लोडमा फरक ad (हेडर/shortcode; लेखभित्रको पंक्ति v48 मा स्थिर) |
| `rel="sponsored"` | Google को सशुल्क-लिङ्क नीति |
| in-article fix | v46 को `explode/implode` बग हटाइयो (HTML मान्य) — v48: तीनवटा एउटै पंक्तिमा, rotation बन्द |
| Zero-collision guard | पुरानो फाइल छुटे दोहोरो ad वा fatal नहोस् — admin notice मात्र |
| `pklv_header_ad_html()` | हेडर सर्भर-साइड छापेर layout shift हटाउने विकल्प |
| `function_exists` guards | कुनै अर्को फाइलले उही function राखे पनि crash हुँदैन |

### अपलोड गर्ने तरिका (क्रम महत्त्वपूर्ण)
1. cPanel → File Manager → `public_html/wp-content/mu-plugins/`
2. **पुरानो फाइल नाम फेर्नुहोस्:** `palikalive-ads.php` → `palikalive-ads.php.bak-2026-09-29` (नहटाई फेर्ने — समस्या आए फिर्ता गर्न मिल्छ)
   *(नाम नफेरी नयाँ राख्दा पुरानै फाइल माथि लेखिन्छ — त्यो पनि ठीक, तर ब्याकअप नहुने)*
3. नयाँ `palikalive-ads.php` अपलोड गर्नुहोस् (अपलोड → यसै नाम)
4. होमपेज + एउटा लेख खोल्नुहोस्

### जाँच (४ बुँदा)
- [ ] हेडरमा ad देखियो (लोगोको दायाँ वा तोकिएको ठाउँमा)
- [ ] एउटा लेख खोल्दा दोस्रो अनुच्छेदपछि **ADVERTISEMENT लेबल सहित तीनवटा ad एउटै पंक्तिमा** देखिए, तीनै बराबर साइज
- [ ] View Source मा `<div class="pl-slot-header-banner"` र `rel="sponsored` देखियो
- [ ] DevTools → Network → डकुमेन्टमा `x-litespeed-cache: hit` (पहिलो लोड `miss`, दोस्रोमा `hit`)

### अझै अडिटमा बाँकी (यो फाइलमा छैन — भएको कोड मात्र राखिएको छ)
- Skip/interstitial ad — भएको फाइल पठाए सोही तरिकाले मिलाउँछु; नभए `palikalive-fixes.php` को `interstitial_once` प्रयोग गर्न सकिन्छ
- Category page slots र Admin settings — भए पठाउनुहोस्

### दुई सम्झनुहोस्
1. **क्यास बन्द गर्नुअब कहिल्यै आवश्यक छैन** — हेडर/shortcode को rotation का लागि स्लटमा २–३ creative राख्नुहोस् (`[palika_ad position="header" count="3"]`)। लेखभित्रको पंक्ति (v48) सधैं स्थिर — नयाँ ३ ad।
2. **LiteSpeed → Cache → TTL Public = 3600** राख्नुहोस् — ad प्रति घण्टा नयाँ सेट पनि पाइन्छ, गति पनि।
