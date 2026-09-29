# Palika Live — अडिटपछिको सुधार गाइड
**आधार:** २९ सेप्टेम्बर २०२६ को अडिट रिपोर्ट + त्यही दिन गरिएको लाइभ साइट जाँच
**तयार:** यो रिपोजिटरी (कोड, सेटिङ विवरण र cPanel का चरणहरू)
**कसले गर्ने:** साइटको cPanel पहुँच भएको व्यक्ति। कोडिङ ज्ञान सामान्य भए पुग्छ — सबै स्टेप कपी–पेस्ट योग्य छन्।

---

## ०. पहिले यो पढ्नुहोस् — यो गाइड कसरी प्रयोग गर्ने

यो गाइडले **के सुधार्नुपर्छ, किन, कुन फाइलमा, कति समयमा र cPanel बाट कसरी** भन्ने कुरा चरणबद्ध रूपमा दिन्छ।

| चरण | काम | समय | जोखिम |
|---|---|---|---|
| **चरण १** | ब्याकअप + तुरुन्तै गर्नुपर्ने ४ साना सुधार (खाली लिङ्क, h1, /home/ पेज, मेनु) | १–२ घण्टा | एकदम कम |
| **चरण २** | SEO एकीकरण, भ्यु काउन्टर, फिचर्ड इमेज, भाषा/मेटा | आधा दिन | कम (पहिले ब्याकअप) |
| **चरण ३** | गति (CSS/फन्ट/JS/तस्बिर) | १ दिन | मध्यम (टेस्ट गर्दै) |
| **चरण ४** | URL/स्लग सफाई + ३०१ रिडाइरेक्ट | २–३ घण्टा | मध्यम |
| **चरण ५** | सुरक्षा हार्डनिङ + फोहोर सफाई | २–३ घण्टा | कम |
| **चरण ६** | मापन, मर्मत तालिका, नियमित चेक | १ घण्टा | — |

**तीनवटा फाइल यो गाइडसँगै दिइएको छ** (यी सबै `fixes/` फोल्डरमा):

1. `fixes/mu-plugins/palikalive-fixes.php` — **सुधार प्याक (mu-plugin)।** थिमको फाइल नछोई तुरुन्तै लागू हुने सुधारहरू (खाली लिङ्क, होमपेज h1, LCP preload, भ्यु काउन्टर, AJAX सुरक्षा)। फाइल rename/delete गर्दा सबै रद्द।
2. `fixes/tools/palika-toolkit.php` — **जाँच उपकरण।** तपाईंको साइटमै गएर अडिटका बुँदा जाँच्छ, श्रेणी/स्लग तालिका, views meta key, मेनुका पुरानो लिङ्क, ABSPATH नभएका फाइल आदि देखाउँछ। दुईवटा सुरक्षित मर्मत (guard थप्ने, .DS_Store हटाउने) पनि गर्छ — ब्याकअप सहित।
3. `fixes/htaccess-palika.txt` — `.htaccess` मा थप्ने सुरक्षा/रिडाइरेक्ट स्निपेट।

> **सबैभन्दा पहिले:** `wp-content/mu-plugins/palikalive-fixes.php` अपलोड गर्नुहोस् र `palika-toolkit.php` चलाएर रिपोर्ट हेर्नुहोस्। चरण १ का ३ मध्ये २ काम (खाली लिङ्क, h1) यसैले गरिदिन्छ।

---

## १. अडिटलाई लाइभ साइटमा जाँच्दा के फेला पर्यो (प्रमाण सहित)

अडिटका बुँदाहरू लाइभ साइटसँग मिल्दाजुल्दा छन्। केही ठाउँमा कारण थप स्पष्ट भयो:

| # | अडिटको बुँदा | लाइभ जाँचको नतिजा |
|---|---|---|
| १.१ | ४ सेक्सनमा खाली "सबै" लिङ्क | **सही।** होमपेजमा `पालिका वार्ता` र `५ प्रश्न` सेक्सनको लिङ्क यथार्थमा `href=""` छ (ब्राउजरले पेजको URL मै लगेको देखिन्छ)। `पालिका खबर`, `राजनीति`, `समाज पालिका`, `पर्यटन`, `स्थानीय तह`, `खेलकुद` आदि सेक्सन ठीक छन्। **वास्तविक कारण थप स्पष्ट:** जहाँ थिमले श्रेणीको *slug* दिएको छ (जस्तै `main_news`, `politics`) त्यहाँ लिङ्क बनेको छ; जहाँ नाम मिलेन (`पालिका वार्ता` vs श्रेणी नाम `पालिका बार्ता` — व/ब फरक!) त्यहाँ `get_cat_ID()` ले `0` फर्काएर लिङ्क खाली भएको छ। |
| १.२ | होमपेजमा h1 छैन | **सही।** होमपेजको पहिलो हेडिङ `h2`/`h3` मात्र छ। |
| १.३ | ३ वटा SEO प्रणाली दोहोरो | **सही — र आज पनि जोखिम छ।** साइटमा Rank Math सक्रिय छ (`/wp-json/` मा `rankmath/v1` देखिन्छ), त्यसैले अहिले दोहोरो देखिँदैन; तर Rank Math कुनै दिन बन्द गर्ने/टकराउने भए दोहोरो meta, canonical र JSON-LD आउँछ। |
| १.४ | हरेक पेज भ्युमा DB write | जाँच गर्न टुलकिट चलाउनुहोस् (views meta key कुन हो देखिन्छ)। समाधान तयार छ। |
| १.५ | पूरा पर्दा ओगट्ने इन्टरस्टिसियल | सही (थिमको skip.php)। निर्णय तपाईंको — तल दुई विकल्प दिएको छु। |
| ७ | लोगो फिचर्ड इमेज / og:locale | लोगो मुद्दा टुलकिटको "धेरै प्रयोग भएका फिचर्ड इमेज" तालिकामा आफैं देखिन्छ। `og:locale` को जड **साइट भाषा** हो (Settings → General) — अडिटले भनेझैं। |
| ८ | मेनु URL, /home/ पेज | **सही र अझ बिग्रेको:** `/home/` पेज (ID 117658) **खाली छ** (कुनै सामग्री छैन) तर ४–५ मेनुमा लिङ्क छ। मेनुमा `http://www.palikalive.com/` र `/home/` दुवै छन्। |
| ३ (slug) | `/content/helth`, `/content/main_news` | पुष्टि भयो — यी श्रेणीका slug हुन् (category base = `content`)। श्रेणी नाम नै `हेल्थ`→slug `helth` (typo), `समाचार`→slug `main_news`। |

**जाँच गर्न नसकिएका कुरा (अडिटले पनि गरेको छैन):** robots.txt, sitemap, सर्भर हेडर, प्लगइनको पूरा सूची, टुटेका लिङ्कको क्रल, र Lighthouse/Core Web Vitals। यी छुट्टै चरणमा हेर्नुपर्छ (चरण ६ मा सूची छ)।

---

## २. चरण ० — ब्याकअप (यो नगरी कुनै पनि फाइल नछुनुहोस्)

**A. फाइलको ब्याकअप (cPanel बाट):**
1. cPanel → **File Manager** → `public_html` मा जानुहोस्।
2. माथिल्लो टुलबारमा `public_html` सेलेक्ट गरिएको अवस्थामा **Compress** थिच्नुहोस् → `Zip Archive` → फाइलको नाम जस्तै `backup-2026-09-29.zip`।
3. बनिसकेपछि त्यो zip मा **डाउनलोड** गरेर आफ्नो कम्प्युटर/Google Drive मा राख्नुहोस् (सर्भरमै मात्र नराख्नुहोस्)।

**B. डाटाबेसको ब्याकअप:**
1. cPanel → **phpMyAdmin** → बायाँबाट आफ्नो डाटाबेस छान्नुहोस्।
2. माथि **Export** ट्याब → Method: `Quick` → Format: `SQL` → **Export**।
3. `.sql` फाइल डाउनलोड भएर आउँछ — सुरक्षित राख्नुहोस्।

**C. (सुझाव) cPanel → Backup** ले "Home Directory" र "Database" पनि डाउनलोड गर्न सकिन्छ। होस्टिङको दैनिक ब्याकअप भए पनि आफ्नै एउटा कपी राख्नु राम्रो।

> **फाइल सम्पादन गर्ने तरिका:** cPanel → File Manager → फाइलमा राइट-क्लिक → **Edit**। सकिएपछि **Save Changes**। कहिलेकाहीँ पुरानो ब्राउजरमा `<?php` बिग्रिन सक्छ — सम्पादन गरेपछि पेज खोलेर जाँच्नुहोस्। "There has been a critical error" आए तुरुन्तै ब्याकअपबाट फिर्ता ल्याउनुहोस्।
> Notepad/MS Word प्रयोग **नगर्नुहोस्** (BOM/encoding बिगार्छ)। cPanel को आफ्नै Editor वा Notepad++/VS Code प्रयोग गर्नुहोस्, encoding **UTF-8 (BOM बिना)** राख्नुहोस्।

---

## ३. चरण १ — आजै गर्नुपर्ने (उच्च प्राथमिकता)

### १.१ — होमपेजका खाली "सबै" लिङ्क ठीक गर्ने (अडिट १.१) ⏱ २० मिनेट

**समस्या:** `get_cat_ID()` ले *नाम* मिलाएर श्रेणी खोज्छ। होमपेज सेक्सनको शीर्षक र श्रेणीको वास्तविक नाम नमिल्दा (जस्तै `पालिका वार्ता` vs `पालिका बार्ता`) त्यो `0` फर्काउँछ र `get_category_link(0)` ले खाली लिङ्क दिन्छ।

**समाधान:** नामको साटो **slug** प्रयोग गर्ने + खाली भए घरपृष्ठमा जाने सुरक्षा।

**पहिले यो slug तालिका हेर्नुहोस्** (यो तपाईंको साइटकै श्रेणी हो, टुलकिटले पनि देखाउँछ):

| होमपेज सेक्सन शीर्षक | थिम फाइल (अडिट अनुसार) | श्रेणीको वास्तविक नाम | ✅ प्रयोग गर्ने slug | बन्ने लिङ्क |
|---|---|---|---|---|
| अर्थ पालिका | `arthapalika.php` (~लाइन ७६) | अर्थ पालिका | `economy` | `/content/economy/` |
| विचार | विचार सेक्सनको फाइल (टुलकिटको खोजमा देखिन्छ) | विचार | `opinion` | `/content/opinion/` |
| पालिका वार्ता | `palikabarta.php` (~लाइन ७४) | पालिका **बा**र्ता (टाइपो) | `interview` | `/content/interview/` |
| ५ प्रश्न | `5prasna.php` (~लाइन ७०) | हाम्रो प्रश्न | `5-questions` | `/content/5-questions/` |
| जनप्रतिनिधि ५ प्रश्न (होमपेजमा छैन) | `janapratinidhi-5prasna.php` (~लाइन ७३) | — | `5-questions` | — |

**कहाँ सम्पादन गर्ने:** cPanel → File Manager → `public_html/wp-content/themes/palikalive/` → माथीका फाइलहरू।
कुन-कुन फाइलमा यो बग छ भनेर पक्का गर्न **टुलकिटको स्क्यान** हेर्नुहोस् (`get_cat_ID` भएका सबै लाइन फाइल:लाइन सहित देखिन्छ)।

**गर्नुपर्ने परिवर्तन — दुई भागमा:**

(क) श्रेणीको मान **slug** बनाउनुहोस्। फाइलमा `$cat1` (वा `$cat`) लाई दिइएको मान खोज्नुहोस् र यसो बनाउनुहोस्:

```php
// पहिले (नाम दिइएको):  $cat1 = 'अर्थ पालिका';
$cat1 = 'economy';        // ✅ slug
```

(ख) लिङ्क बनाउने कोड **यसैले बदल्नुहोस्:**

```php
// पहिले: लिङ्क कहिल्यै खाली हुन सक्थ्यो
$cat1_link = get_category_link( get_cat_ID( $cat1 ) );

// पछि: slug/नाम दुवैतिर खोज्ने, कहिल्यै खाली नहुने
$cat1_link = get_term_link( $cat1, 'category' );
if ( is_wp_error( $cat1_link ) ) {
	$cat1_term = get_term_by( 'name', $cat1, 'category' );       // नाम भए नामले
	$cat1_link = $cat1_term ? get_term_link( $cat1_term ) : '';
}
if ( is_wp_error( $cat1_link ) || '' === $cat1_link ) {
	$cat1_link = home_url( '/' );                                // कम्तीमा खाली नहोस्
}
```

**फाइदा:** slug आधारित भएपछि तपाईंले सेक्सनको नेपाली शीर्षक जहिले पनि ठीक गर्न सक्नुहुन्छ (जस्तै `पालिका खाेज` → `पालिका खोज`) — लिङ्क भाँचिँदैन। नाम आधारित बेला भने शीर्षक ठीक गर्नासाथ लिङ्क फेरि भाँचिन्छ।

**जाँच्ने तरिका:** होमपेज खोल्नुहोस् → चारै सेक्सनको "सबै" मा राइट-क्लिक → *Copy link* → त्यो `/content/economy/`, `/content/opinion/`, `/content/interview/`, `/content/5-questions/` भएको छ कि छैन हेर्नुहोस्। साथै "Open in new tab" गरे वास्तविक श्रेणी पेज खुल्नुपर्छ।

> **छिटो विकल्प (० कोडिङ):** `palikalive-fixes.php` mu-plugin अपलोड गरेपछि खाली `href=""` भएका सबै लिङ्क स्वतः घरपृष्ठमा जान्छन् — साइट कहिल्यै "टुटेको लिङ्क" देखिँदैन। तर सही श्रेणी पेजमा पुर्‍याउन माथिको थिम सम्पादन नै चाहिन्छ।

---

### १.२ — होमपेजमा h1 थप्ने (अडिट १.२) ⏱ १० मिनेट

**किन:** Google र स्क्रिन-रिडरले पेजको मुख्य विषय बुझ्न h1 हेर्छ। होमपेजमा अहिले h2/h3 मात्र छ।

**विकल्प क — थिम टेम्प्लेट (स्थायी, सिफारिस):**
cPanel → File Manager → `themes/palikalive/front-page.php` → **पहिलो सेक्सन लोड हुनुभन्दा अगाडि** (प्रायः `get_header();` पछि) यो राख्नुहोस्:

```php
<h1 class="palika-home-h1">Palika Live — स्थानीय तह, सुशासन र ताजा समाचार</h1>
```

`front-page.php` नभए `header.php` को `</header>` भन्दा तल राख्न सकिन्छ (तर त्यसो गर्दा सबै पेजमा h1 आउँछ — लेखका पेजमा हुँदैन, होसियार)।

**CSS** (WordPress → Appearance → **Customize** → Additional CSS):

```css
.palika-home-h1{
	margin:12px 0 16px;
	padding:9px 14px;
	font-size:17px;
	line-height:1.5;
	font-weight:700;
	background:#f5f5f5;
	border-left:4px solid #c8102e;
}
```
(देखाउनै नचाहनुहुन्छ भने माथिको साटो: `.palika-home-h1{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}` — तर **देखिने h1** नै राम्रो हो।)

**विकल्प ख — mu-plugin:** `palika_fix_cfg('h1_front_page')` डिफल्ट `true` छ — फाइल अपलोड गर्नासाथ h1 आउँछ (शैली पनि सँगै आउँछ)। थिममा टेम्प्लेट h1 राखिसकेपछि mu-plugin स्वतः केही गर्दैन (h1 भेटिए छोड्छ)।

**जाँच:** पेजमा राइट-क्लिक → View Page Source → `Ctrl+F` गरेर `<h1` खोज्नुहोस् — ठ्याक्कै **एक** हुनुपर्छ। साथै एक्स्टेन्सन "Detailed SEO" वा [Rich Results Test](https://search.google.com/test/rich-results) मा हेर्न सकिन्छ।

---

### १.३ — `/home/` खाली पेज र मेनुका पुरानो लिङ्क सफा गर्ने (अडिट ८) ⏱ १५ मिनेट

**समस्या:** `https://palikalive.com/home/` भन्ने पेज **खाली** छ (सामग्री शून्य) तर About/Donate/Privacy जस्तै मेनुमा लिङ्क छ। मेनुको "गृहपृष्ठ" चाहिँ `http://www.palikalive.com/` (पुरानो डोमेन + http) मा गएको छ → हरेक क्लिकमा २ पटक रिडाइरेक्ट हुन्छ।

**गर्नुपर्ने:**
1. **पहिले रिडाइरेक्ट बनाउनुहोस्:** WordPress admin → **Rank Math SEO → Redirections → Add New**
   - Source URL: `/home/`
   - Destination URL: `https://palikalive.com/`
   - Redirection Type: **301 Permanent Move** → Add Redirection
2. **मेनु सफा:** **Appearance → Menus** → प्रत्येक मेनु (मुख्य मेनु, फुटर मेनु आदि) खोल्नुहोस्:
   - `Home` / `गृहपृष्‍ठ` आइटम हटाएर **Custom Links** बाट नयाँ बनाउनुहोस्: Label `गृहपृष्ठ`, URL `https://palikalive.com/`।
   - `Home → /home/` भएको आइटम **Remove** गर्नुहोस्।
   - `http://www.palikalive.com/...` भएका सबै URL `https://palikalive.com/...` बनाउनुहोस् (टुलकिटले यस्ता आइटम लेबल सहित देखाउँछ)।
3. **अब पेज हटाउनुहोस्:** **Pages → Home → Move to Trash**। (रिडाइरेक्ट पहिले नै बनेकोले पुरानो लिङ्क कतै गए पनि ३०१ हुन्छ।)
4. **www → https र https बाध्य गर्ने** (सर्भर तहमा): `fixes/htaccess-palika.txt` को "www रिडाइरेक्ट" भाग `public_html/.htaccess` मा राख्नुहोस् (चरण ५ मा विस्तार छ)।

**जाँच:** `https://palikalive.com/home/` खोल्दा घरपृष्ठमा आउनुपर्छ; `http://palikalive.com` र `https://www.palikalive.com` पनि `https://palikalive.com/` मा आउनुपर्छ (ब्राउजरको एड्रेसबारमा हेर्नुहोस्)।

---

### १.४ — इन्टरस्टिसियल विज्ञापनको नीति तय गर्ने (अडिट १.५) ⏱ २० मिनेट

होमपेज/भित्री पेजमा पूरा स्क्रिन ढाक्ने विज्ञापन १० सेकेन्ड देखिन्छ, कुनै सीमा (cookie/session) छैन। यो **आम्दानीको निर्णय** हो — तर Google को "intrusive interstitial" निर्देशन र खोजबाट आउने पाठकको अनुभवमा नराम्रो प्रभाव पर्छ।

**सिफारिस (मध्यमार्ग):** लेख पेजमा पूरै बन्द, बाँकीमा **सेसनमा एकै पटक**।

**विकल्प क — थिमको `skip.php` सम्पादन** (cPanel → `themes/palikalive/skip.php`), फाइलको सुरुमा `<?php` पछि यो थप्नुहोस्:

```php
// १) लेख हेर्दा इन्टरस्टिसियल नदेखाउने
if ( is_singular( 'post' ) ) {
	return;
}

// २) एउटै सेसनमा एकै पटक मात्र
if ( ! empty( $_COOKIE['palika_intro_seen'] ) ) {
	return;
}
setcookie( 'palika_intro_seen', '1', time() + 12 * HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
```

**विकल्प ख — mu-plugin (थिम नछोई):** `palikalive-fixes.php` मा:
```php
'interstitial_once'      => true,
'interstitial_selectors' => '#skip, .skip, .intro-ad',   // आफ्नो पेजको class/id हाल्नुहोस्
```
अनि ब्राउजरमा इन्टरस्टिसियलमा राइट-क्लिक → Inspect गरेर असली `class`/`id` हेर्नुहोस्।
साथै **focus व्यवस्थापन** थप्नुहोस् (a11y): इन्टरस्टिसियल खुल्दा बन्द बटनमा focus जानुपर्छ।

**जाँच:** नयाँ incognito window मा होमपेज → एक पटक देखिन्छ; अर्को पेजमा जाँदा देखिँदैन। लेख खोल्दा कहिल्यै नआउने।

---

## ४. चरण २ — SEO र डाटा (यो हप्ताभित्र)

### २.१ — तीन SEO प्रणालीलाई एक बनाउने (अडिट १.३) ⏱ १–२ घण्टा

**निर्णय:** **Rank Math नै राख्ने** (यो सक्रिय छ), थिमको SEO आउटपुट बन्द गर्ने।

**कहाँ-कहाँ छ (टुलकिटको "थिमका wp_head hooks" तालिका हेर्नुहोस्):**

| फाइल | अवस्था | गर्नुपर्ने |
|---|---|---|
| `inc/custom-field.php` (priority 1–2) | meta, canonical, OG, schema | फंक्सनको सुरुमा guard |
| `functions.php` सेक्शन १०–१७ (priority 4–5) | उही कुरा दोहोरो | guard |
| `theme-function.php` | पुरानो OG कोड | हटाउने वा guard |
| guard असङ्गत | `class_exists("RankMath")` vs `RANK_MATH_VERSION` | एकै helper प्रयोग |

**कदम १:** `palikalive-fixes.php` mu-plugin अपलोड गर्नुहोस् — यसले `palika_seo_plugin_active()` helper दिन्छ (Rank Math **र** Yoast दुवै समेट्छ)।

**कदम २:** माथीका प्रत्येक फाइलको SEO-सम्बन्धी फंक्सनको **सुरुमा** यो लाइन राख्नुहोस्:

```php
if ( palika_seo_plugin_active() ) {
	return; // Rank Math/Yoast सक्रिय छ — डुप्लिकेट meta नबनाउने।
}
```

(वा फंक्सनको `add_action(...)` लाइनलाई `if ( ! palika_seo_plugin_active() ) { ... }` भित्र बेर्नुहोस्।)

**कदम ३ — छिटो तरिका (कोड नछोई):** टुलकिटको "थिमका wp_head hooks" तालिकाबाट फंक्सनका नाम टिपेर mu-plugin को सेटिङमा राख्नुहोस्:

```php
'head_callbacks_to_remove' => 'palika_seo_meta, sandesh_og_tags',   // टुलकिटले दिएका वास्तविक नाम
```

**कदम ४:** schema fallback लोगोको बाटो ठीक गर्नुहोस् — `assets/images/logo.png` भन्ने फाइल **छैन**। थिमको कोडमा त्यो लाइन यसो बनाउनुहोस्:

```php
// पहिले (भाँचिएको): get_template_directory_uri() . '/assets/images/logo.png'
// पछि (Rank Math वा साइट लोगो प्रयोग):
$logo = get_theme_mod( 'custom_logo' );
$logo_url = $logo ? wp_get_attachment_image_url( $logo, 'full' ) : '';
$logo_url = $logo_url ? $logo_url : 'https://palikalive.com/wp-content/uploads/2026/06/logo_.png';
```

**जाँच (सबैभन्दा महत्वपूर्ण):** कुनै लेख खोल्नुहोस् → View Source → खोज्नुहोस्:
- `name="description"` → **१ मात्र**
- `rel="canonical"` → **१ मात्र**
- `NewsArticle` → **१ मात्र**
- `og:title` → **१ मात्र**
यी १-१ भए Rank Math र थिमको दोहोरो अन्त्य भयो। फेरि [Rich Results Test](https://search.google.com/test/rich-results) मा लेखको URL राखेर errors हेर्नुहोस्।

> **यो चरण नगर्नुहोस्** भने पनि अहिले साइटमा समस्या देखिँदैन (Rank Math सक्रिय छ)। तर कुनै प्लगइन/सेटिङ परिवर्तन गर्नुअघि यो गरे दोहोरो meta को जोखिम सधैंका लागि टर्छ।

### २.२ — भ्यु काउन्टर ठीक गर्ने (अडिट १.४) ⏱ ३० मिनेट

**समस्या:** `sandesh_track_views` (theme-function.php) ले हरेक लेख खुल्दा (bot, preview, prefetch सहित) `update_post_meta` चलाउँछ → डाटाबेसमा अनावश्यक लेखाइ; पेज क्यास भए अझै गलत गन्ती।

**कदम १ — थिमले कुन key मा लेख्छ पत्ता लगाउनुहोस्:** `palika-toolkit.php` चलाएर "views meta key" तालिका हेर्नुहोस् (जस्तै `post_views_count` वा `views`)।

**कदम २ — mu-plugin सेटिङ:**

```php
'views_beacon'    => true,
'views_meta_key'  => 'post_views_count',   // ⬅️ कदम १ मा देखेको key यहाँ ठ्याक्कै लेख्नुहोस् ('auto' पनि चल्छ)
```

यसले के गर्छ:
- थिमको हरेक-भ्यु DB लेखाइ **हटाउँछ**।
- बदलामा पाठकले ५ सेकेन्ड पढेपछि मात्र JS ले `POST /wp-json/palika/v1/view` पठाउँछ।
- Bot/crawler/headless प्रयोगकर्ता **गन्दैन**; एउटै पाठकले एउटै लेख १२ घण्टामा एकै पटक गनिन्छ; admin आफ्नै भ्रमण गन्दैन।

**जाँच:** एउटा लेख खोल्नुहोस् → २०–३० सेकेन्ड पर्खनुहोस् → रिफ्रेस → अर्को लेख खोल्नुहोस्। Tester: `https://palikalive.com/wp-json/palika/v1/view` मा POST गरे `{"recorded":true/false}` आउँछ।
Database मा गन्ती बढेको हेर्न: phpMyAdmin → `wp_postmeta` → त्यो post ID मा त्यो key।

> गन्ती शून्यमा झरेजस्तो देखिए key फरक भएको हुनसक्छ — mu-plugin मा सही key लेख्नुहोस्। **पहिले key पत्ता लगाउनुहोस्, त्यसपछि मात्र यो चलाउनुहोस्।**

### २.३ — लोगो फिचर्ड इमेज, भाषा र मेटा (अडिट ७) ⏱ ३० मिनेट

- **फिचर्ड इमेज:** टुलकिटको "धेरै प्रयोग भएका फिचर्ड इमेज" मा लोगो (`logo_.png`) देखियो भने ती लेखहरूमा वास्तविक फोटो राख्नुपर्छ। नयाँ लेखमा **Featured Image अनिवार्य** बनाउन Rank Math → Titles & Meta → Posts → Schema/OG image rules; साथै सम्पादकसँग लेख्ने नियम बनाउनुहोस्। **Open Graph fallback** पनि लोगो नै छ — Rank Math → Titles & Meta → Global Meta → **OpenGraph Thumbnail** मा एउटा पत्रकारिता-योग्य १२००×६३० ब्रोडर इमेज राख्नुहोस्।
- **भाषा/`og:locale`:** **Settings → General → Site Language → नेपाली** छानेर Save। यसले `og:locale` नेपाली बनाउँछ र साइटलाई सही भाषा-सङ्केत दिन्छ। (नेपाली भाषा प्याक नभए WordPress ले स्वतः डाउनलोड गर्न खोज्छ; `wp-content` लेख्न मिल्ने छ भने हुन्छ — नभए cPanel बाट `wp-content/languages` मा अपलोड गर्नुपर्छ।)
- **ट्यागलाइन:** Settings → General → Tagline मा अंग्रेजीको साटो नेपाली (`स्थानीय तह, सुशासन र ताजा समाचार`) राख्नुहोस् — h1 र मेटा दुवै राम्रो हुन्छ।
- **Meta description:** Rank Math → Titles & Meta → Homepage मा आफ्नै वाक्य लेख्नुहोस् (शीर्षक दोहोर्याउने होइन): जस्तै *"स्थानीय तह, सुशासन, बेरुजु र जनप्रतिनिधिका ताजा समाचार — पालिका लाइभ।"*
- **`og:image:alt`:** Rank Math → Titles & Meta → Global Meta → OpenGraph → "Add image alt" जस्तो विकल्प भए लेख शीर्षकै राख्न मिलाउनुहोस्; नभए लेखमा फिचर्ड इमेजको Alt text अनिवार्य गर्नुहोस् (सम्पादकीय नियम)।
- **बाइलाइन:** प्रायः लेख "पालिका लाइभ" नाममा छन्। **Users** मा चार जना मात्र छन् (इमान जङ्ग बानियाँ, डा. सुदन अधिकारी, पालिका लाइभ, लीलाधर काेइराला)। लेखको बाइलाइन वास्तविक कर्मचारीको नाममा राख्नुहोस्; लेखक प्रोफाइलमा फोटो र bio राख्नुहोस्। ("लीलाधर काेइराला" को नाममा `काे` टाइपो छ — `को` बनाउनुहोस्।)

### २.४ — PDF अपलोड र AJAX हार्डनिङ (अडिट ९) ⏱ ४५ मिनेट

- **PDF अपलोड (`inc/custom-field.php` ~११११):** अहिले फाइलनामको extension मात्र हेरिन्छ — यसमा `malware.php.pdf` जस्ता नाम पनि जाँच्ने तरिका कमजोर हुन्छ। `wp_check_filetype_and_ext()` प्रयोग गर्नुहोस्:

```php
$allowed = array( 'pdf' => 'application/pdf' );
$check   = wp_check_filetype_and_ext( $_FILES['your_field']['tmp_name'], $_FILES['your_field']['name'], $allowed );
if ( empty( $check['ext'] ) || 'pdf' !== strtolower( $check['ext'] ) ) {
	// फाल्ने; सके media_handle_upload() प्रयोग गर्नुहोस् जसले WP नै validate गर्छ।
	return;
}
```
साथै **`wp_die()` हटाउनुहोस्** — `save_post` भित्र `wp_die()` गर्दा सम्पादकले हालेको सामग्री हराउँछ। बदलामा error लाई transient/notice मा राख्नुहोस्।

- **AJAX (`get_nepalitheme_oldpost_html`):** mu-plugin को `ajax_guard` ले nonce मात्र होइन, **capability + प्रकाशित स्थिति** पनि जाँच्छ (draft/private सामग्री निष्क्रिय लेखकसँग चुहिन्न)।
- **`wp-config.php` (cPanel → File Manager → `public_html/wp-config.php`):**

```php
define( 'WP_DEBUG', false );            // लाइभमा false
define( 'DISALLOW_FILE_EDIT', true );   // admin बाट PHP सम्पादन बन्द (सुरक्षा)
```
> `DISALLOW_FILE_EDIT` राख्दा admin → Plugins → Theme File Editor हराउँछ (यो राम्रो हो)। सम्पादन cPanel बाटै गर्नुहोस्।

---

## ५. चरण ३ — गति (Performance)

साइटमा **LiteSpeed Cache** प्लगइन पहिले नै छ — यसैले धेरै काम गर्न सक्छ, नयाँ प्लगइन चाहिँदैन।

### ३.१ — LiteSpeed Cache सेटिङ (अडिट ६) ⏱ १ घण्टा

| सेटिङ | कहाँ | गर्ने |
|---|---|---|
| Cache | Cache → Enable | **ON** (यो सबैभन्दा ठूलो फाइदा) |
| CSS Minify + Combine | Page Optimization → CSS Settings | ON |
| CSS Load Asynchronously | उही | पहिले टेस्ट गरेर मात्र ON (FOUC आउन सक्छ) |
| UCSS / Critical CSS | Page Optimization → CSS | ON (LiteSpeed सर्भरले बनाउँछ — पुरानो फन्ट/लेआउट समस्या समाधान) |
| JS Combine / Defer | Page Optimization → JS Settings | Defer ON; Combine **होस्‌यारीले** (टुट्यो भने बन्द) |
| Lazy Load Images | Page Optimization → Media | **ON** — अनि "Use native `loading` attribute" भए सो पनि ON |
| WebP / Image Optimize | Image Optimization | ExactDN/CDN सँगै — पहिलेजस्तै चलिरहेको छ |
| Font Display: swap | Page Optimization → Font Settings | ON |
| Load Google Fonts Asynchronously | उही | ON |
| Crawler / Guest Mode | Cache | ON (पहिलो पाठकलाई पनि छिटो) |

प्रत्येक परिवर्तनपछि **Purge All** गर्नुहोस् (LiteSpeed Cache → Toolbox → Purge All) र साइट खोलेर लेआउट जाँच्नुहोस्।

### ३.२ — फन्ट घटाउने (अडिट ६) ⏱ ३० मिनेट

हाल ७ फन्ट परिवार (Arya, Ek Mukta, Kalam, Khand, Mukta, Niramit, Roboto) झिकिइन्छ। प्रायः **Mukta** (मुख्य पाठ) मात्र चाहिन्छ, सम्भवतः एउटा हेडलाइन फन्ट।

1. cPanel → File Manager → `themes/palikalive/` → `Ctrl+F` (File Manager को Search) गरे `fonts.googleapis.com` खोज्नुहोस् (प्रायः `header.php` वा `functions.php`)।
2. मुख्य पाठको फन्ट Mukta हो — बाँकी सबै हटाउनुहोस्। जस्तै:

```php
// पहिले (७ परिवार) — यस्तो लिङ्क जहाँ छ त्यहाँ
// https://fonts.googleapis.com/css?family=Arya|Ek+Mukta|Kalam|Khand|Mukta|Niramit|Roboto

// पछि (२ परिवार, आवश्यक weight मात्र):
https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700&family=Khand:wght@500;700&display=swap
```
3. लिङ्कसँगै preconnect राख्नुहोस् (छैन भने):

```html
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```
4. **जाँच:** लेआउट र फन्ट पहिलेजस्तै छ कि छैन (सबै सेक्सन हेर्नुहोस्); टुटेको देखिए हटाएको फन्ट फिर्ता राख्नुहोस्।

### ३.३ — JS/CSS लाइब्रेरी आवश्यक पेजमा मात्र (अडिट ६) ⏱ २ घण्टा

Bootstrap, Popper, Owl Carousel, Magnific Popup, Colorbox, Datepicker सबै पेजमा झिकिइन्छ। टुलकिटको "इन्क्युइ गरिएका script/style" सूचीले फंक्सनको नाम/लाइन दिन्छ। तिनलाई सर्तभित्र राख्नुहोस्, जस्तै:

```php
// पहिले: सबै पेजमा
wp_enqueue_script( 'colorbox' );

// पछि: जहाँ चाहिन्छ त्यहाँ मात्र (उदाहरण)
if ( is_singular( 'post' ) || is_page_template( 'gallery.php' ) ) {
	wp_enqueue_script( 'colorbox' );
}
```
Datepicker admin/फारममा मात्र, Owl आवश्यक सेक्सनमा मात्र, Magnific ग्यालरी पेजमा मात्र।

**CSS (style.css ३५६ KB + Bootstrap १५२ KB):** अन्धाधुन्ध हटाउनु हुँदैन (लेआउट भाँचिन्छ)। क्रम यही राख्नुहोस्:
1. पहिले LiteSpeed को UCSS/Critical CSS चलाउनुहोस् (स्वचालित, ब्याकअप चाहिँदैन)।
2. त्यसपछि Bootstrap प्रयोग भएका class हरू खोजेर (Ctrl+F: `col-md-`, `btn-`, `d-flex`, `modal`) — प्रयोग नभएको भाग मात्र हटाउनुहोस्।
3. `assets/css/main.css`, `customizer.css`, `fontawesome-all.*` जस्ता फाइल थिमबाट इन्क्युइ भएको छ कि छैन जाँच्नुहोस् (टुलकिट सूची) — नभए फोल्डरबाट हटाउन सकिन्छ।

### ३.४ — JS नभए पनि तस्बिर देखिने (अडिट ६) ⏱ १५ मिनेट

हाल तस्बिरहरू `lazy.png` placeholder हुन् र JavaScript चलेन भने देखिँदैनन् (सर्च इन्जिन/धेरै पुरानो ब्राउजरमा समस्या)। LiteSpeed को Lazy Load **ON** गरिसकेपछि mu-plugin सेटिङ:

```php
'native_lazy' => true,
```
यसले placeholder भएको ठाउँमा सक्कली `src` राख्छ + `loading="lazy"`/`decoding="async"` थप्छ (पहिलो तस्बिरलाई `fetchpriority="high"`)। पहिले एउटा लेखमा टेस्ट गर्नुहोस्।

### ३.५ — LCP इमेज preload (अडिट ६) ⏱ ५ मिनेट

mu-plugin को `'lcp_preload' => true` ले लेखको मुख्य तस्बिर `<head>` मै preload गर्छ (LCP सुधार)। यो डिफल्टमै सक्रिय छ।

**नाप्ने:** [PageSpeed Insights](https://pagespeed.web.dev/) र Google Search Console → Core Web Vitals मा परिवर्तन हेर्नुहोस् (२–४ हफ्तामा डाटा आउँछ)।

---

## ६. चरण ४ — URL/स्लग सफाई (रिडाइरेक्ट सहित)

**नियम:** slug परिवर्तन गर्दा **पहिले ३०१ राख्ने, त्यसपछि मात्र slug बदल्ने।** Rank Math → Redirections ले काम गर्छ।

| अहिलेको URL | परिवर्तन | किन | प्राथमिकता |
|---|---|---|---|
| `/content/helth/` | slug `helth` → `health` | साफ टाइपो | उच्च |
| `/content/main_news/` | slug `main_news` → `main-news` | underscore ठीक छैन; **तर ४,४८५ लेखको श्रेणी, मेनु/थिममा धेरै ठाउँ लिङ्क** | कम (छोड्न पनि हुन्छ) |
| श्रेणी नाम `पालिका बार्ता` | नाम → `पालिका वार्ता` | **नाम नै टाइपो**; URL (`interview`) फेरिँदैन | उच्च |
| श्रेणी नाम `पालिका खाेज` | नाम → `पालिका खोज` | दुई वटा मात्रा-चिन्ह (ाे) को गल्ती; URL (`research`) फेरिँदैन | उच्च |
| श्रेणी नाम `हाम्रो प्रश्न` | नाम → `५ प्रश्न` (चाहे) | होमपेज शीर्षकसँग मिलाउन | मध्यम |
| नाम `लीलाधर काेइराला` | → `लीलाधर कोइराला` | Users → Edit | मध्यम |

**चरणहरू (उदाहरण: helth → health):**
1. Rank Math → Redirections → Add New: Source `/content/helth/`, Destination `/content/health/`, Type **301** → Save.
2. WordPress → **Posts → Categories** → "स्वास्थ्य" सम्पादन → Slug: `health` → Update।
3. अब `/content/health/` मा श्रेणी पेज खुल्छ; पुरानो लिङ्क नयाँमा ३०१ हुन्छ।
4. चार-पाँच दिनपछि Search Console → URL Inspection मा पुरानो URL राखेर रिडाइरेक्ट भएको पुष्टि गर्नुहोस्।

**श्रेणी नाम बदल्दा URL फेरिँदैन** (slug फरक हुन्छ) — त्यसैले नाम/टाइपो ठीक गर्ने काम सुरक्षित हो। नाम परिवर्तनले साइटभरिको मेनु/लेबल पनि नयाँ नाम देखाउँछ। *थिमका सेक्सन शीर्षक हार्डकोडेड छन्* — ती माथि चरण १.१ को slug फिक्स गरेपछि जहिले पनि ठीक गर्न सकिन्छ।

---

## ७. चरण ५ — सुरक्षा र सरसफाई

### ५.१ — सर्भर हेडर (अडिट ५) ⏱ २० मिनेट

थिमले `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` पठाइरहेको छ। थप्नुपर्ने: **HSTS** (र सम्भव भए CSP)।

1. cPanel → File Manager → `public_html/.htaccess` → **Edit** (पहिले कपी राख्नुहोस्!)।
2. `# BEGIN WordPress` भाग **नसारी**, फाइलको सुरुमा `fixes/htaccess-palika.txt` मा दिइएका ब्लकहरू टाँस्नुहोस्।
3. HSTS **पहिले छोटो max-age बाट** सुरु गर्नुहोस् (जस्तै 300), साइट ठीक चल्यो भने मात्र १ वर्षमा बढाउनुहोस् — गलत HSTS ले साइट बिगार्न सक्छ।
4. `www` → non-www ३०१ रिडाइरेक्ट पनि त्यहीँ छ (सर्भर तहमा; मेनु लिङ्क ठीक गरेपछि यो सुरक्षा जाल जस्तै काम गर्छ)।
5. CSP पहिले `Content-Security-Policy-Report-Only` मा राख्नुहोस् (विज्ञापन/GA ले तोड्न सक्छ), रिपोर्ट हेरेर मात्र सक्रिय गर्नुहोस्।

### ५.२ — थिम सरसफाई (अडिट ५ र १०) ⏱ १ घण्टा

`palika-toolkit.php` चलाएर "सुरक्षित मर्मत" बटनहरू प्रयोग गर्नुहोस्:

- **ABSPATH guard** — करिब २५ PHP फाइल (`archive.php`, `author.php`, `inc/section/*` आदि) मा `if ( ! defined( 'ABSPATH' ) ) { exit; }` थप्छ (ब्याकअप सहित)।
- **.DS_Store + SCSS + source map** — ब्याकअपमा सार्छ (लगभग १.२ MB हल्का)।

**हातैले गर्नुपर्ने:**
- `video.php` भित्र "delete after fixing" भनिएको admin debug ब्लक हटाउनुहोस्।
- `single-old.php` (३२ KB, प्रयोगमा छैन) र `single-writters.php` (नाममै टाइपो) — फिर्ता ल्याउनु नपर्ने पक्का भएपछि हटाउनुहोस्, नाम ठीक गर्नुहोस्।
- `header.php` को `document.getElementById` override र `AbortError` निल्ने कोड हटाउनुहोस्; असली कारण `custom.js` मा ठीक गर्नुहोस् (यो कोडले असली बग लुकाइरहेको छ)।
- `functions.php` (~१,९७८ लाइन, ३५ सेक्शन) र "patch" फाइल — एकै पटक नछुनुहोस्। काम गर्दै जाँदा सेक्सन-सेक्सन मिलाउनुहोस्, प्रत्येक पटक ब्याकअप।

### ५.३ — साना तर देखिने कमी (अडिट ३, ४, ५) ⏱ १५ मिनेट

- फुटरका **Android/iOS ब्याज** होमपेजमा गएको छ — Play Store/App Store को सही लिङ्क दिनुहोस् (एप नभए ती ब्याज हटाउनुहोस्)।
- लेखको **Viber share** बटनको लिङ्क खाली छ — `viber://forward?text=लेखको URL` बनाउनुहोस् (वा विकल्प लिङ्क राख्नुहोस्)।
- कार्ड तस्बिरको लिङ्कमा **पढ्न मिल्ने नाम** छैन (Lighthouse)। यी तस्बिरमा लेख शीर्षक जस्तै `alt` राख्नुहोस्:

```php
<?php if ( has_post_thumbnail() ) : ?>
	<a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
		<?php the_post_thumbnail( 'medium', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
	</a>
<?php endif; ?>
```
- टिकर/मेनुको दोहोरो मार्कअप (एउटै मेनु ४+ पटक) — राम्रो रिपोर्टमा प्रभाव पार्छ, क्रमशः घटाउनुहोस्।
- तस्बिरको **alt** टेक्स्ट: टुलकिटले कति अपलोडमा alt छैन देखाउँछ। Media Library मा गएर नयाँ अपलोडमा Alt अनिवार्य गर्ने नियम बनाउनुहोस् (SEO + a11y)।

---

## ८. चरण ६ — मापन र नियमित मर्मत

1. **Google Search Console** (नभए जोड्नुहोस्): Sitemap पठाउनुहोस् (`https://palikalive.com/sitemap_index.xml` — Rank Math ले बनाउँछ), Coverage/Enhancements हेर्नुहोस्।
2. **PageSpeed Insights**: चरण ३ अघि र पछि नापेर तुलना गर्नुहोस्।
3. **महिनामा एक पटक:**
   - `palika-toolkit.php` फेरि अपलोड गरी स्क्यान (डुप्लिकेट meta, खाली लिङ्क, views key, नयाँ प्लगइन)।
   - WordPress, थिम र प्लगइन अपडेट (अपडेट अघि ब्याकअप — नियम)।
   - `wp_postmeta` मा "view" जस्ता key बढ्दै गएको हेर्नुहोस् (सफा गर्ने विकल्प छ)।
   - Rank Math → 404 Monitor र Redirections हेर्नुहोस्।
4. **Lighthouse** (Chrome → F12 → Lighthouse) चलाएर Accessibility र SEO स्कोर ट्र्याक गर्नुहोस्।
5. `robots.txt`, sitemap र सर्भर हेडर यो अडिटमा जाँचिएको थिएन — छुट्टै एक घण्टाको सेसनमा हेर्नुहोस् (robots.txt मा `Disallow:` सही छ कि छैन, `sitemap_index.xml` उल्लेख छ कि छैन)।

---

## ९. केही बिग्रियो भने फिर्ता ल्याउने (Rollback)

| के बिग्रियो | तुरुन्तै गर्ने |
|---|---|
| mu-plugin ले समस्या गर्‍यो | cPanel → `wp-content/mu-plugins/palikalive-fixes.php` **Rename** (जस्तै `palikalive-fixes.php.off`) — साइट तुरुन्तै पहिलेजस्तै। |
| टुलकिटको मर्मत (guard/dev फाइल) | `wp-content/uploads/palika-backup-YYYYmmdd-HHMM/` बाट सोही बाटोमा फाइल कपी गर्नुहोस्। |
| थिम फाइल सम्पादन गर्दा सेतो पेज/error | सम्पादित फाइलमा राइट-क्लिक → Edit → बिग्रेको परिवर्तन फिर्ता (Undo) गर्नुहोस्। नभए `backup-2026-09-29.zip` बाट त्यो एउटा फाइल निकालेर फिर्ता राख्नुहोस्। |
| साइट नै नखुल्ने | cPanel → File Manager → `public_html` को `wp-config.php` मा `define('WP_DEBUG', true);` राखेर error हेर्नुहोस् (काम सकिएपछि false)। आवश्यक परे होस्टिङको सपोर्टलाई लेटेस्ट ब्याकअप रिस्टोर गर्न भन्नुहोस्। |

---

## १०. अन्तिम चेकलिस्ट

**चरण १**
- [ ] ब्याकअप (files + database) डाउनलोड भयो
- [ ] `palikalive-fixes.php` mu-plugin अपलोड भयो
- [ ] `palika-toolkit.php` चलाई रिपोर्ट हेरियो (र .txt डाउनलोड)
- [ ] चारै सेक्सनको "सबै" लिङ्क सही श्रेणीमा गयो
- [ ] होमपेजमा ठ्याक्कै एक `h1` छ
- [ ] `/home/` → ३०१ घरपृष्ठ; मेनुका `http://www` लिङ्क सफा
- [ ] इन्टरस्टिसियल नीति लागू (लेखमा बन्द + सेसनमा एक)

**चरण २**
- [ ] थिमको SEO आउटपुट बन्द; description/canonical/JSON-LD प्रत्येक १ मात्र
- [ ] भ्यु-काउन्टर beacon चालु; गन्ती बढेको पुष्टि; सही meta key लेखियो
- [ ] लोगो फिचर्ड इमेज भएका लेख ठीक; OG thumbnail राखियो
- [ ] Site Language = नेपाली; ट्यागलाइन नेपाली; meta description लेखियो
- [ ] PDF अपलोड जाँच + `wp_die` हटाइयो; `DISALLOW_FILE_EDIT` राखियो

**चरण ३**
- [ ] LiteSpeed Cache मुख्य सेटिङ ON + Purge All
- [ ] फन्ट ७ → २; लेआउट ठीक
- [ ] आवश्यक पेजमा मात्र लाइब्रेरी लोड
- [ ] native lazy + LCP preload; PageSpeed तुलना भयो

**चरण ४–६**
- [ ] `helth` → `health` + ३०१; श्रेणी नामका टाइपो ठीक
- [ ] .htaccess मा www रिडाइरेक्ट + HSTS (छोटो max-age बाट)
- [ ] ABSPATH guard र dev फाइल सफाई; video.php debug हटाइयो
- [ ] Search Console + sitemap + 404 Monitor जाँच
- [ ] `palika-toolkit.php` सर्भरबाट **Delete** गरियो

---

### संलग्न फाइलहरू

| फाइल | के हो |
|---|---|
| `fixes/mu-plugins/palikalive-fixes.php` | सुधार प्याक (mu-plugin) — ८ वटा ब्लक, सेटिङबाट चालु/बन्द |
| `fixes/tools/palika-toolkit.php` | स्क्यान + सुरक्षित मर्मत उपकरण (admin मात्र, ब्याकअप सहित) |
| `fixes/htaccess-palika.txt` | `.htaccess` स्निपेट (www रिडाइरेक्ट, HSTS, CSP) |
| `02-theme-file-patches.md` | थिम फाइलमा हातैले गर्ने परिवर्तन — फाइल-दर-फाइल, कपी-पेस्ट कोड सहित |

> ⚠️ काम सकिएपछि `palika-toolkit.php` सर्भरबाट अनिवार्य Delete गर्नुहोस्। यो admin-मात्र भए पनि सर्भरमा नराख्नु राम्रो।
