# MoneyMate

Web app per la gestione di entrate e uscite (frontend HTML + API PHP/MySQL). Versione corrente: `v120/`.

## Setup
1. Importa `v120/install.sql` nel database MySQL.
2. In `v120/api/` copia `config.example.php` -> `config.php` e `db.example.php` -> `db.php`, poi inserisci credenziali SMTP, DB e `JWT_SECRET`.
3. Pubblica `v120/` su un server Apache con PHP e `mod_rewrite`.
