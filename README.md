# RaktárPont

Kis költségű, függőségmentes PHP + SQLite raktárkezelő. A jogosultsági szintek: `0` csak olvasás, `1` írás és olvasás, `2` supervisor, `3` admin.

## Indítás

PHP 8.2+ és a `pdo_sqlite` bővítmény szükséges.

```bash
php -S localhost:8080 -t public
```

Nyisd meg a `http://localhost:8080` címet. Első indításkor a felület bekéri az admin nevét és (legalább 10 karakteres) jelszavát. Az SQLite fájl automatikusan létrejön a `data/inventory.sqlite` helyen; más útvonal az `INVENTORY_DB` környezeti változóval adható meg.

## Jogosultságok

| Szint | Szerepkör | Lehetőségek |
|---:|---|---|
| 0 | Csak olvasás | A számára engedélyezett raktárak és készletadatok megtekintése |
| 1 | Írás és olvasás | Megtekintés, új tétel felvétele és cikkszám alapján frissítése |
| 2 | Supervisor | Az előzőek, olvasó/író felhasználó létrehozása és hozzáférések kezelése |
| 3 | Admin | Minden raktár elérése, bármilyen felhasználó és raktár létrehozása, tételek/raktárak törlése |

A supervisor csak olyan raktárhoz oszthat hozzáférést, amelyhez maga is hozzáfér. Az admin automatikusan minden raktárat lát.

## Biztonság és mentés

A jelszavak PHP `password_hash` használatával tárolódnak, az adatbázis-műveletek paraméterezettek, a módosító kéréseket CSRF token védi. Éles használatban HTTPS-t, rendszeres SQLite-fájlmentést és a `data` könyvtár webszerveren kívüli elhelyezését javasoljuk.

Teszt:

```bash
php tests/smoke.php
```
