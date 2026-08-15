<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\translations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Illuminate\Support\Facades\File;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;

class translationsExtensionController extends Controller
{
    private const DEFAULT_API_URL = 'https://api.euphoriadevelopment.uk/translations';

    public function __construct(
        private ViewFactory $view,
        private BlueprintExtensionLibrary $blueprint,
        private ConfigRepository $config,
        private SettingsRepositoryInterface $settings,
    ){}

    public function getLanguageSettings(Request $request)
    {

        $enabledLanguages = $this->blueprint->dbGet("translations", 'enabledLanguages') ?? [];
        $defaultLanguage = $this->blueprint->dbGet('translations', 'default_language', 'en'); // Default to English
        $customApiUrl = (string) ($this->blueprint->dbGet('translations', 'api_url', '') ?? '');
        $customApiUrl = rtrim(trim($customApiUrl), '/');
        $apiUrl = $customApiUrl !== '' ? $customApiUrl : self::DEFAULT_API_URL;

        if (!is_array($enabledLanguages)) {
            $enabledLanguages = json_decode($enabledLanguages, true);
        }

        return response()->json([
            'success' => true,
            'enabledLanguages' => $enabledLanguages,
            'defaultLanguage' => $defaultLanguage,
            // Frontend uses apiUrl (effective). Admin UI can show customApiUrl.
            'defaultApiUrl' => self::DEFAULT_API_URL,
            'customApiUrl' => $customApiUrl,
            'apiUrl' => $apiUrl,
        ]);
    }

    public function saveLanguageSettings(Request $request)
    {
        if (!$request->user() || !$request->user()->root_admin) {
            throw new AccessDeniedHttpException();
        }

        $validator = Validator::make($request->all(), [
            'languages' => 'array',
            'languages.*' => 'string',
            'defaultLanguage' => 'nullable|string|max:5',
            'customApiUrl' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $languages = $request->input('languages', []);
        $defaultLanguage = $request->input('defaultLanguage', 'en'); // Default to English if not provided
        $customApiUrl = trim((string) $request->input('customApiUrl', ''));
        $customApiUrl = rtrim($customApiUrl, '/');

        if ($customApiUrl !== '') {
            if (!filter_var($customApiUrl, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Custom API URL must be a valid URL.',
                ], 422);
            }

            $scheme = strtolower((string) (parse_url($customApiUrl, PHP_URL_SCHEME) ?? ''));
            if (!in_array($scheme, ['http', 'https'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Custom API URL must start with http:// or https://.',
                ], 422);
            }
        }

        $this->blueprint->dbSet("translations", 'enabledLanguages', json_encode($languages));
        $this->blueprint->dbSet('translations', 'default_language', $defaultLanguage);
        // Store blank to indicate "use default".
        $this->blueprint->dbSet('translations', 'api_url', $customApiUrl);

        return response()->json(['success' => true, 'message' => 'Language settings updated successfully.']);
    }

    public function index(Request $request)
    {
        if (!$request->user() || !$request->user()->root_admin) {
            throw new AccessDeniedHttpException();
        }

        // Fetch stored theme settings
        $default_language = $this->blueprint->dbGet('translations', 'default_language', 'en');

        $this->initializeDefaultSettings();

        // Pass settings to the view
        return $this->view->make(
            'admin.extensions.translations.index', [
              'root' => "/admin/extensions/translations",
              'blueprint' => $this->blueprint,
              'default_language' => $default_language,
            ]
          );
    }

    private function initializeDefaultSettings()
    {
    $settings = [
        'default_language' => 'en',
        // Blank means "use default". See DEFAULT_API_URL.
        'api_url' => '',
    ];

    foreach ($settings as $key => $default) {
        $current = $this->blueprint->dbGet("translations", $key);
        if ($current === null || $current === '') {
            $this->blueprint->dbSet("translations", $key, $default);
        }
        }
    }
}


class translationsSettingsFormRequest extends AdminFormRequest
{
  public function rules(): array
  {
    return [
        'default_language' => 'nullable|string',
        'api_url' => 'nullable|string',
    ];
  }

  public function attributes(): array
  {
    return [
        'default_language' => 'Default Language',
        'api_url' => 'Custom API URL',
    ];
  }
  
}
