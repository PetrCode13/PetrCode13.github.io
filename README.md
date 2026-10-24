# ScriptCMS 1.4.00 JavaS

**Statický frontend ScriptCMS s administrací `/admin/` a externí datovou/auth vrstvou.** Tento balíček je samostatná JavaScriptová implementační základna, nikoli hotový drop-in port se 100% shodou funkcí původního PHP vydání.

## Co je v tomto balíčku implementováno

- statický veřejný web a načítání publikovaných stránek z databáze;
- administrace `/admin/` s přihlášením přes Supabase Auth;
- správa stránek: vytvoření, úprava, publikování/koncept, odstranění (odstranění je omezené na správce);
- základní Markdown náhled s escapováním HTML;
- základní nastavení webu a export stránek/nastavení do JSON;
- správa uživatelů přes Edge Function s kontrolou role na serveru (výpis, vytvoření, změna role, smazání);
- databázové RLS politiky, role `admin`/`editor`, auditní tabulka pro některé administrátorské operace;
- světlý/tmavý režim, responzivní rozhraní a dvoubarevné Material You-inspirované karty.

## Co zatím není ekvivalentem původní PHP verze

- úplná shoda WYSIWYG editoru (editor v této verzi pracuje s Markdownem);
- úplná sada bezpečnostních funkcí původního `core/Security.php` (blokování IP/cest, všechny kontroly a bezpečnostní přehledy);
- automatický aktualizátor s ověřováním podepsaných vydání;
- kompletní migrace původních JSON uživatelů a jejich hesel do Supabase Auth;
- plnohodnotná e-mailová 2FA ekvivalentní původnímu PHP workflow; obnovu hesla zajišťuje Supabase Auth, pokud je správně nastaven poskytovatel e-mailu;
- import médií/správa souborů a všechny pokročilé možnosti původního editoru;
- kompletní regresní a bezpečnostní audit produkčního nasazení.

Tyto body nejsou skryté nebo simulované: vyžadují další implementaci, testy a/nebo služby. Nepoužívejte tento první JavaS build jako tvrzení o 100% funkční shodě s původním ScriptCMS.

## Požadavky

- statický hosting schopný obsluhovat HTML/CSS/JS a soubory v adresáři `admin/`;
- projekt Supabase (PostgreSQL, Auth, Edge Functions);
- Supabase CLI pro nasazení Edge Function, nebo prostředí pro nasazení Supabase Function.

Veřejný web nepotřebuje PHP. Samotná datová/auth vrstva běží u Supabase. Pokud chceš úplně bez externího backendu, nelze bezpečně poskytovat sdílené účty, oprávnění a ukládání změn jen pomocí klientského JavaScriptu.

## Instalace

### 1. Vytvoř Supabase projekt

Vytvoř projekt v [Supabase Dashboard](https://supabase.com/dashboard). Získej Project URL a veřejný `anon`/publishable key v Project Settings → API. Tyto dvě hodnoty vlož do `assets/config.js`:

```js
export const CONFIG = {
  url: 'https://TVUJ-PROJEKT.supabase.co',
  anonKey: 'TVUJ_PUBLIC_ANON_NEBO_PUBLISHABLE_KLIC',
  siteName: 'ScriptCMS',
  version: '1.4.00 JavaS'
};
```

**Nikdy nevkládej `service_role` klíč do `assets/config.js` ani do jiného veřejného souboru.**

### 2. Vytvoř databázi

V Supabase → SQL Editor spusť celý soubor `supabase/schema.sql`. Skript vytváří profily, stránky, nastavení, auditní tabulku, trigger pro nové uživatele a RLS politiky.

### 3. Vytvoř první účet správce

1. V Supabase → Authentication → Users vytvoř uživatele, nebo povol registraci a vytvoř účet přes bezpečně spravovaný postup.
2. V SQL Editoru spusť následující příkaz s přesnou e-mailovou adresou účtu:

```sql
update public.scms_profiles
set role = 'admin'
where lower(email) = lower('admin@example.com');
```

3. Ověř, že byl změněn právě jeden řádek. Ve veřejné aplikaci není možnost povýšit si vlastní účet na správce.

### 4. Nasaď Edge Function pro správu uživatelů

Nainstaluj [Supabase CLI](https://supabase.com/docs/guides/cli) a přihlas se. Z kořene tohoto projektu proveď:

```sh
supabase login
supabase link --project-ref TVUJ_PROJECT_REF
supabase functions deploy admin-users
```

Supabase obvykle poskytuje potřebné proměnné `SUPABASE_URL`, `SUPABASE_ANON_KEY` a `SUPABASE_SERVICE_ROLE_KEY` funkci automaticky. Pokud je tvoje prostředí neposkytuje, nastav je jako secrets podle oficiální dokumentace. Service-role klíč nikdy nekopíruj do frontendu.

### 5. Nahraj statické soubory

Nahraj celý obsah ZIPu do kořenového adresáře statického webu. Administrace je v `/admin/`. Pokud hosting nepodporuje soubory s cestou začínající `/`, uprav v HTML odkazy na relativní cesty podle umístění webu. Pro lokální test použij například jednoduchý statický HTTP server; některé prohlížeče blokují ES moduly při otevření přes `file://`.

### 6. Zabezpeč Supabase Auth

- zapni potvrzování e-mailových adres;
- nastav SMTP poskytovatele, URL webu a povolené redirect URL;
- nastav přiměřené limity pokusů o přihlášení a silnou politiku hesel;
- pravidelně zálohuj databázi a zkoušej obnovu;
- zkontroluj RLS a logy před produkčním provozem.

## Struktura

```text
index.html                         veřejný web
admin/index.html                   administrace
assets/style.css                   UI, světlý/tmavý režim
assets/admin.js                    administrační UI
assets/public.js                   veřejné načítání obsahu
assets/config.js                   veřejná konfigurace Supabase
assets/supabase.js                 Supabase JS klient (verzovaný CDN import)
supabase/schema.sql                databáze a RLS
supabase/functions/admin-users/    serverová správa účtů
```

## Závislosti a důvěra

- Supabase JS klient je připnut na konkrétní verzi přes `esm.sh`; frontend potřebuje síťové spojení k načtení této knihovny a k Supabase API.
- Autentizace a oprávnění nejsou zabezpečeny pouze skrytím prvků v UI: RLS a Edge Function provádějí serverové kontroly.
- Ikony jsou v tomto buildu záměrně jednoduché textové glyfy; další krok může nahradit je připnutou sadou Material Symbols/Lucide.
- Před produkcí doporučujeme bezpečnostní audit, kontrolu CSP/HTTP hlaviček na hostingu, testy RLS a regresní testy všech původních PHP funkcí.

## Licence

Původní ScriptCMS má v archivu soubor `LICENSE`. Tento JavaS port je odvozen z projektu ScriptCMS; před veřejnou redistribucí zkontroluj podmínky licence a přidej požadovaná oznámení.
