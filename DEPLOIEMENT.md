# Guide de Déploiement — PDU Tracker

## Informations sur le projet
- **Framework** : Laravel 13 + Vue 3 + Inertia.js
- **PHP** : >= 8.3
- **BDD** : SQLite (actuel) ou MySQL/PostgreSQL (recommandé en production)
- **Assets** : Vite
- **Dépendances** : Composer + NPM

---

# OPTION 1 : Déploiement sur VPS (Production)

## Fournisseurs recommandés
| Fournisseur | Prix | Localisation |
|-------------|------|--------------|
| DigitalOcean | ~6$/mois | Monde entier |
| Contabo | ~5€/mois | Europe |
| OVH VPS | ~5€/mois | France |
| Hostinger VPS | ~4€/mois | Monde |

## Prérequis
- Un VPS avec Ubuntu 22.04 ou 24.04
- Un nom de domaine (optionnel mais recommandé, ex: pdu-tracker.ci)
- Accès SSH au serveur

---

## Étape 1 : Commander un VPS

1. Allez sur le site du fournisseur (ex: DigitalOcean, OVH)
2. Choisissez un VPS avec au minimum :
   - 1 vCPU
   - 1 Go RAM
   - 25 Go SSD
   - Ubuntu 22.04 ou 24.04
3. Notez l'adresse IP du serveur et le mot de passe root

---

## Étape 2 : Se connecter au VPS en SSH

Depuis un terminal (PowerShell, Git Bash ou PuTTY) :

```bash
ssh root@VOTRE_IP_SERVEUR
```

---

## Étape 3 : Mettre à jour le serveur et installer les dépendances

```bash
# Mise à jour
apt update && apt upgrade -y

# Installer les paquets nécessaires
apt install -y nginx mysql-server php8.3 php8.3-fpm php8.3-cli php8.3-mbstring \
  php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-mysql \
  php8.3-sqlite3 php8.3-intl unzip git curl

# Si PHP 8.3 n'est pas disponible directement :
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml \
  php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-mysql php8.3-sqlite3 php8.3-intl

# Installer Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installer Node.js 20+
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

---

## Étape 4 : Créer un utilisateur dédié

```bash
adduser deploy
usermod -aG www-data deploy
su - deploy
```

---

## Étape 5 : Transférer le projet sur le serveur

### Option A : Via Git (recommandé)

Si votre projet est sur GitHub/GitLab :

```bash
cd /var/www
sudo git clone https://github.com/VOTRE_UTILISATEUR/pdu-tracker.git
sudo chown -R deploy:www-data pdu-tracker
cd pdu-tracker
```

### Option B : Via SCP (sans Git)

Depuis votre PC Windows (PowerShell) :

```powershell
# Compresser le projet (exclure node_modules et vendor)
# Utilisez 7-Zip pour créer pdu-tracker.zip sans node_modules, vendor, .env

# Transférer sur le serveur
scp pdu-tracker.zip root@VOTRE_IP:/var/www/

# Sur le serveur :
cd /var/www
unzip pdu-tracker.zip
chown -R deploy:www-data pdu-tracker
```

---

## Étape 6 : Installer les dépendances sur le serveur

```bash
cd /var/www/pdu-tracker

# Installer les dépendances PHP (sans dev)
composer install --no-dev --optimize-autoloader

# Installer les dépendances Node et compiler les assets
npm install
npm run build
```

---

## Étape 7 : Configurer l'environnement (.env)

```bash
cp .env.example .env
nano .env
```

Modifier les valeurs suivantes :

```env
APP_NAME="PDU Tracker"
APP_ENV=production
APP_KEY=                        # sera généré après
APP_DEBUG=false
APP_URL=http://VOTRE_IP_OU_DOMAINE

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pdu_tracker
DB_USERNAME=pdu_user
DB_PASSWORD=MOT_DE_PASSE_SECURISE
```

Puis générer la clé et migrer :

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # si vous avez des seeders
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Étape 8 : Configurer MySQL (si vous passez de SQLite à MySQL)

```bash
sudo mysql -u root

# Dans MySQL :
CREATE DATABASE pdu_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pdu_user'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_SECURISE';
GRANT ALL PRIVILEGES ON pdu_tracker.* TO 'pdu_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> **Note** : Si vous souhaitez rester sur SQLite en production, gardez
> `DB_CONNECTION=sqlite` et assurez-vous que `database/database.sqlite` existe
> avec les bonnes permissions : `chmod 664 database/database.sqlite`

---

## Étape 9 : Configurer Nginx

```bash
sudo nano /etc/nginx/sites-available/pdu-tracker
```

Contenu :

```nginx
server {
    listen 80;
    server_name VOTRE_DOMAINE_OU_IP;
    root /var/www/pdu-tracker/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 50M;
}
```

Activer le site :

```bash
sudo ln -s /etc/nginx/sites-available/pdu-tracker /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## Étape 10 : Permissions des fichiers

```bash
cd /var/www/pdu-tracker
sudo chown -R deploy:www-data .
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;
sudo chmod -R 775 storage bootstrap/cache database
```

---

## Étape 11 : Configurer HTTPS avec Let's Encrypt (gratuit)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d VOTRE_DOMAINE.com
```

Suivez les instructions. Le certificat se renouvelle automatiquement.

---

## Étape 12 : Configurer le Queue Worker (optionnel mais recommandé)

```bash
sudo nano /etc/systemd/system/pdu-worker.service
```

```ini
[Unit]
Description=PDU Tracker Queue Worker
After=network.target

[Service]
User=deploy
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /var/www/pdu-tracker/artisan queue:work --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable pdu-worker
sudo systemctl start pdu-worker
```

---

## Étape 13 : Tester

Ouvrez votre navigateur et accédez à :
- `http://VOTRE_IP` (si pas de domaine)
- `https://VOTRE_DOMAINE.com` (si domaine configuré)

---

## Résumé VPS — Commandes en une fois

```bash
# Sur le serveur, après avoir uploadé le projet dans /var/www/pdu-tracker :
cd /var/www/pdu-tracker
composer install --no-dev --optimize-autoloader
npm install && npm run build
cp .env.example .env
php artisan key:generate
# Modifier .env avec nano...
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R deploy:www-data . && sudo chmod -R 775 storage bootstrap/cache database
sudo systemctl reload nginx
```

---
---

# OPTION 2 : Hébergement Local + ngrok (Démo/Présentation TFE)

**Idéal pour** : Présentation de soutenance, démo devant un jury, test temporaire.
**Avantage** : Gratuit, pas besoin de serveur externe.
**Inconvénient** : L'accès s'arrête quand vous fermez Laragon/ngrok.

---

## Prérequis
- Laragon installé et fonctionnel (déjà fait ✓)
- Votre projet tourne en local sur `http://pdu-tracker.test` ou `http://localhost`
- Connexion internet

---

## Étape 1 : Installer ngrok

1. Allez sur https://ngrok.com
2. Créez un compte gratuit
3. Téléchargez ngrok pour Windows
4. Extrayez `ngrok.exe` dans un dossier (ex: `C:\ngrok\`)
5. Ajoutez le token d'authentification :

```powershell
C:\ngrok\ngrok.exe config add-authtoken VOTRE_TOKEN_ICI
```

---

## Étape 2 : Lancer votre application avec Laragon

1. Ouvrez **Laragon**
2. Cliquez sur **"Tout démarrer"** (Apache + MySQL)
3. Vérifiez que `http://pdu-tracker.test` fonctionne dans votre navigateur

---

## Étape 3 : Compiler les assets pour la production

```powershell
cd "c:\Users\The Zenith\Downloads\Travail de fin d'etudes(TFE)\site\pdu-tracker"
npx vite build
```

---

## Étape 4 : Exposer avec ngrok

Ouvrez un nouveau terminal PowerShell :

```powershell
C:\ngrok\ngrok.exe http 80 --host-header=pdu-tracker.test
```

OU si vous utilisez `php artisan serve` sur le port 8000 :

```powershell
C:\ngrok\ngrok.exe http 8000
```

---

## Étape 5 : Récupérer l'URL publique

ngrok affichera quelque chose comme :

```
Session Status    online
Forwarding        https://abc123.ngrok-free.app -> http://localhost:80
```

L'URL `https://abc123.ngrok-free.app` est accessible par **tout le monde sur internet**.

---

## Étape 6 : Mettre à jour APP_URL (important pour Inertia.js)

Modifiez temporairement votre fichier `.env` :

```env
APP_URL=https://abc123.ngrok-free.app
```

Puis videz le cache :

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan config:clear
```

---

## Étape 7 : Partager le lien

Envoyez le lien `https://abc123.ngrok-free.app` à toute personne qui veut accéder à l'application.

> ⚠️ **Attention** :
> - Le lien change à chaque redémarrage de ngrok (version gratuite)
> - L'application n'est accessible que tant que votre PC est allumé et ngrok tourne
> - La version gratuite affiche une page d'avertissement ngrok au premier accès

---

## Alternative à ngrok : Cloudflare Tunnel (gratuit, plus stable)

1. Créez un compte sur https://dash.cloudflare.com
2. Installez `cloudflared` : https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/
3. Lancez :

```powershell
cloudflared tunnel --url http://localhost:80
```

Avantage : Pas de page d'avertissement, URL stable possible avec un domaine Cloudflare.

---
---

# Tableau comparatif

| Critère | VPS | ngrok (local) |
|---------|-----|---------------|
| **Coût** | 5-10€/mois | Gratuit |
| **Disponibilité** | 24h/24 | Quand votre PC est allumé |
| **URL stable** | Oui (domaine) | Non (change à chaque fois) |
| **Performance** | Bonne | Dépend de votre connexion |
| **Sécurité** | HTTPS + firewall | HTTPS via ngrok |
| **Idéal pour** | Production réelle | Démo TFE / présentation |
| **Difficulté** | Moyenne | Très facile |

---

# Recommandation

- **Pour la soutenance TFE** : Utilisez **ngrok** (option 2). C'est gratuit et rapide.
- **Pour une mise en production réelle** : Prenez un **VPS** (option 1) avec un nom de domaine.
