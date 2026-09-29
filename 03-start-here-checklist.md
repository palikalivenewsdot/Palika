# ०३ — यहाँबाट सुरु गर्नुहोस् (चरणबद्ध चेकलिस्ट)

**तपाईंको प्रश्न: "सबैभन्दा पहिला के गर्ने?"**

## उत्तर: पहिलो काम — ब्याकअप लिनु, अनि **एउटै फाइल** अपलोड गर्नु।

किन यही पहिलो?
- यो कामले अडिटको **सबैभन्दा ठूलो बग (खाली "सबै" लिङ्क)**, **होमपेज h1**, **LCP preload**, **भ्यु काउन्टर** र **AJAX सुरक्षा** एकैचोटि समाधान गर्छ।
- **थिमको एउटै फाइल छुनु पर्दैन** — त्यसैले साइट बिग्रिने जोखिम लगभग शून्य। मन नपरे फाइलको नाम फेर्ने/डिलिट गर्ने, सबै पहिलेजस्तै।
- फाइल: `fixes/wp-content/mu-plugins/palikalive-fixes.php` → सर्भरमा `wp-content/mu-plugins/` भित्र।
- समय: **१५ मिनेट** (ब्याकअप सहित ३० मिनेट)।

> **यो एउटा काम गरेर मलाई भन्नुहोस् — म अर्को चरण ठ्याक्कै तपाईंको साइटको रिपोर्ट हेरेर दिन्छु।**

---

## चरण १ — ब्याकअप ⏱ १५ मिनेट · जोखिम ०

- [ ] **Files:** cPanel → File Manager → `public_html` → **Compress** → `backup-2026-09-29.zip` → Download (कम्प्युटरमा)
- [ ] **Database:** cPanel → phpMyAdmin → आफ्नो डाटाबेस → **Export** → Quick/SQL → Download

विस्तृत: `01-fix-guide.md` → चरण ०। **यो नगरी अगाडि नबढ्नुहोस्।**

---

## चरण २ — सुधार प्याक अपलोड (पहिलो मुख्य काम) ⏱ १० मिनेट · जोखिम एकदम कम

- [ ] cPanel → File Manager → `public_html/wp-content/` → `mu-plugins` फोल्डर छैन भने **+ Folder** ले बनाउनुहोस् (नाम ठ्याक्कै `mu-plugins`)
- [ ] `fixes/wp-content/mu-plugins/palikalive-fixes.php` अपलोड गर्नुहोस्
- [ ] होमपेज र एउटा लेख Refresh गर्नुहोस्

**जाँच (यही ३ कुरा):**
- [ ] होमपेजमा **h1** देखियो (साइटको नाम — ट्यागलाइन)
- [ ] चारै सेक्सनको "सबै" लिङ्क अब रित्तो छैन (राइट-क्लिक → Copy link): `/content/economy/`, `/content/opinion/`, `/content/interview/`, `/content/5-questions/`
- [ ] लेखमा गए **केही बिग्रेको छैन** (लेआउट, तस्बिर, मेनु ठीक)

> बिग्रियो भने: फाइलको नाम `palikalive-fixes.php.off` बनाउनुहोस् — साइट तुरुन्तै पहिलेजस्तै।

---

## चरण ३ — साइट जाँच उपकरण चलाउने ⏱ २० मिनेट · जोखिम ० (स्क्यान मात्र)

- [ ] `fixes/public_html/palika-toolkit.php` → `public_html/` मा अपलोड गर्नुहोस्
- [ ] ब्राउजरमा खोल्नुहोस्: `https://palikalive.com/palika-toolkit.php` (admin लगइन चाहिन्छ)
- [ ] माथिको **"पूरा रिपोर्ट डाउनलोड (.txt)"** थिचेर राख्नुहोस्

**यो रिपोर्टबाट ३ कुरा नोट गर्नुहोस्:**
- [ ] **views meta key** — कुन key मा भ्यु गनिएको छ (जस्तै `post_views_count`)
- [ ] **wp_head hooks** — थिमका SEO फंक्सनका नाम (जस्तै `palika_seo_meta`)
- [ ] **श्रेणी तालिका** — slug सही छ कि (`interview`, `5-questions` आदि)

---

## चरण ४ — रिपोर्टकै आधारमा २ लाइन मिलाउने ⏱ १५ मिनेट · जोखिम एकदम कम

- [ ] `wp-content/mu-plugins/palikalive-fixes.php` → Edit → यी दुई लाइन भर्नुहोस्:

```php
'views_meta_key'     => 'post_views_count',        // चरण ३ मा देखेको key
'seo_head_callbacks' => 'palika_seo_meta',         // चरण ३ मा देखेको थिमको फंक्सन (एकभन्दा बढी भए कमाले छुट्याउने)
```
- [ ] Save → एउटा लेख खोल्नुहोस् → View Source → `description`, `canonical`, `NewsArticle` प्रत्येक **१ मात्र** छ कि हेर्नुहोस्
- [ ] टुलकिट पेज फेरि खोलेर **views** गन्ती बढेको पुष्टि गर्नुहोस्

---

## चरण ५ — WordPress का सेटिङ (कोड चाहिँदैन) ⏱ २० मिनेट

- [ ] **Settings → General → Site Language → नेपाली** (यसैले `og:locale` ठीक हुन्छ)
- [ ] **Tagline** नेपालीमा: `स्थानीय तह, सुशासन र ताजा समाचार`
- [ ] **Rank Math → Titles & Meta → Homepage** — आफ्नै वाक्यमा meta description (शीर्षक दोहोर्याउने होइन)
- [ ] **Rank Math → Titles & Meta → Global Meta → OpenGraph Thumbnail** — १२००×६३० को फोटो
- [ ] **Rank Math → Redirections** — `/home/` → `/` (301) *(mu-plugin ले पनि गर्छ, दुवै ठीक)*
- [ ] **Appearance → Menus** — `http://www.palikalive.com` भएका लिङ्क `https://palikalive.com/...` बनाउनुहोस्; `Home → /home/` आइटम हटाउनुहोस्
- [ ] **Posts → Categories** — नामका टाइपो: `पालिका बार्ता` → `पालिका वार्ता`, `पालिका खाेज` → `पालिका खोज` (slug फेरिँदैन)
- [ ] **Users** — `लीलाधर काेइराला` → `लीलाधर कोइराला`

---

## चरण ६ — थिमका मुख्य स्निपेट ⏱ १–२ घण्टा · जोखिम कम

कोड: `fixes/theme-snippets.md`। यो क्रममा गर्नुहोस्:

- [ ] **S1** — सेक्सन लिङ्कको असली फिक्स (`arthapalika.php`, `palikabarta.php`, `5prasna.php`, `janapratinidhi-5prasna.php`) — *यो अडिट १.१ को पूर्ण समाधान*
- [ ] **S3** — इन्टरस्टिसियल: लेखमा नदेखाउने + सेसनमा एकै पटक (`skip.php`)
- [ ] **S4** — PDF अपलोड जाँच + `wp_die` हटाउने (`inc/custom-field.php`)
- [ ] **S5** — लोगो fallback ठीक (`inc/custom-field.php`)
- [ ] **S12** — `wp-config.php` hardening (debug false, file editor बन्द)

---

## चरण ७ — गति ⏱ १–२ घण्टा · जोखिम मध्यम (टेस्ट गर्दै)

- [ ] **LiteSpeed Cache** — Cache ON, CSS/JS Minify, Defer, Lazy Load, UCSS, WebP, Font Display swap → प्रत्येक पटक **Purge All**
- [ ] **S6** — फन्ट ७ → २ (`header.php`)
- [ ] **S7** — लाइब्रेरी आवश्यक पेजमा मात्र (`functions.php`)
- [ ] LiteSpeed Lazy Load ON भइसकेपछि mu-plugin मा `'native_lazy_images' => true` गर्नुहोस्
- [ ] [PageSpeed Insights](https://pagespeed.web.dev/) मा अघि/पछि नापेर भिन्नता हेर्नुहोस्

---

## चरण ८ — URL र सफाई ⏱ १ घण्टा

- [ ] Rank Math → Redirections: `/content/helth/` → `/content/health/` (301) **अगाडि**, अनि Categories मा slug `helth` → `health`
- [ ] Pages → **Home → Trash** (चरण ५ को रिडाइरेक्ट पहिले नै बनेकोले सुरक्षित)
- [ ] टुलकिटको **"ब्याकअप सहित guard थप्नुहोस्"** र **"dev फाइल ब्याकअपमा सार्नुहोस्"** बटन (ABSPATH + .DS_Store/SCSS)
- [ ] **S9** — `video.php` को debug भाग हटाउने; `single-old.php` (प्रयोगमा छैन) र `single-writters.php` (टाइपो नाम) व्यवस्थापन

---

## चरण ९ — सर्भर हेडर ⏱ २० मिनेट

- [ ] `fixes/htaccess/palika-snippets.txt` बाट `www → non-www` + **HSTS (सुरुमा `max-age=300`)** `public_html/.htaccess` मा टाँस्ने (*# BEGIN WordPress* भन्दा माथि)
- [ ] `http://palikalive.com`, `https://www.palikalive.com` — दुवै `https://palikalive.com/` मा आएको जाँच्ने
- [ ] **CSP पहिले Report-Only** मा मात्र (विज्ञापन/GA ले तोड्न सक्छ)

---

## चरण १० — काम सकिएपछि ⏱ १५ मिनेट

- [ ] **`palika-toolkit.php` सर्भरबाट Delete गर्नुहोस्** (admin-मात्र भए पनि राख्नु हुँदैन)
- [ ] Search Console → `sitemap_index.xml` पठाउने, Coverage हेर्ने
- [ ] महिनामा एक पटक: टुलकिट फेरि चलाउने, WordPress/थिम/प्लगइन अपडेट (ब्याकअप लिएर)

---

## पछि गर्न सकिने (आवश्यक छैन, तर राम्रो) — २०२६ का थप कुरा

- **थिमभित्रकै SEO एकीकरण** — mu-plugin को `seo_dedupe` ले अहिले नै रोकेको छ; स्थायी समाधान चाहनुहुन्छ भने `theme-snippets.md` → S13/S14 लागू गर्नुहोस्
- **CSS सफाई** (६२० KB → कम) — Bootstrap प्रयोग नभएको भाग हटाउने / LiteSpeed UCSS मा भर पर्ने
- **लेखक बाइलाइन** — "पालिका लाइभ" को साटो वास्तविक रिपोर्टरको नाम+फोटो (news credibility)
- **टिकर/मेनुको दोहोरो मार्कअप** घटाउने — राम्रो कोड स्वास्थ्य
- **Android/iOS ब्याज** र **Viber बटन** — `theme-snippets.md` → S11

> **कुन कुरा यहाँ समेटिएको छैन:** robots.txt/sitemap को भित्री जाँच, Lighthouse को field डाटा (Core Web Vitals), र सर्भरको CSP रिपोर्ट हेर्ने काम। यी चरण १० पछि छुट्टै एक सेसनमा गर्नुपर्छ।
