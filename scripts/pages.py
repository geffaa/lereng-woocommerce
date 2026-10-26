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
    p('Fresh beans before the last ones run out. 10% off every bag, free to change.', 'lead'),
    h('How it works'),
    ul(['Choose a coffee on its product page and pick <b>Subscribe &amp; save</b>, or start the <a href="/product/roasters-choice-subscription/">Roaster’s Choice</a> for a different origin each time.',
        'We roast on the next Monday or Thursday and ship the same day. After that it arrives every 1, 2 or 4 weeks.',
        'Each renewal is a normal order: you get the same email, tracking and receipt.',
        'From <a href="/my-account/subscriptions/">My account</a> you can skip the next delivery, pause for up to three months, change coffee, size, grind or frequency, or cancel.']),
    sc('[lereng_subscription_picker]'),
    h('Questions'),
    details('How do I pay for renewals?', p('Each renewal creates an order and we email a payment link (QRIS or virtual account) two days before roast day. Pay it and it ships as usual; if it isn’t paid by roast day we skip that delivery and keep your subscription.')),
    details('Can I change the coffee?', p('Yes, any time before the renewal date. Single origins and blends in the same size keep the 10% discount.')),
    details('What if I’m away?', p('Pause the subscription and choose the date it should restart. We send a reminder a week before.')),
    details('Is there a minimum?', p('No. Cancel after the first delivery if it isn’t for you.')),
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
    p('Fresh-roasted coffee for cafés, restaurants, villas and offices in Bali and beyond.', 'lead'),
    ul(['Tiered prices from 5 kg a month, with invoice terms for approved accounts', 'A fixed roast day and delivery slot for your order', 'Barista training at our tasting bar, and help dialling in your grinder', 'Your own espresso blend from 20 kg a month']),
    h('Apply for an account'), sc('[lereng_wholesale_form]'),
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
