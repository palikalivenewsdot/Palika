# Palika — palikalive.com सुधार किट

२९ सेप्टेम्बर २०२६ को अडिट रिपोर्टलाई **लाइभ साइटमा जाँचेर** बनाइएको सुधार किट। सबै काम **cPanel बाटै** गर्न मिल्ने गरी लेखिएको छ — कुनै विकास वातावरण वा SSH चाहिँदैन।

## छिटो सुरु (३ कदम)

1. **ब्याकअप** — cPanel → File Manager बाट `public_html` को zip, र phpMyAdmin बाट database export (विवरण: `01-fix-guide.md` → चरण ०)।
2. **`fixes/mu-plugins/palikalive-fixes.php`** लाई cPanel → File Manager प्रयोग गरी `public_html/wp-content/mu-plugins/` भित्र Upload गर्नुहोस् (फोल्डर छैन भने `+ Folder` ले बनाउनुहोस्)।
   → होमपेजका खाली "सबै" लिङ्क, h1, LCP preload, भ्यु-काउन्टर, AJAX सुरक्षा लागू हुन्छ।
3. **`fixes/tools/palika-toolkit.php`** लाई `public_html/` मा राखेर `https://palikalive.com/palika-toolkit.php` खोल्नुहोस् (admin लगइन चाहिन्छ) → तपाईंको साइटकै वास्तविक रिपोर्ट हेर्नुहोस्। काम सकिएपछि फाइल Delete गर्नुहोस्।

## के-के छ

| फाइल | काम |
|---|---|
| `01-fix-guide.md` | **मुख्य गाइड** — लाइभ जाँचको नतिजा, ६ चरणको काम, cPanel का हरेक क्लिक, rollback, चेकलिस्ट |
| `02-theme-file-patches.md` | थिम फाइलमा हातैले गर्ने परिवर्तन — फाइल-दर-फाइल, कपी-पेस्ट कोड सहित |
| `fixes/mu-plugins/palikalive-fixes.php` | सुधार प्याक (mu-plugin) — ८ वटा ब्लक, एक-एक गरेर चालु/बन्द गर्न मिल्ने |
| `fixes/tools/palika-toolkit.php` | स्क्यान उपकरण + २ सुरक्षित मर्मत (ABSPATH guard, dev फाइल सफाई) — ब्याकअप सहित |
| `fixes/htaccess-palika.txt` | `.htaccess` स्निपेट — www रिडाइरेक्ट, HSTS, CSP |

## लाइभ जाँचका मुख्य नतिजा (छोटो)

- **खाली "सबै" लिङ्कको असली कारण:** होमपेज शीर्षक `पालिका वार्ता` र श्रेणी नाम `पालिका बार्ता` (व/ब फरक) नमिल्दा `get_cat_ID()` ले `0` दिन्छ → लिङ्क खाली। समाधान: श्रेणीको **slug** (`interview`, `economy`, `opinion`, `5-questions`) प्रयोग गर्ने।
- **होमपेजमा h1 छैन** — पहिलो हेडिङ h2/h3 मात्र।
- **`/home/` पेज खाली छ** तर ४–५ मेनुमा लिङ्क छ; मेनुमा `http://www.palikalive.com` पनि छ।
- **Rank Math + LiteSpeed Cache सक्रिय** — SEO दोहोरो आज देखिँदैन, तर Rank Math अफ गर्ने बित्तिकै थिमको दोहोरो meta/JSON-LD देखिन्छ। रोक्न guard थप्नुपर्छ।
- श्रेणी slug: `main_news`, `helth` (टाइपो), `research` (नाम `पालिका खाेज` — टाइपो) — सबै श्रेणी `/content/…` आधारमा छन्।

विस्तृत प्रमाण र चरणहरू `01-fix-guide.md` मा छन्।

> ⚠️ काम सकिएपछि `palika-toolkit.php` सर्भरबाट हटाउनुहोस्।
