window.addEventListener('load', function () {
    (() => {
        const SETTINGS_ENDPOINT = '/extensions/translations/admin/languages/settings';
        const DEFAULT_API_BASE_URL = 'https://api.euphoriadevelopment.uk/translations';

        const defaultLanguage = 'en'; // Fallback default language
        const targetLanguage = localStorage.getItem('selectedLanguage') || defaultLanguage;

        if (targetLanguage === 'en') {
            return;
        }
        const translationCache = new Map();

        const normalizeApiBaseUrl = (url) => {
            if (!url) return '';
            return String(url).trim().replace(/\/+$/, '');
        };

        const joinUrl = (base, path) => {
            const normalizedBase = normalizeApiBaseUrl(base);
            const normalizedPath = String(path || '').replace(/^\/+/, '');
            return `${normalizedBase}/${normalizedPath}`;
        };

        let apiBaseUrlPromise = null;
        const getApiBaseUrl = async () => {
            const fromWindow = normalizeApiBaseUrl(window.TRANSLATIONS_API_URL || '');
            if (fromWindow) return fromWindow;

            if (apiBaseUrlPromise) return apiBaseUrlPromise;

            apiBaseUrlPromise = (async () => {
                try {
                    const response = await fetch(SETTINGS_ENDPOINT, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        return DEFAULT_API_BASE_URL;
                    }

                    const data = await response.json();
                    const apiUrl = normalizeApiBaseUrl(data.apiUrl || data.defaultApiUrl || '');
                    return apiUrl || DEFAULT_API_BASE_URL;
                } catch (error) {
                    console.error('Error fetching translation settings:', error);
                    return DEFAULT_API_BASE_URL;
                }
            })();

            return apiBaseUrlPromise;
        };

        const fetchTranslations = async (texts, targetLang) => {
            try {
                if (texts.length === 0) {
                    return {};
                }

                const apiBaseUrl = await getApiBaseUrl();
                const endpoint = joinUrl(apiBaseUrl, '/translate/bulk');
        
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        texts,
                        targetLang,
                    }),
                });
        
                const data = await response.json();
                if (data.success) {
                    return data.translations || {};
                } else {
                    console.error('Bulk translation failed:', data.error);
                    return {};
                }
            } catch (error) {
                console.error('Error fetching bulk translations:', error);
                return {};
            }
        };

        const collectTextNodes = () => {
            const textNodes = [];
            const excludedSelectors = [
                '#language-selector-button',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > a:nth-child(4) > div.ServerRow___StyledDiv4-sc-1ibsw91-10.gQExFz',
                'body > footer',
                '#NavigationLink > span',
                '#logo > a > span',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.grid.grid-cols-4.gap-2.sm\\:gap-4.mb-4 > div.grid.grid-cols-6.gap-2.md\\:gap-4.col-span-4.lg\\:col-span-1.order-last.lg\\:order-none > div:nth-child(3) > div.flex.flex-col.justify-center.overflow-hidden.w-full > div',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.grid.grid-cols-4.gap-2.sm\\:gap-4.mb-4 > div.grid.grid-cols-6.gap-2.md\\:gap-4.col-span-4.lg\\:col-span-1.order-last.lg\\:order-none > div:nth-child(4) > div.flex.flex-col.justify-center.overflow-hidden.w-full > div',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.grid.grid-cols-4.gap-2.sm\\:gap-4.mb-4 > div.grid.grid-cols-6.gap-2.md\\:gap-4.col-span-4.lg\\:col-span-1.order-last.lg\\:order-none > div:nth-child(5) > div.flex.flex-col.justify-center.overflow-hidden.w-full > div',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.grid.grid-cols-4.gap-2.sm\\:gap-4.mb-4 > div.grid.grid-cols-6.gap-2.md\\:gap-4.col-span-4.lg\\:col-span-1.order-last.lg\\:order-none > div:nth-child(6) > div.flex.flex-col.justify-center.overflow-hidden.w-full > div',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.grid.grid-cols-4.gap-2.sm\\:gap-4.mb-4 > div.grid.grid-cols-6.gap-2.md\\:gap-4.col-span-4.lg\\:col-span-1.order-last.lg\\:order-none > div:nth-child(7) > div.flex.flex-col.justify-center.overflow-hidden.w-full > div',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.grid.grid-cols-4.gap-2.sm\\:gap-4.mb-4 > div.grid.grid-cols-6.gap-2.md\\:gap-4.col-span-4.lg\\:col-span-1.order-last.lg\\:order-none > div:nth-child(8) > div.flex.flex-col.justify-center.overflow-hidden.w-full > div',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.grid.grid-cols-4.gap-4.mb-4 > div.col-span-4.sm\\:col-span-2.lg\\:col-span-1.self-end > div > button:nth-child(1)',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.grid.grid-cols-4.gap-4.mb-4 > div.col-span-4.sm\\:col-span-2.lg\\:col-span-1.self-end > div > button.style-module_4LBM1DKx.style-module_3kBDV_wo.style-module_Yp7-2Fw-.flex-1',
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.grid.grid-cols-4.gap-4.mb-4 > div.col-span-4.sm\\:col-span-2.lg\\:col-span-1.self-end > div > button.style-module_4LBM1DKx.style-module_3kBDV_wo.style-module_2vOYXZWm.flex-1',
                '#language-dropdown > ul > li', 
                '#app > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.FileEditContainer___StyledDiv3-sc-48rzpu-7.igexuH > div.CodemirrorEditor__EditorContainer-sc-1dzlt6m-0.hWjPIP',
                '#sidebar',
                '#app > div.nebula-weblinks',
                'body > div.App___StyledDiv-sc-2l91w7-0.fnfeQw > div.Fade__Container-sc-1p0gm8n-0.hcgQjy > section > div.ContentContainer-sc-x3r2dw-0.PageContentBlock___StyledContentContainer-sc-kbxq2g-0.jyeSuy.HeRWk.fade-appear-done.fade-enter-done > div.FileEditContainer___StyledDiv3-sc-48rzpu-7.igexuH'
            ]; 

            // Collect all excluded elements and their descendants
            const excludedElements = new Set();
            excludedSelectors.forEach((selector) => {
                document.querySelectorAll(selector).forEach((element) => {
                    excludedElements.add(element);
                    element.querySelectorAll('*').forEach((child) => excludedElements.add(child)); // Add all descendants
                });
            });

            // Process all elements except those in the excluded set
            const elements = document.querySelectorAll('*:not(script):not(style):not(.no-translate)');
            elements.forEach((element) => {
                if (excludedElements.has(element)) {
                    return; // Skip this element and its child nodes
                }

                // Collect text nodes
                element.childNodes.forEach((node) => {
                    if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim() !== '') {
                        textNodes.push(node);
                    }
                });
            });

                return textNodes;
            };

        const applyTranslations = (textNodes, translations) => {
            textNodes.forEach((node) => {
                const originalText = node.nodeValue;
                const trimmedText = originalText.trim();
                if (translations[trimmedText]) {
                    // Preserve leading/trailing whitespace
                    const leading = originalText.match(/^\s*/)[0];
                    const trailing = originalText.match(/\s*$/)[0];
                    node.nodeValue = leading + translations[trimmedText] + trailing;
                }
            });
        };

            const translatePage = async () => {
                const textNodes = collectTextNodes();
                const textsToTranslate = textNodes.map((node) => node.nodeValue.trim());

                const textsToFetch = textsToTranslate.filter((text) => !translationCache.has(text));
                const cachedTranslations = Object.fromEntries(
                    textsToTranslate.map((text) => [text, translationCache.get(text)]).filter(([, value]) => value)
                );

                const fetchedTranslations = await fetchTranslations(textsToFetch, targetLanguage);

                Object.entries(fetchedTranslations).forEach(([original, translated]) => {
                    translationCache.set(original, translated);
                });

                const allTranslations = { ...cachedTranslations, ...fetchedTranslations };
                applyTranslations(textNodes, allTranslations);
            };

            const observeDOMChanges = () => {
                let timeoutId;

                const observer = new MutationObserver(() => {
                    if (timeoutId) {
                        clearTimeout(timeoutId);
                    }

                    timeoutId = setTimeout(() => {
                        translatePage();
                    }, 300); // Throttle DOM changes to every 300ms
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                });
            };
        
            const initialize = async () => {
                await translatePage();
            };
        
        // Initialize and observe DOM changes
        initialize();
        observeDOMChanges();
    })();
});
