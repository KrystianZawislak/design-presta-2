<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/DesignMenuModeItem.php';
require_once __DIR__ . '/classes/DesignMenuMode.php';

class DesignMenu extends Module
{
    const ENABLED = 'DESIGNMENU_ENABLED';
    const COOKIE_KEY = 'designmenu_mode';

    protected $activeModes;
    protected $mainMenuItems;

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
        $this->description = $this->trans('Adds a mode switcher to the header and picks which main menu entries each mode shows.', [], 'Modules.Designmenu.Admin');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->registerHook('displayDesignMenuModes')
            && $this->registerHook('actionFrontControllerInitAfter')
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
        $key = DesignMenuMode::$definition['primary'];

        $queries = [
            'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                `' . $key . '` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `position` int(10) unsigned NOT NULL DEFAULT 0,
                `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
                PRIMARY KEY (`' . $key . '`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $table . '_lang` (
                `' . $key . '` int(10) unsigned NOT NULL,
                `id_lang` int(10) unsigned NOT NULL,
                `label` varchar(64) NOT NULL,
                PRIMARY KEY (`' . $key . '`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $table . '_shop` (
                `' . $key . '` int(10) unsigned NOT NULL,
                `id_shop` int(10) unsigned NOT NULL,
                PRIMARY KEY (`' . $key . '`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . DesignMenuModeItem::TABLE . '` (
                `id_designmenu_mode` int(10) unsigned NOT NULL,
                `id_shop` int(10) unsigned NOT NULL,
                `page_identifier` varchar(191) NOT NULL,
                PRIMARY KEY (`id_designmenu_mode`, `id_shop`, `page_identifier`)
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

        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . DesignMenuModeItem::TABLE . '`,
             `' . $table . '_shop`, `' . $table . '_lang`, `' . $table . '`'
        );
    }

    public function hookActionFrontControllerInitAfter()
    {
        $identifiers = DesignMenuModeItem::getForMode($this->getSelectedModeId(), (int) $this->context->shop->id);

        if (!$identifiers) {
            return;
        }

        $tree = $this->getMainMenuTree();

        if (!$tree) {
            return;
        }

        $tree['children'] = $this->filterNodes($tree['children'], array_fill_keys($identifiers, true));

        $this->context->smarty->assign('designmenu_menu', $tree);
    }

    protected function filterNodes(array $nodes, array $allowed): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            if ($node['page_identifier'] && !isset($allowed[$node['page_identifier']])) {
                continue;
            }

            $node['children'] = $this->filterNodes($node['children'], $allowed);
            $kept[] = $node;
        }

        return $kept;
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

    protected function getMainMenuTree(): array
    {
        $mainMenu = Module::getInstanceByName('ps_mainmenu');

        if (!$mainMenu || !$mainMenu->active) {
            return [];
        }

        if (!Validate::isLoadedObject($this->context->customer)) {
            $this->context->customer = new Customer();
        }

        return $mainMenu->getWidgetVariables('displayTop', []);
    }

    public function getMainMenuItems(): array
    {
        if ($this->mainMenuItems !== null) {
            return $this->mainMenuItems;
        }

        $this->mainMenuItems = [];
        $tree = $this->getMainMenuTree();

        if ($tree) {
            $this->flattenNodes($tree['children'], 0);
        }

        return $this->mainMenuItems;
    }

    protected function flattenNodes(array $nodes, int $depth): void
    {
        foreach ($nodes as $node) {
            if (!$node['page_identifier']) {
                continue;
            }

            if (!isset($this->mainMenuItems[$node['page_identifier']])) {
                $this->mainMenuItems[$node['page_identifier']] = [
                    'page_identifier' => $node['page_identifier'],
                    'label' => $node['label'],
                    'depth' => $depth,
                ];
            }

            $this->flattenNodes($node['children'], $depth + 1);
        }
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

        if (Tools::isSubmit('submitDesignMenuNeutral')) {
            DesignMenuModeItem::setForMode(0, (int) $this->context->shop->id, $this->submittedItems());
            $output .= $this->displayConfirmation($this->trans('Neutral menu saved.', [], 'Modules.Designmenu.Admin'));
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

        return $output . $this->renderSettingsForm() . $this->renderNeutralForm() . $this->renderModeList();
    }

    protected function moduleUrl(array $params = []): string
    {
        return $this->context->link->getAdminLink('AdminModules', true, [], array_merge([
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name,
        ], $params));
    }

    protected function helperToken(): string
    {
        return Tools::getAdminTokenLite('AdminModules');
    }

    protected function helperIndex(): string
    {
        return AdminController::$currentIndex . '&configure=' . $this->name;
    }

    protected function currentMode(): DesignMenuMode
    {
        return new DesignMenuMode((int) Tools::getValue(DesignMenuMode::$definition['primary']));
    }

    protected function submittedItems(): array
    {
        $identifiers = [];

        foreach ($this->getMainMenuItems() as $item) {
            if (Tools::getValue('items_' . $this->itemInputName($item['page_identifier']))) {
                $identifiers[] = $item['page_identifier'];
            }
        }

        return $identifiers;
    }

    protected function itemInputName(string $identifier): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $identifier);
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

        DesignMenuModeItem::setForMode((int) $mode->id, (int) $this->context->shop->id, $this->submittedItems());

        return [];
    }

    protected function hydrateMode(DesignMenuMode $mode): array
    {
        $errors = [];
        $idDefaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $labels = [];

        $mode->active = (bool) Tools::getValue('active');
        $mode->position = Tools::getIsset('position') ? (int) Tools::getValue('position') : DesignMenuMode::getNextPosition();

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $labels[$idLang] = trim((string) Tools::getValue('label_' . $idLang));
        }

        if ($labels[$idDefaultLang] === '') {
            $errors[] = $this->trans('The label is required in the default language.', [], 'Modules.Designmenu.Admin');
        }

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $mode->label[$idLang] = $labels[$idLang] !== '' ? $labels[$idLang] : $labels[$idDefaultLang];

            if ($mode->label[$idLang] !== '' && !Validate::isGenericName($mode->label[$idLang])) {
                $errors[] = $this->trans('The label contains characters that are not allowed (%s).', [$language['iso_code']], 'Modules.Designmenu.Admin');
            }
        }

        return $errors;
    }

    protected function itemsInput(): array
    {
        $items = $this->getMainMenuItems();

        if (!$items) {
            return [
                [
                    'type' => 'free',
                    'label' => $this->trans('Menu entries', [], 'Modules.Designmenu.Admin'),
                    'name' => 'items_empty',
                    'desc' => $this->trans('The Main menu module has no entries yet. Add them in its own configuration first.', [], 'Modules.Designmenu.Admin'),
                ],
            ];
        }

        $values = [];
        foreach ($items as $item) {
            $values[] = [
                'id' => $this->itemInputName($item['page_identifier']),
                'name' => str_repeat('— ', $item['depth']) . $item['label'],
                'val' => 1,
            ];
        }

        return [
            [
                'type' => 'checkbox',
                'label' => $this->trans('Menu entries', [], 'Modules.Designmenu.Admin'),
                'name' => 'items',
                'hint' => $this->trans('Entries come from the Main menu module, at every level. Tick the ones this mode shows; tick none to show all of them. Hiding an entry also hides everything under it.', [], 'Modules.Designmenu.Admin'),
                'values' => [
                    'query' => $values,
                    'id' => 'id',
                    'name' => 'name',
                ],
            ],
        ];
    }

    protected function itemsFieldsValues(int $idMode): array
    {
        $selected = array_fill_keys(DesignMenuModeItem::getForMode($idMode, (int) $this->context->shop->id), true);
        $values = [];

        foreach ($this->getMainMenuItems() as $item) {
            $values['items_' . $this->itemInputName($item['page_identifier'])] = isset($selected[$item['page_identifier']]);
        }

        return $values;
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
        $helper->token = $this->helperToken();
        $helper->currentIndex = $this->helperIndex();
        $helper->submit_action = 'submitDesignMenuSettings';
        $helper->fields_value = [self::ENABLED => (int) Configuration::get(self::ENABLED)];

        return $helper->generateForm([$fields_form]);
    }

    protected function renderNeutralForm(): string
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Neutral menu', [], 'Modules.Designmenu.Admin'),
                    'icon' => 'icon-list',
                ],
                'input' => $this->itemsInput(),
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'name' => 'submitDesignMenuNeutral',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = $this->helperToken();
        $helper->currentIndex = $this->helperIndex();
        $helper->submit_action = 'submitDesignMenuNeutral';
        $helper->fields_value = $this->itemsFieldsValues(0);

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
                'input' => array_merge(
                    [
                        ['type' => 'hidden', 'name' => DesignMenuMode::$definition['primary']],
                        [
                            'type' => 'text',
                            'lang' => true,
                            'label' => $this->trans('Label', [], 'Modules.Designmenu.Admin'),
                            'name' => 'label',
                            'required' => true,
                            'hint' => $this->trans('Languages left empty reuse the default language.', [], 'Modules.Designmenu.Admin'),
                        ],
                    ],
                    $this->itemsInput(),
                    [
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
                    ]
                ),
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

        $values = [
            DesignMenuMode::$definition['primary'] => (int) $mode->id,
            'active' => (int) $mode->active,
            'position' => $mode->id ? (int) $mode->position : DesignMenuMode::getNextPosition(),
        ];

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $values['label'][$idLang] = is_array($mode->label) ? ($mode->label[$idLang] ?? '') : '';
        }

        $values = array_merge($values, $this->itemsFieldsValues((int) $mode->id));

        $languages = $this->context->controller->getLanguages();

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = $this->helperToken();
        $helper->currentIndex = $this->helperIndex();
        $helper->submit_action = 'submitDesignMenuMode';
        $helper->languages = $languages;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->tpl_vars = [
            'fields_value' => $values,
            'languages' => $languages,
            'id_language' => (int) $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    protected function renderModeList(): string
    {
        $idShop = (int) $this->context->shop->id;
        $modes = DesignMenuMode::getModes((int) $this->context->language->id, $idShop, false);
        $labels = [];

        foreach ($this->getMainMenuItems() as $item) {
            $labels[$item['page_identifier']] = $item['label'];
        }

        foreach ($modes as &$mode) {
            $identifiers = DesignMenuModeItem::getForMode((int) $mode[DesignMenuMode::$definition['primary']], $idShop);
            $names = [];

            foreach ($identifiers as $identifier) {
                $names[] = $labels[$identifier] ?? $identifier;
            }

            if (!$names) {
                $mode['items'] = $this->trans('All entries', [], 'Modules.Designmenu.Admin');
            } elseif (count($names) > 4) {
                $mode['items'] = implode(', ', array_slice($names, 0, 4))
                    . ' ' . $this->trans('and %d more', [count($names) - 4], 'Modules.Designmenu.Admin');
            } else {
                $mode['items'] = implode(', ', $names);
            }
        }
        unset($mode);

        $fields_list = [
            DesignMenuMode::$definition['primary'] => [
                'title' => $this->trans('ID', [], 'Admin.Global'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'search' => false,
                'orderby' => false,
            ],
            'label' => [
                'title' => $this->trans('Label', [], 'Modules.Designmenu.Admin'),
                'search' => false,
                'orderby' => false,
            ],
            'items' => [
                'title' => $this->trans('Menu entries', [], 'Modules.Designmenu.Admin'),
                'search' => false,
                'orderby' => false,
            ],
            'position' => [
                'title' => $this->trans('Position', [], 'Modules.Designmenu.Admin'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'search' => false,
                'orderby' => false,
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
        $helper->token = $this->helperToken();
        $helper->currentIndex = $this->helperIndex();

        return $helper->generateList($modes, $fields_list);
    }
}
