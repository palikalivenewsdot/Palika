# Palika Live — २०२६ सुधार किट (palikalive.com)

२९ सेप्टेम्बर २०२६ को अडिट (palikalive.zip को static audit) लाई **लाइभ साइटमा जाँचेर** बनाइएको सुधार किट। सबै काम **cPanel बाटै** — कुनै developer, SSH वा विकास वातावरण चाहिँदैन।

## ▶ सुरु यहाँबाट: [`03-start-here-checklist.md`](03-start-here-checklist.md)

**पहिलो काम (१५ मिनेट):** ब्याकअप लिनु → `fixes/wp-content/mu-plugins/palikalive-fixes.php` लाई सर्भरको `wp-content/mu-plugins/` मा अपलोड गर्नु। यही एउटा फाइलले खाली "सबै" लिङ्क, होमपेज h1, LCP preload, भ्यु काउन्टर र AJAX सुरक्षा समाधान गर्छ — **थिमको एउटै फाइल छुनु नपर्ने**।

## फाइल नक्सा

| फाइल | सर्भरमा कहाँ जान्छ | के हो |
|---|---|---|
| `fixes/wp-content/mu-plugins/palikalive-fixes.php` | `wp-content/mu-plugins/` | **सुधार प्याक v2.0.0** — ९ ब्लक, सेटिङबाट चालु/बन्द (`views_meta_key = sandesh_post_views_count` मिलाइयो), rename गर्दा सबै रद्द |
| `fixes/wp-content/mu-plugins/palikalive-speed.php` | `wp-content/mu-plugins/` | **Speed Pack v1.0.0** — फन्ट ७ → पेजले प्रयोग गरेको मात्र (`display=swap`), fonts.gstatic + image CDN preconnect, `?pklv_speed_report=1` रिपोर्ट |
| `fixes/public_html/palika-scan.php` | `public_html/` | **छिटो स्क्यान** (admin-मात्र, read-only) — चलाइसकियो, अब डिलिट गर्ने |
| `fixes/public_html/palika-cache-find.php` | `public_html/` | **क्यास निदान** (admin-मात्र, read-only) — LiteSpeed सेटिङ + नक्यास गर्ने कोड + hit/miss टेस्ट |
| `fixes/public_html/palika-toolkit.php` | `public_html/` | पुरानो जाँच उपकरण — अब आवश्यक छैन |
| `fixes/htaccess/palika-snippets.txt` | `public_html/.htaccess` | www → non-www, HSTS, CSP (Report-Only) स्निपेट |
| `fixes/theme-snippets.md` | — | थिम फाइलका १४ स्निपेट (कोड English, व्याख्या नेपाली) |
| `fixes/ad-engine/review.md` | — | Ad Engine समीक्षा — क्यास बन्द गर्ने ब्लक, खण्ड २ का ७ बुँदा र समाधान |
| `fixes/wp-content/mu-plugins/palikalive-ads.php` | `wp-content/mu-plugins/` (उही नाम) | **Ad Engine v48 — एउटै फाइल** — क्यास-मैत्री, kill switch, हेडरमा rotation, लेखभित्र दोस्रो अनुच्छेदपछि तीनवटा ad को स्थिर पंक्ति (ADVERTISEMENT लेबल) |
| `fixes/wp-content/mu-plugins/palikalive-single.php` | `wp-content/mu-plugins/` | **लेख पेज सुधार v3.0.0 — सिफारिस गरिएको तरिका (थिम छुँदैन)** — टाइटल ब्लक तल स्क्रोल गर्नासाथ लुक्ने, सब-हेडलाइनको रातो लाइन हटाई सफा lead, शेयर बारमा जीवित शेयर संख्या + ५ बटन (FB, X, Messenger, WhatsApp, Share); गणना admin-ajax बाट |
| `fixes/wp-content/themes/palikalive/single.php` | ⚠️ प्रयोग **नगर्नुहोस्** (वैकल्पिक) | पूरा थिम टेम्प्लेट v5.0.0 — पेस्ट गर्दा कटिए/बिग्रिए साइटमा fatal error आउँछ; माथिको mu-plugin ले नै तीनैं काम गर्छ |
| `03-start-here-checklist.md` | — | **मुख्य चेकलिस्ट** — कुन क्रममा के गर्ने, समय र जाँच सहित |
| `01-fix-guide.md` | — | विस्तृत गाइड — लाइभ जाँचको प्रमाण, cPanel का हरेक क्लिक, rollback, चेकलिस्ट |

## लाइभ जाँचमा पुष्टि भएका मुख्य कुरा

- खाली "सबै" लिङ्कको जड — होमपेज शीर्षक `पालिका वार्ता` vs श्रेणी नाम `पालिका बार्ता` (व/ब फरक) → `get_cat_ID()` ले `0`, अनि `href=""` (सुधार प्याकले लाइभमा ठीक गर्यो — अब `href=""` = ०)
- होमपेजमा `<h1>` छैन; `/home/` पेज खाली तर ४–५ मेनुमा; मेनुमा `http://www.palikalive.com`
- Rank Math + LiteSpeed Cache सक्रिय — SEO दोहोरो आज देखिँदैन (canonical/description/og:title = १-१)
- श्रेणी slug: `economy`, `opinion`, `interview`, `5-questions`, `main_news`, `helth` (टाइपो) — सबै `/content/…` आधारमा
- **क्यास ठीक छ:** `litespeed.conf.cache = 1`, TTL 604800, कुनै exclusion छैन, `DONOTCACHEPAGE` को रूपमा थिममा केही छैन → लेख/श्रेणी `x-litespeed-cache: hit` दिन्छन् (पहिलेको `miss` cold cache मात्र थियो)
- **लगइन admin ले सधैं ताजा पेज देख्छ** (`vary_group administrator=99`) → क्यास जाँच्ने बेला Incognito चलाउनुहोस्
- **थाक्ने ठूला बोझ:** भाषा `en_US` (og:locale), `NewsArticle` schema = ०, फन्ट ७ फ्यामिली, दोहोरो image optimization (EWWW + LiteSpeed)

## समेटिएको छैन (छुट्टै सेसनमा)

robots.txt/sitemap को भित्री जाँच · Lighthouse को field डाटा (Core Web Vitals) · सर्भरको CSP रिपोर्ट · पूरा प्लगइन सूची र टुटेका लिङ्कको क्रल।

> ⚠️ काम सकिएपछि `palika-scan.php` र `palika-cache-find.php` सर्भरबाट अनिवार्य Delete गर्नुहोस्।
