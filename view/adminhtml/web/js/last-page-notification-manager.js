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

        getLastAccessedPage: function () {
            const raw = sessionStorage.getItem('mbissonho-last-admin-page-accessed');

            if (!raw) {
                return null;
            }

            try {
                return JSON.parse(raw);
            } catch (e) {
                return null;
            }
        },

        checkIfAdminUserIsLoggedIn: function (){
            let self = this;
            const lastPage = self.getLastAccessedPage();

            if (!lastPage || !lastPage.route_path) {
                clearInterval(self.checkInterval);
                return;
            }

            const editDetails = lastPage.edit_details || {};

            $.ajax({
                url: self.options.backendUrl,
                showLoader: false,
                dataType: 'json',
                type: 'GET',
                // Server builds the keyed admin URL for this exact route; the
                // browser cannot mint the per-route secret key on its own.
                data: {
                    route_path: lastPage.route_path,
                    entity_param_name: editDetails.url_entity_param_name || '',
                    entity_param_value: editDetails.url_entity_param_value || 0
                },
                success: function (response) {
                    if(response.logged_in) {
                        self.removeToast($('.toast'), 50, 200);
                        if(!self.linkDisplayed) {
                            self.buildAndDisplayLinkToLastAccessedPage(response.redirect_url);
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

        buildAndDisplayLinkToLastAccessedPage: function (redirectUrl) {
            if(!redirectUrl) return;

            // Build the node via the DOM API (no innerHTML interpolation) so the
            // label and URL are treated strictly as text/attribute values.
            const link = $('<a></a>')
                .attr('href', redirectUrl)
                .text(this.options.goToTheSavedPageNotificationMessage);
            const toast = $('<div class="toast"></div>').append(link);

            this.toastyContainer.append(toast);
            this.linkDisplayed = true;
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
