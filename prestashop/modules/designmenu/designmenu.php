<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/DesignMenuTarget.php';
require_once __DIR__ . '/classes/DesignMenuMode.php';
require_once __DIR__ . '/classes/DesignMenuLink.php';

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
        $this->description = $this->trans('Header mode switcher: defines the modes shown in the header and the links each of them shows.', [], 'Modules.Designmenu.Admin');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        Shop::addTableAssociation(DesignMenuMode::$definition['table'], ['type' => 'shop']);
        Shop::addTableAssociation(DesignMenuLink::$definition['table'], ['type' => 'shop']);
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
        $modeTable = _DB_PREFIX_ . DesignMenuMode::$definition['table'];
        $modeKey = DesignMenuMode::$definition['primary'];
        $linkTable = _DB_PREFIX_ . DesignMenuLink::$definition['table'];
        $linkKey = DesignMenuLink::$definition['primary'];

        $queries = [
            'CREATE TABLE IF NOT EXISTS `' . $modeTable . '` (
                `' . $modeKey . '` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `target_type` varchar(16) NOT NULL,
                `id_category` int(10) unsigned NOT NULL DEFAULT 0,
                `id_cms` int(10) unsigned NOT NULL DEFAULT 0,
                `position` int(10) unsigned NOT NULL DEFAULT 0,
                `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
                PRIMARY KEY (`' . $modeKey . '`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $modeTable . '_lang` (
                `' . $modeKey . '` int(10) unsigned NOT NULL,
                `id_lang` int(10) unsigned NOT NULL,
                `label` varchar(64) NOT NULL,
                `custom_url` varchar(255) NOT NULL DEFAULT "",
                PRIMARY KEY (`' . $modeKey . '`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $modeTable . '_shop` (
                `' . $modeKey . '` int(10) unsigned NOT NULL,
                `id_shop` int(10) unsigned NOT NULL,
                PRIMARY KEY (`' . $modeKey . '`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $linkTable . '` (
                `' . $linkKey . '` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `id_designmenu_mode` int(10) unsigned NOT NULL DEFAULT 0,
                `target_type` varchar(16) NOT NULL,
                `id_category` int(10) unsigned NOT NULL DEFAULT 0,
                `id_cms` int(10) unsigned NOT NULL DEFAULT 0,
                `position` int(10) unsigned NOT NULL DEFAULT 0,
                `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
                PRIMARY KEY (`' . $linkKey . '`),
                KEY `id_designmenu_mode` (`id_designmenu_mode`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $linkTable . '_lang` (
                `' . $linkKey . '` int(10) unsigned NOT NULL,
                `id_lang` int(10) unsigned NOT NULL,
                `label` varchar(64) NOT NULL,
                `custom_url` varchar(255) NOT NULL DEFAULT "",
                PRIMARY KEY (`' . $linkKey . '`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $linkTable . '_shop` (
                `' . $linkKey . '` int(10) unsigned NOT NULL,
                `id_shop` int(10) unsigned NOT NULL,
                PRIMARY KEY (`' . $linkKey . '`, `id_shop`)
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
        $modeTable = _DB_PREFIX_ . DesignMenuMode::$definition['table'];
        $linkTable = _DB_PREFIX_ . DesignMenuLink::$definition['table'];

        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . $linkTable . '_shop`, `' . $linkTable . '_lang`, `' . $linkTable . '`,
             `' . $modeTable . '_shop`, `' . $modeTable . '_lang`, `' . $modeTable . '`'
        );
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

        if (Tools::isSubmit('statusdesignmenu_link')) {
            $output .= $this->toggleLink();
        }

        if (Tools::isSubmit('deletedesignmenu_link')) {
            $output .= $this->deleteLink();
        }

        if (Tools::isSubmit('submitDesignMenuMode')) {
            $errors = $this->saveMode();

            if ($errors) {
                return $this->displayError(implode('<br>', $errors)) . $this->renderModeForm();
            }

            $output .= $this->displayConfirmation($this->trans('Mode saved.', [], 'Modules.Designmenu.Admin'));
        }

        if (Tools::isSubmit('submitDesignMenuLink')) {
            $errors = $this->saveLink();

            if ($errors) {
                return $this->displayError(implode('<br>', $errors)) . $this->renderLinkForm();
            }

            $output .= $this->displayConfirmation($this->trans('Link saved.', [], 'Modules.Designmenu.Admin'));
        }

        if (Tools::isSubmit('adddesignmenu_mode') || Tools::isSubmit('updatedesignmenu_mode')) {
            return $output . $this->renderModeForm();
        }

        if (Tools::isSubmit('adddesignmenu_link') || Tools::isSubmit('updatedesignmenu_link')) {
            return $output . $this->renderLinkForm();
        }

        return $output . $this->renderSettingsForm() . $this->renderModeList() . $this->renderLinkList();
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

    protected function currentLink(): DesignMenuLink
    {
        return new DesignMenuLink((int) Tools::getValue(DesignMenuLink::$definition['primary']));
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

        return $this->displayConfirmation($this->trans('Mode deleted, together with its links.', [], 'Modules.Designmenu.Admin'));
    }

    protected function toggleLink(): string
    {
        $link = $this->currentLink();

        if (!Validate::isLoadedObject($link) || !$link->toggleStatus()) {
            return $this->displayError($this->trans('This link could not be updated.', [], 'Modules.Designmenu.Admin'));
        }

        return $this->displayConfirmation($this->trans('Link updated.', [], 'Modules.Designmenu.Admin'));
    }

    protected function deleteLink(): string
    {
        $link = $this->currentLink();

        if (!Validate::isLoadedObject($link) || !$link->delete()) {
            return $this->displayError($this->trans('This link could not be deleted.', [], 'Modules.Designmenu.Admin'));
        }

        return $this->displayConfirmation($this->trans('Link deleted.', [], 'Modules.Designmenu.Admin'));
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

    protected function saveLink(): array
    {
        $link = $this->currentLink();
        $errors = $this->hydrateLink($link);

        if ($errors) {
            return $errors;
        }

        if (!$link->save()) {
            return [$this->trans('This link could not be saved.', [], 'Modules.Designmenu.Admin')];
        }

        return [];
    }

    protected function hydrateMode(DesignMenuMode $mode): array
    {
        $mode->active = (bool) Tools::getValue('active');
        $mode->position = Tools::getIsset('position') ? (int) Tools::getValue('position') : DesignMenuMode::getNextPosition();

        return array_merge($this->hydrateLabel($mode), $this->hydrateTarget($mode));
    }

    protected function hydrateLink(DesignMenuLink $link): array
    {
        $idMode = (int) Tools::getValue('id_designmenu_mode');

        $link->id_designmenu_mode = $idMode;
        $link->active = (bool) Tools::getValue('active');
        $link->position = Tools::getIsset('position') ? (int) Tools::getValue('position') : DesignMenuLink::getNextPosition($idMode);

        $errors = array_merge($this->hydrateLabel($link), $this->hydrateTarget($link));

        if ($idMode && !Validate::isLoadedObject(new DesignMenuMode($idMode))) {
            $errors[] = $this->trans('Choose a mode for this link.', [], 'Modules.Designmenu.Admin');
        }

        return $errors;
    }

    protected function hydrateLabel(ObjectModel $object): array
    {
        $errors = [];
        $idDefaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $labels = [];

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $labels[$idLang] = trim((string) Tools::getValue('label_' . $idLang));
        }

        if ($labels[$idDefaultLang] === '') {
            $errors[] = $this->trans('The label is required in the default language.', [], 'Modules.Designmenu.Admin');
        }

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $object->label[$idLang] = $labels[$idLang] !== '' ? $labels[$idLang] : $labels[$idDefaultLang];

            if ($object->label[$idLang] !== '' && !Validate::isGenericName($object->label[$idLang])) {
                $errors[] = $this->trans('The label contains characters that are not allowed (%s).', [$language['iso_code']], 'Modules.Designmenu.Admin');
            }
        }

        return $errors;
    }

    protected function hydrateTarget(ObjectModel $object): array
    {
        $errors = [];
        $languages = Language::getLanguages(false);
        $idDefaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $object->target_type = (string) Tools::getValue('target_type');
        $object->id_category = (int) Tools::getValue('id_category');
        $object->id_cms = (int) Tools::getValue('id_cms');

        $urls = [];
        foreach ($languages as $language) {
            $idLang = (int) $language['id_lang'];
            $urls[$idLang] = trim((string) Tools::getValue('custom_url_' . $idLang));
        }

        foreach ($languages as $language) {
            $idLang = (int) $language['id_lang'];
            $object->custom_url[$idLang] = $urls[$idLang] !== '' ? $urls[$idLang] : $urls[$idDefaultLang];
        }

        if (!in_array($object->target_type, DesignMenuTarget::getTypes(), true)) {
            $errors[] = $this->trans('Choose what this links to.', [], 'Modules.Designmenu.Admin');

            return $errors;
        }

        if ($object->target_type === DesignMenuTarget::CATEGORY && !Validate::isLoadedObject(new Category($object->id_category))) {
            $errors[] = $this->trans('Choose a category.', [], 'Modules.Designmenu.Admin');
        }

        if ($object->target_type === DesignMenuTarget::CMS && !Validate::isLoadedObject(new CMS($object->id_cms))) {
            $errors[] = $this->trans('Choose a content page.', [], 'Modules.Designmenu.Admin');
        }

        if ($object->target_type === DesignMenuTarget::URL) {
            if ($object->custom_url[$idDefaultLang] === '') {
                $errors[] = $this->trans('Enter the address in the default language.', [], 'Modules.Designmenu.Admin');
            } else {
                foreach ($languages as $language) {
                    $idLang = (int) $language['id_lang'];

                    if (!Validate::isUrl($object->custom_url[$idLang])) {
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
        $helper->token = $this->helperToken();
        $helper->currentIndex = $this->helperIndex();
        $helper->submit_action = 'submitDesignMenuSettings';
        $helper->fields_value = [self::ENABLED => (int) Configuration::get(self::ENABLED)];

        return $helper->generateForm([$fields_form]);
    }

    protected function targetInputs(): array
    {
        return [
            [
                'type' => 'select',
                'label' => $this->trans('Links to', [], 'Modules.Designmenu.Admin'),
                'name' => 'target_type',
                'hint' => $this->trans('Only the field matching this choice is used; the other two are ignored.', [], 'Modules.Designmenu.Admin'),
                'options' => [
                    'query' => [
                        ['id' => DesignMenuTarget::CATEGORY, 'name' => $this->trans('Category', [], 'Modules.Designmenu.Admin')],
                        ['id' => DesignMenuTarget::CMS, 'name' => $this->trans('Content page', [], 'Modules.Designmenu.Admin')],
                        ['id' => DesignMenuTarget::URL, 'name' => $this->trans('Custom address', [], 'Modules.Designmenu.Admin')],
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
        ];
    }

    protected function labelInput(): array
    {
        return [
            'type' => 'text',
            'lang' => true,
            'label' => $this->trans('Label', [], 'Modules.Designmenu.Admin'),
            'name' => 'label',
            'required' => true,
            'hint' => $this->trans('Languages left empty reuse the default language.', [], 'Modules.Designmenu.Admin'),
        ];
    }

    protected function activeInput(): array
    {
        return [
            'type' => 'switch',
            'label' => $this->trans('Displayed', [], 'Modules.Designmenu.Admin'),
            'name' => 'active',
            'values' => [
                ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
            ],
        ];
    }

    protected function positionInput(string $hint): array
    {
        return [
            'type' => 'text',
            'label' => $this->trans('Position', [], 'Modules.Designmenu.Admin'),
            'name' => 'position',
            'class' => 'fixed-width-sm',
            'hint' => $hint,
        ];
    }

    protected function renderForm(array $fields_form, array $fieldsValue, string $submitAction): string
    {
        $languages = $this->context->controller->getLanguages();

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = $this->helperToken();
        $helper->currentIndex = $this->helperIndex();
        $helper->submit_action = $submitAction;
        $helper->languages = $languages;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->tpl_vars = [
            'fields_value' => $fieldsValue,
            'languages' => $languages,
            'id_language' => (int) $this->context->language->id,
        ];

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
                        $this->labelInput(),
                    ],
                    $this->targetInputs(),
                    [
                        $this->positionInput($this->trans('Modes are shown from the lowest number to the highest.', [], 'Modules.Designmenu.Admin')),
                        $this->activeInput(),
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

        $values = $this->commonFieldsValues($mode, DesignMenuMode::$definition['primary']);
        $values['position'] = $mode->id ? (int) $mode->position : DesignMenuMode::getNextPosition();

        return $this->renderForm($fields_form, $values, 'submitDesignMenuMode');
    }

    protected function renderLinkForm(): string
    {
        $link = $this->currentLink();

        if (Tools::isSubmit('submitDesignMenuLink')) {
            $this->hydrateLink($link);
        }

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $link->id
                        ? $this->trans('Edit link', [], 'Modules.Designmenu.Admin')
                        : $this->trans('New link', [], 'Modules.Designmenu.Admin'),
                    'icon' => 'icon-link',
                ],
                'input' => array_merge(
                    [
                        ['type' => 'hidden', 'name' => DesignMenuLink::$definition['primary']],
                        [
                            'type' => 'select',
                            'label' => $this->trans('Mode', [], 'Modules.Designmenu.Admin'),
                            'name' => 'id_designmenu_mode',
                            'hint' => $this->trans('The link is shown only while this mode is selected in the header.', [], 'Modules.Designmenu.Admin'),
                            'options' => [
                                'query' => $this->getModeOptions(),
                                'id' => 'id',
                                'name' => 'name',
                            ],
                        ],
                        $this->labelInput(),
                    ],
                    $this->targetInputs(),
                    [
                        $this->positionInput($this->trans('Links are shown from the lowest number to the highest, within their mode.', [], 'Modules.Designmenu.Admin')),
                        $this->activeInput(),
                    ]
                ),
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'name' => 'submitDesignMenuLink',
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

        $values = $this->commonFieldsValues($link, DesignMenuLink::$definition['primary']);
        $values['id_designmenu_mode'] = (int) $link->id_designmenu_mode;
        $values['position'] = $link->id ? (int) $link->position : DesignMenuLink::getNextPosition((int) $link->id_designmenu_mode);

        return $this->renderForm($fields_form, $values, 'submitDesignMenuLink');
    }

    protected function commonFieldsValues(ObjectModel $object, string $primary): array
    {
        $values = [
            $primary => (int) $object->id,
            'target_type' => $object->target_type,
            'id_category' => (int) $object->id_category,
            'id_cms' => (int) $object->id_cms,
            'active' => (int) $object->active,
        ];

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $values['label'][$idLang] = is_array($object->label) ? ($object->label[$idLang] ?? '') : '';
            $values['custom_url'][$idLang] = is_array($object->custom_url) ? ($object->custom_url[$idLang] ?? '') : '';
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

    protected function getModeOptions(): array
    {
        $options = [['id' => 0, 'name' => $this->trans('Neutral (no mode selected)', [], 'Modules.Designmenu.Admin')]];

        foreach ($this->getAllModes() as $mode) {
            $options[] = [
                'id' => (int) $mode[DesignMenuMode::$definition['primary']],
                'name' => $mode['label'],
            ];
        }

        return $options;
    }

    protected function getAllModes(): array
    {
        return DesignMenuMode::getModes(
            (int) $this->context->language->id,
            (int) $this->context->shop->id,
            false
        );
    }

    protected function renderModeList(): string
    {
        $idLang = (int) $this->context->language->id;
        $modes = $this->getAllModes();

        foreach ($modes as &$mode) {
            $mode['target'] = $this->describeTarget($mode, $idLang);
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
            'target' => [
                'title' => $this->trans('Links to', [], 'Modules.Designmenu.Admin'),
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

        return $this->renderList(
            $modes,
            $fields_list,
            DesignMenuMode::$definition['table'],
            DesignMenuMode::$definition['primary'],
            $this->trans('Modes', [], 'Modules.Designmenu.Admin'),
            $this->trans('Add a mode', [], 'Modules.Designmenu.Admin')
        );
    }

    protected function renderLinkList(): string
    {
        $idLang = (int) $this->context->language->id;
        $links = DesignMenuLink::getLinks($idLang, (int) $this->context->shop->id, false);
        $modeNames = [0 => $this->trans('Neutral (no mode selected)', [], 'Modules.Designmenu.Admin')];

        foreach ($this->getAllModes() as $mode) {
            $modeNames[(int) $mode[DesignMenuMode::$definition['primary']]] = $mode['label'];
        }

        foreach ($links as &$link) {
            $idMode = (int) $link['id_designmenu_mode'];
            $link['mode'] = $modeNames[$idMode] ?? $this->trans('Missing mode', [], 'Modules.Designmenu.Admin');
            $link['target'] = $this->describeTarget($link, $idLang);
        }
        unset($link);

        $fields_list = [
            DesignMenuLink::$definition['primary'] => [
                'title' => $this->trans('ID', [], 'Admin.Global'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'search' => false,
                'orderby' => false,
            ],
            'mode' => [
                'title' => $this->trans('Mode', [], 'Modules.Designmenu.Admin'),
                'search' => false,
                'orderby' => false,
            ],
            'label' => [
                'title' => $this->trans('Label', [], 'Modules.Designmenu.Admin'),
                'search' => false,
                'orderby' => false,
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

        return $this->renderList(
            $links,
            $fields_list,
            DesignMenuLink::$definition['table'],
            DesignMenuLink::$definition['primary'],
            $this->trans('Links', [], 'Modules.Designmenu.Admin'),
            $this->trans('Add a link', [], 'Modules.Designmenu.Admin')
        );
    }

    protected function renderList(array $rows, array $fieldsList, string $table, string $primary, string $title, string $addLabel): string
    {
        $helper = new HelperList();
        $helper->module = $this;
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->no_link = true;
        $helper->identifier = $primary;
        $helper->table = $table;
        $helper->title = $title;
        $helper->actions = ['edit', 'delete'];
        $helper->show_toolbar = true;
        $helper->toolbar_btn = [
            'new' => [
                'href' => $this->moduleUrl(['add' . $table => 1]),
                'desc' => $addLabel,
            ],
        ];
        $helper->listTotal = count($rows);
        $helper->token = $this->helperToken();
        $helper->currentIndex = $this->helperIndex();

        return $helper->generateList($rows, $fieldsList);
    }

    protected function describeTarget(array $row, int $idLang): string
    {
        if ($row['target_type'] === DesignMenuTarget::CATEGORY) {
            $category = new Category((int) $row['id_category'], $idLang);

            return Validate::isLoadedObject($category)
                ? $category->name
                : $this->trans('Missing category', [], 'Modules.Designmenu.Admin');
        }

        if ($row['target_type'] === DesignMenuTarget::CMS) {
            $page = new CMS((int) $row['id_cms'], $idLang);

            return Validate::isLoadedObject($page)
                ? $page->meta_title
                : $this->trans('Missing content page', [], 'Modules.Designmenu.Admin');
        }

        return (string) $row['custom_url'];
    }
}
