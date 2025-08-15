# Tulenarium - Event Management System

Jednoduchá webová aplikace pro správu a zobrazení eventů s možností nahrávání fotek a videí.

## Funkcionalita

- **Veřejný přehled eventů** s časovou osou
- **Administrace** s přihlašováním
- **Upload a správa médií** (fotky, videa)
- **Lightbox galerie** pro prohlížení fotek
- **Responzivní design** pro mobilní zařízení
- **AJAX načítání** detailů bez reloadu stránky

## Technické požadavky

- **PHP 8.0+**
- **MySQL 5.7+** nebo **MariaDB 10.2+**
- **Apache** s mod_rewrite
- **Minimum 50MB** volného místa na disku

## Instalace

### 1. Stažení a nahrání souborů

Nahrajte všechny soubory do webového adresáře vašeho serveru.

### 2. Konfigurace databáze

Otevřte soubor `config.php` a upravte připojení k databázi:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tulenarium');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3. Nastavení administrace

V `config.php` můžete změnit přihlašovací údaje pro administraci:

```php
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'your_secure_password');
```

### 4. Instalace databáze

Přejděte na `install.php` ve vašem prohlížeči a klikněte na tlačítko "Nainstalovat aplikaci".

### 5. Nastavení oprávnění

Ujistěte se, že adresář `uploads/` má správná oprávnění pro zápis:

```bash
chmod 755 uploads/
```

## Struktura souborů

```
tulenarium/
├── config.php          # Konfigurace aplikace
├── install.php         # Instalační skript
├── index.php           # Hlavní stránka s přehledem eventů
├── admin.php           # Administrace
├── event.php           # Detail eventu (AJAX)
├── .htaccess           # Apache konfigurace
├── uploads/            # Adresář pro nahrané soubory
└── README.md           # Tento soubor
```

## Použití

### Administrace

1. Přejděte na `admin.php`
2. Přihlaste se pomocí nakonfigurovaných údajů
3. Přidávejte nové eventy pomocí formuláře
4. Nahrávejte fotky a videa
5. Vyberte náhledovou fotku pro každý event

### Veřejný přehled

- Přejděte na `index.php` pro zobrazení všech eventů
- Klikněte na jakýkoli event pro zobrazení detailu
- Prohlížejte fotky v lightbox galerii
- Přehrávejte videa přímo na stránce

## Konfigurace

### Povolené typy souborů

V `config.php` můžete upravit povolené typy souborů:

```php
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi', 'webm']);
```

### Maximální velikost souboru

```php
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
```

### Base URL

Pokud aplikace není v root adresáři, upravte:

```php
define('BASE_URL', '/cesta/k/aplikaci/');
```

## Databázová struktura

### Tabulka `events`

| Sloupec | Typ | Popis |
|---------|-----|-------|
| id | INT | Primární klíč (AUTO_INCREMENT) |
| title | VARCHAR(255) | Název eventu |
| description | TEXT | Popis eventu |
| event_date | DATE | Datum konání |
| people_count | INT | Počet účastníků |
| location | VARCHAR(255) | Místo konání |
| media | TEXT | JSON seznam nahraných souborů |
| thumbnail | VARCHAR(255) | Cesta k náhledové fotce |
| created_at | TIMESTAMP | Datum vytvoření |
| updated_at | TIMESTAMP | Datum aktualizace |

## Zabezpečení

- Přihlašovací údaje jsou hashovány
- Upload souborů je omezen na povolené typy
- Ochrana proti hot-linkingu
- Zabránění přímému přístupu k PHP souborům v uploads
- Validace velikosti souborů

## Customizace

### CSS styly

Styly jsou přímo v HTML souborech pro jednoduchost. Můžete je extrahovat do samostatných CSS souborů.

### Přidání funkcionalit

- Editace eventů
- Kategorije eventů  
- Systém komentářů
- Export dat
- API rozhraní

## Troubleshooting

### Chyba připojení k databázi

Zkontrolujte údaje v `config.php` a ujistěte se, že databázový server běží.

### Nefunguje upload souborů

1. Zkontrolujte oprávnění adresáře `uploads/`
2. Ověřte PHP nastavení `upload_max_filesize` a `post_max_size`
3. Zkontrolujte dostupné místo na disku

### Nefunguje rewrite (krásné URL)

Ujistěte se, že je povolen mod_rewrite v Apache a `.htaccess` soubor je čitelný.

## Podpora

Pro hlášení chyb nebo návrhy vytvořte issue v repository projektu.

## Licence

Tento projekt je poskytován "jak je" bez jakékoli záruky.

