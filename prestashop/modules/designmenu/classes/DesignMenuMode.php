<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DesignMenuMode extends ObjectModel
{
    public $position = 0;
    public $active = true;
    public $label;

    public static $definition = [
        'table' => 'designmenu_mode',
        'primary' => 'id_designmenu_mode',
        'multilang' => true,
        'fields' => [
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'label' => ['type' => self::TYPE_STRING, 'lang' => true, 'required' => true, 'validate' => 'isGenericName', 'size' => 64],
        ],
    ];

    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        Shop::addTableAssociation(self::$definition['table'], ['type' => 'shop']);

        parent::__construct($id, $idLang, $idShop);
    }

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
        $sql->select('m.*, ml.`label`');
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
        return DesignMenuModeItem::deleteForMode((int) $this->id) && parent::delete();
    }
}
