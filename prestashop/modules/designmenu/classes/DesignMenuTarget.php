<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DesignMenuTarget
{
    const CATEGORY = 'category';
    const CMS = 'cms';
    const URL = 'url';

    public static function getTypes(): array
    {
        return [self::CATEGORY, self::CMS, self::URL];
    }

    public static function resolveUrl(Link $link, array $row, int $idLang): string
    {
        if ($row['target_type'] === self::CATEGORY && $row['id_category']) {
            return $link->getCategoryLink((int) $row['id_category'], null, $idLang);
        }

        if ($row['target_type'] === self::CMS && $row['id_cms']) {
            return $link->getCMSLink((int) $row['id_cms'], null, null, $idLang);
        }

        if ($row['target_type'] === self::URL) {
            return (string) $row['custom_url'];
        }

        return '';
    }
}
