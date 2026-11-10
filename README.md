# RaktárPont

Kis költségű, függőségmentes PHP + SQLite raktárkezelő. A jogosultsági szintek: `0` csak olvasás, `1` írás és olvasás, `2` supervisor, `3` admin.

## Indítás

PHP 8.2+ és a `pdo_sqlite` bővítmény szükséges.

```bash
php -S localhost:8080 -t public
```

Nyisd meg a `http://localhost:8080` címet. Első indításkor a felület bekéri az admin nevét és (legalább 10 karakteres) jelszavát. Az SQLite fájl automatikusan létrejön a `data/inventory.sqlite` helyen; más útvonal az `INVENTORY_DB` környezeti változóval adható meg.

## Készlettételek lokációja

A készlettételhez a raktáron belüli lokáció is megadható (például `A-02 polc`). Ha üresen marad, az értéke `allokálatlan`. A lokáció a készlettáblázatban is látható. A meglévő tételek automatikusan `allokálatlan` lokációt kapnak az első indításkor. Azonos raktárban a cikkszám szerinti mentés a lokációt is frissíti.

## Jogosultságok

| Szint | Szerepkör | Lehetőségek |
|---:|---|---|
| 0 | Csak olvasás | A számára engedélyezett raktárak és készletadatok megtekintése |
| 1 | Írás és olvasás | Megtekintés, új tétel felvétele és cikkszám alapján frissítése |
| 2 | Supervisor | Az előzőek, olvasó/író felhasználó létrehozása és hozzáférések kezelése |
| 3 | Admin | Minden raktár elérése, bármilyen felhasználó és raktár létrehozása, tételek/raktárak törlése |

A supervisor csak olyan raktárhoz oszthat hozzáférést, amelyhez maga is hozzáfér. Az admin automatikusan minden raktárat lát.

## Adminisztráció és hibabejelentések

A főmenüben a készletkezeléstől elkülönített **Adminisztráció** oldal csak supervisornak és adminnak jelenik meg; a közvetlen oldal- és műveletkérések is jogosultságellenőrzést kapnak. Az admin más felhasználók szerepkörét is módosíthatja. A saját szerepkör nem módosítható. A supervisor olvasó/író fiókokat hozhat létre, és csak a saját raktáraihoz adhat vagy vonhat meg hozzáférést. A szerepkörváltozás a következő kérésnél a már bejelentkezett felhasználóra is érvényes.

A **Hibabejelentések** oldalon minden bejelentkezett felhasználó küldhet jegyet. Kötelező az email-cím (szerveroldali szintaktikai ellenőrzéssel), a típus (**Hiba**, **Fejlesztési javaslat**, **Kérdés**), a tárgy és a leírás. Nincs emailküldés vagy külső szolgáltatás. A beküldő csak a saját jegyeit és azok hozzászólásait látja; az admin és a supervisor minden jegyet kezelhet. A főmenüben piros számláló jelzi nekik az aktív jegyek számát, az oldal betöltésekor frissülve. Az aktív jegyekhez a beküldő és a kezelők hozzászólhatnak; lezárni és újranyitni csak admin vagy supervisor tud. A táblák meglévő adatbázisban is automatikusan létrejönnek.

A jogosultságok és a jegykezelés HTTP-integrációs tesztje (Python 3 standard könyvtár, külön csomag nem kell; ideiglenes adatbázist és helyi PHP-szervert használ):

```bash
python3 tests/management_http.py
# Eltérő PHP-útvonal esetén: PHP_BIN=/path/to/php python3 tests/management_http.py
```

## Megjelenés

A fő navigáció a fejlécben érhető el. A bejelentkezett felhasználók a **Megjelenés** menüben választhatnak **Világos**, **Sötét**, **Rendszer** és **Egyéni (custom)** módot. A Rendszer az operációs rendszer színmódját követi, annak változásakor is; ez az új és meglévő felhasználók alapbeállítása, valamint a bejelentkezési felület módja.

Az egyéni palettán a háttér, panelek, szöveg, másodlagos szöveg, kiemelés és szegély színe állítható. A színek és a mód élő előnézetben láthatók, a visszaállítás gomb visszahozza a legutóbb mentett értékeket. A **Mentés** a saját felhasználó `theme_mode` és `custom_theme` mezőibe rögzíti a választást, így új bejelentkezéskor vagy másik eszközön is megmarad. Más mód kiválasztása nem törli az elmentett egyéni palettát. JavaScript nélkül is menthető a beállítás; csak az élő előnézethez szükséges JavaScript. A színkódok szerveroldali ellenőrzést, a mentés CSRF-védelmet kap. A szükséges adatbázismezők automatikusan létrejönnek.

## Biztonság és mentés

A jelszavak PHP `password_hash` használatával tárolódnak, az adatbázis-műveletek paraméterezettek, a módosító kéréseket CSRF token védi. Éles használatban HTTPS-t, rendszeres SQLite-fájlmentést és a `data` könyvtár webszerveren kívüli elhelyezését javasoljuk.

Teszt:

```bash
php tests/smoke.php
```
