"""
Builds data/catalog.json: the Lereng catalogue. Origins, processes, altitudes and varietals are
the real ones Indonesian specialty coffee is known for; producers and farm names are invented.
Run: python3 scripts/catalog.py
"""
import json

SIZES = [("200g", 200, 1.0), ("500g", 500, 2.3), ("1kg", 1000, 4.3)]
GRINDS = ["Whole bean", "Espresso", "Pour over", "French press", "Moka pot"]
ROUND = lambda x: int(round(x / 5000.0) * 5000)

RECIPES = {
    "light": "Pour over · 15 g coffee to 250 g water at 93 °C · 2:45 total",
    "medium": "Pour over · 16 g to 250 g at 92 °C · 2:30, or espresso 18 g in, 38 g out, 28 s",
    "medium-dark": "Espresso · 18 g in, 36 g out, 27 s · great with milk",
    "dark": "Tubruk or moka pot · 10 g to 150 g just-off-boil water · let it settle 4 minutes",
}

# (slug, name, island, province, region, producer, process, roast, altitude, varietals, notes, taste tags, score, price200, story)
SINGLE = [
 ("gayo-wine-natural","Gayo Wine Natural","Sumatra","Aceh","Gayo Highlands, Bener Meriah","Koperasi Tani Ketiara Atas","Natural","light","1,450–1,600 m","Ateng, Tim Tim, Bourbon",["Red wine","Dark cherry","Cacao nib"],["fruity","winey"],86.5,155000,"Cherries dried whole on raised beds for 28 days until the skins turn the colour of plum."),
 ("gayo-washed","Gayo Washed","Sumatra","Aceh","Gayo Highlands, Aceh Tengah","Kelompok Tani Lut Tawar","Washed","light","1,400–1,550 m","Ateng, Gayo 1",["Lime","Brown sugar","Black tea"],["citrus","sweet"],85.0,125000,"Grown around Lake Lut Tawar and washed in spring water from the ridge above the farms."),
 ("gayo-wet-hulled","Gayo Giling Basah","Sumatra","Aceh","Takengon","Pak Ramli and neighbours","Wet-hulled","medium-dark","1,300–1,500 m","Ateng, Tim Tim",["Cedar","Dark chocolate","Molasses"],["earthy","chocolatey"],84.0,105000,"Giling basah is how most Sumatran coffee is processed: hulled while still wet, giving that heavy, cedar-like body."),
 ("kerinci-honey","Kerinci Yellow Honey","Sumatra","Jambi","Kayu Aro, Kerinci","Koperasi Alko Kerinci","Honey","light","1,500–1,700 m","Andungsari, Sigarar Utang",["Pineapple","Honey","Lemongrass"],["fruity","sweet"],86.0,145000,"Grown on the slopes of Mount Kerinci, the highest volcano in Indonesia, next to the Kayu Aro tea estates."),
 ("kerinci-washed","Kerinci Washed","Sumatra","Jambi","Gunung Tujuh, Kerinci","Kelompok Tani Danau Kaco","Washed","light","1,400–1,600 m","Sigarar Utang",["Green apple","Jasmine","Cane sugar"],["floral","citrus"],85.25,130000,"A clean, bright washed coffee from smallholders around Lake Gunung Tujuh."),
 ("mandailing-classic","Mandailing Classic","Sumatra","North Sumatra","Pakantan, Mandailing Natal","Koperasi Pakantan","Wet-hulled","medium-dark","1,200–1,500 m","Sigarar Utang, Typica",["Dark chocolate","Tobacco","Spice"],["chocolatey","spicy"],83.75,110000,"The name that made Sumatran coffee famous abroad, roasted a little darker for a syrupy cup."),
 ("lintong-natural","Lintong Natural","Sumatra","North Sumatra","Lintong Nihuta, Humbang Hasundutan","Ama Siregar","Natural","medium","1,300–1,500 m","Sigarar Utang, Ateng",["Blueberry","Clove","Fudge"],["fruity","spicy"],85.5,135000,"Grown on the high plateau south of Lake Toba, where the morning mist clears late."),
 ("sidikalang-washed","Sidikalang Washed","Sumatra","North Sumatra","Sidikalang, Dairi","Kelompok Tani Sitinjo","Washed","medium","1,100–1,400 m","Sigarar Utang",["Toffee","Orange peel","Nutmeg"],["sweet","spicy"],84.25,115000,"Dairi has grown coffee since the Dutch era; this lot is a modern washed take on it."),
 ("solok-radjo-natural","Solok Radjo Natural","Sumatra","West Sumatra","Aie Dingin, Solok","Koperasi Solok Radjo","Natural","light","1,400–1,700 m","Andungsari, Sigarar Utang",["Strawberry","Rum","Milk chocolate"],["fruity","sweet"],86.25,150000,"A young cooperative in the hills around Mount Talang, now a regular at national cupping competitions."),
 ("java-malabar-washed","Java Malabar Washed","Java","West Java","Mount Malabar, Bandung","Kebun Pangalengan","Washed","light","1,400–1,600 m","Sigarar Utang, Typica, Lini S",["Mandarin","Hazelnut","Caramel"],["citrus","nutty"],85.0,125000,"The slopes of Mount Malabar, south of Bandung, are where Java's specialty revival started."),
 ("java-puntang-honey","Java Puntang Honey","Java","West Java","Mount Puntang, Bandung","Pak Ayi and family","Honey","light","1,300–1,500 m","Sigarar Utang, Lini S",["Peach","Honey","Rose"],["floral","sweet"],86.75,160000,"A family plot on Mount Puntang that has placed in the Indonesian specialty coffee championship."),
 ("java-halu-natural","Java Halu Natural","Java","West Java","Mount Halu, West Bandung","Kelompok Tani Margamulya","Natural","light","1,450–1,650 m","Typica, Sigarar Utang",["Raspberry","Cocoa","Vanilla"],["fruity","chocolatey"],85.75,145000,"Dried slowly in the cool air of Mount Halu, under the shade of eucalyptus."),
 ("java-papandayan-washed","Java Papandayan Washed","Java","West Java","Mount Papandayan, Garut","Koperasi Cikajang","Washed","light","1,500–1,700 m","Sigarar Utang, Andungsari",["Lemon","Black tea","Brown sugar"],["citrus","floral"],85.25,130000,"High-grown near the Papandayan crater; volcanic soil, cold nights, slow ripening."),
 ("java-cikuray-semi-washed","Java Cikuray","Java","West Java","Mount Cikuray, Garut","Kebun Cikuray","Semi-washed","medium","1,300–1,500 m","Lini S, Sigarar Utang",["Milk chocolate","Almond","Orange"],["chocolatey","nutty"],84.25,115000,"An easy, round coffee that works as well in a V60 as in a flat white."),
 ("java-sindoro-natural","Java Sindoro Natural","Java","Central Java","Mount Sindoro, Temanggung","Kelompok Tani Sindoro","Natural","medium","1,200–1,500 m","Kartika, Lini S",["Jackfruit","Brown sugar","Spice"],["fruity","spicy"],84.75,125000,"Temanggung is better known for tobacco; its farmers now grow arabica between the rows."),
 ("java-ijen-washed","Java Ijen Washed","Java","East Java","Ijen Plateau, Bondowoso","Kebun Blawan","Washed","medium","1,100–1,450 m","Typica, Bourbon",["Hazelnut","Caramel","Red apple"],["nutty","sweet"],84.0,110000,"From the old estates on the Ijen plateau, near the blue-fire crater."),
 ("java-argopuro-honey","Java Argopuro Honey","Java","East Java","Mount Argopuro, Situbondo","Kelompok Tani Argopuro","Honey","light","1,200–1,500 m","Typica, Lini S",["Apricot","Honey","Cinnamon"],["sweet","spicy"],85.0,130000,"A forest coffee: grown under native shade trees on the slopes of Argopuro."),
 ("bali-kintamani-washed","Bali Kintamani Washed","Bali","Bali","Kintamani, Bangli","Subak Abian Kerta Waringin","Washed","light","1,200–1,500 m","Kopyol, Typica",["Orange","Brown sugar","Lemongrass"],["citrus","sweet"],85.25,130000,"Farmed under the subak abian system, Bali's traditional cooperative way of sharing land and water."),
 ("bali-kintamani-natural","Bali Kintamani Natural","Bali","Bali","Belantih, Kintamani","Pak Ketut Sudira","Natural","light","1,300–1,500 m","Kopyol, Lini S",["Mango","Cacao","Tamarind"],["fruity","sweet"],86.0,145000,"Grown between orange trees, which is why so many Kintamani coffees taste of citrus."),
 ("flores-bajawa-washed","Flores Bajawa Washed","Nusa Tenggara","East Nusa Tenggara","Bajawa, Ngada","Koperasi Fa Masa","Washed","light","1,200–1,600 m","Typica, Catimor",["Jasmine","Lime","Black tea"],["floral","citrus"],85.5,140000,"High on the Bajawa plateau of Flores, under the Inerie volcano."),
 ("flores-manggarai-natural","Flores Manggarai Natural","Nusa Tenggara","East Nusa Tenggara","Colol, Manggarai Timur","Kelompok Tani Colol","Natural","medium","1,200–1,500 m","Typica, Juria",["Dark berries","Spice","Cocoa"],["fruity","spicy"],85.0,135000,"Colol village keeps old Typica and Juria trees that most of Indonesia replanted decades ago."),
 ("toraja-sapan-washed","Toraja Sapan Washed","Sulawesi","South Sulawesi","Sapan, Tana Toraja","Koperasi Sapan Minanga","Washed","medium","1,500–1,800 m","S795, Typica",["Red currant","Dark chocolate","Cedar"],["fruity","chocolatey"],85.75,145000,"From the highlands of Tana Toraja, where coffee is grown around carved tongkonan houses."),
 ("toraja-kalosi-wet-hulled","Toraja Kalosi","Sulawesi","South Sulawesi","Kalosi, Enrekang","Kelompok Tani Latimojong","Wet-hulled","medium-dark","1,400–1,700 m","S795, Typica",["Dark chocolate","Spice","Earth"],["chocolatey","earthy"],84.0,115000,"Kalosi is a trading town for coffee from the Latimojong mountains; full-bodied and low in acidity."),
 ("mamasa-natural","Mamasa Natural","Sulawesi","West Sulawesi","Mamasa","Kelompok Tani Rantebulahan","Natural","light","1,300–1,600 m","S795, Typica",["Plum","Brown sugar","Rose"],["fruity","floral"],85.5,140000,"A small, remote valley west of Toraja reached by a single winding road."),
 ("papua-wamena-washed","Papua Wamena Washed","Papua","Highland Papua","Baliem Valley, Wamena","Koperasi Baliem Arabica","Washed","medium","1,500–1,900 m","Typica",["Milk chocolate","Hazelnut","Plum"],["chocolatey","nutty"],85.0,155000,"Typica trees planted in the 1950s, grown without chemicals in the Baliem Valley."),
 ("papua-moanemani-washed","Papua Moanemani","Papua","Central Papua","Moanemani, Dogiyai","Kelompok Tani Kamuu","Washed","medium","1,500–1,700 m","Typica",["Caramel","Orange","Spice"],["sweet","citrus"],84.75,150000,"From the Kamuu valley; flown out of the highlands by small plane before it reaches us."),
 ("lampung-robusta-fine","Lampung Fine Robusta","Sumatra","Lampung","Sumberjaya, Lampung Barat","Kelompok Tani Way Petai","Natural","medium-dark","700–900 m","Robusta (BP 42, BP 358)",["Dark chocolate","Peanut","Molasses"],["chocolatey","nutty"],82.5,75000,"Fine robusta: red-cherry picked and carefully dried, made for kopi susu and tubruk."),
 ("temanggung-robusta-natural","Temanggung Fine Robusta","Java","Central Java","Mount Sumbing, Temanggung","Kelompok Tani Sumbing","Natural","dark","800–1,100 m","Robusta",["Cocoa","Roasted grain","Spice"],["chocolatey","earthy"],82.0,70000,"Sun-dried robusta from the lower slopes of Sumbing; strong and sweet with condensed milk."),
 ("liberika-jambi","Jambi Liberica","Sumatra","Jambi","Tanjung Jabung Barat","Kelompok Tani Mekar Jaya","Natural","medium","10–50 m","Liberica",["Jackfruit","Smoke","Dark sugar"],["fruity","earthy"],82.0,95000,"Liberica grows on the peat lowlands of Jambi's east coast; big beans, unusual jackfruit sweetness."),
]

BLENDS = [
 ("lereng-house-espresso","Lereng House Espresso","medium-dark",["Milk chocolate","Caramel","Red apple"],["chocolatey","sweet"],"Mandailing, Java Cikuray, Bali Kintamani",115000,"Our everyday espresso. Built to taste good straight and better with milk."),
 ("pagi-filter-blend","Pagi Filter Blend","light",["Orange","Honey","Black tea"],["citrus","sweet"],"Gayo washed, Flores Bajawa",120000,"Bright and easy, for the first cup of the day."),
 ("kopi-susu-blend","Kopi Susu Blend","medium-dark",["Palm sugar","Dark chocolate","Roasted nut"],["chocolatey","nutty"],"Lampung fine robusta, Gayo wet-hulled",95000,"Arabica and robusta, roasted for es kopi susu with gula aren."),
 ("malam-decaf","Malam Decaf","medium",["Milk chocolate","Toffee","Plum"],["chocolatey","sweet"],"Gayo, sugarcane decaffeinated",130000,"Decaffeinated with sugarcane ethyl acetate, so it still tastes like coffee after dinner."),
 ("tubruk-blend","Tubruk Blend","dark",["Cocoa","Spice","Roasted grain"],["chocolatey","spicy"],"Temanggung robusta, Toraja Kalosi",80000,"Ground fine for kopi tubruk: boiled water straight onto the grounds, sugar optional."),
]

MICROLOTS = [
 ("gayo-anaerobic-natural-lot-07","Gayo Anaerobic Natural · Lot 07","Sumatra","Aceh","Bener Meriah","Ibu Rahmah","Anaerobic natural","light","1,550 m","Ateng Super",["Strawberry yoghurt","Cinnamon","Red wine"],["fruity","winey"],88.0,195000,60,"Fermented in sealed tanks for 96 hours before drying. Only 60 bags of this lot."),
 ("kerinci-carbonic-lot-02","Kerinci Carbonic Maceration · Lot 02","Sumatra","Jambi","Kayu Aro","Koperasi Alko Kerinci","Carbonic maceration","light","1,650 m","Andungsari",["Grape","Hibiscus","Candied orange"],["fruity","floral"],88.5,220000,40,"Whole cherries fermented under CO₂, a technique borrowed from Beaujolais winemaking."),
 ("java-puntang-gesha","Java Puntang Gesha","Java","West Java","Mount Puntang","Pak Ayi and family","Washed","light","1,450 m","Gesha",["Jasmine","Bergamot","Peach"],["floral","citrus"],89.0,260000,30,"Gesha seedlings planted in 2019; this is the first harvest big enough to sell."),
 ("bali-kintamani-double-ferment","Bali Kintamani Double Ferment","Bali","Bali","Belantih","Pak Ketut Sudira","Double-fermented honey","light","1,450 m","Kopyol",["Passion fruit","Molasses","Lime"],["fruity","citrus"],87.5,185000,50,"Fermented twice, once in cherry and once as parchment, then dried as a red honey."),
]

def coffee(slug, name, kind, **k):
    base = k["price"]
    variations = []
    sizes = SIZES if kind != "microlot" else [("100g", 100, 1.0), ("250g", 250, 2.35)]
    for size, grams, mult in sizes:
        for g in GRINDS:
            variations.append({"size": size, "grind": g, "price": ROUND(base * mult), "weight_g": grams + 40,
                               "sku": f"LR-{slug[:18].upper()}-{grams}-{g.split()[0][:2].upper()}"})
    return {"slug": slug, "name": name, "type": "coffee", "kind": kind, **k, "variations": variations}

products = []
for s in SINGLE:
    (slug,name,island,prov,region,producer,process,roast,alt,var,notes,taste,score,price,story) = s
    products.append(coffee(slug, name, "single-origin", island=island, province=prov, region=region, producer=producer, process=process, roast=roast,
        altitude=alt, varietals=var, notes=notes, taste=taste, score=score, price=price, story=story, species="Robusta" if "Robusta" in var else ("Liberica" if "Liberica" in var else "Arabica"),
        harvest="2026", recipe=RECIPES[roast], subscribable=True, category="single-origin"))
for (slug,name,roast,notes,taste,components,price,story) in BLENDS:
    products.append(coffee(slug, name, "blend", roast=roast, notes=notes, taste=taste, components=components, price=price, story=story,
        process="Blend", island="Blend", species="Arabica & robusta" if "robusta" in components.lower() else "Arabica", recipe=RECIPES[roast], subscribable=True, category="blends"))
for (slug,name,island,prov,region,producer,process,roast,alt,var,notes,taste,score,price,stock,story) in MICROLOTS:
    p = coffee(slug, name, "microlot", island=island, province=prov, region=region, producer=producer, process=process, roast=roast, altitude=alt,
        varietals=var, notes=notes, taste=taste, score=score, price=price, story=story, species="Arabica", harvest="2026", recipe=RECIPES[roast], subscribable=False, category="micro-lots")
    p["stock"] = stock
    products.append(p)

# Pre-order: the next micro-lot, roasting soon.
products[-1]["preorder"] = {"ships": "2026-11-20", "note": "Roasting on 18 November. Ships from 20 November."}

DRIP = [("drip-bag-gayo","Drip Bags · Gayo Washed (10)",95000,"gayo-washed"),("drip-bag-kintamani","Drip Bags · Bali Kintamani (10)",95000,"bali-kintamani-washed"),("drip-bag-house","Drip Bags · Pagi Filter Blend (10)",85000,"pagi-filter-blend")]
for slug,name,price,src in DRIP:
    products.append({"slug": slug, "name": name, "type": "simple", "category": "drip-bags", "price": price, "weight_g": 150, "sku": f"LR-{slug.upper()}",
                     "description": "Ten single-serve drip bags. Hang one on a cup, pour 180 ml of water just off the boil in three pours.", "source": src, "subscribable": True})

COLD = [("cold-brew-concentrate-500","Cold Brew Concentrate · 500 ml",85000),("cold-brew-concentrate-1l","Cold Brew Concentrate · 1 L",155000)]
for slug,name,price in COLD:
    products.append({"slug": slug, "name": name, "type": "simple", "category": "cold-brew", "price": price, "weight_g": 600 if "500" in slug else 1100, "sku": f"LR-{slug.upper()}",
                     "description": "Steeped for 18 hours from Java Cikuray. Mix 1:2 with water or milk. Keep refrigerated; delivered chilled in Bali only.",
                     "shipping_class": "chilled-bali-only"})

EQUIP = [
 ("ceramic-dripper-02","Ceramic Dripper 02","brewers",285000,450,"A cone dripper with spiral ribs, size 02 (1–4 cups). Heavy ceramic keeps the brew temperature steady."),
 ("flat-bottom-dripper","Flat-Bottom Dripper 185","brewers",395000,350,"Three small holes and a flat bed for an even, forgiving extraction."),
 ("glass-pour-over-carafe","Glass Pour-Over Carafe 6-cup","brewers",620000,700,"Borosilicate glass carafe with a wooden collar. Brew and serve in one piece."),
 ("immersion-brewer","Immersion Brewer","brewers",545000,400,"Pressure brewer for a full cup in two minutes. Travels well."),
 ("french-press-600","French Press 600 ml","brewers",345000,700,"Double-wall steel press with a fine mesh filter."),
 ("moka-pot-3","Moka Pot 3-cup","brewers",395000,600,"Aluminium stovetop brewer. Works with our Tubruk and Kopi Susu blends."),
 ("hand-grinder-c3","Hand Grinder · Conical Burr","grinders",895000,550,"Stainless steel conical burrs with 36 click settings, from espresso to French press."),
 ("electric-grinder-flat","Electric Grinder · Flat Burr","grinders",3450000,3200,"64 mm flat burrs, single-dose hopper, low retention. For the home bar."),
 ("gooseneck-kettle","Gooseneck Kettle 0.9 L","kettles-scales",745000,900,"Stovetop gooseneck for a slow, precise pour. Works on induction."),
 ("electric-kettle-temp","Electric Kettle · Temperature Control",'kettles-scales',1650000,1400,"Holds water at any temperature from 60 to 100 °C."),
 ("brew-scale","Brew Scale with Timer","kettles-scales",425000,500,"0.1 g resolution, built-in timer, USB-C charging."),
 ("paper-filters-02","Paper Filters 02 · 100 pcs","filters",65000,150,"Unbleached paper filters for 02 cone drippers."),
 ("paper-filters-flat","Paper Filters 185 · 100 pcs","filters",85000,150,"Wave filters for flat-bottom drippers."),
 ("glass-server-600","Glass Server 600 ml","brewers",225000,400,"Heat-resistant server with 100 ml markings."),
]
for slug,name,cat,price,w,desc in EQUIP:
    products.append({"slug": slug, "name": name, "type": "simple", "category": cat, "price": price, "weight_g": w, "sku": f"LR-EQ-{slug.upper()[:16]}", "description": desc, "stock": 25})

MERCH = [
 ("enamel-mug","Enamel Camp Mug",145000,350,None,"Speckled enamel mug, 350 ml. Survives campfires and dishwashers."),
 ("canvas-tote","Canvas Tote",125000,250,None,"Heavy canvas tote, fits 3 kg of beans and a laptop."),
 ("lereng-tee","Lereng Tee",225000,250,["S","M","L","XL"],"Heavyweight cotton tee with the Lereng contour-line print."),
 ("bucket-hat","Bucket Hat",195000,150,None,"Washed cotton bucket hat for the farm visits."),
]
for slug,name,price,w,sizes,desc in MERCH:
    p = {"slug": slug, "name": name, "type": "variable-merch" if sizes else "simple", "category": "merch", "price": price, "weight_g": w, "sku": f"LR-MR-{slug.upper()}", "description": desc, "stock": 40}
    if sizes: p["sizes"] = sizes
    products.append(p)

GIFT = {"slug": "lereng-gift-card", "name": "Lereng Gift Card", "type": "gift-card", "category": "gift-cards", "amounts": [100000, 250000, 500000, 1000000],
        "description": "Sent by email right away, with your message. Works on everything in the shop, including subscriptions, and never expires."}
products.append(GIFT)

BUNDLES = [
 {"slug": "discovery-box", "name": "Discovery Box · Pick 3", "type": "bundle-pick", "category": "bundles", "pick": 3, "size": "100g", "discount": 15, "from": "single-origin",
  "description": "Choose any three single origins in 100 g bags and save 15%. The easiest way to find your coffee."},
 {"slug": "brew-starter-kit", "name": "Brew Starter Kit", "type": "bundle-fixed", "category": "bundles", "discount": 12,
  "items": [["ceramic-dripper-02", 1], ["paper-filters-02", 1], ["glass-server-600", 1], ["pagi-filter-blend", 1]],
  "description": "Everything for your first pour over: dripper, filters, server and 200 g of Pagi Filter Blend, 12% less than buying them separately."},
 {"slug": "office-pack", "name": "Office Pack · 3 kg", "type": "bundle-fixed", "category": "bundles", "discount": 10,
  "items": [["lereng-house-espresso", 3]], "variation": {"size": "1kg", "grind": "Whole bean"},
  "description": "Three kilos of House Espresso, whole bean, for the office machine. 10% off."},
]
products += BUNDLES

products.append({"slug": "roasters-choice-subscription", "name": "Roaster's Choice Subscription", "type": "subscription-only", "category": "subscriptions",
                 "price": 125000, "description": "A different single origin every delivery, chosen by our roasters, with tasting notes and a brew card. Pause or cancel anytime.",
                 "sizes": ["200g", "500g"], "grind_choice": True})

CATEGORIES = [
 ("coffee", "Coffee", "Kopi", None), ("single-origin", "Single origin", "Single origin", "coffee"), ("blends", "Blends", "Blend", "coffee"),
 ("micro-lots", "Micro-lots", "Micro-lot", "coffee"), ("drip-bags", "Drip bags", "Drip bag", "coffee"), ("cold-brew", "Cold brew", "Cold brew", "coffee"),
 ("equipment", "Equipment", "Peralatan", None), ("brewers", "Brewers", "Alat seduh", "equipment"), ("grinders", "Grinders", "Grinder", "equipment"),
 ("kettles-scales", "Kettles & scales", "Ketel & timbangan", "equipment"), ("filters", "Filters", "Filter", "equipment"),
 ("merch", "Merch", "Merchandise", None), ("gift-cards", "Gift cards", "Kartu hadiah", None), ("bundles", "Bundles", "Paket", None), ("subscriptions", "Subscriptions", "Langganan", None),
]

catalog = {"brand": "Lereng Coffee Roasters", "currency": "IDR", "categories": [dict(zip(["slug","name","name_id","parent"], c)) for c in CATEGORIES], "products": products}
json.dump(catalog, open("data/catalog.json", "w"), indent=1, ensure_ascii=False)
coffees = [p for p in products if p["type"] == "coffee"]
print(f"{len(products)} products, {len(coffees)} coffees, {sum(len(p['variations']) for p in coffees)} coffee variations")
