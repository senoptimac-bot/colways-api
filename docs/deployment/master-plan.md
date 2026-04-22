# 🏆 Master Plan de Déploiement Colways (Professionnel)

*Ce document est la référence absolue pour le pilotage du déploiement de Colways. Il garantit une transition fluide du local vers la production sans bugs.*

---

## 🏗️ Architecture Cible

| Composant | Technologie | Hébergement | Rôle |
| :--- | :--- | :--- | :--- |
| **Backend** | Laravel 11 | Hostinger | API, Base de données, Logique métier |
| **Frontend** | React Native (Web) | Vercel | Interface utilisateur PWA ultra-rapide |
| **Stockage** | Cloudinary | Cloudinary SaaS | Optimisation et diffusion des images (CDN) |
| **Pipeline** | GitHub Actions | GitHub | Automatisation du déploiement (CI/CD) |

---

## 🛠️ Phase 1 : Automatisation de l'API (Zéro-Manuel)

L'objectif est de supprimer l'usage du "File Manager" Hostinger pour éviter toute corruption de fichier.

### 📋 Actions de Pilotage :
1. **GitHub Flow** : Le code est "poussé" sur la branche `main`.
2. **Déploiement Automatique** : GitHub se connecte à Hostinger via FTP sécurisé.
3. **Synchronisation DB** : Le script lance `php artisan migrate --force` automatiquement pour que la base de données soit toujours à jour.
4. **Optimisation** : Activation automatique de `config:cache` et `route:cache` pour une vitesse maximale sur le serveur.

---

## 📱 Phase 2 : Excellence Web PWA (Fluidité Totale)

Nous ne voulons pas d'un simple site web, nous voulons une **PWA (Progressive Web App)** qui se comporte comme une application native.

### 📋 Points de Vigilance :
- **Safe Area Insets** : Correction chirurgicale des marges pour que le contenu ne soit pas caché par l'encoche de l'iPhone ou la barre Safari.
- **Navigation Bar** : Fixer la barre de navigation en bas de l'écran (Sticky) pour une ergonomie parfaite au pouce.
- **Icône "Installer l'app"** : Configuration du manifeste pour permettre aux utilisateurs d'ajouter Colways à leur écran d'accueil avec une icône premium.
- **Domaine Personnalisé** : Liaison du domaine professionnel (ex: `app.colways.com`) via Vercel.

---

## 🔒 Phase 3 : Sécurité & Monitoring

- **SSL/HTTPS** : Forçage du HTTPS sur tous les points d'entrée.
- **Logs de Production** : Centralisation des erreurs pour pouvoir les corriger avant que les utilisateurs ne s'en aperçoivent.
- **Gestion des Secrets** : Toutes les clés API (Google, Cloudinary) sont isolées dans les "Secrets" GitHub.

---

## 📅 Chronogramme d'Exécution

1. **[Etape 1]** : Configuration du Repo GitHub et secrets.
2. **[Etape 2]** : Premier déploiement automatisé de l'API vers Hostinger.
3. **[Etape 3]** : Déploiement du Frontend sur Vercel.
4. **[Etape 4]** : Correction des bugs UI Web spécifiques (Safari/Chrome).
5. **[Etape 5]** : Test final "Guerilla" (achat, publication, profil).

---

> [!TIP]
> **Vision à long terme :** Une fois le Web stabilisé et fluide, le déploiement sur l'App Store et le Play Store ne sera qu'une simple formalité technique d'empaquetage (Build Expo), car le cœur du système sera déjà parfait.
