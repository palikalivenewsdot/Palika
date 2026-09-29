# ०३ — अब के गर्ने (बाँकी कामको चेकलिस्ट)

आजको मिति: **२०२६-०९-२९**। साइट: `palikalive.com` (WP 7.1.2, PHP 8.5.10, LiteSpeed Cache 7.9.1, Rank Math 1.0.279)।
नीति: **थिम फाइल कहिल्यै छुँदैनौं** — हरेक परिवर्तन `wp-content/mu-plugins/` भित्र एउटा छुट्टै फाइल। मन नपरे फाइलको नाम फेर्ने वा डिलिट गर्ने — साइट पहिलेजस्तै।

---

## ✅ सकिएका काम (प्रमाणित)

| # | काम | फाइल / प्रमाण |
|---|---|---|
| १ | लेख पेज सुधार (टाइटल auto-hide, सफा lead, ५ शेयर बटन + जीवित संख्या, होमपेज h1 लुकाइएको) | `mu-plugins/palikalive-single.php` v3.1.0 — तपाईंले लाइभमा पुष्टि गर्नुभयो |
| २ | Ad Engine (क्यास-मैत्री, header rotation, लेखभित्र ३ ad, ADVERTISEMENT लेबल) | `mu-plugins/palikalive-ads.php` v48 — लाइभमा `pklv-v48-css` देखियो |
| ३ | सुधार प्याक | `mu-plugins/palikalive-fixes.php` v2.0.0 — `views_meta_key = sandesh_post_views_count` राखियो; `seo_head_callbacks` छोइएन |
| ४ | स्क्यान | `palika-scan.php` चलेको, रिपोर्ट विश्लेषण भइसकेको |
| ५ | क्यास निदान | `palika-cache-find.php` चलेको — **क्यास चालु र ठीक**: `cache=1`, TTL 604800, कुनै exclusion छैन, थिमको कोड सफा; पछि `x-litespeed-cache: hit` देखियो |
| ६ | बाहिरी सफाई | `/home/` → 301, www → non-www 301, होमपेजमा खाली `href=""` = ०, HSTS लाइभ |

**याद राख्नुहोस्:** लगइन गरेको admin लाई LiteSpeed ले सधैं ताजा पेज देखाउँछ (`vary_group administrator=99`) — क्यास जाँच्ने बेला **Incognito** चलाउनुहोस्।

---

## ⏳ चरण A — गति १ (आजको काम)

- [ ] `mu-plugins/palikalive-speed.php` (**Speed Pack v1.0.0**) अपलोड गर्नुहोस्
	  — फन्ट ७ → पेजले साँच्चै प्रयोग गरेको मात्र, `display=swap`, र fonts.gstatic + image CDN को **preconnect**
- [ ] LiteSpeed Cache → **Purge All**
- [ ] `https://palikalive.com/?pklv_speed_report=1` खोलेर रिपोर्ट पठाउनुहोस् (के बदल्यो + कुन CSS/JS ह्यान्डल चढ्यो)
- [ ] **LiteSpeed → Page Optimization**:
	  - [ ] CSS Minify **ON** · CSS Combine **OFF** · UCSS **OFF**
	  - [ ] JS Minify **ON** · JS Combine **OFF** · JS Deferred/Delayed **OFF**
	  - [ ] HTML Minify **ON** · Inline CSS/JS Minify **ON** · Remove Comments **ON**
	  - [ ] Lazy Load **OFF** (थिम आफैं गर्छ) · **Image Optimization सबै OFF** (EWWW चलिरहेको छ — दोहोरो काम हटाउने)
- [ ] पछि: LiteSpeed → **Crawler ON** (क्यास न्यानो रहन्छ) + `sitemap_index.xml` हाल्ने

## ⏳ चरण B — WordPress सेटिङ (कोड चाहिँदैन)

- [ ] Settings → General → **Site Language → नेपाली** (अहिले `og:locale = en_US` छ)
- [ ] Rank Math → Titles & Meta → **Posts → Schema Type = NewsArticle** (अहिले सबै पेजमा `NewsArticle` = ०)
- [ ] Rank Math → Titles & Meta → Global Meta → **OpenGraph Thumbnail** १२००×६३०
- [ ] Posts → Categories — **नाम मात्र** (slug नछुने):
	  - [ ] ID 236 `पालिका खाेज` → `पालिका खोज`
	  - [ ] ID 20 `पालिका बार्ता` → `पालिका वार्ता`
	  - [ ] ID 3782 `हाम्राे प्रश्न` → `हाम्रो प्रश्न`
- [ ] ID 377 slug `helth` → `health` — **पहिले** Rank Math → Redirections मा `/content/helth/` → `/content/health/` (301) राखेर मात्र

## ⏳ चरण C — सफाई (५ मिनेट)

- [ ] Plugins → **Palika Live — Ad Position Migration (One-Time Tool)** → Deactivate → Delete
- [ ] Plugins → **xml-sitemap-feed** भेटिए Deactivate → Delete (सक्रिय सूचीमा छैन; Rank Math सँग दोहोरो)
- [ ] File Manager → `public_html/palika-scan.php` **Delete** (रिपोर्ट आइसक्यो)
- [ ] File Manager → `public_html/palika-cache-find.php` **Delete**
- [ ] `/public_html/backup/` फोल्डर — zip कपी राखेर Delete, वा Directory Privacy (password) लगाउने
- [ ] `public_html/wp-config.php` खोलेर `PKLV_ADS` खोज्नुहोस् — `PKLV_ADS_NOCACHE` भेटिए `false` बनाउनुहोस्

## ⏳ चरण D — गति २ (रिपोर्टपछि)

- [ ] **S7** — लाइब्रेरी (Colorbox / Owl / datepicker / Bootstrap JS) आवश्यक पेजमा मात्र चलाउने — कुन ह्यान्डल कहाँ चल्छ भन्ने डाटा Speed Pack को रिपोर्टले दिन्छ
- [ ] **S6 बाँकी** — `Khand` चाहिएको भए `'always_keep' => array( 'Khand' )` (एक लाइन)
- [ ] LiteSpeed → Cache → **Browser Cache TTL** व्यवस्थित (अहिले 31557600 सेकेन्ड = १ वर्ष, ठीकै छ)
- [ ] PageSpeed Insights मा अघि/पछि नाप्ने

## ⏳ चरण E — बाँकी सुधार (mu-plugin मा मात्र)

- [ ] **S3** — इन्टरस्टिसियल ad: लेखमा नदेखाउने + सेसनमा एकै पटक (mu-plugin रूपमा दिने)
- [ ] **S9** — `video.php` को debug भाग, `single-old.php` (प्रयोग नभएको) को व्यवस्थापन
- [ ] Pages → **Home (ID 117658) → Trash** (गृहपृष्ठ लिङ्क बाँकी रहने)
- [ ] Search Console → `sitemap_index.xml` पठाउने, Coverage हेर्ने

---

## 🧾 राखिएका फाइलहरू (repo)

| फाइल | कहाँ जान्छ | अवस्था |
|---|---|---|
| `fixes/wp-content/mu-plugins/palikalive-single.php` | `wp-content/mu-plugins/` | ✅ लाइभ (v3.1.0) |
| `fixes/wp-content/mu-plugins/palikalive-ads.php` | `wp-content/mu-plugins/` | ✅ लाइभ (v48.0.0) |
| `fixes/wp-content/mu-plugins/palikalive-fixes.php` | `wp-content/mu-plugins/` | ✅ लाइभ (v2.0.0, views key मिलाइयो) |
| `fixes/wp-content/mu-plugins/palikalive-speed.php` | `wp-content/mu-plugins/` | ⏳ आज अपलोड गर्ने (v1.0.0) |
| `fixes/public_html/palika-scan.php` | `public_html/` | ⏳ काम सकियो — डिलिट गर्ने |
| `fixes/public_html/palika-cache-find.php` | `public_html/` | ⏳ काम सकियो — डिलिट गर्ने |
| `fixes/public_html/palika-toolkit.php` | `public_html/` | पुरानो स्क्यानर — अब चाहिँदैन |
| `fixes/wp-content/themes/palikalive/single.php` | ❌ **कहिल्यै नचढाउने** | critical error दिएको (theme paste banned) |
| `fixes/theme-snippets.md` | — | थिम स्निपेटका स्रोत (अब mu-plugin रूपमा मात्र लागू) |
| `fixes/htaccess/palika-snippets.txt` | `public_html/.htaccess` | www 301 + HSTS **पहिल्यै लाइभ** |

> **रोलब्याक सधैं उही:** mu-plugin फाइलको नाम फेर्ने (`.bak`) वा डिलिट गर्ने → LiteSpeed → Purge All।
