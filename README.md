# design-presta-2

Sklep PrestaShop 8.2.7 (PHP 8.1) uruchamiany w Dockerze.

## Uruchomienie

```bash
cp .env.example .env
```

Uzupełnij `DB_PASSWORD` i `DB_ROOT_PASSWORD` własnymi wartościami, potem:

```bash
docker compose up -d
```

| Usługa     | Adres                   |
|------------|-------------------------|
| Sklep      | http://localhost:1001   |
| phpMyAdmin | http://localhost:1002   |

## Instalacja

Instalator nie uruchamia się automatycznie (`PS_INSTALL_AUTO=0`). Przy pierwszym wejściu
na http://localhost:1001 sklep przekieruje na kreator. W kroku konfiguracji bazy:

| Pole              | Wartość                |
|-------------------|------------------------|
| Adres serwera     | `mysql`                |
| Nazwa bazy        | wartość `DB_NAME`      |
| Login             | wartość `DB_USER`      |
| Hasło             | wartość `DB_PASSWORD`  |
| Prefiks tabel     | `ps_`                  |

Po zakończeniu instalacji PrestaShop wymaga usunięcia katalogu instalatora:

```bash
docker exec presta2-shop rm -rf /var/www/html/install
```

Nazwę wygenerowanego katalogu panelu administracyjnego odczytasz tak:

```bash
docker exec presta2-shop sh -c 'ls -d /var/www/html/admin*'
```

## Zatrzymanie

```bash
docker compose down
```

Pliki sklepu leżą w katalogu `prestashop/` na dysku i `down` ich nie rusza. Baza siedzi
w wolumenie `mysql_data` i też przeżywa `down`; kasuje ją dopiero `docker compose down -v`.

Katalog `prestashop/` jest w `.gitignore` poza `themes/`, `modules/` i `override/` —
rdzeń PrestaShopu nie trafia do repo, bo odtwarza go tag obrazu z `docker-compose.yml`.
