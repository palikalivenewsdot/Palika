# Palika Live — २०२६ सुधार किट (palikalive.com)

२९ सेप्टेम्बर २०२६ को अडिट (palikalive.zip को static audit) लाई **लाइभ साइटमा जाँचेर** बनाइएको सुधार किट। सबै काम **cPanel बाटै** — कुनै developer, SSH वा विकास वातावरण चाहिँदैन।

## ▶ सुरु यहाँबाट: [`03-start-here-checklist.md`](03-start-here-checklist.md)

**पहिलो काम (१५ मिनेट):** ब्याकअप लिनु → `fixes/wp-content/mu-plugins/palikalive-fixes.php` लाई सर्भरको `wp-content/mu-plugins/` मा अपलोड गर्नु। यही एउटा फाइलले खाली "सबै" लिङ्क, होमपेज h1, LCP preload, भ्यु काउन्टर र AJAX सुरक्षा समाधान गर्छ — **थिमको एउटै फाइल छुनु नपर्ने**।

## फाइल नक्सा

| फाइल | सर्भरमा कहाँ जान्छ | के हो |
|---|---|---|
| `fixes/wp-content/mu-plugins/palikalive-fixes.php` | `wp-content/mu-plugins/` | **सुधार प्याक** — ९ ब्लक, सेटिङबाट चालु/बन्द, rename गर्दा सबै रद्द |
| `fixes/public_html/palika-toolkit.php` | `public_html/` | **जाँच उपकरण** (admin-मात्र) — स्क्यान रिपोर्ट + ब्याकअप सहित २ सुरक्षित मर्मत |
| `fixes/htaccess/palika-snippets.txt` | `public_html/.htaccess` | www → non-www, HSTS, CSP (Report-Only) स्निपेट |
| `fixes/theme-snippets.md` | — | थिम फाइलका १४ स्निपेट (कोड English, व्याख्या नेपाली) |
| `fixes/ad-engine/review.md` | — | Ad Engine समीक्षा — क्यास बन्द गर्ने ब्लक, खण्ड २ का ७ बुँदा र समाधान |
| `fixes/wp-content/mu-plugins/palikalive-ads.php` | `wp-content/mu-plugins/` (उही नाम) | **Ad Engine v48 — एउटै फाइल** — क्यास-मैत्री, kill switch, हेडरमा rotation, लेखभित्र दोस्रो अनुच्छेदपछि तीनवटा ad को स्थिर पंक्ति (ADVERTISEMENT लेबल) |
| `fixes/wp-content/mu-plugins/palikalive-single-post.php` | `wp-content/mu-plugins/` | **लेख पेज सुधार v1.0** — टाइटल+सब-हेडलाइन स्क्रोलमा auto-hide, सब-हेडलाइनको रातो लाइन हटाई सफा शैली, शेयर बारमा जीवित शेयर संख्या + ५ बटन (FB, X, Messenger, WhatsApp, Share) |
| `03-start-here-checklist.md` | — | **मुख्य चेकलिस्ट** — कुन क्रममा के गर्ने, समय र जाँच सहित |
| `01-fix-guide.md` | — | विस्तृत गाइड — लाइभ जाँचको प्रमाण, cPanel का हरेक क्लिक, rollback, चेकलिस्ट |

## लाइभ जाँचमा पुष्टि भएका मुख्य कुरा

- खाली "सबै" लिङ्कको जड — होमपेज शीर्षक `पालिका वार्ता` vs श्रेणी नाम `पालिका बार्ता` (व/ब फरक) → `get_cat_ID()` ले `0`, अनि `href=""`
- होमपेजमा `<h1>` छैन; `/home/` पेज खाली तर ४–५ मेनुमा; मेनुमा `http://www.palikalive.com`
- Rank Math + LiteSpeed Cache सक्रिय — SEO दोहोरो आज देखिँदैन, तर Rank Math अफ गर्दा थिमको दोहोरो meta/JSON-LD देखिन्छ (सुधार प्याकले रोक्छ)
- श्रेणी slug: `economy`, `opinion`, `interview`, `5-questions`, `main_news`, `helth` (टाइपो) — सबै `/content/…` आधारमा

## समेटिएको छैन (छुट्टै सेसनमा)

robots.txt/sitemap को भित्री जाँच · Lighthouse को field डाटा (Core Web Vitals) · सर्भरको CSP रिपोर्ट · पूरा प्लगइन सूची र टुटेका लिङ्कको क्रल।

> ⚠️ काम सकिएपछि `palika-toolkit.php` सर्भरबाट अनिवार्य Delete गर्नुहोस्।
