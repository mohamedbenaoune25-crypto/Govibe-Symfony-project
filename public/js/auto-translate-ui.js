(function () {
    'use strict';

    var locale = (document.documentElement.lang || 'fr').toLowerCase().split('-')[0];
    if (locale === 'fr') {
        return;
    }

    var sourceLocale = 'fr';
    var endpoint = '/translate';
    var cacheStorageKey = 'govibe_auto_i18n_cache_v2';
    var translating = false;
    var cache = new Map();
    var textNodeState = new WeakMap();
    var attrState = new WeakMap();
    var translationTimer = null;
    var retryDelayMs = 1000;

    function cacheKey(text, target) {
        return target + '|' + text;
    }

    function loadCache() {
        try {
            var raw = window.localStorage.getItem(cacheStorageKey);
            if (!raw) {
                return;
            }

            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return;
            }

            Object.keys(parsed).forEach(function (key) {
                if (typeof parsed[key] === 'string') {
                    cache.set(key, parsed[key]);
                }
            });
        } catch (_err) {
            // Ignore localStorage issues.
        }
    }

    function saveCache() {
        try {
            var obj = {};
            cache.forEach(function (value, key) {
                obj[key] = value;
            });
            window.localStorage.setItem(cacheStorageKey, JSON.stringify(obj));
        } catch (_err) {
            // Ignore localStorage issues.
        }
    }

    function shouldTranslateText(value) {
        if (!value) {
            return false;
        }

        var text = String(value).trim();
        return text.length > 0;
    }

    function collectTranslatableNodes(root) {
        var nodes = [];
        var attrs = [];
        var uniqueTexts = new Set();

        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node || !node.parentElement) {
                    return NodeFilter.FILTER_REJECT;
                }

                var parent = node.parentElement;
                var tagName = (parent.tagName || '').toLowerCase();
                if (['script', 'style', 'noscript', 'code', 'pre', 'textarea'].indexOf(tagName) !== -1) {
                    return NodeFilter.FILTER_REJECT;
                }

                return shouldTranslateText(node.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        });

        var current;
        while ((current = walker.nextNode())) {
            var text = String(current.nodeValue || '').trim();

            var existing = textNodeState.get(current);
            if (existing && existing.locale === locale && existing.translated === text) {
                continue;
            }

            nodes.push({ node: current, text: text });
            uniqueTexts.add(text);
        }

        var selector = '[placeholder], [title], [aria-label], [alt], [data-bs-title], [data-bs-original-title], input[type="submit"], input[type="button"], button[value]';
        root.querySelectorAll(selector).forEach(function (el) {
            ['placeholder', 'title', 'aria-label', 'alt', 'data-bs-title', 'data-bs-original-title', 'value'].forEach(function (attrName) {
                if (!el.hasAttribute(attrName)) {
                    return;
                }

                var value = el.getAttribute(attrName) || '';
                var normalized = value.trim();
                if (!shouldTranslateText(normalized)) {
                    return;
                }

                var elementState = attrState.get(el) || {};
                var attrExisting = elementState[attrName];
                if (attrExisting && attrExisting.locale === locale && attrExisting.translated === normalized) {
                    return;
                }

                attrs.push({ element: el, attr: attrName, text: normalized });
                uniqueTexts.add(normalized);
            });
        });

        return {
            nodes: nodes,
            attrs: attrs,
            uniqueTexts: Array.from(uniqueTexts)
        };
    }

    async function translateBatch(texts) {
        var payload = {
            texts: texts,
            target: locale,
            source: sourceLocale
        };

        var response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            throw new Error('Translation request failed');
        }

        var data = await response.json();
        if (!data || data.success !== true || typeof data.translations !== 'object') {
            throw new Error('Invalid translation response');
        }

        return data.translations;
    }

    async function translatePage(root) {
        if (translating) {
            return;
        }

        translating = true;
        try {
            var collected = collectTranslatableNodes(root);
            if (collected.uniqueTexts.length === 0) {
                retryDelayMs = 1000;
                return;
            }

            var pending = [];
            collected.uniqueTexts.forEach(function (text) {
                var key = cacheKey(text, locale);
                if (!cache.has(key)) {
                    pending.push(text);
                }
            });

            var chunkSize = 30;
            var hadFailures = false;
            for (var i = 0; i < pending.length; i += chunkSize) {
                var chunk = pending.slice(i, i + chunkSize);
                if (chunk.length === 0) {
                    continue;
                }

                try {
                    var translations = await translateBatch(chunk);
                    Object.keys(translations).forEach(function (sourceText) {
                        var translated = translations[sourceText];
                        if (typeof translated === 'string' && translated.trim() !== '') {
                            cache.set(cacheKey(sourceText, locale), translated);
                        }
                    });
                } catch (_err) {
                    hadFailures = true;
                }
            }

            if (hadFailures) {
                var currentDelay = retryDelayMs;
                retryDelayMs = Math.min(retryDelayMs * 2, 8000);
                window.setTimeout(function () {
                    scheduleTranslation(document.documentElement);
                }, currentDelay);
            } else {
                retryDelayMs = 1000;
            }

            saveCache();

            collected.nodes.forEach(function (item) {
                var key = cacheKey(item.text, locale);
                var translatedText = cache.get(key);
                if (typeof translatedText !== 'string' || translatedText.trim() === '') {
                    return;
                }

                textNodeState.set(item.node, {
                    locale: locale,
                    source: item.text,
                    translated: translatedText
                });
                item.node.nodeValue = item.node.nodeValue.replace(item.text, translatedText);
            });

            collected.attrs.forEach(function (item) {
                var key = cacheKey(item.text, locale);
                var translatedAttr = cache.get(key);
                if (typeof translatedAttr !== 'string' || translatedAttr.trim() === '') {
                    return;
                }

                var elementState = attrState.get(item.element) || {};
                elementState[item.attr] = {
                    locale: locale,
                    source: item.text,
                    translated: translatedAttr
                };
                attrState.set(item.element, elementState);
                item.element.setAttribute(item.attr, translatedAttr);
            });
        } finally {
            translating = false;
        }
    }

    function scheduleTranslation(root) {
        if (translationTimer) {
            window.clearTimeout(translationTimer);
        }

        translationTimer = window.setTimeout(function () {
            translationTimer = null;
            translatePage(root || document.documentElement);
        }, 120);
    }

    function setupObserver() {
        var observer = new MutationObserver(function (mutations) {
            if (translating) {
                return;
            }

            var needsTranslation = false;
            mutations.forEach(function (mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    needsTranslation = true;
                }
                if (mutation.type === 'characterData' && shouldTranslateText(mutation.target.nodeValue || '')) {
                    needsTranslation = true;
                }
            });

            if (needsTranslation) {
                window.requestIdleCallback
                    ? window.requestIdleCallback(function () { scheduleTranslation(document.documentElement); }, { timeout: 1200 })
                    : scheduleTranslation(document.documentElement);
            }
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    function init() {
        loadCache();
        scheduleTranslation(document.documentElement);
        setupObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
