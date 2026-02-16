# PowerShell script: replace_balikovna_snippets.ps1
# Spustit v kořenovém adresáři pluginu (kde je includes\class-wc-balikovna-label.php).
# Pokud PowerShell blokuje spuštění, spusť s: powershell -ExecutionPolicy Bypass -File .\replace_balikovna_snippets.ps1

$target = Join-Path $PSScriptRoot 'includes\class-wc-balikovna-label.php'
if (-not (Test-Path $target)) {
    Write-Error "Soubor nenalezen: $target"
    exit 1
}

$orig = Get-Content -Raw -LiteralPath $target
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$bak = "$target.bak.$timestamp"
Copy-Item -LiteralPath $target -Destination $bak -Force
Write-Host "Backup vytvořen: $bak`n"

# FIND A
$findA = @'
// --- Robustní detekce delivery type v generate_label (meta -> prepare_label_data) ---
$delivery_type = $order->get_meta(\'_wc_balikovna_delivery_type\');
'@

$replaceA = @'
// --- START: korektní načtení delivery_type + branch pro admin UI (add_label_button) ---
$delivery_type = $order->get_meta(\'_wc_balikovna_delivery_type\');
$branch_name   = $order->get_meta(\'_wc_balikovna_branch_name\');
$branch_type   = $order->get_meta(\'_wc_balikovna_branch_type\');
$branch_icon   = $order->get_meta(\'_wc_balikovna_branch_icon\');

// Pokud meta chybí, zkusíme detekovat pomocí prepare_label_data() — pouze pro zobrazení v adminu.
// Nepřepisujeme objednávku a nevracíme pole, jen doplníme hodnoty pro UI.
if ( empty( $delivery_type ) ) {
    $detected = $this->prepare_label_data( $order );
    if ( ! is_wp_error( $detected ) ) {
        if ( ! empty( $detected[\'deliveryType\'] ) ) {
            $delivery_type = $detected[\'deliveryType\'];
        }
        if ( ! empty( $detected[\'recipient\'] ) && is_array( $detected[\'recipient\'] ) ) {
            if ( empty( $branch_name ) && ! empty( $detected[\'recipient\'][\'branch_name\'] ) ) {
                $branch_name = $detected[\'recipient\'][\'branch_name\'];
            }
            if ( empty( $branch_type ) && ! empty( $detected[\'recipient\'][\'branch_type\'] ) ) {
                $branch_type = $detected[\'recipient\'][\'branch_type\'];
            }
            if ( empty( $branch_icon ) && ! empty( $detected[\'recipient\'][\'branch_icon\'] ) ) {
                $branch_icon = $detected[\'recipient\'][\'branch_icon\'];
            }
        }
    } else {
        # debug only — nezastavujeme UI
        error_log( \'WC Balíkovna DEBUG (admin display): prepare_label_data failed: \' . $detected->get_error_message() );
    }
}
// --- END: korektní načtení delivery_type + branch pro admin UI ---
'@

# FIND B
$findB = @'
// Robustní detekce delivery type (meta -> fallback na prepare_label_data)
$delivery_type = $order->get_meta(\'_wc_balikovna_delivery_type\');

if ( empty( $delivery_type ) ) {
    error_log( \'WC Balíkovna Label: delivery_type meta empty for order #\' . $order->get_id() . \' — trying prepare_label_data()\' );

    $detection = $this->prepare_label_data( $order );
    if ( is_wp_error( $detection ) ) {
        // pokud detekce selhala, napiš chybovou hlášku a vrať se s užitečnou chybou
        error_log( \'WC Balíkovna Label ERROR: prepare_label_data failed for order #\' . $order->get_id() . \' - \' . $detection->get_error_message() );
        return array(
            \'success\' => false,
            \'message\' => __( \'Nelze určit typ dopravy (box/adresa) pro tuto objednávku. Zkontrolujte metadata Balíkovny.\', \'wc-balikovna-komplet\' )
        );
    }

    if ( isset( $detection[\'deliveryType\'] ) && ! empty( $detection[\'deliveryType\'] ) ) {
        $delivery_type = $detection[\'deliveryType\'];
        error_log( \'WC Balíkovna Label: delivery_type detected by prepare_label_data: \' . $delivery_type . \' for order #\' . $order->get_id() );
    }
}

// Konečná kontrola
if ( empty( $delivery_type ) ) {
    error_log( \'WC Balíkovna Label ERROR: No delivery type resolved for order #\' . $order->get_id() );
    return array(
        \'success\' => false,
        \'message\' => __( \'Tato objednávka nemá určen typ dodání (box vs adresa).\', \'wc-balikovna-komplet\' )
    );
}
'@

$replaceB = @'
// Robustní detekce delivery type (meta -> fallback na prepare_label_data)
$delivery_type = $order->get_meta(\'_wc_balikovna_delivery_type\');

if ( empty( $delivery_type ) ) {
    error_log( \'WC Balíkovna Label: delivery_type meta empty for order #\' . $order->get_id() . \' — trying prepare_label_data()\' );

    $detection = $this->prepare_label_data( $order );
    if ( is_wp_error( $detection ) ) {
        // pokud detekce selhala, napiš chybovou hlášku a vrať se s užitečnou chybou
        error_log( \'WC Balíkovna Label ERROR: prepare_label_data failed for order #\' . $order->get_id() . \' - \' . $detection->get_error_message() );
        return array(
            \'success\' => false,
            \'message\' => __( \'Nelze určit typ dopravy (box/adresa) pro tuto objednávku. Zkontrolujte metadata Balíkovny.\', \'wc-balikovna-komplet\' )
        );
    }

    if ( isset( $detection[\'deliveryType\'] ) && ! empty( $detection[\'deliveryType\'] ) ) {
        $delivery_type = $detection[\'deliveryType\'];
        error_log( \'WC Balíkovna Label: delivery_type detected by prepare_label_data: \' . $delivery_type . \' for order #\' . $order->get_id() );

        // Volitelně: persist resolved delivery type so admin + future calls use it.
        # Uložíme to jen když meta byla původně prázdná (tj. detekovali jsme ji)
        try {
            $order->update_meta_data(\'_wc_balikovna_delivery_type\', $delivery_type);
            $order->save();
            error_log( \'WC Balíkovna DEBUG: persisted delivery_type \"\' . $delivery_type . \'\" to order #\' . $order->get_id() );
        } catch ( Exception $e ) {
            error_log( \'WC Balíkovna DEBUG: could not persist delivery_type: \' . $e->getMessage() );
        }
    }
}

// Konečná kontrola
if ( empty( $delivery_type ) ) {
    error_log( \'WC Balíkovna Label ERROR: No delivery type resolved for order #\' . $order->get_id() );
    return array(
        \'success\' => false,
        \'message\' => __( \'Tato objednávka nemá určen typ dodání (box vs adresa).\', \'wc-balikovna-komplet\' )
    );
}
'@

# FIND D: improve has_balikovna check (strpos)
$findD = @'
foreach ( $order->get_items( \'shipping\' ) as $si ) {
            if ( method_exists( $si, \'get_method_id\' ) && $si->get_method_id() === \'balikovna\' ) {
                $has_balikovna = true;
                break;
            }
        }
'@

$replaceD = @'
foreach ( $order->get_items( \'shipping\' ) as $si ) {
            $methodIdForCheck = method_exists( $si, \'get_method_id\' ) ? $si->get_method_id() : ( $si[\'method_id\'] ?? \'\' );
            if ( $methodIdForCheck && strpos( $methodIdForCheck, \'balikovna\' ) !== false ) {
                $has_balikovna = true;
                break;
            }
        }
'@

$modified = $orig
$counts = @{ A=0; B=0; D=0 }

if ($modified.Contains($findA)) {
    $modified = $modified.Replace($findA, $replaceA)
    $counts['A'] = 1
} else {
    Write-Host "Blok A nenalezen (možná již upraven) `n"
}

if ($modified.Contains($findB)) {
    $modified = $modified.Replace($findB, $replaceB)
    $counts['B'] = 1
} else {
    Write-Host "Blok B nenalezen (možná již upraven) `n"
}

if ($modified.Contains($findD)) {
    $modified = $modified.Replace($findD, $replaceD)
    $counts['D'] = 1
} else {
    Write-Host "Blok D nenalezen (možná se liší) `n"
}

# Write back
Set-Content -LiteralPath $target -Value $modified -Encoding UTF8

Write-Host "Hotovo. Náhrady provedeny: A=$($counts['A']), B=$($counts['B']), D=$($counts['D'])."
Write-Host "Soubor upraven: $target"
Write-Host "Záloha: $bak"
Write-Host "Nezapomeň vymazat OPcache / restartovat Local, pak otestovat objednávky."