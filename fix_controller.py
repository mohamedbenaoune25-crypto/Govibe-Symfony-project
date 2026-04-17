with open('src/Controller/Web/CheckoutController.php', 'r', encoding='utf-8') as f:
    php = f.read()

php = php.replace('f.departure LIKE', 'f.departureAirport LIKE')

with open('src/Controller/Web/CheckoutController.php', 'w', encoding='utf-8') as f:
    f.write(php)
print("done")
