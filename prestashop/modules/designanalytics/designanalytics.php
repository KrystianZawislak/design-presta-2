<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DesignAnalytics extends Module
{
    const ENABLED = 'DESIGNANALYTICS_ENABLED';
    const MEASUREMENT_ID = 'DESIGNANALYTICS_MEASUREMENT_ID';
    const DEBUG_MODE = 'DESIGNANALYTICS_DEBUG_MODE';
    const COOKIE_KEY = 'designanalytics_purchase';

    public function __construct()
    {
        $this->name = 'designanalytics';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Design Presta';
        $this->bootstrap = true;
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->trans('Design Analytics', [], 'Modules.Designanalytics.Admin');
        $this->description = $this->trans('Sends page views and completed orders to Google Analytics 4.', [], 'Modules.Designanalytics.Admin');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayOrderConfirmation')
            && Configuration::updateValue(self::ENABLED, 0)
            && Configuration::updateValue(self::MEASUREMENT_ID, '')
            && Configuration::updateValue(self::DEBUG_MODE, 0);
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::ENABLED)
            && Configuration::deleteByName(self::MEASUREMENT_ID)
            && Configuration::deleteByName(self::DEBUG_MODE);
    }

    public function hookDisplayHeader()
    {
        $measurementId = $this->activeMeasurementId();

        if ($measurementId === '') {
            return '';
        }

        $this->context->smarty->assign([
            'designanalytics_measurement_id' => $measurementId,
            'designanalytics_config' => $this->encode($this->configPayload()),
        ]);

        return $this->fetch('module:designanalytics/views/templates/hook/gtag.tpl');
    }

    public function hookDisplayOrderConfirmation(array $params)
    {
        $order = $params['order'] ?? null;

        if ($this->activeMeasurementId() === '' || !Validate::isLoadedObject($order)) {
            return '';
        }

        if ($this->alreadySent((int) $order->id)) {
            return '';
        }

        $this->markSent((int) $order->id);
        $this->context->smarty->assign('designanalytics_purchase', $this->encode($this->purchasePayload($order)));

        return $this->fetch('module:designanalytics/views/templates/hook/purchase.tpl');
    }

    protected function activeMeasurementId(): string
    {
        if (!Configuration::get(self::ENABLED)) {
            return '';
        }

        $measurementId = (string) Configuration::get(self::MEASUREMENT_ID);

        return $this->isMeasurementId($measurementId) ? $measurementId : '';
    }

    protected function isMeasurementId(string $measurementId): bool
    {
        return (bool) preg_match('/^G-[A-Z0-9]{4,20}$/', $measurementId);
    }

    protected function configPayload(): array
    {
        if (!Configuration::get(self::DEBUG_MODE)) {
            return [];
        }

        return ['debug_mode' => true];
    }

    protected function purchasePayload(Order $order): array
    {
        $currency = new Currency((int) $order->id_currency);
        $tax = (float) $order->total_paid_tax_incl - (float) $order->total_paid_tax_excl;

        return [
            'transaction_id' => (string) $order->reference,
            'value' => Tools::ps_round((float) $order->total_paid_tax_incl, 2),
            'tax' => Tools::ps_round($tax, 2),
            'shipping' => Tools::ps_round((float) $order->total_shipping_tax_incl, 2),
            'currency' => (string) $currency->iso_code,
            'items' => $this->purchaseItems($order),
        ];
    }

    protected function purchaseItems(Order $order): array
    {
        $items = [];

        foreach ($order->getProducts() as $product) {
            $reference = trim((string) ($product['product_reference'] ?? ''));

            $items[] = [
                'item_id' => $reference !== '' ? $reference : (string) $product['product_id'],
                'item_name' => (string) $product['product_name'],
                'price' => Tools::ps_round((float) $product['unit_price_tax_incl'], 2),
                'quantity' => (int) $product['product_quantity'],
            ];
        }

        return $items;
    }

    protected function alreadySent(int $idOrder): bool
    {
        return (int) $this->context->cookie->{self::COOKIE_KEY} === $idOrder;
    }

    protected function markSent(int $idOrder): void
    {
        $this->context->cookie->{self::COOKIE_KEY} = $idOrder;
        $this->context->cookie->write();
    }

    protected function encode(array $payload): string
    {
        $precision = ini_set('serialize_precision', '-1');

        $json = (string) json_encode(
            $payload ?: new stdClass(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        if ($precision !== false) {
            ini_set('serialize_precision', $precision);
        }

        return $json;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitDesignAnalyticsSettings')) {
            $errors = $this->saveSettings();

            $output .= $errors
                ? $this->displayError(implode('<br>', $errors))
                : $this->displayConfirmation($this->trans('Settings saved.', [], 'Modules.Designanalytics.Admin'));
        }

        return $output . $this->renderSettingsForm();
    }

    protected function saveSettings(): array
    {
        $measurementId = Tools::strtoupper(trim((string) Tools::getValue(self::MEASUREMENT_ID)));
        $enabled = (int) Tools::getValue(self::ENABLED);

        if ($measurementId !== '' && !$this->isMeasurementId($measurementId)) {
            return [$this->trans('The measurement ID must look like G-XXXXXXXXXX.', [], 'Modules.Designanalytics.Admin')];
        }

        if ($enabled && $measurementId === '') {
            return [$this->trans('Add a measurement ID before you turn the tracking on.', [], 'Modules.Designanalytics.Admin')];
        }

        Configuration::updateValue(self::ENABLED, $enabled);
        Configuration::updateValue(self::MEASUREMENT_ID, $measurementId);
        Configuration::updateValue(self::DEBUG_MODE, (int) Tools::getValue(self::DEBUG_MODE));

        return [];
    }

    protected function renderSettingsForm(): string
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Google Analytics 4', [], 'Modules.Designanalytics.Admin'),
                    'icon' => 'icon-line-chart',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Measurement ID', [], 'Modules.Designanalytics.Admin'),
                        'name' => self::MEASUREMENT_ID,
                        'class' => 'fixed-width-lg',
                        'hint' => $this->trans('Google Analytics shows it under Admin, Data streams, your web stream. It looks like G-XXXXXXXXXX.', [], 'Modules.Designanalytics.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Send data to Google Analytics', [], 'Modules.Designanalytics.Admin'),
                        'name' => self::ENABLED,
                        'hint' => $this->trans('Turn this off to stop the tracking without clearing the measurement ID.', [], 'Modules.Designanalytics.Admin'),
                        'values' => [
                            ['id' => 'enabled_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                            ['id' => 'enabled_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Debug mode', [], 'Modules.Designanalytics.Admin'),
                        'name' => self::DEBUG_MODE,
                        'hint' => $this->trans('Marks every hit as a debug hit so it shows up in the DebugView report. Keep it off in production.', [], 'Modules.Designanalytics.Admin'),
                        'values' => [
                            ['id' => 'debug_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                            ['id' => 'debug_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'name' => 'submitDesignAnalyticsSettings',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitDesignAnalyticsSettings';
        $helper->fields_value = [
            self::MEASUREMENT_ID => (string) Configuration::get(self::MEASUREMENT_ID),
            self::ENABLED => (int) Configuration::get(self::ENABLED),
            self::DEBUG_MODE => (int) Configuration::get(self::DEBUG_MODE),
        ];

        return $helper->generateForm([$fields_form]);
    }
}
