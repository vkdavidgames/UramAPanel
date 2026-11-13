@extends('layouts.admin')
<?php 
    // Define extension information.
    $EXTENSION_ID = "translations";
    $EXTENSION_NAME = stripslashes("Translations");
    $EXTENSION_VERSION = "0.6";
    $EXTENSION_DESCRIPTION = stripslashes("Translations for Pterodactyl using Blueprint!");
    $EXTENSION_ICON = "/assets/extensions/translations/icon.png";
    $EXTENSION_WEBSITE = "https://euphoriadevelopment.uk";
    $EXTENSION_WEBICON = "bi bi-link-45deg";
?>
@include('blueprint.admin.template')

@section('title')
    {{ $EXTENSION_NAME }}
@endsection

@section('content-header')
    @yield('extension.header')
@endsection

@section('content')
    @yield('extension.config')
    @yield('extension.description')<head>
    @section('meta')
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @show
</head>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <strong><i class="fa fa-globe"></i> Translations</strong> by <strong>Euphoria Development</strong>
                </h3>
            </div>
            <div class="box-body">
                <p>
                    <strong>Identifier:</strong> <code>translations</code><br>
                    <strong>Uninstall:</strong> <code>blueprint -remove translations</code><br>
                    <strong>Support:</strong> If any errors occur, use Redprint!<br>
                    <code>bash <(curl -s https://redprint.zip)</code>
                </p>
                <hr>
                <p>
                    <strong>Get Support:</strong> 
                    <a href="https://discord.gg/Cus2zP4pPH" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-discord"></i> Join our Discord
                    </a>
                </p>
                <p>
                    <strong>Contribute:</strong> 
                    <a href="https://github.com/RepGraphics/blueprint-translations" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-github"></i> Contribute to Translations
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <strong><i class="fa fa-language"></i> Manage Languages</strong>
                </h3>
                <p class="box-subtitle">Select the languages you want to enable for your Panel.</p>
            </div>
            <div class="box-body">
                <ul id="language-list" style="list-style: none; padding: 0;">
                    <!-- Languages will be dynamically populated here -->
                </ul>

                <div class="form-group" style="margin-top: 15px;">
                    <label for="default-language">Default Language</label>
                    <select id="default-language" class="form-control">
                        <!-- Populate this dropdown dynamically with available languages -->
                    </select>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label for="custom-api-url">Custom API URL (Optional)</label>
                    <input id="custom-api-url" class="form-control" type="url" placeholder="https://api.euphoriadevelopment.uk/translations">
                    <p class="text-muted" style="margin-top: 5px;">
                        Leave blank to use the default API. Do not include a trailing slash.
                    </p>
                </div>

                <button id="save-language-settings" class="btn btn-primary">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
    const languageList = document.getElementById('language-list');
    const defaultLanguageDropdown = document.getElementById('default-language');
    const customApiUrlInput = document.getElementById('custom-api-url');
    const saveButton = document.getElementById('save-language-settings');

    const settingsEndpoint = '/extensions/translations/admin/languages/settings';

    const normalizeApiBaseUrl = (url) => {
        if (!url) return '';
        return url.trim().replace(/\/+$/, '');
    };

    // Fetch available languages and current settings
    const fetchLanguageSettings = async () => {
        try {
            const response = await fetch(settingsEndpoint, { method: 'GET' });
            if (!response.ok) {
                throw new Error(`Settings request failed with status ${response.status}`);
            }

            const data = await response.json();
            return {
                enabledLanguages: data.enabledLanguages || [],
                defaultLanguage: data.defaultLanguage || 'en', // Default to English if not set
                defaultApiUrl: data.defaultApiUrl || 'https://api.euphoriadevelopment.uk/translations',
                customApiUrl: normalizeApiBaseUrl(data.customApiUrl || ''),
                apiUrl: normalizeApiBaseUrl(data.apiUrl || data.defaultApiUrl || 'https://api.euphoriadevelopment.uk/translations'),
            };
        } catch (err) {
            console.error('Failed to fetch language settings:', err);
            return {
                enabledLanguages: [],
                defaultLanguage: 'en',
                defaultApiUrl: 'https://api.euphoriadevelopment.uk/translations',
                customApiUrl: '',
                apiUrl: 'https://api.euphoriadevelopment.uk/translations',
            };
        }
    };

    const fetchLanguages = async (apiBaseUrl) => {
        const response = await fetch(`${apiBaseUrl}/`, { method: 'GET' });
        const data = await response.json();
        return data.languages || [];
    };

    // Populate the language list and default language dropdown
    const populateLanguageList = async () => {
    const { enabledLanguages, defaultLanguage, customApiUrl, apiUrl } = await fetchLanguageSettings();
    customApiUrlInput.value = customApiUrl || '';

    let languages = [];
    try {
        languages = await fetchLanguages(apiUrl);
    } catch (err) {
        console.error('Failed to fetch languages:', err);
        languages = [];
    }

    // Check if the user has a language set in localStorage
    const userLanguage = localStorage.getItem('selectedLanguage') || defaultLanguage;

    // Populate the language list
    languageList.innerHTML = ''; // Clear existing items
    languages.forEach(lang => {
        const listItem = document.createElement('li');
        listItem.style.padding = '5px 0';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = enabledLanguages.includes(lang.code);
        checkbox.setAttribute('data-lang', lang.code);

        const label = document.createElement('label');
        label.textContent = ` ${lang.name}`;
        label.style.marginLeft = '10px'; // Add a gap between the checkbox and the label

        // Highlight the default language or user-selected language in the list
        if (lang.code === userLanguage) {
            label.style.fontWeight = 'bold'; // Make the default or user-selected language bold
        }

        listItem.appendChild(checkbox);
        listItem.appendChild(label);
        languageList.appendChild(listItem);
    });

    // Populate the default language dropdown
    defaultLanguageDropdown.innerHTML = ''; // Clear existing options
    languages.forEach(lang => {
        const option = document.createElement('option');
        option.value = lang.code;
        option.textContent = lang.name;

        // Highlight the default language in the dropdown
        if (lang.code === userLanguage) {
            option.selected = true; // Mark the default or user-selected language as selected
        }

        defaultLanguageDropdown.appendChild(option);
    });

    // Ensure English ("en") is always an option
    if (!languages.some(lang => lang.code === 'en')) {
        const englishOption = document.createElement('option');
        englishOption.value = 'en';
        englishOption.textContent = 'English';
        if (userLanguage === 'en') {
            englishOption.selected = true;
        }
        defaultLanguageDropdown.appendChild(englishOption);
    }
};

    // Save language settings
    saveButton.addEventListener('click', async () => {
        const checkboxes = languageList.querySelectorAll('input[type="checkbox"]');
        const enabledLanguages = Array.from(checkboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.getAttribute('data-lang'));

        const defaultLanguage = defaultLanguageDropdown.value;
        const customApiUrl = normalizeApiBaseUrl(customApiUrlInput.value || '');

        const csrfToken = document.querySelector('meta[name="_token"]').getAttribute('content'); // Get CSRF token

        const response = await fetch(settingsEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken, // Include CSRF token in the headers
            },
            body: JSON.stringify({ languages: enabledLanguages, defaultLanguage, customApiUrl }),
        });

        const data = await response.json();
        if (data.success) {
            alert('Language settings saved successfully!');
        } else {
            alert(data.message || 'Failed to save language settings.');
        }
    });

    // Initialize the language list and dropdown
    await populateLanguageList();
});
</script>

@endsection
