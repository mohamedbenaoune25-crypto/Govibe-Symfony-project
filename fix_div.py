import re
with open('templates/checkout/index.html.twig', 'r', encoding='utf-8') as f:
    html = f.read()

# Fix the duplicate block using regex to avoid whitespace issues
html = re.sub(
    r'<div style="display:flex;[^>]+>\s*<div style="display:flex;[^>]+>\s*<div class="gv-filter-pills">',
    '<div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom: 1rem; gap:1rem;">\n    <div class="gv-filter-pills">',
    html
)

html = html.replace('f.departure LIKE', 'f.departureAirport LIKE')

with open('templates/checkout/index.html.twig', 'w', encoding='utf-8') as f:
    f.write(html)
print("done")
