define([
    'jquery',
    'underscore',
], function ($, _) {
    'use strict';

    $.widget('mbissonho.fillHidenInput', {
        options: {},

        /** @inheritdoc */
        _create: function () {
            let lastAdminPageAccessedRecord = sessionStorage.getItem('mbissonho-last-admin-page-accessed');

            if(lastAdminPageAccessedRecord) {
                this.element.val(lastAdminPageAccessedRecord);
            }
        }
    });

    return $.mbissonho.fillHidenInput;
});
