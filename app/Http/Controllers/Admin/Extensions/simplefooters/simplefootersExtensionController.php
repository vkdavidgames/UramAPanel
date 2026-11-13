<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\simplefooters;

use Illuminate\View\View;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\SoftwareVersionService;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;

class simplefootersExtensionController extends Controller {

  /**
   * simplefootersExtensionController constructor.
   */
  public function __construct(
    private BlueprintExtensionLibrary $blueprint,
    private SoftwareVersionService $version,
    private ViewFactory $view,
    private SettingsRepositoryInterface $settings,
    private ConfigRepository $config
  ){}

  /**
   * Return the extension index view.
   */
  public function index(): View
  {
    $db_status = $this->blueprint->dbGet('simplefooters', 'status');
    $db_text = $this->blueprint->dbGet('simplefooters', 'text');
    $db_fontsize = $this->blueprint->dbGet('simplefooters', 'fontsize');
    $db_fontcolor = $this->blueprint->dbGet('simplefooters', 'fontcolor');

    if($db_status == "") {
      $defaultDb_status = "true";
      $this->blueprint->dbSet('simplefooters', 'status', "$defaultDb_status");
      $db_status = $this->blueprint->dbGet('simplefooters', 'status');
    }

    if($db_text == "") {
      $defaultText = "Simple Footers";
      $this->blueprint->dbSet('simplefooters', 'text', "$defaultText");
      $text = $this->blueprint->dbGet('simplefooters', 'text');
    }

    if($db_fontsize == ""){
      $defaultFontsize = "12px";
      $this->blueprint->dbSet('simplefooters', 'fontsize', "$defaultFontsize");
      $text = $this->blueprint->dbGet('simplefooters', 'fontsize');
    }

    if($db_fontcolor == ""){
      $defaultFontcolor = "#606D7B";
      $this->blueprint->dbSet('simplefooters', 'fontcolor', "$defaultFontcolor");
      $text = $this->blueprint->dbGet('simplefooters', 'fontcolor');
    }
    

    return $this->view->make('admin.extensions.simplefooters.index', [
      'blueprint' => $this->blueprint,

      'db_status' => $db_status,
      'text' => $db_text,
      'fontsize' => $db_fontsize,
      'fontcolor' => $db_fontcolor,
      
      'version' => $this->version,
      'root' => "/admin/extensions/simplefooters"
    ]);
  }

  /**
   * @throws \Pterodactyl\Exceptions\Model\DataValidationException
   * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
   */
  public function update(simplefootersSettingsFormRequest $request): RedirectResponse
  {
    foreach ($request->normalize() as $key => $value) {
      $this->settings->set('simplefooters::' . $key, $value);
    }

    return redirect()->route('admin.extensions.simplefooters.index');
  }
}

class simplefootersSettingsFormRequest extends AdminFormRequest
{
  public function rules(): array
  {
    return [
      'status' => 'string',
      'text' => 'string',
      'fontsize' => 'string',
      'fontcolor' => 'starts_with:#|string',
    ];
  }

  public function attributes(): array
  {
    return [
      'status' => 'simplefooters status toggle',
      'text' => 'simplefooters text',
      'fontsize' => 'simplefooters fontsize',
      'fontcolor' => 'simplefooters fontcolor',
    ];
  }
}