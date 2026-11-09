# MoneyMate

Web app per la gestione di entrate e uscite (frontend HTML + API PHP/MySQL). Versione corrente: 1.2.0.

## Setup
1. Importa `install.sql` nel database MySQL.
2. In `api/` copia `config.example.php` -> `config.php` e `db.example.php` -> `db.php`, poi inserisci credenziali SMTP, DB e `JWT_SECRET`.
3. Pubblica la root del progetto su un server Apache con PHP e `mod_rewrite`.
