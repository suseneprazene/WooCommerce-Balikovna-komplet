(function ($) {
    'use strict';

    function observeShippingMethods() {

        function getContainerForInput($input) {
            // Pokusíme se nejprve vybrat přímého potomka .shipping-method__after-shipping-rate
            var $li = $input.closest('li');
            var $container = $li.children('.shipping-method__after-shipping-rate');

            // fallback: najít první .shipping-method__after-shipping-rate uvnitř li (pokud není child)
            if ($container.length === 0) {
                $container = $li.find('.shipping-method__after-shipping-rate').first();
            }

            // poslední fallback: najít pluginové třídy uvnitř li
            if ($container.length === 0) {
                $container = $li.find('.wc-balikovna-branch-selection, .wc-balikovna-address-notice').closest('.shipping-method__after-shipping-rate');
            }

            return $container;
        }

        function forceHide($el) {
            if (!$el || !$el.length) return;
            $el.get(0).style.setProperty('display', 'none', 'important');
            $el.attr('aria-hidden', 'true');
        }

        function forceShow($el, displayType) {
            if (!$el || !$el.length) return;
            var disp = displayType || 'block';
            $el.get(0).style.setProperty('display', disp, 'important');
            $el.attr('aria-hidden', 'false');
        }

        function updateShippingContentVisibility() {
            // Re-query všech inputů (důležité — DOM může být překreslen)
            var $allShippingInputs = $('input[name^="shipping_method"]');
            var $checked = $allShippingInputs.filter(':checked');
            var selectedVal = $checked.val() || null;

            console.log('🔄 updateShippingContentVisibility — selected:', selectedVal);

            // Najdeme konkrétní pluginové inputy (re-query)
            var $boxInput = $('input[name^="shipping_method"][value="balikovna:2"]');
            var $addrInput = $('input[name^="shipping_method"][value="balikovna:3"]');

            // Najdeme jejich kontejnery dynamicky
            var $boxContainer = getContainerForInput($boxInput);
            var $addrContainer = getContainerForInput($addrInput);

            console.log(' - boxInput found:', $boxInput.length, ' addrInput found:', $addrInput.length);
            console.log(' - boxContainer found:', $boxContainer.length, ' addrContainer found:', $addrContainer.length);

            // Default: natvrdo schovat oba pluginové kontejnery
            forceHide($boxContainer);
            forceHide($addrContainer);

            // Zobrazit pouze aktuální
            if (selectedVal === 'balikovna:2') {
                console.log('📦 Vybrána balikovna:2 — zobrazím box, skryju adresu');
                forceShow($boxContainer, 'block');
                forceHide($addrContainer);
            }
            else if (selectedVal === 'balikovna:3') {
                console.log('📬 Vybrána balikovna:3 — zobrazím adresu, skryju box');
                forceShow($addrContainer, 'block');
                forceHide($boxContainer);
            }
            else {
                console.log('🔒 Jiná metoda — pluginové panely skryty');
                forceHide($boxContainer);
                forceHide($addrContainer);
            }

            // Debug: vypišeme konečný stav viditelnosti
            console.log(' -> box visible:', $boxContainer.is(':visible'), ' addr visible:', $addrContainer.is(':visible'));

            // (volitelně) pošlete debug přes AJAX pokud máte wc_balikovna_ajaxurl definovanou
            if (typeof wc_balikovna_ajaxurl !== 'undefined') {
                try {
                    var payload = [];
                    $allShippingInputs.each(function () {
                        var $inp = $(this);
                        var $cont = getContainerForInput($inp);
                        payload.push({
                            methodID: $inp.val(),
                            selected: $inp.is(':checked'),
                            visible: $cont.length ? $cont.is(':visible') : false,
                            display: $cont.length ? $cont.css('display') : null
                        });
                    });

                    // jednoduchý POST (nepřeháníme to)
                    $.post(wc_balikovna_ajaxurl, {
                        action: 'log_shipping_debug_data',
                        data: payload
                    }).fail(function (err) {
                        console.warn('WC Balíkovna debug AJAX fail', err);
                    });
                } catch (e) {
                    console.warn('WC Balíkovna debug AJAX exception', e);
                }
            }
        }

        // Reagujeme na změnu (klik) a updated_checkout (AJAX)
        $(document.body).on('change', 'input[name^="shipping_method"]', function () {
            console.log('🔔 change event detected');
            // malé zpoždění pro jistotu, pokud se checkbox mění skrze JS
            setTimeout(updateShippingContentVisibility, 10);
        });

        $(document.body).on('updated_checkout', function () {
            console.log('🔔 updated_checkout event detected — waiting for DOM render');
            setTimeout(updateShippingContentVisibility, 120);
        });

        // Inicializace při načtení stránky
        $(document).ready(function () {
            console.log('🚦 Inicializuji ovládání dopravy (initial run).');
            setTimeout(updateShippingContentVisibility, 60);
        });
    }

    $(document).ready(function () {
        observeShippingMethods();
    });

})(jQuery);