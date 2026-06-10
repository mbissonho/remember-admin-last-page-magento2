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
        entityDetailsDisplayed: false,
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
                            self.maybeDisplayEntityDetails(lastPage.entity_token);
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

        maybeDisplayEntityDetails: function (entityToken) {
            let self = this;

            // Opt-in feature: only fetch when enabled and the remembered page
            // actually carried a sealed entity reference. The server still
            // re-checks auth + ACL before returning anything.
            if (!self.options.entityDetailsActive
                || !self.options.entityPreviewUrl
                || !entityToken
                || self.entityDetailsDisplayed) {
                return;
            }

            $.ajax({
                url: self.options.entityPreviewUrl,
                showLoader: false,
                dataType: 'json',
                type: 'GET',
                data: {
                    entity_token: entityToken
                },
                success: function (response) {
                    if (response && response.details) {
                        self.displayEntityDetails(response.details);
                    }
                },
                error: function (response) {
                    console.warn(response);
                }
            });
        },

        displayEntityDetails: function (details) {
            const fields = (details && Array.isArray(details.fields)) ? details.fields : [];

            if (!fields.length) {
                return;
            }

            // Build via the DOM API only (no innerHTML interpolation) so the
            // already-masked values are treated strictly as text.
            const toast = $('<div class="toast toast-entity-details"></div>');

            if (details.label) {
                toast.append($('<div class="toast-entity-label"></div>').text(String($t(details.label))));
            }

            fields.forEach(function (field) {
                if (!field || typeof field.value === 'undefined') {
                    return;
                }

                const row = $('<div class="toast-entity-field"></div>');

                if (field.label) {
                    row.append($('<span class="toast-entity-field-label"></span>')
                        .text(String($t(field.label)) + ': '));
                }

                row.append($('<span class="toast-entity-field-value"></span>').text(String(field.value)));
                toast.append(row);
            });

            this.toastyContainer.append(toast);
            this.entityDetailsDisplayed = true;
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
