define([
    'jquery',
    'underscore',
    'mage/translate',
    'mage/cookies'
], function ($, $t, _) {
    'use strict';

    $.widget('mbissonho.lastPageNotificationManager', {
        options: {},
        toastyContainer: undefined,
        linkDisplayed: false,
        originalPageTitle: document.title,
        checkInterval: undefined,
        /** @inheritdoc */
        _create: function () {
            this.toastyContainer = $('#toast-container');

            if(this.hasLastAccessedPageOnBrowserSession()){
                this.showToast(String($t(this.options.hasSavedPageNotificationMessage)), false);
                this.blinkPageTitle();
                this.checkInterval = setInterval(function () {
                    this.checkIfAdminUserIsLoggedIn()
                }.bind(this), 5000);
            }
        },

        blinkPageTitle: function () {
            setInterval(function () {
                document.title = document.title === this.originalPageTitle ?
                    $t(this.options.loginPageTitleBlinkMessage) :
                    this.originalPageTitle
            }.bind(this), 3000);
        },

        hasLastAccessedPageOnBrowserSession: function () {
            return null !== sessionStorage.getItem('mbissonho-last-admin-page-accessed')
        },

        checkIfAdminUserIsLoggedIn: function (){
            let self = this;
            //TODO: Change to the correct backend front name
            $.ajax({
                url: self.options.backendUrl,
                showLoader: false,
                dataType: 'json',
                type: 'GET',
                success: function (response) {
                    if(response.logged_in) {
                        self.removeToast($('.toast'), 50, 200);
                        if(!self.linkDisplayed) {
                            self.buildAndDisplayLinkToLastAccessedPage(
                                {},
                                response.secret_key
                            );
                            clearInterval(self.checkInterval);
                        }
                    } else {
                        self.linkDisplayed = false;
                    }

                },
                complete: function (jqXHR) {
                    //Override ajaxSetup to avoid page reload
                },
                error: function (response) {
                    console.warn(response);
                }
            });
        },

        buildAndDisplayLinkToLastAccessedPage: function (lastAccessedPageObject, secretKey) {
            let self = this, link = `${window.location.origin}/admin/customer/index/index/key/${secretKey}`
            const linkElement  = `<a href="${link}">${this.options.goToTheSavedPageNotificationMessage}</a>`;
            self.showToast(linkElement, false);
            self.linkDisplayed = true;
        },

        showToast: function (content, removeAutomatically = true, fadeOutAfter = 15000) {
            const toast = $('<div class="toast"></div>').html(content);

            this.toastyContainer.append(toast);

            if(!removeAutomatically) return;

            this.removeToast(toast, fadeOutAfter);
        },

        removeToast: function (toast, fadeOutAfter = 0, removeAfterFadeout = 1000) {
            setTimeout(function () {
                toast?.addClass('fade-out');
            }, fadeOutAfter);

            setTimeout(function () {
                toast?.remove();
            }, fadeOutAfter + removeAfterFadeout);
        }
    });

    return $.mbissonho.lastPageNotificationManager;
});
