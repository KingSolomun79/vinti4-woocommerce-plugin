(function () {
    var settings = window.wc.wcSettings.getSetting('vinti4_data', {});
    var label = window.wp.htmlEntities.decodeEntities(settings.title) || 'Vinti4';

    var Label = function () {
        return window.wp.element.createElement('span', null, label);
    };

    var Content = function () {
        return window.wp.element.createElement(
            'div',
            null,
            window.wp.htmlEntities.decodeEntities(settings.description || '')
        );
    };

    window.wc.wcBlocksRegistry.registerPaymentMethod({
        name: 'vinti4',
        label: window.wp.element.createElement(Label),
        content: window.wp.element.createElement(Content),
        edit: window.wp.element.createElement(Content),
        canMakePayment: function () { return true; },
        ariaLabel: label,
        supports: { features: settings.supports || ['products'] },
    });
})();
