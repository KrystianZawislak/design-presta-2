<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DesignMenuSelectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        if (Tools::getIsset('id_designmenu_mode')) {
            $this->module->selectMode(Tools::getValue('id_designmenu_mode'));
        }

        Tools::redirect($this->backUrl());
    }

    protected function backUrl(): string
    {
        $back = (string) Tools::getValue('back');

        if (!preg_match('#^/[A-Za-z0-9_\-./?&=%+~,;@!$\'()\[\]]*$#', $back)
            || strpos($back, '//') === 0
            || strpos($back, '..') !== false
        ) {
            return $this->context->link->getPageLink('index');
        }

        return $this->context->shop->getBaseURL(true) . ltrim($back, '/');
    }
}
