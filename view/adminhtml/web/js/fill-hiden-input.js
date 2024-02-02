define([
    'jquery',
    'underscore',
], function ($, _) {
    'use strict';

    $.widget('mbissonho.fillHidenInput', {
        options: {},

        /** @inheritdoc */
        _create: function () {
            let last = sessionStorage.getItem('last-admin-page-accessed');

            if(last) {
                this.element.val(last);
            }
        }
    });

    return $.mbissonho.fillHidenInput;
});
