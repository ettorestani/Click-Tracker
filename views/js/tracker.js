/**
 * Click Tracker Module - Frontend Tracking Script
 *
 * Vanilla JavaScript click tracking for PrestaShop
 * No jQuery dependencies
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   AFL-3.0
 */

(function() {
    'use strict';

    /**
     * ET_ClickTracker Module
     * Namespaced to avoid conflicts with other modules
     */
    var ET_ClickTracker = {
        /**
         * Configuration (set from PHP)
         */
        config: {
            classes: [],
            bodyClasses: [],
            externalOnly: false,
            debug: false,
            token: '',
            ajaxUrl: '',
            context: 'other',
            pageType: '',
            productData: null,
            shouldTrackByPageType: false
        },

        /**
         * Track if already initialized
         */
        initialized: false,

        /**
         * Debounce tracking to prevent duplicates
         */
        lastTrackedElement: null,
        lastTrackedTime: 0,
        debounceMs: 500,

        /**
         * Initialize tracker
         * @param {Object} options Configuration options
         */
        init: function(options) {
            if (this.initialized) {
                this.log('Already initialized');
                return;
            }

            // Merge options
            if (options) {
                for (var key in options) {
                    if (options.hasOwnProperty(key) && this.config.hasOwnProperty(key)) {
                        this.config[key] = options[key];
                    }
                }
            }

            // Validate configuration
            if (!this.config.classes || this.config.classes.length === 0) {
                this.log('No CSS selectors configured');
                return;
            }

            if (!this.config.ajaxUrl) {
                this.log('No AJAX URL configured');
                return;
            }

            // Check if we should track on this page
            if (!this.shouldTrackOnPage()) {
                this.log('Tracking disabled for this page');
                return;
            }

            // Setup event listener using event delegation
            this.setupEventListener();

            this.initialized = true;
            this.log('Initialized with selectors: ' + this.config.classes.join(', '));
        },

        /**
         * Check if tracking should be active on this page
         * @returns {boolean} True if should track
         */
        shouldTrackOnPage: function() {
            // If server determined this page should be tracked
            if (this.config.shouldTrackByPageType) {
                return true;
            }

            // Check body selectors (classes and IDs) if configured
            if (this.config.bodyClasses && this.config.bodyClasses.length > 0) {
                for (var i = 0; i < this.config.bodyClasses.length; i++) {
                    var selector = this.config.bodyClasses[i];

                    if (selector.charAt(0) === '#') {
                        if (document.body.id === selector.substring(1)) {
                            this.log('Body ID matched: ' + selector);
                            return true;
                        }
                    } else if (selector.charAt(0) === '.') {
                        if (document.body.classList.contains(selector.substring(1))) {
                            this.log('Body class matched: ' + selector);
                            return true;
                        }
                    }
                }
            }

            return false;
        },

        /**
         * Setup click event listener using event delegation
         */
        setupEventListener: function() {
            var self = this;

            document.addEventListener('click', function(event) {
                self.handleClick(event);
            }, true); // Use capture phase
        },

        /**
         * Handle click event
         * @param {Event} event Click event
         */
        handleClick: function(event) {
            var match = this.findMatchingElement(event.target);

            if (!match) {
                return; // Not a tracked element
            }

            // Debounce check
            if (this.isDuplicate(match.element)) {
                this.log('Duplicate click ignored');
                return;
            }

            // Check external only setting
            if (this.config.externalOnly && !this.isExternalLink(match.element)) {
                this.log('Non-external link ignored');
                return;
            }

            this.sendTracking(this.collectData(match.element, match.selector));
        },

        /**
         * Find the clicked element (or closest ancestor) matching a tracked selector
         * @param {Element} target Click target
         * @returns {Object|null} {element, selector} or null
         */
        findMatchingElement: function(target) {
            var element = target;
            var maxDepth = 10; // Prevent infinite loops
            var depth = 0;
            var selector;

            while (element && element !== document.body && depth < maxDepth) {
                selector = this.getMatchingSelector(element);
                if (selector) {
                    return { element: element, selector: selector };
                }
                element = element.parentElement;
                depth++;
            }

            return null;
        },

        /**
         * Return the first configured selector matched by the element
         * @param {Element} element Element to check
         * @returns {string|null} Selector or null
         */
        getMatchingSelector: function(element) {
            if (!element || !element.matches) {
                return null;
            }

            for (var i = 0; i < this.config.classes.length; i++) {
                try {
                    if (element.matches(this.config.classes[i])) {
                        return this.config.classes[i];
                    }
                } catch (e) {
                    this.log('Invalid selector: ' + this.config.classes[i]);
                }
            }

            return null;
        },

        /**
         * Check if click is a duplicate (debouncing)
         * @param {Element} element Clicked element
         * @returns {boolean} True if duplicate
         */
        isDuplicate: function(element) {
            var now = Date.now();

            if (element === this.lastTrackedElement &&
                (now - this.lastTrackedTime) < this.debounceMs) {
                return true;
            }

            this.lastTrackedElement = element;
            this.lastTrackedTime = now;

            return false;
        },

        /**
         * Check if the element links outside the shop
         * (other domain, or non-http schemes such as tel:, mailto:, whatsapp:)
         * @param {Element} element Element to check
         * @returns {boolean} True if external
         */
        isExternalLink: function(element) {
            var href = (element.getAttribute('href') || '').trim();

            if (href === '' || href.charAt(0) === '#' || /^javascript:/i.test(href)) {
                return false;
            }

            var scheme = href.match(/^([a-z][a-z0-9+.\-]*):/i);
            if (scheme && !/^https?$/i.test(scheme[1])) {
                return true;
            }

            try {
                return new URL(href, window.location.href).hostname !== window.location.hostname;
            } catch (e) {
                return false;
            }
        },

        /**
         * Collect tracking data from element
         * @param {Element} element Clicked element
         * @param {string} selector Matched configured selector
         * @returns {Object} Tracking data
         */
        collectData: function(element, selector) {
            var data = {
                context: this.config.context,
                page_type: this.config.pageType,
                clicked_class: this.getSelectorLabel(selector),
                element_type: this.getElementType(element),
                page_url: window.location.href,
                token: this.config.token
            };

            // Product name and category are resolved server side from the ID
            if (this.config.context === 'product' && this.config.productData) {
                data.id_product = this.config.productData.id_product;
            } else {
                data.page_title = this.getPageTitle();
            }

            return data;
        },

        /**
         * Label stored for a selector: simple ".class" selectors without the dot
         * (same rule as ClickTracker::getSelectorLabel)
         * @param {string} selector CSS selector
         * @returns {string} Label
         */
        getSelectorLabel: function(selector) {
            return /^\.[a-zA-Z0-9_\-]+$/.test(selector) ? selector.substring(1) : selector;
        },

        /**
         * Check if any class name contains one of the given words as a whole segment
         * (segments are separated by "-" or "_": "btn-call" matches "call", "callout" does not)
         * @param {Element} element Element to analyze
         * @param {Array} words Words to look for
         * @returns {boolean}
         */
        hasClassWord: function(element, words) {
            var classes = element.classList || [];

            for (var i = 0; i < classes.length; i++) {
                var segments = classes[i].toLowerCase().split(/[-_]+/);
                for (var j = 0; j < segments.length; j++) {
                    if (words.indexOf(segments[j]) !== -1) {
                        return true;
                    }
                }
            }

            return false;
        },

        /**
         * Determine element type: the link target wins over class names
         * @param {Element} element Element to analyze
         * @returns {string} Element type
         */
        getElementType: function(element) {
            var href = (element.getAttribute('href') || '').trim().toLowerCase();

            if (href) {
                if (href.indexOf('whatsapp:') === 0 ||
                    href.indexOf('wa.me/') !== -1 ||
                    href.indexOf('api.whatsapp.com') !== -1 ||
                    href.indexOf('web.whatsapp.com') !== -1 ||
                    href.indexOf('chat.whatsapp.com') !== -1) {
                    return 'whatsapp';
                }

                if (href.indexOf('tel:') === 0 || href.indexOf('callto:') === 0) {
                    return 'phone';
                }

                if (href.indexOf('geo:') === 0 ||
                    /(^|\/\/|\.)google\.[a-z.]+\/maps/.test(href) ||
                    href.indexOf('maps.google.') !== -1 ||
                    href.indexOf('goo.gl/maps') !== -1 ||
                    href.indexOf('maps.app.goo.gl') !== -1 ||
                    href.indexOf('maps.apple.com') !== -1 ||
                    href.indexOf('waze.com') !== -1) {
                    return 'maps';
                }
            }

            if (this.hasClassWord(element, ['whatsapp', 'wa'])) {
                return 'whatsapp';
            }

            if (this.hasClassWord(element, ['phone', 'tel', 'call', 'telephone'])) {
                return 'phone';
            }

            if (this.hasClassWord(element, ['maps', 'map'])) {
                return 'maps';
            }

            return 'other';
        },

        /**
         * Get page title
         * @returns {string} Page title
         */
        getPageTitle: function() {
            // Try to get from PrestaShop page object
            if (typeof prestashop !== 'undefined' && prestashop.page && prestashop.page.title) {
                return prestashop.page.title;
            }

            // Try H1
            var h1 = document.querySelector('h1');
            if (h1 && h1.textContent) {
                return h1.textContent.trim();
            }

            // Fallback to document title
            return document.title || '';
        },

        /**
         * Build form data from tracking data
         * @param {Object} data Tracking data
         * @returns {FormData}
         */
        buildFormData: function(data) {
            var formData = new FormData();

            for (var key in data) {
                if (data.hasOwnProperty(key) && data[key] !== null && data[key] !== undefined) {
                    formData.append(key, data[key]);
                }
            }

            return formData;
        },

        /**
         * Send tracking data to server.
         * sendBeacon survives page navigation (links opening in the same tab);
         * in debug mode fetch is used to read the server response.
         * @param {Object} data Tracking data
         */
        sendTracking: function(data) {
            var formData = this.buildFormData(data);

            try {
                if (!this.config.debug && navigator.sendBeacon) {
                    if (navigator.sendBeacon(this.config.ajaxUrl, formData)) {
                        return;
                    }
                    formData = this.buildFormData(data);
                }

                if (typeof fetch === 'function') {
                    this.sendWithFetch(formData);
                } else {
                    this.sendWithXHR(formData);
                }
            } catch (e) {
                this.log('Error sending tracking: ' + e.message);
            }
        },

        /**
         * Send data using Fetch API (keepalive lets the request outlive the page)
         * @param {FormData} formData Tracking data
         */
        sendWithFetch: function(formData) {
            var self = this;

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                keepalive: true
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(result) {
                self.log('Tracking sent: ' + (result.success ? 'success' : 'failed (' + result.error + ')'));
            })
            .catch(function(error) {
                self.log('Tracking error: ' + error.message);
            });
        },

        /**
         * Send data using XMLHttpRequest (fallback)
         * @param {FormData} formData Tracking data
         */
        sendWithXHR: function(formData) {
            var self = this;
            var xhr = new XMLHttpRequest();

            xhr.open('POST', this.config.ajaxUrl, true);
            xhr.withCredentials = true;

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        self.log('Tracking sent successfully');
                    } else {
                        self.log('Tracking failed with status: ' + xhr.status);
                    }
                }
            };

            xhr.onerror = function() {
                self.log('Tracking request error');
            };

            xhr.send(formData);
        },

        /**
         * Log message if debug mode is enabled
         * @param {string} message Message to log
         */
        log: function(message) {
            if (this.config.debug && typeof console !== 'undefined' && console.log) {
                console.log('[ET_ClickTracker] ' + message);
            }
        }
    };

    // Expose to global scope with et_ prefix
    window.ET_ClickTracker = ET_ClickTracker;

    // Auto-initialize when DOM is ready (config is printed by Media::addJsDef)
    function autoInit() {
        if (window.et_clickTrackerConfig) {
            ET_ClickTracker.init(window.et_clickTrackerConfig);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit);
    } else {
        autoInit();
    }

})();
