<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DesignMenuLink extends ObjectModel
{
    public $id_designmenu_mode = 0;
    public $target_type = DesignMenuTarget::CATEGORY;
    public $id_category = 0;
    public $id_cms = 0;
    public $position = 0;
    public $active = true;
    public $label;
    public $custom_url;

    public static $definition = [
        'table' => 'designmenu_link',
        'primary' => 'id_designmenu_link',
        'multilang' => true,
        'fields' => [
            'id_designmenu_mode' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'target_type' => ['type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 16],
            'id_category' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_cms' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'label' => ['type' => self::TYPE_STRING, 'lang' => true, 'required' => true, 'validate' => 'isGenericName', 'size' => 64],
            'custom_url' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isUrl', 'size' => 255],
        ],
    ];

    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        Shop::addTableAssociation(self::$definition['table'], ['type' => 'shop']);

        parent::__construct($id, $idLang, $idShop);
    }

    public static function getNextPosition(int $idMode): int
    {
        return 1 + (int) Db::getInstance()->getValue(
            'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
             WHERE `id_designmenu_mode` = ' . $idMode
        );
    }

    public static function getLinks(int $idLang, int $idShop, bool $activeOnly, ?int $idMode = null): array
    {
        $table = self::$definition['table'];
        $primary = self::$definition['primary'];

        $sql = new DbQuery();
        $sql->select('l.*, ll.`label`, ll.`custom_url`');
        $sql->from($table, 'l');
        $sql->innerJoin($table . '_lang', 'll', 'll.`' . $primary . '` = l.`' . $primary . '` AND ll.`id_lang` = ' . $idLang);
        $sql->innerJoin($table . '_shop', 'ls', 'ls.`' . $primary . '` = l.`' . $primary . '` AND ls.`id_shop` = ' . $idShop);

        if ($activeOnly) {
            $sql->where('l.`active` = 1');
        }

        if ($idMode !== null) {
            $sql->where('l.`id_designmenu_mode` = ' . $idMode);
        }

        $sql->orderBy('l.`id_designmenu_mode` ASC, l.`position` ASC, l.`' . $primary . '` ASC');

        return Db::getInstance()->executeS($sql) ?: [];
    }

    public static function deleteByMode(int $idMode): bool
    {
        $done = true;

        foreach (Db::getInstance()->executeS(
            'SELECT `' . self::$definition['primary'] . '` FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
             WHERE `id_designmenu_mode` = ' . $idMode
        ) ?: [] as $row) {
            $link = new self((int) $row[self::$definition['primary']]);
            $done = $link->delete() && $done;
        }

        return $done;
    }
}
