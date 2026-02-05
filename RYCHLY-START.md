# 🚀 Rychlý start - WooCommerce Balíkovna

Tento průvodce vám ukáže, jak co nejrychleji nainstalovat a spustit plugin Balíkovny.

## ⏱️ Instalace za 5 minut

### 1️⃣ Stažení (30 sekund)

```
👉 Přejděte na: https://github.com/suseneprazene/WooCommerce-Balikovna-komplet
👉 Klikněte na zelené tlačítko "Code"
👉 Vyberte "Download ZIP"
```

### 2️⃣ Nahrání do WordPressu (1 minuta)

```
👉 WordPress Admin → Pluginy → Přidat nový
👉 Klikněte "Nahrát plugin"
👉 Vyberte stažený ZIP soubor
👉 Klikněte "Instalovat"
👉 Klikněte "Aktivovat plugin"
```

### 3️⃣ Přidání dopravní metody (2 minuty)

```
👉 WooCommerce → Nastavení → Doprava
👉 Vyberte dopravní zónu (např. "Česká republika")
👉 Klikněte "Přidat dopravní metodu"
👉 Vyberte "Balíkovna"
👉 Klikněte "Přidat dopravní metodu"
```

### 4️⃣ Základní konfigurace (2 minuty)

Klikněte na název metody **Balíkovna** a nastavte:

#### Povinné údaje:
- ✅ **Zapnout metodu** - zaškrtněte
- 💰 **Cena dopravy** - např. `59`
- 🔑 **API Token**: `5e2c2954-5c9e-41c0-9854-9686c1b080eb`
- 🔐 **Private Key**: `l4M4p9fj1AoaKuOPyj3f0uBBB82PBCdHhYAfURzMgLnFigXcBW/pTbGxfWL/Sss1n566o+7qDpw1FZ1G5nOTlA==`

#### Volitelné:
- 🎁 **Doprava zdarma od** - např. `1000` (Kč)
- 💵 **Povolit dobírku** - zaškrtněte
- 📦 **Výchozí hmotnost** - např. `2.5` (kg)

**💾 Klikněte "Uložit změny"**

---

## ✅ Test funkčnosti

### Frontend test (1 minuta):
1. Otevřete váš e-shop v novém okně (jako zákazník)
2. Přidejte produkt do košíku
3. Přejděte k pokladně
4. V části "Doprava" vyberte **Balíkovna**
5. Měl by se zobrazit výběr výdejního místa ✨

### Admin test (1 minuta):
1. Vytvořte testovací objednávku s dopravou Balíkovna
2. Otevřete detail objednávky
3. Na pravé straně najděte metabox **"Balíkovna - Zásilka"**
4. Mělo by být tlačítko **"Vytvořit zásilku"** ✅

---

## 🎯 Nejčastější problémy a řešení

### ❌ Plugin se nezobrazuje
**→ Řešení**: Ujistěte se, že máte nainstalovaný a aktivní WooCommerce

### ❌ Metoda se nezobrazuje na pokladně
**→ Řešení**: Zkontrolujte, že je metoda zapnutá a přiřazená ke správné dopravní zóně

### ❌ Výběr pobočky nefunguje
**→ Řešení**: Zkontrolujte API přihlašovací údaje v nastavení metody

### ❌ Nelze vytvořit zásilku
**→ Řešení**: Zákazník musí vybrat výdejní místo při objednávce

---

## 📚 Další informace

- **Podrobný instalační návod**: [INSTALACE.md](INSTALACE.md)
- **Kompletní dokumentace**: [README.md](README.md)
- **Řešení problémů**: [INSTALACE.md - Sekce řešení problémů](INSTALACE.md#-řešení-problémů)

---

## 🆘 Potřebujete pomoc?

1. Přečtěte si [podrobný instalační návod](INSTALACE.md)
2. Zkontrolujte [sekci řešení problémů](INSTALACE.md#-řešení-problémů)
3. Vytvořte issue na [GitHubu](https://github.com/suseneprazene/WooCommerce-Balikovna-komplet/issues)

---

**Hotovo! Váš e-shop má nyní integrovanou Balíkovnu! 🎉**
