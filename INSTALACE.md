# Instalační návod - WooCommerce Balíkovna Plugin

Tento návod vás provede instalací pluginu WooCommerce-Balikovna-komplet na váš WordPress web.

## 📋 Příprava před instalací

Ujistěte se, že váš WordPress splňuje následující požadavky:

- ✅ WordPress verze 5.8 nebo vyšší
- ✅ WooCommerce verze 5.0 nebo vyšší
- ✅ PHP verze 7.4 nebo vyšší
- ✅ cURL rozšíření pro PHP (obvykle standardně nainstalované)

---

## 🚀 Metoda 1: Instalace přes WordPress Admin (DOPORUČENO)

Toto je nejjednodušší způsob instalace pluginu.

### Krok 1: Stažení pluginu

1. Stáhněte si plugin jako ZIP soubor z GitHubu:
   - Přejděte na: https://github.com/suseneprazene/WooCommerce-Balikovna-komplet
   - Klikněte na zelené tlačítko **Code**
   - Vyberte **Download ZIP**
   - Soubor se uloží jako `WooCommerce-Balikovna-komplet-main.zip` nebo podobný název

### Krok 2: Nahrání do WordPressu

1. Přihlaste se do administrace vašeho WordPressu
2. V levém menu přejděte na **Pluginy → Přidat nový**
3. Klikněte na tlačítko **Nahrát plugin** (nahoře vedle "Přidat nový plugin")
4. Klikněte na **Vybrat soubor**
5. Vyberte stažený ZIP soubor
6. Klikněte na **Instalovat**

### Krok 3: Aktivace pluginu

1. Po dokončení instalace klikněte na **Aktivovat plugin**
2. Plugin by se měl objevit v seznamu aktivních pluginů

---

## 💻 Metoda 2: Instalace přes FTP/SFTP

Tato metoda je vhodná, pokud nemůžete nahrát ZIP soubor přes WordPress admin (např. kvůli limitům velikosti souboru).

### Krok 1: Stažení a rozbalení

1. Stáhněte si plugin jako ZIP soubor (viz Metoda 1, Krok 1)
2. Rozbalte ZIP soubor na vašem počítači
3. Měli byste mít složku s názvem `WooCommerce-Balikovna-komplet-main` nebo podobně

### Krok 2: Přejmenování složky (DŮLEŽITÉ!)

1. Přejmenujte složku na: **`woocommerce-balikovna-komplet`** (bez suffixu -main nebo -master)
2. Toto je důležité pro správnou funkci pluginu

### Krok 3: Nahrání přes FTP

1. Otevřete váš FTP klient (např. FileZilla, Cyberduck, Total Commander)
2. Připojte se k vašemu webhostingu
3. Přejděte do složky: `/wp-content/plugins/`
4. Nahrajte celou složku `woocommerce-balikovna-komplet` do této složky
5. Struktura by měla vypadat takto:
   ```
   /wp-content/plugins/woocommerce-balikovna-komplet/
   ├── woocommerce-balikovna-komplet.php
   ├── includes/
   ├── assets/
   ├── templates/
   ├── tcpdf/
   ├── fpdi/
   └── ...
   ```

### Krok 4: Aktivace

1. Přejděte do WordPress administrace
2. Jděte na **Pluginy**
3. Najděte **WooCommerce-Balikovna-komplet** v seznamu
4. Klikněte na **Aktivovat**

---

## 🖥️ Metoda 3: Instalace přes WP-CLI (Pro pokročilé)

Pokud máte přístup k příkazové řádce a máte nainstalované WP-CLI:

```bash
# Přejděte do složky WordPress instalace
cd /cesta/k/wordpress

# Stáhněte plugin z GitHubu
wp plugin install https://github.com/suseneprazene/WooCommerce-Balikovna-komplet/archive/refs/heads/main.zip --activate

# Nebo pokud máte ZIP soubor lokálně:
wp plugin install /cesta/k/souboru.zip --activate
```

---

## ⚙️ Konfigurace po instalaci

Po úspěšné aktivaci pluginu je třeba jej nakonfigurovat:

### 1. Přidání dopravní metody

1. V WordPress administraci přejděte na: **WooCommerce → Nastavení → Doprava**
2. Vyberte dopravní zónu (např. "Česká republika" nebo "Všechny lokality")
3. Klikněte na **Přidat dopravní metodu**
4. Z rozbalovacího menu vyberte **Balíkovna**
5. Klikněte na **Přidat dopravní metodu**

### 2. Nastavení dopravní metody

1. Klikněte na název metody **Balíkovna** pro její úpravu
2. Nastavte následující parametry:

#### Základní nastavení:
- **Zapnout metodu**: ✅ Zaškrtněte
- **Název metody**: např. "Balíkovna - Výdejní místo"
- **Cena dopravy**: např. 59 Kč
- **Doprava zdarma od**: např. 1000 Kč (volitelné)
- **Povolit dobírku**: ✅ Zaškrtněte (pokud chcete nabízet platbu na dobírku)
- **Výchozí hmotnost zásilky**: např. 2.5 kg

#### API přihlašovací údaje:

⚠️ **DŮLEŽITÉ**: Pro testování můžete použít tyto testovací údaje:

```
API Token: 5e2c2954-5c9e-41c0-9854-9686c1b080eb
Private Key: l4M4p9fj1AoaKuOPyj3f0uBBB82PBCdHhYAfURzMgLnFigXcBW/pTbGxfWL/Sss1n566o+7qDpw1FZ1G5nOTlA==
```

3. Klikněte na **Uložit změny**

### 3. Nastavení e-shopu (volitelné)

Pro správné fungování štítků je dobré nastavit údaje vašeho e-shopu:

1. Přejděte na: **WooCommerce → Nastavení → Obecné**
2. Vyplňte:
   - Adresu obchodu
   - Město obchodu
   - PSČ obchodu
   - Zemi obchodu

---

## ✅ Ověření instalace

### Test na frontendu:

1. Přejděte na váš e-shop jako zákazník
2. Přidejte produkt do košíku
3. Přejděte k pokladně (checkout)
4. V části "Doprava" byste měli vidět možnost **Balíkovna**
5. Po výběru této metody by se měl zobrazit výběr výdejního místa

### Test v administraci:

1. Vytvořte testovací objednávku s dopravou přes Balíkovnu
2. V detailu objednávky by měl být na pravé straně metabox **"Balíkovna - Zásilka"**
3. Měli byste vidět tlačítko **"Vytvořit zásilku"**

---

## 🔧 Řešení problémů

### Plugin se nezobrazuje v seznamu pluginů

**Příčina**: Nesprávný název složky nebo struktura souborů

**Řešení**:
1. Ujistěte se, že složka pluginu má název **přesně**: `woocommerce-balikovna-komplet`
2. Zkontrolujte, že hlavní soubor `woocommerce-balikovna-komplet.php` je přímo ve složce pluginu
3. Struktura by měla být: `/wp-content/plugins/woocommerce-balikovna-komplet/woocommerce-balikovna-komplet.php`

### Chyba při aktivaci: "Plugin vyžaduje WooCommerce"

**Příčina**: WooCommerce není nainstalovaný nebo aktivní

**Řešení**:
1. Nainstalujte a aktivujte WooCommerce plugin
2. Poté znovu aktivujte Balíkovna plugin

### Chyba: "Headers already sent"

**Příčina**: Soubory obsahují mezery nebo prázdné řádky před `<?php`

**Řešení**:
1. Stáhněte plugin znovu z GitHubu (oficiální verze)
2. Neupravujte soubory ručně

### Metoda Balíkovna se nezobrazuje na pokladně

**Příčina**: Není správně nakonfigurována dopravní metoda

**Řešení**:
1. Zkontrolujte, že je metoda zapnutá v nastavení dopravy
2. Ověřte, že je metoda přiřazena ke správné dopravní zóně
3. Zkontrolujte, že splňujete podmínky (např. minimální částka objednávky)

### Výběr výdejního místa se nezobrazuje

**Příčina**: JavaScript není správně načten nebo API nefunguje

**Řešení**:
1. Zkontrolujte API přihlašovací údaje v nastavení
2. Otevřete konzoli prohlížeče (F12) a zkontrolujte chyby
3. Zkuste vypnout cache pluginy a znovu načíst stránku

### Nelze vytvořit zásilku

**Příčina**: Nesprávné API údaje nebo chybějící výdejní místo

**Řešení**:
1. Ověřte, že jsou správně zadány API Token a Private Key
2. Zkontrolujte, že zákazník vybral výdejní místo při objednávce
3. Zkontrolujte WordPress debug log pro podrobnosti chyby

### Nelze stáhnout štítek (PDF)

**Příčina**: Problém s TCPDF knihovnou nebo právy k souborům

**Řešení**:
1. Zkontrolujte, že složky `tcpdf` a `fpdi` jsou kompletní
2. Ověřte práva k souborům (obvykle 755 pro složky, 644 pro soubory)
3. Zkontrolujte, že PHP má dostatek paměti (minimálně 128MB)

---

## 📝 Zapnutí debug módu (pro pokročilé)

Pokud se vyskytují problémy a potřebujete detailní informace:

1. Otevřete soubor `wp-config.php` v kořenové složce WordPressu
2. Najděte řádek s `WP_DEBUG` a změňte na:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
3. Chyby se budou logovat do souboru `/wp-content/debug.log`
4. Po vyřešení problému vypněte debug mód zpět

---

## 🆘 Podpora

Pokud máte problémy s instalací:

1. **Zkontrolujte dokumentaci**: Přečtěte si README.md v repozitáři
2. **GitHub Issues**: Vytvořte issue na https://github.com/suseneprazene/WooCommerce-Balikovna-komplet/issues
3. **Kontrola logů**: Zkontrolujte WordPress debug log pro detaily chyb

---

## 🔄 Aktualizace pluginu

Když bude k dispozici nová verze:

### Aktualizace přes WordPress Admin:
1. Deaktivujte plugin
2. Smažte starou verzi
3. Nahrajte novou verzi (stejný postup jako při instalaci)
4. Aktivujte plugin

### Aktualizace přes FTP:
1. Záloha: Stáhněte si starou verzi pluginu jako zálohu
2. Smažte starou složku `woocommerce-balikovna-komplet` na serveru
3. Nahrajte novou verzi
4. Znovu aktivujte plugin v WordPress administraci

⚠️ **Důležité**: Nastavení pluginu (API klíče, ceny) zůstanou zachovány v databázi.

---

## ✨ Hotovo!

Plugin je nyní nainstalován a připraven k použití. Pro podrobnosti o použití pluginu viz hlavní README.md soubor.

**Užívejte si jednoduchou integraci Balíkovny ve vašem WooCommerce e-shopu!** 🎉
