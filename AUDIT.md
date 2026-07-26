# DataForge — Audit technique complet

**Auteur** : revue technique (CTO / Lead Architect / Data Engineer / Data Analyst / Product Designer)
**Date** : 26/07/2026
**Portée** : intégralité du code — architecture, base de données, modèles, repositories, services, contrôleurs, vues, JS, CSS, scripts Python, sécurité, performance, DevOps.
**Méthode** : lecture directe du code + vérifications ciblées (voir citations `fichier:ligne`). Aucune affirmation de ce rapport n'est une supposition générique — chaque point ⚠️/❌/🚨 renvoie à un fichier réel.

---

## Résumé exécutif

DataForge est allé beaucoup plus loin que la plupart des projets "vibe-coded" : Repository Pattern respecté partout, Service Layer propre, séparation Laravel/Python nette via un contrat JSON unique, 40+ tables/relations cohérentes, pipeline en 11 étapes réellement piloté par des données (pas des fanions arbitraires), IA branchée à trois niveaux (chat, insights automatiques, suggestions de graphiques). Le design system est réel, pas un habillage Bootstrap par défaut.

Mais deux catégories de risques sont sous-estimées pour un usage au-delà d'un poste personnel :

1. **Il n'y a aucun test automatisé, aucun contrôle de version, et aucun traitement asynchrone.** Ce ne sont pas des détails : ce sont les trois choses qui, ensemble, rendent le projet fragile dès qu'il grossit ou qu'il est utilisé par plus d'une personne.
2. **Les traitements Python sont synchrones et chargent tout en mémoire.** Un fichier de 80 Mo bloque une requête HTTP pendant la durée du traitement (jusqu'à 120s de timeout configuré) et peut faire tomber le worker PHP en cas de pic mémoire pandas. C'est le premier obstacle réel à "importation de fichiers très volumineux" (point 1 de la liste métier).

Le reste — qualité, nettoyage, jointures, EDA, dashboards, IA, rapports — est fonctionnellement complet et globalement bien conçu. Le travail qui reste n'est pas "construire les fonctionnalités manquantes", c'est **industrialiser ce qui existe déjà**.

**Note globale actuelle (niveau production multi-utilisateurs) : 6.5/10.**
**Note globale (niveau outil personnel / prototype avancé) : 8.5/10.**
L'écart entre les deux notes est exactement l'écart entre "ça marche très bien pour moi" et "ça tient la charge/le temps/les autres utilisateurs".

---

## Phase 1 — Audit par module

### 1. Architecture générale

- ✅ MVC + Repository Pattern + Service Layer appliqués de façon **cohérente et sans exception** sur les 12+ modules (Import, Qualité, Pipeline, Analyse, Visualisation, Dashboard, IA, Rapport, Export, Relations/Jointures, Tests statistiques, ML). Aucun contrôleur ne contient de logique métier ou de requête Eloquent brute au-delà du strict nécessaire.
- ✅ Un seul point de passage Laravel↔Python (`PythonRunnerService`), contrat JSON stable (`{"success": bool, "data"|"error"}`), jamais d'arguments CLI (évite l'injection de commande), chaque appel tracé dans `python_executions`.
- ✅ Extraction de `TableOnboardingService` a évité la duplication entre import initial et jointure — bon réflexe d'ingénierie plutôt que copier-coller.
- ⚠️ **Un seul verrou d'autorisation pour tout** : `ProjectPolicy` protège tout le graphe de données (dataset → table → colonne → analyse → visualisation…) uniquement via la relation implicite "cette table appartient à ce projet, ce projet m'appartient". Ça fonctionne aujourd'hui parce que le binding de route est toujours scoppé (`scopeBindings()`), mais c'est un fil unique : si une route oublie un jour l'imbrication `projects/{project}/datasets/{dataset}/tables/{table}`, il n'y a pas de seconde barrière.
- 🚨 **Aucun contrôle de version (`git init` jamais fait).** Des dizaines de migrations, plus de 60 fichiers de service/contrôleur, un design system entier — tout ça n'existe qu'en un seul exemplaire sur disque. Pas d'historique, pas de diff possible, pas de retour arrière, pas de sauvegarde hors du poste. C'est le risque le plus élevé du projet, largement devant les manques fonctionnels : une erreur de manipulation ou une panne disque perd tout, sans aucun recours.

### 2. Base de données / Migrations

- ✅ Schéma propre : `projects → datasets → dataset_tables → dataset_columns`, avec journaux séparés (`quality_reports`, `analyses`, `pipeline_steps`, `dataset_relationships`, `statistical_tests`, `ml_analyses`, `reports`) qui **n'écrasent jamais l'historique** (nouvelle ligne à chaque exécution) — bon choix pour la traçabilité analytique.
- ✅ `python_executions` journalise chaque appel Python avec durée, entrée, sortie — excellent pour le débogage et l'audit.
- ⚠️ **MySQL, pas PostgreSQL.** Le cahier des charges le plus récent demande PostgreSQL ; le projet tourne en MySQL/MariaDB depuis le premier commit (`.env: DB_CONNECTION=mysql`). Deux migrations utilisent en plus une syntaxe `ALTER TABLE ... MODIFY COLUMN ... ENUM(...)` strictement MySQL (`2026_07_25_212729_...`, `2026_07_25_220548_...`) — non portable telle quelle sur PostgreSQL. **Point de décision, pas un oubli** : je ne migre pas la base de données sans validation explicite, voir section "Décisions à trancher" en fin de rapport.
- ⚠️ Aucune politique de rétention sur `python_executions` (298 lignes après une journée de tests intensifs, chaque ligne contenant les payloads JSON complets). Sans purge, cette table grossira indéfiniment.
- 🚨 **Seul `Project` a `SoftDeletes`.** Supprimer un `Dataset` (et donc toutes ses tables, colonnes, analyses, visualisations, insights en cascade) est **définitif et immédiat** — un clic malheureux sur "Supprimer" perd tout le travail d'analyse associé, sans confirmation à deux niveaux ni corbeille.

### 3. Modèles Eloquent

- ✅ `$fillable` strict partout (jamais de `$guarded = []`), casts systématiques vers les enums PHP natifs (`ColumnType`, `ChartType`, `RelationshipStatus`…) — élimine une classe entière de bugs "string magique mal orthographiée".
- ✅ Relations bien nommées et cohérentes ; `Project::pipelineProgress()` calcule un état réel plutôt que de faire confiance à un champ déclaratif.
- ⚠️ `Project::pipelineProgress()` exécute ~10 requêtes `exists()` à chaque affichage de la page projet. Correct pour un usage mono-utilisateur, mais à mettre en cache (`Cache::remember`, TTL court) si le nombre de projets/tables grossit.
- ❌ Pas de `casts()` typés PHP 8.1+ (méthode statique) — le projet utilise encore la propriété `protected $casts`. Ce n'est pas un bug (Laravel 10 supporte les deux), juste une modernisation possible sans urgence.

### 4. Repositories

- ✅ Une interface + une implémentation Eloquent par agrégat, injectées partout via le conteneur (`RepositoryServiceProvider`) — jamais de `new Model()` en dur dans les services. Permet de remplacer Eloquent par autre chose (ou de mocker en test) sans toucher aux services.
- ✅ Aucune duplication de requêtes constatée entre repositories.
- ⚠️ Certains repositories (ex. `EloquentStatisticalTestRepository`) n'ont qu'un `find`/`forTable`/`create`/`delete` — c'est très bien tant que c'est tout ce dont le service a besoin, mais si un futur besoin de filtrage/pagination apparaît, il faudra l'ajouter à l'interface (pas un défaut, juste une remarque de vigilance API).

### 5. Services (Service Layer)

- ✅ Chaque service a une seule responsabilité claire (`DataQualityService`, `ExploratoryAnalysisService`, `JoinService`, `MlAnalysisService`…), aucun "God Service".
- ✅ Les erreurs Python métier (`{"success": false}`) sont converties en exceptions typées (`PythonExecutionException`) plutôt que remontées comme des tableaux à vérifier partout — bonne discipline d'erreurs.
- ✅ Les échecs non critiques (génération d'insights IA, visualisations par défaut) sont **volontairement absorbés** (`try/catch` + `Log::warning`) pour ne jamais faire échouer un import à cause d'un bonus — bon compromis robustesse/fonctionnalité.
- ⚠️ Aucun mécanisme de **retry** sur les appels Python : si Groq/le pont Python répond une erreur transitoire (timeout réseau, rate limit), l'échec est définitif pour cette requête — l'utilisateur doit relancer manuellement. Acceptable en usage personnel, fragile en production.

### 6. Contrôleurs

- ✅ Fins, sans logique métier, chaque action fait `authorize` → `service` → `redirect/view`. Le pattern est identique sur les ~16 contrôleurs, ce qui rend le code prévisible.
- ✅ Séparation lecture/écriture propre (`GET` = affichage, `POST/DELETE` = action + redirection avec message flash).
- ⚠️ Pas de version API (JSON pur) — tout est couplé au rendu Blade. Si un jour DataForge doit exposer une API (app mobile, intégration externe), il faudra dupliquer une partie de la logique de présentation, pas la logique métier (qui reste dans les services, donc réutilisable).

### 7. Routes

- ✅ Nommage cohérent et imbrication logique (`projects.datasets.tables.analysis.show`), `scopeBindings()` utilisé correctement pour garantir qu'un `{table}` appartient bien au `{dataset}` de l'URL.
- 🚨 **Aucune route n'est limitée en débit (`throttle`)** — y compris les routes qui déclenchent des appels Python coûteux (import, EDA, ML, génération de rapport PDF) ou des appels à l'API Groq (facturés). Un utilisateur (ou un script malveillant avec une session volée) peut déclencher des dizaines d'exécutions Python ou d'appels IA par seconde sans aucune limite applicative.

### 8. Sécurité (FormRequests, Policies, autorisation)

- ✅ Chaque action de mutation passe soit par un `$this->authorize()` explicite, soit par un `FormRequest::authorize()` équivalent (`ImportDatasetRequest`, `UpdateProjectRequest`) — vérifié sur les 16 contrôleurs, aucune faille d'autorisation trouvée.
- ✅ Limite de taille de fichier réellement appliquée à l'import (`ImportDatasetRequest`, 100 Mo/fichier) + whitelist d'extensions — ce n'est pas juste "on espère que l'utilisateur envoie un bon fichier".
- ✅ Aucun payload Python construit par concaténation de chaînes : tout passe par des fichiers JSON temporaires (`Symfony\Process` avec tableau d'arguments), donc pas d'injection de commande possible même si un nom de colonne contient des caractères spéciaux.
- ✅ `df.eval()` (preprocessing "créer une colonne calculée") est le seul point où l'utilisateur influence une expression évaluée dynamiquement côté Python — c'est un risque **connu et documenté** dans le code, pas un oubli, mais reste le point d'attention n°1 si DataForge devient multi-utilisateur non fiable (un utilisateur pourrait tenter d'y injecter du code Python arbitraire selon les capacités réelles de `pandas.eval`).
- 🚨 **Zéro rate limiting** (voir point 7) — c'est autant un problème de sécurité que de coût (chaque appel IA consomme du quota Groq).

### 9. Vues Blade / Composants

- ✅ Réutilisation systématique de composants (`x-transform-modal`, `x-pipeline-stepper`) plutôt que de dupliquer le HTML entre pages.
- ✅ Depuis la refonte visuelle : design system cohérent (tokens CSS, dark mode réel, typographie à trois registres) propagé à **toutes** les pages sans réécriture individuelle, grâce à la discipline "tout passe par `.df-card`/`.df-stat-tile`/les classes Bootstrap déjà retargetées".
- ⚠️ Certaines pages (Analyse, ML) ont des formulaires modaux assez longs (beaucoup de champs conditionnels en JS `data-steps`) — fonctionnel, mais la logique de révélation de champs est dupliquée entre `transform-modal.js`, le générateur de visualisations et les tests statistiques/ML. Un composant Blade générique "formulaire à étapes conditionnelles" factoriserait ça.
- ❌ Pas de vue "aperçu des données brutes avant import" — l'utilisateur découvre ce qui a été importé seulement après coup (page "Données"), jamais avant de valider l'import.

### 10. JavaScript

- ✅ Pas de framework front lourd — vanilla JS modulaire (un fichier par responsabilité : `charts.js`, `dashboard-filter.js`, `data-grid.js`, `dropzone.js`, `theme-toggle.js`), chacun avec une garde d'auto-désactivation (`if (!container) return`) qui permet de tout charger sur toutes les pages sans effet de bord.
- ✅ Les deux bugs de "race condition" (réponses AJAX qui arrivent dans le désordre, dans `data-grid.js` et `dashboard-filter.js`) ont été trouvés et corrigés avec un garde de séquence — signe que le JS a été testé en conditions réelles, pas juste écrit puis oublié.
- ⚠️ Un seul bundle JS (`app.js`) chargé sur **toutes** les pages, actuellement ~1.24 Mo non minifié réduit à ~368 Ko gzip. Pas critique aujourd'hui, mais Vite avertit déjà sur la taille du chunk — à surveiller si de nouvelles librairies (Chart.js/ApexCharts/Gridstack pèsent la majorité du poids) s'ajoutent.
- ❌ Aucun test JS automatisé (pas de Vitest/Jest) — la seule validation a été manuelle via navigateur à chaque fonctionnalité.

### 11. CSS / Design system

- ✅ Système de tokens CSS custom properties correctement pensé pour le dark mode (pas une inversion — chaque token redéfini indépendamment), résolution du thème synchrone avant premier rendu (pas de flash).
- ✅ Palette et typographie délibérées, cohérentes avec l'identité du produit plutôt que des choix Bootstrap par défaut.
- ⚠️ Pas de purge/tree-shaking CSS (Bootstrap complet importé) — 240 Ko de CSS dont une bonne partie n'est probablement jamais utilisée (classes Bootstrap non exploitées par l'app). Un `PurgeCSS`/`content` scanning réduirait sensiblement le poids.

### 12. Scripts Python

- ✅ Contrat d'entrée/sortie strictement identique sur les 15 scripts (`run_script(main)`), zéro divergence — un nouveau script s'écrit en copiant n'importe quel autre et ça fonctionne du premier coup.
- ✅ Détection de types soigneuse (`is_numeric_series` exclut les booléens, `sep=None` auto-détecte le séparateur CSV) — corrige deux bugs réels trouvés en testant sur les vraies données de l'utilisateur, pas des cas synthétiques.
- ✅ Le bug `pd.to_datetime` sur colonnes numériques (semaines 1-52 interprétées comme timestamps epoch) a été détecté et corrigé pendant le développement du module ML — preuve d'un test réel contre des données réelles plutôt qu'un jeu d'essai fabriqué.
- 🚨 **Aucune limite mémoire ni chunking.** `pd.read_csv`/`read_excel` chargent le fichier entier en RAM à chaque étape du pipeline (import, qualité, nettoyage, EDA, ML...) — un fichier de 100 Mo (la limite d'upload actuelle) peut représenter plusieurs centaines de Mo une fois désérialisé en DataFrame, multiplié par le nombre d'étapes qui relisent le cache Parquet indépendamment. Sur un poste avec peu de RAM disponible, c'est le scénario de plantage le plus probable.
- ⚠️ Chaque étape du pipeline **relit le fichier Parquet depuis le disque** à chaque exécution (qualité, EDA, ML, export...) plutôt que de réutiliser un DataFrame déjà en mémoire dans un process Python persistant — c'est le prix normal d'une architecture "process Python jetable par appel" (plus simple, plus sûre), mais ça a un coût I/O qui devient sensible sur de gros fichiers.

### 13. Performance

- ✅ Pas de N+1 constaté sur les pages vérifiées (chargement eager cohérent).
- ⚠️ Chaque appel `PythonRunnerService::run()` a un coût fixe de démarrage de l'interpréteur Python (~100-300ms) en plus du traitement — négligeable à l'unité, mais un import Excel multi-feuilles avec auto-génération (colonnes + qualité + EDA + insights IA + 3 visualisations) **par table** peut facilement chaîner 8-10 appels Python synchrones à la suite, donc 8-10 démarrages d'interpréteur + 8-10 appels réseau Groq (si l'IA insight est activée) — tout **dans la même requête HTTP**. C'est directement la cause du choix `PYTHON_TIMEOUT=120s`.
- 🚨 **Aucune file d'attente (queue).** C'est le changement d'architecture le plus impactant qu'il reste à faire : tant que l'import reste synchrone, il y a un plafond dur sur "quelle taille de fichier / combien de feuilles / combien d'insights IA" avant que la requête timeout ou que l'utilisateur pense que l'app a planté.

### 14. Tests / Qualité du code

- 🚨 **Aucun test automatisé réel.** `tests/Feature/ExampleTest.php` et `tests/Unit/ExampleTest.php` sont les fichiers par défaut de Laravel, jamais remplacés. Toute la validation faite pendant ce projet (et il y en a eu beaucoup, sérieusement) a été manuelle : navigateur réel, `tinker`, scripts Python en ligne de commande. C'est rigoureux sur le moment, mais **ça ne protège pas contre la régression** — rien n'empêche qu'une future modification casse silencieusement l'import Excel multi-feuilles ou le calcul de corrélation sans que personne ne s'en aperçoive avant de le découvrir en production.
- ✅ En revanche, la discipline de vérification manuelle a été bonne : chaque fonctionnalité listée dans ce projet a été testée avec de vraies données avant d'être déclarée terminée (pas de "ça devrait marcher").

### 15. DevOps / Infrastructure

- 🚨 Pas de git (déjà couvert en section 1 — je le remets ici car c'est aussi un sujet DevOps).
- ❌ Pas de CI, pas de linter automatisé exécuté en pré-commit (PHP-CS-Fixer/Pint, ESLint, Black/Ruff pour Python), pas de `.env.example` vérifié à jour.
- ❌ Aucune stratégie de sauvegarde de la base de données ni du dossier `storage/app` (qui contient tous les fichiers importés + caches Parquet + rapports PDF générés).
- ⚠️ Dépendance à Apache/XAMPP en local (`php artisan serve` cassé sous Windows pour ce projet, documenté) — pas un problème en soi, mais aucune configuration de déploiement (Docker explicitement exclu du cahier des charges, donc pas de reproche ici) n'existe pour un environnement autre que ce poste précis.

---

## Phase 2 — Comparaison avec la vision DataForge

| # | Fonctionnalité | Statut | Note /10 | Comment l'améliorer |
|---|---|---|---|---|
| 1 | Importer des données | ✅ Présente | 8/10 | Fonctionne pour CSV/Excel/JSON/Parquet avec détection de délimiteur et gestion des feuilles vides. Manque : import depuis une URL/API, import SQL (le format existe dans l'enum mais est explicitement bloqué), traitement asynchrone pour les gros fichiers. |
| 2 | Comprendre automatiquement les données | ✅ Présente | 8/10 | Détection de type par colonne + stats + échantillons, automatique dès l'import. Manque : détection de la langue/locale des dates (actuellement heuristique simple), détection de colonnes d'identifiant (clé primaire probable) pour accélérer la détection de relations. |
| 3 | Détecter les problèmes (qualité) | ✅ Présente | 8/10 | Score de qualité + grade + résumé automatique dès l'import. Manque : explication actionnable par problème détecté ("cette colonne a 40% de valeurs manquantes, voici les 3 façons de la traiter" plutôt qu'un chiffre seul). |
| 4 | Nettoyer les données | ✅ Présente | 7/10 | Dédoublonnage, correction de dates, espaces, casse — couvre les cas courants. Manque : nettoyage suggéré automatiquement par l'IA à partir de l'audit qualité (aujourd'hui l'utilisateur choisit lui-même l'opération, sans recommandation ciblée). |
| 5 | Prétraiter les données | ✅ Présente | 8/10 | Large catalogue d'opérations (renommer, encoder, normaliser, catégoriser, colonne calculée...), rejouable. Bien couvert. |
| 6 | Fusionner plusieurs datasets | ✅ Présente | 8/10 | Jointure réelle (inner/left/right/outer) à partir d'une relation confirmée, résultat traité comme une table normale (qualité/EDA/insights automatiques). Manque : jointure à plus de deux tables en une seule opération (aujourd'hui il faut chaîner les jointures deux par deux). |
| 7 | Détecter les relations entre feuilles Excel | ✅ Présente | 8/10 | Détection par similarité de nom + chevauchement de valeurs, à l'échelle du projet entier (pas seulement au sein d'un même classeur) — dépasse la demande initiale. Quelques faux positifs à faible confiance (attendu et déjà signalé à l'utilisateur), l'utilisateur reste seul décideur (bon choix UX). |
| 8 | Construire automatiquement les jointures | ✅ Présente | 7/10 | Une fois la relation confirmée, un clic suffit. Manque : suggestion automatique du type de jointure le plus pertinent (aujourd'hui "left" par défaut sans justification), aperçu du résultat avant validation définitive. |
| 9 | Générer des statistiques descriptives | ✅ Présente | 9/10 | Moyenne/médiane/mode/écart-type/variance/quartiles par colonne numérique, calculé automatiquement. Complet. |
| 10 | Analyse exploratoire | ✅ Présente | 8/10 | Stats + corrélations + histogrammes + boxplots + distributions, tout automatique. Manque : détection de tendance temporelle automatique quand une colonne date existe (aujourd'hui il faut aller manuellement dans le module ML pour ça). |
| 11 | Détecter les anomalies | ⚠️ Partiellement présente | 5/10 | Les outliers IQR sont calculés dans l'audit qualité, mais ne sont pas mis en avant comme une fonctionnalité de détection d'anomalie à part entière (pas d'alerte visuelle dédiée, pas de liste "voici vos 5 valeurs les plus suspectes"). À renforcer. |
| 12 | Suggérer les KPI | ❌ Absente | 2/10 | Rien n'existe pour suggérer *quels chiffres* méritent de devenir des KPI de dashboard. Les widgets KPI existent (l'utilisateur choisit manuellement colonne + statistique), mais aucune suggestion automatique ("cette colonne a une forte variance et un impact business probable, envisagez-en un KPI"). Vrai manque. |
| 13 | Choisir automatiquement les graphiques les plus pertinents | ⚠️ Partiellement présente | 6/10 | L'IA suggère des graphiques en texte ("un histogramme de X serait utile") dans les insights, et 3 visualisations par défaut sont créées automatiquement. Manque le dernier kilomètre : transformer la suggestion textuelle en un **bouton "créer ce graphique"** qui pré-remplit le formulaire — aujourd'hui l'utilisateur doit relire la phrase et recréer le graphique lui-même. |
| 14 | Générer un dashboard | ✅ Présente | 7/10 | Constructeur drag-and-drop complet (Gridstack), filtre global fonctionnel, mais **pas de génération automatique** d'un premier dashboard — l'utilisateur part toujours d'une page vide. |
| 15 | Produire des insights IA | ✅ Présente | 8/10 | 9 catégories d'insights (résumé, points clés, anomalies, tendances, opportunités, risques, recommandations, graphiques suggérés, conclusion), ancrés strictement dans les données réelles (pas d'hallucination constatée pendant les tests). Manque : bouton "régénérer avec plus de contexte" si l'utilisateur n'est pas satisfait d'une génération. |
| 16 | Générer un rapport professionnel | ✅ Présente | 8/10 | PDF complet (qualité + EDA + insights + graphiques rendus en image), généré en un clic. Manque : personnalisation du contenu (choisir quelles tables/sections inclure) — aujourd'hui c'est tout ou rien. |
| — | Exporter les résultats | ✅ Présente | 8/10 | CSV/Excel/JSON par table, PNG par graphique. Manque : export du dashboard entier en une image/PDF unique. |

**Moyenne pondérée : 7.1/10.** Le cœur du pipeline (import → nettoyage → analyse → dashboard → rapport) est solide. Les points faibles sont concentrés sur la couche "intelligence proactive" (suggestion de KPI, détection d'anomalie mise en avant, lien direct insight→action).

---

## Phase 3 — Les 25 vrais problèmes du Data Analyst

| # | Problème | Présent ? | Qualité | Comment améliorer |
|---|---|---|---|---|
| 1 | Fichiers très volumineux | ⚠️ Partiel | 4/10 | Limite de 100 Mo/fichier, mais traitement synchrone + pandas sans chunking. **Priorité #1** : passer l'import (et idéalement toutes les étapes lourdes) en jobs de queue Laravel avec suivi de progression, et évaluer `chunksize` pandas pour les très gros CSV. |
| 2 | Plusieurs fichiers à la fois | ✅ Oui | 8/10 | `files[]` multiple à l'import, chacun traité indépendamment. Bon. |
| 3 | Excel multi-feuilles | ✅ Oui | 9/10 | Une table par feuille, feuilles vides ignorées automatiquement (bug réel corrigé). Très bon. |
| 4 | Relations entre plusieurs feuilles | ✅ Oui | 8/10 | Détection à l'échelle du projet, pas seulement du classeur. Voir #7 ci-dessus pour les axes d'amélioration. |
| 5 | Compréhension automatique des colonnes | ✅ Oui | 8/10 | Type détecté + stats + échantillon, sans action utilisateur. |
| 6 | Colonnes inutiles | ✅ Oui | 6/10 | Un indicateur `is_useless` existe et s'affiche (badge "peu utile"), mais aucune action groupée pour les supprimer en un clic depuis cette détection. |
| 7 | Valeurs manquantes | ✅ Oui | 8/10 | Comptage + pourcentage par colonne, visible partout. |
| 8 | Doublons | ✅ Oui | 8/10 | Détection dans l'audit qualité + opération de dédoublonnage dédiée dans le pipeline. |
| 9 | Incohérences | ⚠️ Partiel | 5/10 | La casse et les espaces sont corrigés ; les incohérences de fond (ex. "France"/"FR"/"french" pour la même valeur catégorielle) ne sont pas détectées automatiquement. |
| 10 | Formats incorrects | ✅ Oui | 7/10 | Correction de dates dédiée, conversion de type disponible en prétraitement. |
| 11 | Uniformisation automatique | ⚠️ Partiel | 5/10 | Les opérations existent (normaliser, standardiser, encoder) mais restent **manuelles** — pas de proposition automatique "voici ce que je uniformiserais". |
| 12 | Pipeline de nettoyage | ✅ Oui | 8/10 | Catalogue complet, chaque étape journalisée. |
| 13 | Historique des transformations | ✅ Oui | 9/10 | Chaque étape stockée avec ordre, libellé, paramètres, lignes affectées, horodatage. Très complet. |
| 14 | Pipeline rejouable | ✅ Oui | 8/10 | Vue Notebook dédiée, replay fonctionnel testé. |
| 15 | Statistiques automatiques | ✅ Oui | 9/10 | Voir Phase 2 #9. |
| 16 | Corrélations | ✅ Oui | 9/10 | Matrice complète, recalculée à chaque analyse et à chaque filtre de dashboard. |
| 17 | Heatmaps | ✅ Oui | 9/10 | Rendu ApexCharts interactif + rendu Matplotlib statique pour les rapports PDF. |
| 18 | Détection des outliers | ⚠️ Partiel | 6/10 | Calculés (méthode IQR) et stockés dans l'audit qualité et les boxplots, mais pas mis en avant comme fonctionnalité de détection d'anomalie autonome (même remarque que Phase 2 #11). |
| 19 | Choix automatique des meilleurs graphiques | ⚠️ Partiel | 6/10 | 3 graphiques par défaut + suggestions textuelles IA. Manque le pont direct suggestion → création (voir Phase 2 #13). |
| 20 | Génération automatique d'insights | ✅ Oui | 8/10 | Voir Phase 2 #15. |
| 21 | Génération automatique de recommandations | ✅ Oui | 8/10 | Catégorie dédiée dans les insights, ancrée aux données réelles. |
| 22 | Génération automatique de rapports | ✅ Oui | 8/10 | Voir Phase 2 #16. |
| 23 | Export propre | ✅ Oui | 8/10 | Voir Phase 2 "Exporter les résultats". |
| 24 | Sauvegarde complète des projets | 🚨 Absent | 2/10 | Aucune sauvegarde applicative de `storage/app` ni de la base. Si le disque est perdu, tout est perdu — fichiers sources, caches Parquet, rapports générés, base de données. |
| 25 | Reprise d'une analyse ultérieurement | ✅ Oui | 8/10 | Tout est persistant (projets, datasets, analyses, dashboards) — un utilisateur peut fermer l'app et reprendre exactement où il en était. Le seul vrai manque est la sauvegarde/export d'un projet complet vers un fichier portable (pour changer de machine). |

---

## Synthèse : par où commencer

Classé par rapport risque/effort, **sans coder pour l'instant** — c'est à vous de valider les priorités avant que je conçoive puis implémente quoi que ce soit :

1. **`git init` immédiat.** Cinq minutes, risque quasi nul, élimine le risque de perte totale du projet. Je recommande de le faire avant toute autre modification, quelle qu'elle soit.
2. **Stratégie de sauvegarde** de `storage/app` + dump SQL régulier — même un script manuel planifié suffirait pour commencer.
3. **File d'attente (queue) pour les traitements Python longs** — c'est le changement d'architecture qui débloque réellement "gros fichiers" et "plusieurs insights IA en cascade sans timeout". Impact large (touche import, EDA, ML, rapport), donc à concevoir soigneusement avant d'implémenter (queue driver, UI de progression, gestion des échecs).
4. **Suite de tests automatisés minimale** sur les chemins critiques (import CSV/Excel, calcul de qualité, jointure, génération de rapport) — pas besoin d'une couverture à 100%, mais un filet sur les 5-6 parcours qui feraient le plus mal en cas de régression silencieuse.
5. **Rate limiting** sur les routes coûteuses (import, ML, IA, export, rapport) — peu d'effort, protège à la fois le coût Groq et la stabilité du serveur.
6. **Suggestion de KPI + pont "insight → créer ce graphique"** — les deux manques fonctionnels les plus visibles pour l'utilisateur final, une fois les fondations (1-5) sécurisées.

Je n'implémente rien de cette liste tant que vous ne me dites pas laquelle attaquer en premier — et pour tout ce qui touche à un changement d'architecture (notamment la queue et une éventuelle migration PostgreSQL), je reviendrai avec une proposition de conception détaillée avant d'écrire la moindre ligne de code, comme demandé.

## Décisions à trancher avec vous

- **PostgreSQL vs MySQL** : le cahier des charges le plus récent mentionne PostgreSQL, le projet est bâti sur MySQL depuis le début (schéma, migrations natives `ENUM`, tests). Voulez-vous une migration réelle (travail non trivial : réécrire les migrations à base d'`ENUM`, revalider chaque requête), ou le mention PostgreSQL était-elle une erreur de copier-coller du cahier des charges ?
- **Queue driver** : base de données (`database`), Redis, ou autre ? Ça détermine l'infrastructure nécessaire (Redis = un service de plus à faire tourner en local).
- **Portée de la suite de tests** : je propose de commencer par les 5-6 parcours les plus critiques plutôt qu'une couverture exhaustive immédiate — d'accord avec cette approche progressive ?
