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
            context: 'cms',
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
                this.log('No CSS classes configured');
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
            this.log('Initialized with classes: ' + this.config.classes.join(', '));
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
                        // ID selector - check body id
                        var idToCheck = selector.substring(1);
                        if (document.body.id === idToCheck) {
                            this.log('Body ID matched: ' + selector);
                            return true;
                        }
                    } else if (selector.charAt(0) === '.') {
                        // Class selector - check body classes
                        var classToCheck = selector.substring(1);
                        if (document.body.classList.contains(classToCheck)) {
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
            var target = event.target;

            // Find the matching element (could be the target or a parent)
            var matchedElement = this.findMatchingElement(target);

            if (!matchedElement) {
                return; // Not a tracked element
            }

            // Debounce check
            if (this.isDuplicate(matchedElement)) {
                this.log('Duplicate click ignored');
                return;
            }

            // Check external only setting
            if (this.config.externalOnly && !this.isExternalLink(matchedElement)) {
                this.log('Non-external link ignored');
                return;
            }

            // Collect and send tracking data
            var data = this.collectData(matchedElement);
            this.sendTracking(data);

            // Mark as tracked
            this.markAsTracked(matchedElement);
        },

        /**
         * Find element matching tracked classes
         * @param {Element} target Click target
         * @returns {Element|null} Matched element or null
         */
        findMatchingElement: function(target) {
            var element = target;
            var maxDepth = 10; // Prevent infinite loops
            var depth = 0;

            while (element && element !== document.body && depth < maxDepth) {
                if (this.matchesSelector(element)) {
                    return element;
                }
                element = element.parentElement;
                depth++;
            }

            return null;
        },

        /**
         * Check if element matches any tracked selector
         * @param {Element} element Element to check
         * @returns {boolean} True if matches
         */
        matchesSelector: function(element) {
            if (!element || !element.matches) {
                return false;
            }

            for (var i = 0; i < this.config.classes.length; i++) {
                try {
                    if (element.matches(this.config.classes[i])) {
                        return true;
                    }
                } catch (e) {
                    this.log('Invalid selector: ' + this.config.classes[i]);
                }
            }

            return false;
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
         * Mark element as tracked
         * @param {Element} element Element to mark
         */
        markAsTracked: function(element) {
            element.setAttribute('data-et-clicktracker-tracked', '1');
        },

        /**
         * Check if link is external
         * @param {Element} element Element to check
         * @returns {boolean} True if external
         */
        isExternalLink: function(element) {
            var href = element.getAttribute('href') || '';
            return href.indexOf('http://') === 0 || href.indexOf('https://') === 0;
        },

        /**
         * Collect tracking data from element
         * @param {Element} element Clicked element
         * @returns {Object} Tracking data
         */
        collectData: function(element) {
            var data = {
                context: this.config.context,
                clicked_class: this.getClickedClass(element),
                element_type: this.getElementType(element),
                page_url: window.location.href,
                token: this.config.token
            };

            // Add context-specific data
            if (this.config.context === 'product' && this.config.productData) {
                data.id_product = this.config.productData.id_product;
                data.product_name = this.config.productData.product_name;
                data.id_category = this.config.productData.id_category;
            } else {
                data.page_title = this.getPageTitle();
            }

            return data;
        },

        /**
         * Get clicked element's class that matches configuration
         * @param {Element} element Clicked element
         * @returns {string} Matching class name
         */
        getClickedClass: function(element) {
            var classes = element.className || '';

            // Find which configured class matches
            for (var i = 0; i < this.config.classes.length; i++) {
                var selector = this.config.classes[i];
                try {
                    if (element.matches(selector)) {
                        // Return the selector without the leading dot
                        return selector.replace(/^\./, '');
                    }
                } catch (e) {
                    // Invalid selector
                }
            }

            // Fallback to first class
            if (typeof classes === 'string') {
                var classList = classes.split(/\s+/);
                return classList[0] || 'unknown';
            }

            return 'unknown';
        },

        /**
         * Determine element type from href or attributes
         * @param {Element} element Element to analyze
         * @returns {string} Element type
         */
        getElementType: function(element) {
            var href = (element.getAttribute('href') || '').toLowerCase();
            var classes = (element.className || '').toLowerCase();
            var text = (element.textContent || '').toLowerCase();

            // Check for WhatsApp
            if (href.indexOf('wa.me') !== -1 ||
                href.indexOf('whatsapp') !== -1 ||
                href.indexOf('api.whatsapp') !== -1 ||
                classes.indexOf('whatsapp') !== -1 ||
                classes.indexOf('wa-') !== -1) {
                return 'whatsapp';
            }

            // Check for Phone
            if (href.indexOf('tel:') === 0 ||
                classes.indexOf('phone') !== -1 ||
                classes.indexOf('tel') !== -1 ||
                classes.indexOf('call') !== -1) {
                return 'phone';
            }

            // Check for Maps
            if (href.indexOf('maps.google') !== -1 ||
                href.indexOf('google.com/maps') !== -1 ||
                href.indexOf('goo.gl/maps') !== -1 ||
                href.indexOf('maps.app.goo.gl') !== -1 ||
                classes.indexOf('maps') !== -1 ||
                classes.indexOf('map') !== -1) {
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
         * Send tracking data to server
         * @param {Object} data Tracking data
         */
        sendTracking: function(data) {
            var self = this;

            try {
                // Use fetch if available, otherwise XMLHttpRequest
                if (typeof fetch === 'function') {
                    this.sendWithFetch(data);
                } else {
                    this.sendWithXHR(data);
                }
            } catch (e) {
                this.log('Error sending tracking: ' + e.message);
            }
        },

        /**
         * Send data using Fetch API
         * @param {Object} data Tracking data
         */
        sendWithFetch: function(data) {
            var self = this;
            var formData = new FormData();

            for (var key in data) {
                if (data.hasOwnProperty(key) && data[key] !== null && data[key] !== undefined) {
                    formData.append(key, data[key]);
                }
            }

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(result) {
                self.log('Tracking sent: ' + (result.success ? 'success' : 'failed'));
            })
            .catch(function(error) {
                self.log('Tracking error: ' + error.message);
            });
        },

        /**
         * Send data using XMLHttpRequest (fallback)
         * @param {Object} data Tracking data
         */
        sendWithXHR: function(data) {
            var self = this;
            var xhr = new XMLHttpRequest();
            var formData = new FormData();

            for (var key in data) {
                if (data.hasOwnProperty(key) && data[key] !== null && data[key] !== undefined) {
                    formData.append(key, data[key]);
                }
            }

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

    // Auto-initialize when DOM is ready if config is available
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (window.et_clickTrackerConfig) {
                ET_ClickTracker.init(window.et_clickTrackerConfig);
            }
        });
    } else {
        // DOM already loaded
        if (window.et_clickTrackerConfig) {
            ET_ClickTracker.init(window.et_clickTrackerConfig);
        }
    }

})();
