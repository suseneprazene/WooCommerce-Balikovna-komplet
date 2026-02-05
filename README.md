# WooCommerce-Balikovna-komplet

Kompletní integrace Balíkovny pro WooCommerce s výběrem výdejního místa a tiskem štítků.

> 🚀 **Chcete rychle začít?** Podívejte se na [Rychlý start průvodce](RYCHLY-START.md) (instalace za 5 minut!)

## Popis

WooCommerce-Balikovna-komplet je WordPress plugin pro integraci dopravní služby Balíkovna do WooCommerce e-shopů. Plugin umožňuje zákazníkům vybrat si výdejní místo přímo během objednávání a majitelům e-shopů jednoduše vytvářet zásilky a tisknout štítky.

## Funkce

- ✅ **Výběr výdejního místa** - Zákazníci si mohou během checkout procesu vybrat výdejní místo Balíkovny
- ✅ **Integrace s WooCommerce** - Plná integrace jako dopravní metoda
- ✅ **API komunikace** - Propojení s Balíkovna API pro vytváření zásilek
- ✅ **Tisk štítků** - Automatické generování PDF štítků s čárovými kódy
- ✅ **Admin rozhraní** - Jednoduché ovládání přímo z detailu objednávky
- ✅ **Hromadné akce** - Vytváření zásilek pro více objednávek najednou
- ✅ **Email notifikace** - Informace o výdejním místě v objednávkových emailech
- ✅ **Dobírka** - Podpora pro platbu na dobírku

## Požadavky

- WordPress 5.8 nebo vyšší
- WooCommerce 5.0 nebo vyšší
- PHP 7.4 nebo vyšší
- cURL rozšíření pro PHP

## Instalace

### Rychlá instalace (Doporučeno)

1. **Stáhněte plugin** jako ZIP soubor z GitHubu (zelené tlačítko "Code" → "Download ZIP")
2. **Nahrajte do WordPressu**: Pluginy → Přidat nový → Nahrát plugin
3. **Aktivujte plugin** v seznamu pluginů
4. **Nakonfigurujte** v WooCommerce > Nastavení > Doprava

📖 **[Podrobný instalační návod](INSTALACE.md)** - Kompletní průvodce s obrázky a řešením problémů

### Další metody instalace

- **FTP/SFTP**: Nahrajte složku `woocommerce-balikovna-komplet` do `/wp-content/plugins/`
- **WP-CLI**: `wp plugin install https://github.com/suseneprazene/WooCommerce-Balikovna-komplet/archive/refs/heads/main.zip --activate`

💡 **Tip**: Pro podrobné instrukce, řešení problémů a screenshots viz [INSTALACE.md](INSTALACE.md)

## Konfigurace

### API přihlašovací údaje

Po aktivaci pluginu je nutné nastavit API přihlašovací údaje:

1. Přejděte do **WooCommerce > Nastavení > Doprava**
2. Vyberte dopravní zónu a najděte metodu **Balíkovna**
3. V nastavení metody zadejte:
   - **API Token:** Váš API token z Balíkovny
   - **Private Key:** Váš soukromý klíč z Balíkovny

**Pro testování můžete použít tyto testovací údaje:**
- **API Token:** `5e2c2954-5c9e-41c0-9854-9686c1b080eb`
- **Private Key:** `l4M4p9fj1AoaKuOPyj3f0uBBB82PBCdHhYAfURzMgLnFigXcBW/pTbGxfWL/Sss1n566o+7qDpw1FZ1G5nOTlA==`

⚠️ **Pro produkční použití musíte nahradit tyto údaje svými skutečnými API klíči získanými od Balíkovny.**

### Nastavení dopravní metody

1. Přejděte do **WooCommerce > Nastavení > Doprava**
2. Vyberte dopravní zónu a klikněte na **Přidat dopravní metodu**
3. Zvolte **Balíkovna**
4. Nastavte:
   - Název (např. "Výdejní místo Balíkovna")
   - Cenu dopravy
   - Částku pro dopravu zdarma (volitelné)
   - Výchozí hmotnost zásilky (v kg)
   - API Token a Private Key
   - Povolit/zakázat dobírku

## Použití

### Pro zákazníky

1. Během checkout procesu vyberte dopravní metodu "Balíkovna"
2. Objeví se výběr výdejního místa
3. Vyhledejte pobočku podle PSČ nebo města
4. Vyberte si preferované výdejní místo
5. Dokončete objednávku

### Pro administrátory

#### Vytvoření zásilky

1. Otevřete detail objednávky ve WooCommerce administraci
2. V metaboxu "Balíkovna - Zásilka" klikněte na **Vytvořit zásilku**
3. Zásilka bude automaticky vytvořena přes API

#### Tisk štítku

1. Po vytvoření zásilky klikněte na **Stáhnout štítek**
2. PDF štítek se automaticky stáhne

#### Zrušení zásilky

1. V detailu objednávky klikněte na **Zrušit zásilku**
2. Potvrďte akci

#### Hromadné akce

1. Přejděte na seznam objednávek
2. Zaškrtněte objednávky, pro které chcete vytvořit zásilky
3. V dropdown menu "Hromadné akce" vyberte:
   - **Balíkovna - Vytvořit zásilky** pro vytvoření zásilek
   - **Balíkovna - Stáhnout štítky** pro stažení štítků

## Struktura pluginu

```
woocommerce-balikovna-komplet/
├── woocommerce-balikovna-komplet.php  (hlavní soubor)
├── includes/
│   ├── class-balikovna-api.php        (API komunikace)
│   ├── class-balikovna-shipping.php   (WC Shipping Method)
│   ├── class-balikovna-admin.php      (Admin rozhraní)
│   ├── class-balikovna-order.php      (Order meta & hooks)
│   └── class-balikovna-label-generator.php (PDF štítky)
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   ├── js/
│   │   ├── admin.js
│   │   └── branch-selector.js
│   └── template.pdf
├── templates/
│   └── branch-selector.php
├── languages/
│   └── wc-balikovna.pot
├── tcpdf/                             (TCPDF knihovna)
└── fpdi/                              (FPDI knihovna)
```

## Technické detaily

### API Endpointy

Plugin komunikuje s následujícími API endpointy:

- `GET /v1/branches` - Seznam výdejních míst
- `POST /v1/shipments` - Vytvoření zásilky
- `GET /v1/shipments/{id}` - Detail zásilky
- `GET /v1/labels/{id}` - Stažení štítku
- `DELETE /v1/shipments/{id}` - Zrušení zásilky

### Order Meta

Plugin ukládá následující metadata k objednávkám:

- `_balikovna_branch_id` - ID výdejního místa
- `_balikovna_branch_name` - Název výdejního místa
- `_balikovna_branch_address` - Adresa výdejního místa
- `_balikovna_shipment_id` - ID vytvořené zásilky
- `_balikovna_tracking_number` - Tracking číslo

### Bezpečnost

- ✅ Nonce ověření pro všechny AJAX requesty
- ✅ Capability kontroly (`manage_woocommerce`)
- ✅ Sanitizace všech vstupů
- ✅ Escapování všech výstupů

## Changelog

### 1.0.0 (2024-02-05)
- Iniciální vydání
- Základní integrace Balíkovna API
- Výběr výdejního místa na checkout stránce
- Vytváření zásilek a tisk štítků
- Admin rozhraní pro správu zásilek

## Autor

**suseneprazene**
- GitHub: [@suseneprazene](https://github.com/suseneprazene)

## Licence

Tento projekt je licencován pod MIT licencí.

## Podpora

Pro nahlášení chyb nebo žádosti o nové funkce vytvořte issue na GitHubu:
https://github.com/suseneprazene/WooCommerce-Balikovna-komplet/issues
