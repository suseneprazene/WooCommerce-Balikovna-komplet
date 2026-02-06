# WooCommerce Balíkovna Komplet

Kompletní WordPress plugin pro integraci Balíkoven České pošty do WooCommerce.

## Popis

Plugin umožňuje zákazníkům vybrat si pobočku Balíkovny České pošty přímo při checkoutu. Automaticky načítá aktuální data poboček z API České pošty a zobrazuje je v přehledném rozhraní s vyhledáváním.

## Funkce

- ✅ Dva typy doručení: Do boxu nebo Na adresu
- ✅ Generování štítků pro tisk
- ✅ API integrace s Českou poštou
- ✅ Vlastní dopravní metoda pro WooCommerce
- ✅ Vyhledávání poboček podle města, PSČ nebo adresy
- ✅ Select2 dropdown s pokročilým vyhledáváním
- ✅ Zobrazení otevíracích hodin (tooltip)
- ✅ Automatická synchronizace dat z České pošty
- ✅ Zobrazení vybrané pobočky v objednávce (admin i zákazník)
- ✅ Informace o pobočce v emailových notifikacích
- ✅ Možnost nastavení dopravy zdarma od určité částky
- ✅ Podpora Git Updater pro automatické aktualizace

## Požadavky

- WordPress 5.0 nebo vyšší
- WooCommerce 5.0 nebo vyšší
- PHP 7.4 nebo vyšší

## Instalace

1. Stáhněte plugin jako ZIP soubor
2. V administraci WordPress přejděte do: Pluginy → Přidat nový → Nahrát plugin
3. Vyberte stažený ZIP soubor a klikněte na "Instalovat"
4. Aktivujte plugin
5. Přejděte do WooCommerce → Nastavení → Balíkovna
6. Klikněte na "Aktualizovat data poboček" pro načtení seznamu poboček

## Nastavení

### Dopravní metoda

1. Přejděte do: WooCommerce → Nastavení → Doprava
2. Vyberte zónu doručení
3. Klikněte na "Přidat dopravní metodu"
4. Vyberte "Balíkovna České pošty"
5. Nastavte cenu dopravy a volitelně práh pro dopravu zdarma

### Synchronizace dat

1. Přejděte do: WooCommerce → Nastavení → Balíkovna
2. Klikněte na "Aktualizovat data poboček"
3. Data se automaticky cachují na 24 hodin

## Použití

### Pro zákazníky

1. Při checkoutu vyberte dopravu "Balíkovna České pošty"
2. Zobrazí se pole pro výběr pobočky
3. Začněte psát název obce nebo PSČ
4. Vyberte požadovanou pobočku ze seznamu
5. Při najetí myší na ikonu (ⓘ) se zobrazí otevírací hodiny

### Pro administrátory

- Vybraná pobočka se zobrazí v detailu objednávky
- Informace jsou automaticky přidány do všech emailů
- V meta boxu "Balíkovna" najdete kompletní informace včetně otevíracích hodin

## Git Updater

Plugin podporuje automatické aktualizace přes [Git Updater](https://github.com/afragen/git-updater).

Po instalaci Git Updater pluginu se budou aktualizace automaticky stahovat z GitHub repozitáře.

## API Endpointy

Plugin vytváří následující REST API endpointy:

- `GET /wp-json/balikovna/v1/search?q={term}` - Vyhledávání poboček
- `GET /wp-json/balikovna/v1/hours/{id}` - Otevírací hodiny pobočky

## Databázové tabulky

Plugin vytváří dvě vlastní tabulky:

- `{prefix}_balikovna_branches` - Seznam poboček
- `{prefix}_balikovna_opening_hours` - Otevírací hodiny

## Vývoj

### Struktura souborů

```
woocommerce-balikovna-komplet/
├── woocommerce-balikovna-komplet.php  # Hlavní soubor pluginu
├── includes/
│   ├── class-wc-balikovna-install.php    # Instalace a synchronizace dat
│   ├── class-wc-balikovna-api.php        # REST API endpointy
│   ├── class-wc-balikovna-shipping.php   # Dopravní metoda
│   ├── class-wc-balikovna-checkout.php   # Checkout integrace
│   ├── class-wc-balikovna-admin.php      # Admin funkce
│   ├── class-wc-balikovna-order.php      # Zobrazení v objednávkách
│   └── class-wc-balikovna-settings.php   # Nastavení
├── assets/
│   ├── css/
│   │   └── balikovna-checkout.css        # Styly pro checkout
│   └── js/
│       └── balikovna-checkout.js         # JavaScript pro checkout
└── README.md
```

## Bezpečnost

- Všechny vstupy jsou sanitizovány
- Všechny výstupy jsou escapovány
- SQL dotazy používají prepared statements
- AJAX requesty jsou ověřovány pomocí nonce

## Podpora

Pro hlášení chyb nebo návrhy na vylepšení vytvořte issue na [GitHubu](https://github.com/suseneprazene/WooCommerce-Balikovna-komplet/issues).

## Licence

GPL v3 or later

## Autor

[suseneprazene](https://github.com/suseneprazene)

## Changelog

### 1.0.2
- Přidána podpora pro dva typy doručení (Box a Adresa)
- Přidána možnost generování štítků
- Iframe výběr pobočky pro Box typ
- Automatická validace adresy pro Address typ
- API integrace pro tisk štítků
- Vylepšená kompatibilita s WooCommerce 9.5

### 1.0.0
- Počáteční vydání
- Základní funkcionalita
- Integrace s WooCommerce
- REST API pro vyhledávání poboček
- Admin nastavení

