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
