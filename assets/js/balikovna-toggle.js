(function ($) {
    'use strict';

    // Pomocné funkce
    function getContainerForInput($input) {
        if (!$input || $input.length === 0) return $();
        var $li = $input.closest('li');
        var $container = $li.children('.shipping-method__after-shipping-rate');
        if ($container.length === 0) {
            $container = $li.find('.shipping-method__after-shipping-rate').first();
        }
        if ($container.length === 0) {
            $container = $li.find('.wc-balikovna-branch-selection, .wc-balikovna-address-notice').closest('.shipping-method__after-shipping-rate');
        }
        return $container;
    }

    function forceHide($el) {
        if (!$el || !$el.length) return;
        $el.each(function () {
            try {
                this.style.setProperty('display', 'none', 'important');
                this.setAttribute('aria-hidden', 'true');
            } catch (e) {}
        });
    }

    function forceShow($el, displayType) {
        if (!$el || !$el.length) return;
        var disp = displayType || 'block';
        $el.each(function () {
            try {
                this.style.setProperty('display', disp, 'important');
                this.setAttribute('aria-hidden', 'false');
            } catch (e) {}
        });
    }

    // Hlavní logika: najdi všechny balikovna inputy a zobraz jen vybraný kontejner
    function updateBalikovnaVisibility(reason) {
        var $all = $('input[name^="shipping_method"]');
        var $balikovnaInputs = $all.filter(function () {
            var v = $(this).val() || '';
            return v.indexOf('balikovna:') === 0;
        });

        var $checked = $balikovnaInputs.filter(':checked');
        var selectedVal = $checked.val() || null;

        console.log('🔄 balikovna update — reason:', reason || '', ' selected:', selectedVal, ' balikovna count:', $balikovnaInputs.length);

        // Najdi a skryj všechny balikovna kontejnery natvrdo
        var containers = $();
        $balikovnaInputs.each(function () {
            var $inp = $(this);
            containers = containers.add(getContainerForInput($inp));
        });
        // Unikátní set
        containers = containers.filter(function (i, el) { return el; });

        // Skryj všechny
        forceHide(containers);

        // Zobraz pouze ten, který je checked
        if (selectedVal) {
            // Najít container pro ten checked
            var $selCont = getContainerForInput($checked);
            if ($selCont.length) {
                console.log('📍 Zobrazuji kontainer pro', selectedVal);
                forceShow($selCont, 'block');
            } else {
                console.log('⚠️ Nelze najít container pro vybraný balikovna input:', selectedVal);
            }
        } else {
            console.log('🔒 Žádný balikovna input není vybrán - všechny balikovna kontejnery skryty');
        }

        // debug state
        containers.each(function () {
            var $c = $(this);
            console.log(' - container for:', $c.closest('li').find('input[name^="shipping_method"]').val(), ' visible:', $c.is(':visible'), ' display:', $c.css('display'));
        });
    }

    // Enforce s retrys pro případ, že něco přepíše později
    function enforceWithRetries(reason) {
        updateBalikovnaVisibility(reason + ' initial');
        [80, 200, 600].forEach(function (d) {
            setTimeout(function () {
                updateBalikovnaVisibility(reason + ' retry ' + d);
            }, d);
        });
    }

    // Eventy
    $(document.body).on('change', 'input[name^="shipping_method"]', function () {
        setTimeout(function () { updateBalikovnaVisibility('change'); }, 10);
    });

    $(document.body).on('updated_checkout', function () {
        // Po AJAX renderu počkáme, poté zkusíme opakovaně vymoci pravidlo
        setTimeout(function () {
            enforceWithRetries('updated_checkout');
        }, 80);
    });

    // MutationObserver: pokud DOM mění jiný skript, zareagujeme
    var observer = null;
    function createObserver() {
        var target = document.querySelector('form.checkout') || document.body;
        if (observer) {
            try { observer.disconnect(); } catch (e) {}
        }
        observer = new MutationObserver(function () {
            // rychlé přepočítání, ale debounced by mohl být lepší
            setTimeout(function () { updateBalikovnaVisibility('mutation'); }, 20);
        });
        observer.observe(target, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'aria-hidden'] });
    }

    $(document).ready(function () {
        // initial run a observer
        setTimeout(function () { enforceWithRetries('page_load'); }, 50);
        createObserver();
    });

    // clean-up
    $(window).on('beforeunload', function () {
        if (observer) {
            try { observer.disconnect(); } catch (e) {}
        }
    });

})(jQuery);