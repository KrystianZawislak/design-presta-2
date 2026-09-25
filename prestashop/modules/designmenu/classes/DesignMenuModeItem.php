<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DesignMenuModeItem
{
    const TABLE = 'designmenu_mode_item';

    public static function getForMode(int $idMode, int $idShop): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `page_identifier` FROM `' . _DB_PREFIX_ . self::TABLE . '`
             WHERE `id_designmenu_mode` = ' . $idMode . ' AND `id_shop` = ' . $idShop
        ) ?: [];

        return array_column($rows, 'page_identifier');
    }

    public static function setForMode(int $idMode, int $idShop, array $identifiers): bool
    {
        if (!self::deleteForMode($idMode, $idShop)) {
            return false;
        }

        $rows = [];
        foreach (array_unique($identifiers) as $identifier) {
            $rows[] = [
                'id_designmenu_mode' => $idMode,
                'id_shop' => $idShop,
                'page_identifier' => pSQL($identifier),
            ];
        }

        return $rows ? Db::getInstance()->insert(self::TABLE, $rows) : true;
    }

    public static function deleteForMode(int $idMode, ?int $idShop = null): bool
    {
        $where = '`id_designmenu_mode` = ' . $idMode;

        if ($idShop !== null) {
            $where .= ' AND `id_shop` = ' . $idShop;
        }

        return Db::getInstance()->delete(self::TABLE, $where);
    }
}
