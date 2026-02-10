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
    function updateBalikovnaVisibility() {
        var $all = $('input[name^="shipping_method"]');
        var $balikovnaInputs = $all.filter(function () {
            var v = $(this).val() || '';
            return v.indexOf('balikovna:') === 0;
        });

        var $checked = $balikovnaInputs.filter(':checked');
        var selectedVal = $checked.val() || null;

        // Najdi a skryj všechny balikovna kontejnery natvrdo
        var containers = $();
        $balikovnaInputs.each(function () {
            var $inp = $(this);
            containers = containers.add(getContainerForInput($inp));
        });

        forceHide(containers);

        // Zobraz pouze ten, který je checked
        if (selectedVal) {
            var $selCont = getContainerForInput($checked);
            if ($selCont.length) {
                forceShow($selCont, 'block');
            }
        }
    }

    // Enforce s retrys pro případ, že něco přepíše později
    function enforceWithRetries() {
        updateBalikovnaVisibility();
        [80, 200, 600].forEach(function (d) {
            setTimeout(function () {
                updateBalikovnaVisibility();
            }, d);
        });
    }

    // Eventy
    $(document.body).on('change', 'input[name^="shipping_method"]', function () {
        setTimeout(function () { updateBalikovnaVisibility(); }, 10);
    });

    $(document.body).on('updated_checkout', function () {
        setTimeout(function () {
            enforceWithRetries();
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
            setTimeout(function () { updateBalikovnaVisibility(); }, 20);
        });
        observer.observe(target, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'aria-hidden'] });
    }

    $(document).ready(function () {
        setTimeout(function () { enforceWithRetries(); }, 50);
        createObserver();
    });

    // clean-up
    $(window).on('beforeunload', function () {
        if (observer) {
            try { observer.disconnect(); } catch (e) {}
        }
    });

})(jQuery);