with open('src/Controller/Web/CheckoutController.php', 'r', encoding='utf-8') as f:
    text = f.read()

old_search = '''        if (\) {
            \->andWhere('f.destination LIKE :search OR f.departureAirport LIKE :search OR c.statusReservation LIKE :search')
               ->setParameter('search', '%' . \ . '%');
        }'''

new_search = '''        if (\) {
            if (is_numeric(\)) {
                \->andWhere('f.destination LIKE :search OR f.departureAirport LIKE :search OR c.statusReservation LIKE :search OR c.checkoutId = :exact')
                   ->setParameter('search', '%' . \ . '%')
                   ->setParameter('exact', \);
            } else {
                \->andWhere('f.destination LIKE :search OR f.departureAirport LIKE :search OR c.statusReservation LIKE :search')
                   ->setParameter('search', '%' . \ . '%');
            }
        }'''

if old_search in text:
    text = text.replace(old_search, new_search)
    with open('src/Controller/Web/CheckoutController.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Patched successfully")
else:
    print("Search block not found")
