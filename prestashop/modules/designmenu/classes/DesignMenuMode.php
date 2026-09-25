<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DesignMenuMode extends ObjectModel
{
    public $target_type = DesignMenuTarget::CATEGORY;
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

    public static function getNextPosition(): int
    {
        return 1 + (int) Db::getInstance()->getValue(
            'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`'
        );
    }

    public static function getModes(int $idLang, int $idShop, bool $activeOnly): array
    {
        $table = self::$definition['table'];
        $primary = self::$definition['primary'];

        $sql = new DbQuery();
        $sql->select('m.*, ml.`label`, ml.`custom_url`');
        $sql->from($table, 'm');
        $sql->innerJoin($table . '_lang', 'ml', 'ml.`' . $primary . '` = m.`' . $primary . '` AND ml.`id_lang` = ' . $idLang);
        $sql->innerJoin($table . '_shop', 'ms', 'ms.`' . $primary . '` = m.`' . $primary . '` AND ms.`id_shop` = ' . $idShop);

        if ($activeOnly) {
            $sql->where('m.`active` = 1');
        }

        $sql->orderBy('m.`position` ASC, m.`' . $primary . '` ASC');

        return Db::getInstance()->executeS($sql) ?: [];
    }

    public function delete()
    {
        return DesignMenuLink::deleteByMode((int) $this->id) && parent::delete();
    }
}
