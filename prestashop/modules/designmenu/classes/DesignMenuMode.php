<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DesignMenuMode extends ObjectModel
{
    const TARGET_CATEGORY = 'category';
    const TARGET_CMS = 'cms';
    const TARGET_URL = 'url';

    public $target_type = self::TARGET_CATEGORY;
    public $id_category = 0;
    public $id_cms = 0;
    public $position = 0;
    public $active = true;
    public $label;
    public $custom_url;

    public static $definition = [
        'table' => 'designmenu_mode',
        'primary' => 'id_designmenu_mode',
        'multilang' => true,
        'fields' => [
            'target_type' => ['type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 16],
            'id_category' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_cms' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'label' => ['type' => self::TYPE_STRING, 'lang' => true, 'required' => true, 'validate' => 'isGenericName', 'size' => 64],
            'custom_url' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isUrl', 'size' => 255],
        ],
    ];

    public static function getTargetTypes(): array
    {
        return [self::TARGET_CATEGORY, self::TARGET_CMS, self::TARGET_URL];
    }

    public static function getNextPosition(): int
    {
        return 1 + (int) Db::getInstance()->getValue(
            'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`'
        );
    }

    public static function getModes(int $idLang, int $idShop, bool $activeOnly): array
    {
        $sql = new DbQuery();
        $sql->select('m.*, ml.`label`, ml.`custom_url`');
        $sql->from(self::$definition['table'], 'm');
        $sql->innerJoin(self::$definition['table'] . '_lang', 'ml', 'ml.`' . self::$definition['primary'] . '` = m.`' . self::$definition['primary'] . '` AND ml.`id_lang` = ' . (int) $idLang);
        $sql->innerJoin(self::$definition['table'] . '_shop', 'ms', 'ms.`' . self::$definition['primary'] . '` = m.`' . self::$definition['primary'] . '` AND ms.`id_shop` = ' . (int) $idShop);
        if ($activeOnly) {
            $sql->where('m.`active` = 1');
        }
        $sql->orderBy('m.`position` ASC, m.`' . self::$definition['primary'] . '` ASC');

        return Db::getInstance()->executeS($sql) ?: [];
    }

    public function getTargetUrl(Link $link, int $idLang): string
    {
        if ($this->target_type === self::TARGET_CATEGORY && $this->id_category) {
            return $link->getCategoryLink((int) $this->id_category, null, $idLang);
        }

        if ($this->target_type === self::TARGET_CMS && $this->id_cms) {
            return $link->getCMSLink((int) $this->id_cms, null, null, $idLang);
        }

        if ($this->target_type === self::TARGET_URL) {
            $url = is_array($this->custom_url) ? ($this->custom_url[$idLang] ?? '') : $this->custom_url;

            return (string) $url;
        }

        return '';
    }
}
