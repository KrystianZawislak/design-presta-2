<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/DesignMenuMode.php';

class DesignMenu extends Module
{
    const ENABLED = 'DESIGNMENU_ENABLED';
    const COOKIE_KEY = 'designmenu_mode';

    protected $activeModes;

    public function __construct()
    {
        $this->name = 'designmenu';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Design Presta';
        $this->bootstrap = true;
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->trans('Design Menu', [], 'Modules.Designmenu.Admin');
        $this->description = $this->trans('Header mode switcher: defines the modes shown in the header and where each of them leads.', [], 'Modules.Designmenu.Admin');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        Shop::addTableAssociation(DesignMenuMode::$definition['table'], ['type' => 'shop']);
    }

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->registerHook('displayDesignMenuModes')
            && Configuration::updateValue(self::ENABLED, 1);
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallDb()
            && Configuration::deleteByName(self::ENABLED);
    }

    protected function installDb(): bool
    {
        $table = _DB_PREFIX_ . DesignMenuMode::$definition['table'];
        $primary = DesignMenuMode::$definition['primary'];

        $queries = [
            'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                `' . $primary . '` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `target_type` varchar(16) NOT NULL,
                `id_category` int(10) unsigned NOT NULL DEFAULT 0,
                `id_cms` int(10) unsigned NOT NULL DEFAULT 0,
                `position` int(10) unsigned NOT NULL DEFAULT 0,
                `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
                PRIMARY KEY (`' . $primary . '`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $table . '_lang` (
                `' . $primary . '` int(10) unsigned NOT NULL,
                `id_lang` int(10) unsigned NOT NULL,
                `label` varchar(64) NOT NULL,
                `custom_url` varchar(255) NOT NULL DEFAULT "",
                PRIMARY KEY (`' . $primary . '`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $table . '_shop` (
                `' . $primary . '` int(10) unsigned NOT NULL,
                `id_shop` int(10) unsigned NOT NULL,
                PRIMARY KEY (`' . $primary . '`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
        ];

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    protected function uninstallDb(): bool
    {
        $table = _DB_PREFIX_ . DesignMenuMode::$definition['table'];

        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . $table . '_shop`, `' . $table . '_lang`, `' . $table . '`');
    }

    public function hookDisplayDesignMenuModes()
    {
        if (!Configuration::get(self::ENABLED)) {
            return '';
        }

        $modes = $this->getFrontModes();

        if (!$modes) {
            return '';
        }

        $this->context->smarty->assign([
            'designmenu_modes' => $modes,
            'designmenu_select_url' => $this->context->link->getModuleLink($this->name, 'select'),
            'designmenu_back' => $_SERVER['REQUEST_URI'],
        ]);

        return $this->fetch('module:designmenu/views/templates/hook/modes.tpl');
    }

    public function selectMode($idMode): bool
    {
        $idMode = (int) $idMode;

        if (!$idMode) {
            unset($this->context->cookie->{self::COOKIE_KEY});
            $this->context->cookie->write();

            return true;
        }

        if (!$this->isSelectable($idMode)) {
            return false;
        }

        $this->context->cookie->{self::COOKIE_KEY} = $idMode;
        $this->context->cookie->write();

        return true;
    }

    public function getSelectedModeId(): int
    {
        $idMode = (int) $this->context->cookie->{self::COOKIE_KEY};

        return $idMode && $this->isSelectable($idMode) ? $idMode : 0;
    }

    protected function isSelectable(int $idMode): bool
    {
        foreach ($this->getActiveModes() as $mode) {
            if ((int) $mode[DesignMenuMode::$definition['primary']] === $idMode) {
                return true;
            }
        }

        return false;
    }

    protected function getActiveModes(): array
    {
        if ($this->activeModes === null) {
            $this->activeModes = DesignMenuMode::getModes(
                (int) $this->context->language->id,
                (int) $this->context->shop->id,
                true
            );
        }

        return $this->activeModes;
    }

    protected function getFrontModes(): array
    {
        $selectedId = $this->getSelectedModeId();
        $modes = [];

        foreach ($this->getActiveModes() as $mode) {
            $idMode = (int) $mode[DesignMenuMode::$definition['primary']];

            $modes[] = [
                'id' => $idMode,
                'label' => $mode['label'],
                'active' => $idMode === $selectedId,
            ];
        }

        return $modes;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitDesignMenuSettings')) {
            Configuration::updateValue(self::ENABLED, (int) Tools::getValue(self::ENABLED));
            $output .= $this->displayConfirmation($this->trans('Settings saved.', [], 'Modules.Designmenu.Admin'));
        }

        if (Tools::isSubmit('statusdesignmenu_mode')) {
            $output .= $this->toggleMode();
        }

        if (Tools::isSubmit('deletedesignmenu_mode')) {
            $output .= $this->deleteMode();
        }

        if (Tools::isSubmit('submitDesignMenuMode')) {
            $errors = $this->saveMode();

            if ($errors) {
                return $this->displayError(implode('<br>', $errors)) . $this->renderModeForm();
            }

            $output .= $this->displayConfirmation($this->trans('Mode saved.', [], 'Modules.Designmenu.Admin'));
        }

        if (Tools::isSubmit('adddesignmenu_mode') || Tools::isSubmit('updatedesignmenu_mode')) {
            return $output . $this->renderModeForm();
        }

        return $output . $this->renderSettingsForm() . $this->renderModeList();
    }

    protected function moduleUrl(array $params = []): string
    {
        return $this->context->link->getAdminLink('AdminModules', true, [], array_merge([
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name,
        ], $params));
    }

    protected function currentMode(): DesignMenuMode
    {
        return new DesignMenuMode((int) Tools::getValue(DesignMenuMode::$definition['primary']));
    }

    protected function toggleMode(): string
    {
        $mode = $this->currentMode();

        if (!Validate::isLoadedObject($mode) || !$mode->toggleStatus()) {
            return $this->displayError($this->trans('This mode could not be updated.', [], 'Modules.Designmenu.Admin'));
        }

        return $this->displayConfirmation($this->trans('Mode updated.', [], 'Modules.Designmenu.Admin'));
    }

    protected function deleteMode(): string
    {
        $mode = $this->currentMode();

        if (!Validate::isLoadedObject($mode) || !$mode->delete()) {
            return $this->displayError($this->trans('This mode could not be deleted.', [], 'Modules.Designmenu.Admin'));
        }

        return $this->displayConfirmation($this->trans('Mode deleted.', [], 'Modules.Designmenu.Admin'));
    }

    protected function saveMode(): array
    {
        $mode = $this->currentMode();
        $errors = $this->hydrateMode($mode);

        if ($errors) {
            return $errors;
        }

        if (!$mode->save()) {
            return [$this->trans('This mode could not be saved.', [], 'Modules.Designmenu.Admin')];
        }

        return [];
    }

    protected function hydrateMode(DesignMenuMode $mode): array
    {
        $errors = [];
        $languages = Language::getLanguages(false);
        $idDefaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $mode->target_type = (string) Tools::getValue('target_type');
        $mode->id_category = (int) Tools::getValue('id_category');
        $mode->id_cms = (int) Tools::getValue('id_cms');
        $mode->active = (bool) Tools::getValue('active');
        $mode->position = Tools::getIsset('position') ? (int) Tools::getValue('position') : DesignMenuMode::getNextPosition();

        $labels = [];
        $urls = [];
        foreach ($languages as $language) {
            $idLang = (int) $language['id_lang'];
            $labels[$idLang] = trim((string) Tools::getValue('label_' . $idLang));
            $urls[$idLang] = trim((string) Tools::getValue('custom_url_' . $idLang));
        }

        if ($labels[$idDefaultLang] === '') {
            $errors[] = $this->trans('The label is required in the default language.', [], 'Modules.Designmenu.Admin');
        }

        foreach ($languages as $language) {
            $idLang = (int) $language['id_lang'];
            $mode->label[$idLang] = $labels[$idLang] !== '' ? $labels[$idLang] : $labels[$idDefaultLang];
            $mode->custom_url[$idLang] = $urls[$idLang] !== '' ? $urls[$idLang] : $urls[$idDefaultLang];

            if ($mode->label[$idLang] !== '' && !Validate::isGenericName($mode->label[$idLang])) {
                $errors[] = $this->trans('The label contains characters that are not allowed (%s).', [$language['iso_code']], 'Modules.Designmenu.Admin');
            }
        }

        if (!in_array($mode->target_type, DesignMenuMode::getTargetTypes(), true)) {
            $errors[] = $this->trans('Choose what this mode links to.', [], 'Modules.Designmenu.Admin');

            return $errors;
        }

        if ($mode->target_type === DesignMenuMode::TARGET_CATEGORY && !Validate::isLoadedObject(new Category($mode->id_category))) {
            $errors[] = $this->trans('Choose a category for this mode.', [], 'Modules.Designmenu.Admin');
        }

        if ($mode->target_type === DesignMenuMode::TARGET_CMS && !Validate::isLoadedObject(new CMS($mode->id_cms))) {
            $errors[] = $this->trans('Choose a content page for this mode.', [], 'Modules.Designmenu.Admin');
        }

        if ($mode->target_type === DesignMenuMode::TARGET_URL) {
            if ($mode->custom_url[$idDefaultLang] === '') {
                $errors[] = $this->trans('Enter the address in the default language.', [], 'Modules.Designmenu.Admin');
            } else {
                foreach ($languages as $language) {
                    $idLang = (int) $language['id_lang'];
                    if (!Validate::isUrl($mode->custom_url[$idLang])) {
                        $errors[] = $this->trans('The address is not a valid URL (%s).', [$language['iso_code']], 'Modules.Designmenu.Admin');
                    }
                }
            }
        }

        return $errors;
    }

    protected function renderSettingsForm(): string
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Designmenu.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Show the mode switcher', [], 'Modules.Designmenu.Admin'),
                        'name' => self::ENABLED,
                        'hint' => $this->trans('Turn this off to hide the whole switcher row without deleting the modes.', [], 'Modules.Designmenu.Admin'),
                        'values' => [
                            ['id' => 'enabled_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                            ['id' => 'enabled_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'name' => 'submitDesignMenuSettings',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitDesignMenuSettings';
        $helper->fields_value = [self::ENABLED => (int) Configuration::get(self::ENABLED)];

        return $helper->generateForm([$fields_form]);
    }

    protected function renderModeForm(): string
    {
        $mode = $this->currentMode();

        if (Tools::isSubmit('submitDesignMenuMode')) {
            $this->hydrateMode($mode);
        }

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $mode->id
                        ? $this->trans('Edit mode', [], 'Modules.Designmenu.Admin')
                        : $this->trans('New mode', [], 'Modules.Designmenu.Admin'),
                    'icon' => 'icon-list-ul',
                ],
                'input' => [
                    [
                        'type' => 'hidden',
                        'name' => DesignMenuMode::$definition['primary'],
                    ],
                    [
                        'type' => 'text',
                        'lang' => true,
                        'label' => $this->trans('Label', [], 'Modules.Designmenu.Admin'),
                        'name' => 'label',
                        'required' => true,
                        'hint' => $this->trans('Shown in the header switcher. Languages left empty reuse the default language.', [], 'Modules.Designmenu.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Links to', [], 'Modules.Designmenu.Admin'),
                        'name' => 'target_type',
                        'hint' => $this->trans('Only the field matching this choice is used; the other two are ignored.', [], 'Modules.Designmenu.Admin'),
                        'options' => [
                            'query' => [
                                ['id' => DesignMenuMode::TARGET_CATEGORY, 'name' => $this->trans('Category', [], 'Modules.Designmenu.Admin')],
                                ['id' => DesignMenuMode::TARGET_CMS, 'name' => $this->trans('Content page', [], 'Modules.Designmenu.Admin')],
                                ['id' => DesignMenuMode::TARGET_URL, 'name' => $this->trans('Custom address', [], 'Modules.Designmenu.Admin')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Category', [], 'Modules.Designmenu.Admin'),
                        'name' => 'id_category',
                        'options' => [
                            'query' => $this->getCategoryOptions(),
                            'id' => 'id_category',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Content page', [], 'Modules.Designmenu.Admin'),
                        'name' => 'id_cms',
                        'options' => [
                            'query' => $this->getCmsOptions(),
                            'id' => 'id_cms',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'lang' => true,
                        'label' => $this->trans('Custom address', [], 'Modules.Designmenu.Admin'),
                        'name' => 'custom_url',
                        'hint' => $this->trans('Full address or one starting with a slash. Languages left empty reuse the default language.', [], 'Modules.Designmenu.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Position', [], 'Modules.Designmenu.Admin'),
                        'name' => 'position',
                        'class' => 'fixed-width-sm',
                        'hint' => $this->trans('Modes are shown from the lowest number to the highest.', [], 'Modules.Designmenu.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Displayed', [], 'Modules.Designmenu.Admin'),
                        'name' => 'active',
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'name' => 'submitDesignMenuMode',
                ],
                'buttons' => [
                    [
                        'href' => $this->moduleUrl(),
                        'title' => $this->trans('Back to list', [], 'Admin.Actions'),
                        'icon' => 'process-icon-back',
                    ],
                ],
            ],
        ];

        $languages = $this->context->controller->getLanguages();

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitDesignMenuMode';
        $helper->languages = $languages;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->tpl_vars = [
            'fields_value' => $this->getModeFieldsValues($mode),
            'languages' => $languages,
            'id_language' => (int) $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    protected function getModeFieldsValues(DesignMenuMode $mode): array
    {
        $values = [
            DesignMenuMode::$definition['primary'] => (int) $mode->id,
            'target_type' => $mode->target_type,
            'id_category' => (int) $mode->id_category,
            'id_cms' => (int) $mode->id_cms,
            'position' => $mode->id ? (int) $mode->position : DesignMenuMode::getNextPosition(),
            'active' => (int) $mode->active,
        ];

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $values['label'][$idLang] = is_array($mode->label) ? ($mode->label[$idLang] ?? '') : '';
            $values['custom_url'][$idLang] = is_array($mode->custom_url) ? ($mode->custom_url[$idLang] ?? '') : '';
        }

        return $values;
    }

    protected function getCategoryOptions(): array
    {
        $options = [['id_category' => 0, 'name' => $this->trans('-- none --', [], 'Modules.Designmenu.Admin')]];

        foreach (Category::getSimpleCategories((int) $this->context->language->id) as $category) {
            $options[] = ['id_category' => (int) $category['id_category'], 'name' => $category['name']];
        }

        return $options;
    }

    protected function getCmsOptions(): array
    {
        $options = [['id_cms' => 0, 'name' => $this->trans('-- none --', [], 'Modules.Designmenu.Admin')]];

        foreach (CMS::getCMSPages((int) $this->context->language->id, null, true) as $page) {
            $options[] = ['id_cms' => (int) $page['id_cms'], 'name' => $page['meta_title']];
        }

        return $options;
    }

    protected function renderModeList(): string
    {
        $idLang = (int) $this->context->language->id;
        $modes = DesignMenuMode::getModes($idLang, (int) $this->context->shop->id, false);

        foreach ($modes as &$mode) {
            $mode['target'] = $this->describeTarget($mode, $idLang);
        }
        unset($mode);

        $fields_list = [
            DesignMenuMode::$definition['primary'] => [
                'title' => $this->trans('ID', [], 'Admin.Global'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'label' => [
                'title' => $this->trans('Label', [], 'Modules.Designmenu.Admin'),
            ],
            'target' => [
                'title' => $this->trans('Links to', [], 'Modules.Designmenu.Admin'),
                'search' => false,
                'orderby' => false,
            ],
            'position' => [
                'title' => $this->trans('Position', [], 'Modules.Designmenu.Admin'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'active' => [
                'title' => $this->trans('Displayed', [], 'Modules.Designmenu.Admin'),
                'align' => 'center',
                'class' => 'fixed-width-sm',
                'active' => 'status',
                'type' => 'bool',
                'search' => false,
                'orderby' => false,
            ],
        ];

        $helper = new HelperList();
        $helper->module = $this;
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->no_link = true;
        $helper->identifier = DesignMenuMode::$definition['primary'];
        $helper->table = DesignMenuMode::$definition['table'];
        $helper->title = $this->trans('Modes', [], 'Modules.Designmenu.Admin');
        $helper->actions = ['edit', 'delete'];
        $helper->show_toolbar = true;
        $helper->toolbar_btn = [
            'new' => [
                'href' => $this->moduleUrl(['adddesignmenu_mode' => 1]),
                'desc' => $this->trans('Add a mode', [], 'Modules.Designmenu.Admin'),
            ],
        ];
        $helper->listTotal = count($modes);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        return $helper->generateList($modes, $fields_list);
    }

    protected function describeTarget(array $mode, int $idLang): string
    {
        if ($mode['target_type'] === DesignMenuMode::TARGET_CATEGORY) {
            $category = new Category((int) $mode['id_category'], $idLang);

            return Validate::isLoadedObject($category)
                ? $category->name
                : $this->trans('Missing category', [], 'Modules.Designmenu.Admin');
        }

        if ($mode['target_type'] === DesignMenuMode::TARGET_CMS) {
            $page = new CMS((int) $mode['id_cms'], $idLang);

            return Validate::isLoadedObject($page)
                ? $page->meta_title
                : $this->trans('Missing content page', [], 'Modules.Designmenu.Admin');
        }

        return (string) $mode['custom_url'];
    }
}
