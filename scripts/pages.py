"""Builds data/pages.json: content pages as block markup. Run: python3 scripts/pages.py"""
import json, html

def p(text, cls=None):
    c = f' {{"className":"{cls}"}}' if cls else ''
    k = f' class="{cls}"' if cls else ''
    return f'<!-- wp:paragraph{c} -->\n<p{k}>{text}</p>\n<!-- /wp:paragraph -->'
def h(text, level=2):
    return f'<!-- wp:heading {{"level":{level}}} -->\n<h{level} class="wp-block-heading">{text}</h{level}>\n<!-- /wp:heading -->'
def ul(items):
    li = ''.join(f'<!-- wp:list-item --><li>{i}</li><!-- /wp:list-item -->' for i in items)
    return f'<!-- wp:list -->\n<ul class="wp-block-list">{li}</ul>\n<!-- /wp:list -->'
def details(summary, body):
    return f'<!-- wp:details -->\n<details class="wp-block-details"><summary>{summary}</summary>{body}</details>\n<!-- /wp:details -->'
def sc(code):
    return f'<!-- wp:shortcode -->\n{code}\n<!-- /wp:shortcode -->'
def table(head, rows):
    th = ''.join(f'<th>{x}</th>' for x in head)
    tr = ''.join('<tr>' + ''.join(f'<td>{x}</td>' for x in r) + '</tr>' for r in rows)
    return f'<!-- wp:table {{"className":"is-style-stripes"}} -->\n<figure class="wp-block-table is-style-stripes"><table><thead><tr>{th}</tr></thead><tbody>{tr}</tbody></table></figure>\n<!-- /wp:table -->'
def btn(label, url):
    return f'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{url}">{label}</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'

def wide(html_, cls=''):
    """A wide section of hand-written markup (landing pages)."""
    c = f' {{"align":"wide","className":"{cls}"}}' if cls else ' {"align":"wide"}'
    k = f' {cls}' if cls else ''
    return f'<!-- wp:group{c} -->\n<div class="wp-block-group alignwide{k}"><!-- wp:html -->\n{html_}\n<!-- /wp:html --></div>\n<!-- /wp:group -->'
def wide_sc(code, cls):
    return f'<!-- wp:group {{"align":"wide","className":"{cls}"}} -->\n<div class="wp-block-group alignwide {cls}">' + sc(code) + '</div>\n<!-- /wp:group -->'
def hero(kicker, title, lead, actions, facts):
    a = ''.join(f'<a class="lp-btn{" lp-btn--ghost" if i else ""}" href="{u}">{l}</a>' for i, (l, u) in enumerate(actions))
    f = ''.join(f'<li><b>{b}</b><span>{t}</span></li>' for b, t in facts)
    return wide(f'<section class="lp-hero"><div class="lp-hero__text"><p class="kicker">{kicker}</p><h1>{title}</h1><p class="lp-lead">{lead}</p><div class="lp-actions">{a}</div></div><ul class="lp-facts">{f}</ul></section>')
def lp_steps(kicker, title, items, cls=''):
    li = ''.join(f'<li><b>{i+1:02d}</b><h3>{h_}</h3><p>{t}</p></li>' for i, (h_, t) in enumerate(items))
    return wide(f'<section class="lp-section {cls}"><p class="kicker">{kicker}</p><h2>{title}</h2><ol class="lp-steps">{li}</ol></section>')
def faq(title, items):
    d = ''.join(f'<details><summary>{q}</summary><p>{a}</p></details>' for q, a in items)
    return wide(f'<section class="lp-faq"><h2>{title}</h2><div class="lp-faq__list">{d}</div></section>')

GUIDES = [
 ("Pour over (V60 or similar)", "15 g · 250 g water · 93 °C · 2:45", ["Grind medium-fine, like table salt.", "Rinse the paper filter with hot water and discard the water.", "Bloom: pour 40 g of water, wait 40 seconds.", "Pour slowly in circles to 150 g by 1:15, then to 250 g by 1:45.", "Let it drain; aim to finish around 2:45. Coarser if slower, finer if faster."]),
 ("Flat-bottom dripper", "16 g · 260 g water · 92 °C · 3:00", ["Grind medium.", "Bloom with 50 g for 30 seconds.", "Pour in four equal pulses of 50–55 g, every 30 seconds.", "Swirl gently at the end for a flat bed."]),
 ("Immersion brewer (AeroPress style)", "15 g · 220 g water · 85 °C · 2:00", ["Grind medium-fine.", "Add coffee and all the water, stir three times.", "Put the plunger on to stop dripping; wait until 1:45.", "Swirl, then press gently for 15 seconds."]),
 ("French press", "30 g · 500 g water · 95 °C · 4:00", ["Grind coarse, like breadcrumbs.", "Pour all the water, stir once.", "At 4:00, break the crust and skim the foam off the top.", "Press slowly and pour everything out so it doesn't keep brewing."]),
 ("Moka pot", "Fill the basket level · water to just under the valve", ["Grind finer than pour over, coarser than espresso.", "Use hot water in the base so the coffee heats less on the stove.", "Medium-low heat, lid open; take it off as soon as it starts to sputter.", "Cool the base under the tap to stop extraction."]),
 ("Kopi tubruk", "10 g · 150 g water just off the boil", ["Grind very fine (our Tubruk Blend is ground for this).", "Put coffee (and sugar, if you like) in the glass.", "Pour the water, stir once.", "Wait four minutes for the grounds to settle, then sip from the top."]),
 ("Iced kopi susu", "18 g espresso or 40 g strong pour over · 120 ml milk · 20 ml gula aren syrup", ["Dissolve palm sugar 1:1 in hot water for the syrup.", "Fill a glass with ice, add milk and syrup.", "Pour the coffee over the top and stir."]),
]
guides = [p('How we brew our coffee at the tasting bar. Start with these recipes, then adjust one thing at a time: grind finer if it tastes sour or thin, coarser if it tastes bitter or dry.', 'lead')]
for title, spec, steps in GUIDES:
    guides += [h(title), p(f'<span class="spec">{spec}</span>'), f'<!-- wp:list {{"ordered":true}} -->\n<ol class="wp-block-list">' + ''.join(f'<!-- wp:list-item --><li>{s}</li><!-- /wp:list-item -->' for s in steps) + '</ol>\n<!-- /wp:list -->']
guides += [h('Water'), p('Coffee is 98% water. Bali tap water is hard; filtered water or a light mineral water (around 50–100 ppm) makes every coffee here taste brighter and sweeter.')]

pages = {
 "subscriptions": {"title": "Subscriptions", "template": "page-wide", "content": "\n".join([
    hero('Subscriptions', 'Fresh beans before the last ones run out.',
         'Pick a coffee, or let our roasters choose. It arrives every one, two or four weeks, roasted that week, with 10% off every bag.',
         [('Choose a coffee', '#choose'), ('Roaster’s Choice', '/product/roasters-choice-subscription/')],
         [('10%', 'off every bag, every delivery'), ('1 · 2 · 4', 'weeks between deliveries, your pick'), ('Mon &amp; Thu', 'roast days, shipped the same afternoon'), ('0', 'minimum deliveries, cancel anytime')]),
    lp_steps('How it works', 'Set it up once, change it whenever you like.', [
        ('Choose', 'Pick a coffee, size and grind and choose <b>Subscribe &amp; save</b>, or start the Roaster’s Choice for a different origin each time.'),
        ('We roast', 'Your first bag is roasted on the next Monday or Thursday and ships the same afternoon.'),
        ('Pay each delivery', 'Two days before each roast day we email a QRIS or virtual-account link. Each renewal is a normal order with tracking and a receipt.'),
        ('Stay in control', 'From <a href="/my-account/subscriptions/">My account</a>: skip a delivery, pause for up to three months, change coffee, size, grind or frequency, or cancel.'),
    ]),
    '<!-- wp:html -->\n<div id="choose" class="lp-anchor"></div>\n<!-- /wp:html -->',
    wide('<div class="lp-head"><p class="kicker">Choose your coffee</p><h2>Most subscribed this month</h2></div>'),
    wide_sc('[lereng_subscription_picker]', 'lp-picker'),
    faq('Questions', [
        ('How do I pay for renewals?', 'Each renewal creates an order and we email a payment link (QRIS or virtual account) two days before roast day. Pay it and it ships as usual; if it isn’t paid by roast day we skip that delivery and keep your subscription.'),
        ('Can I change the coffee?', 'Yes, any time before the renewal date. Single origins and blends in the same size keep the 10% discount.'),
        ('What if I’m away?', 'Pause the subscription and choose the date it should restart. We send a reminder a week before.'),
        ('Is there a minimum?', 'No. Cancel after the first delivery if it isn’t for you.'),
    ]),
 ])},
 "brew-guides": {"title": "Brew guides", "content": "\n".join(guides)},
 "shipping": {"title": "Shipping and returns", "content": "\n".join([
    p('We roast on Monday and Thursday. Orders in by 10:00 (Bali time) on a roast day make that day’s batch and ship the same afternoon.', 'lead'),
    h('Rates and delivery times'), sc('[lereng_shipping_table]'),
    p('Rates are per kilogram, rounded up, including packaging. Delivery is free in Bali over Rp 300.000 and in Java over Rp 750.000 with the regular service. Cold brew travels chilled with GoSend in south Bali only, or you can collect it at the roastery.'),
    h('Pick up at the roastery'), p('Jl. Raya Kerobokan No. 88, Badung, Tuesday to Sunday, 8:00–17:00. Coffee is ready from 14:00 on its roast day.'),
    h('Returns'), p('Opened coffee can’t be returned, but if a bag isn’t what you expected, tell us and we’ll send a different coffee. Equipment can be returned unused within 14 days; we cover the return shipping if it arrived damaged.'),
 ])},
 "track-order": {"title": "Track an order", "content": "\n".join([p('Enter your order number and the email you used at checkout.'), sc('[lereng_track_order]')])},
 "gift-card-balance": {"title": "Gift card balance", "content": "\n".join([p('Check how much is left on a Lereng gift card. Codes look like LRG-XXXX-XXXX.'), sc('[lereng_gift_card_balance]'), btn('Send a gift card', '/product/lereng-gift-card/')])},
 "wholesale": {"title": "Wholesale for cafés and offices", "template": "page-wide", "content": "\n".join([
    hero('Wholesale', 'Coffee for cafés, restaurants and offices.',
         'Fresh-roasted coffee for cafés, restaurants, villas and offices in Bali and beyond, roasted on a fixed day for your order.',
         [('Apply for an account', '#apply'), ('See prices', '#prices')],
         [('25%', 'off 1 kg bags from 5 kg a month'), ('30%', 'off once an order reaches 10 kg'), ('14 days', 'invoice terms for approved accounts'), ('1 day', 'to hear back after you apply')]),
    wide('<section class="lp-section"><p class="kicker">What you get</p><h2>A roaster that works on your schedule.</h2><ul class="lp-cards">'
         '<li><h3>Tiered prices</h3><p>Tiered prices from 5 kg a month, with invoice terms for approved accounts</p></li>'
         '<li><h3>Fixed roast day</h3><p>A fixed roast day and delivery slot for your order</p></li>'
         '<li><h3>Barista training</h3><p>Barista training at our tasting bar, and help dialling in your grinder</p></li>'
         '<li><h3>Your own blend</h3><p>Your own espresso blend from 20 kg a month</p></li></ul></section>'),
    '<!-- wp:html -->\n<div id="prices" class="lp-anchor"></div>\n<!-- /wp:html -->',
    wide('<div class="lp-head"><p class="kicker">Prices</p><h2>What a kilo costs</h2></div>'),
    wide_sc('[lereng_wholesale_tiers]', 'lp-tiers'),
    lp_steps('How it works', 'From application to first delivery in a week.', [
        ('Apply', 'Tell us about your business and how much coffee you use.'),
        ('Taste', 'We reply within one working day and send samples, or invite you to a cupping in Kerobokan.'),
        ('Order', 'Once approved, your account shows wholesale prices and a quick-order page for 1 kg bags.'),
        ('Pay by invoice', 'Bank transfer within 14 days; one invoice per delivery, with a tax invoice for your NPWP.'),
    ]),
    '<!-- wp:html -->\n<div id="apply" class="lp-anchor"></div>\n<!-- /wp:html -->',
    wide('<div class="lp-apply__intro"><p class="kicker">Apply</p><h2>Apply for an account</h2><p>It takes two minutes. We look at every application by hand and reply on WhatsApp or email within one working day.</p><ul class="lp-contact"><li>WhatsApp <b>+62 812 0000 0088</b></li><li>Email <b>wholesale@lereng.example</b></li><li>Cupping: Thursday 14:00 at the roastery</li></ul></div>', 'lp-apply'),
    wide_sc('[lereng_wholesale_form]', 'lp-apply-form'),
 ])},
 "about": {"title": "About Lereng", "content": "\n".join([
    p('Lereng means slope. Almost all of Indonesia’s best coffee grows on the slopes of volcanoes, between 1,200 and 1,900 metres, on farms of one or two hectares.', 'lead'),
    p('We buy from farmers and cooperatives on six islands, cup every lot blind, and pay above the local cherry price for the ones we choose. Every bag names the producer and the place.'),
    p('Everything is roasted in small batches in Kerobokan, Bali, twice a week.'),
    p('<em>Lereng is a fictional brand built as a portfolio project. The origins, processes and varieties are real; producers and the roastery are invented.</em>'),
 ])},
 "terms": {"title": "Terms and conditions", "option": "woocommerce_terms_page_id", "content": "\n".join([
    p('This is a demonstration store. No real orders are fulfilled and no real payments are taken: payments run in a sandbox and emails are captured in the <a href="/demo-inbox/">demo inbox</a>.'),
    p('Prices are in Indonesian rupiah and include 11% PPN.'),
 ])},
}
json.dump(pages, open('data/pages.json', 'w'), indent=1, ensure_ascii=False)
print(len(pages), 'pages')
