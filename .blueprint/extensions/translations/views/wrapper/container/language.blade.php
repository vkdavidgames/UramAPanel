<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<div id="language-selector-container" class="no-translate" style="position: fixed; top: 10px; right: 10px; z-index: 1000;">
    <button id="language-selector-button" class="no-translate" style="background-color: hsla(0, 0%, 0%, 0.8) !important; color: white; border: none; padding: 10px 10px; border-radius: 5px; cursor: pointer;">
        <i class="fa-solid fa-language"></i>
    </button>
    <div id="language-dropdown" class="no-translate" style="display: none; position: absolute; top: 40px; overflow-y: scroll; height: auto; max-height: 30vh; padding: 0.5rem; margin-top: 5px; right: 0; background-color: hsla(0, 0%, 0%, 0.8) !important; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
        <ul style="list-style: none; margin: 0; padding: 10px; max-height: 200px; overflow-y: auto;">
        </ul>
    </div>
    <!-- Tooltip for current language -->
    <div id="language-tooltip" class="no-translate" style="display: none; position: absolute; top: 50%; right: 110%; transform: translateY(-50%); background-color: hsla(0, 0%, 0%, 0.8); color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px; opacity: 0; transition: opacity 0.5s;">
        <!-- Tooltip text will be dynamically set -->
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
    const button = document.getElementById('language-selector-button');
    const dropdown = document.getElementById('language-dropdown');
    const dropdownList = dropdown.querySelector('ul') || dropdown;
    const tooltip = document.getElementById('language-tooltip');

    const settingsEndpoint = '/extensions/translations/admin/languages/settings';

    const normalizeApiBaseUrl = (url) => {
        if (!url) return '';
        return url.trim().replace(/\/+$/, '');
    };

    const fetchLanguageSettings = async () => {
        try {
            const response = await fetch(settingsEndpoint, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Settings request failed with status ${response.status}`);
            }

            const data = await response.json();
            const settings = {
                enabledLanguages: data.enabledLanguages || [],
                defaultLanguage: data.defaultLanguage || 'en',
                apiUrl: normalizeApiBaseUrl(data.apiUrl || data.defaultApiUrl || 'https://api.euphoriadevelopment.uk/translations'),
            };

            // Let translations.js reuse this without another round-trip.
            window.TRANSLATIONS_API_URL = settings.apiUrl;

            return settings;
        } catch (error) {
            console.error('Failed to fetch language settings:', error);
            const settings = {
                enabledLanguages: [],
                defaultLanguage: 'en',
                apiUrl: 'https://api.euphoriadevelopment.uk/translations',
            };
            window.TRANSLATIONS_API_URL = settings.apiUrl;
            return settings;
        }
    };

    // Fetch available translations from the API
    const fetchAvailableTranslations = async (apiBaseUrl) => {
        try {
            const response = await fetch(`${apiBaseUrl}/`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const data = await response.json();
            if (data.success) {
                return data.languages;
            } else {
                console.error('Failed to fetch available translations:', data.error);
                return [];
            }
        } catch (error) {
            console.error('Error fetching available translations:', error);
            return [];
        }
    };

    const populateDropdown = async () => {
        const { enabledLanguages, defaultLanguage, apiUrl } = await fetchLanguageSettings();
        const languages = await fetchAvailableTranslations(apiUrl);
    
        // If no enabled languages, default to English and hide the container
        if (enabledLanguages.length === 0) {
            document.getElementById('language-selector-container').style.display = 'none';
            localStorage.setItem('selectedLanguage', 'en'); // Default to English
            return;
        }
    
        dropdownList.innerHTML = ''; // Clear existing items
    
        // Ensure English ("en") is always included in the dropdown
        const englishOption = {
            code: 'en',
            name: 'English',
        };
    
        // Add English to the dropdown first
        const englishListItem = document.createElement('li');
        englishListItem.style.padding = '5px 10px';
        englishListItem.style.cursor = 'pointer';
        englishListItem.style.listStyle = 'none';
        englishListItem.setAttribute('data-lang', englishOption.code);
        englishListItem.textContent = englishOption.name;
    
        // Highlight the default language if it's English
        if (defaultLanguage === 'en') {
            englishListItem.style.fontWeight = 'bold';
        }
    
        dropdownList.appendChild(englishListItem);
    
        // Add click event for English
        englishListItem.addEventListener('click', () => {
            localStorage.setItem('selectedLanguage', 'en'); // Save English as the selected language
            location.reload(); // Reload the page to apply the language change
        });
    
        // Add all other enabled languages to the dropdown
        languages.forEach(lang => {
            if (lang.code === 'en' || !enabledLanguages.includes(lang.code)) return; // Skip English (already added) and disabled languages
    
            const listItem = document.createElement('li');
            listItem.style.padding = '5px 10px';
            listItem.style.cursor = 'pointer';
            listItem.style.listStyle = 'none';
            listItem.setAttribute('data-lang', lang.code);
            listItem.textContent = lang.name;
    
            // Highlight the default language
            if (lang.code === defaultLanguage) {
                listItem.style.fontWeight = 'bold'; // Highlight the default language
            }
    
            dropdownList.appendChild(listItem);
    
            // Add click event to each language item
            listItem.addEventListener('click', () => {
                localStorage.setItem('selectedLanguage', lang.code); // Save selected language
                location.reload(); // Reload the page to apply the language change
            });
        });
    
        // Set the default language in localStorage if no language is selected
        const selectedLanguage = localStorage.getItem('selectedLanguage');
        if (!selectedLanguage) {
            localStorage.setItem('selectedLanguage', defaultLanguage);
        }
    };

    // Show tooltip with current language
    const showTooltip = () => {
        const currentLanguage = localStorage.getItem('selectedLanguage') || 'en';
        const languageText = Array.from(dropdown.querySelectorAll('li')).find(item => item.getAttribute('data-lang') === currentLanguage)?.textContent || 'English';
        tooltip.textContent = `${languageText}`;
        tooltip.style.display = 'block';
        tooltip.style.opacity = '1';

        // Fade out after 5 seconds
        setTimeout(() => {
            tooltip.style.opacity = '0';
            setTimeout(() => {
                tooltip.style.display = 'none';
            }, 500); // Wait for fade-out transition
        }, 5000);
    };

    // Toggle dropdown visibility
    button.addEventListener('click', () => {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (event) => {
        if (!button.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Populate the dropdown and show tooltip on page load
    await populateDropdown();
    showTooltip();
});
</script>
