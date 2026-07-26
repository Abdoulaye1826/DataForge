# DataForge — De pipeline technique à copilote intelligent

**Objet** : audit de l'existant face à la nouvelle vision (contexte métier, compréhension sémantique, IA proactive, pipeline proposé par l'IA, mémoire, notebook explicatif, dashboard justifié, insights par sévérité, rapport narratif). Aucun code n'a été modifié — ce document est une proposition à valider, conformément à la méthode demandée.

**Principe directeur retenu pour la suite** : chaque nouvelle capacité IA vient **s'ajouter en couche au-dessus de l'exécution existante**, jamais la remplacer. Le pipeline technique actuel (import, `PipelineStepService`, `clean_data.py`/`preprocess.py`, `VisualizationService`, `AiInsightService`...) reste le moteur d'exécution. Ce qui change, c'est qui décide de la prochaine étape : hier l'utilisateur choisissait seul dans un menu ; demain l'IA propose, l'utilisateur valide, et la même mécanique d'exécution tourne derrière. C'est ce qui permet de construire cette vision **sans rien casser** de ce qui fonctionne déjà.

---

## 1. Contexte métier avant l'import

**État actuel : ❌ Absent.** `Project` n'a ni domaine ni objectif. Rien ne guide l'IA sur "pourquoi ce projet existe".

**Pourquoi c'est la fondation de tout le reste** : quasiment chaque autre point de ce document (diagnostic qualité, insights, pipeline suggéré, rapport narratif) gagne en pertinence si l'IA sait qu'elle analyse des données RH plutôt que des données de vente. Sans ce point, l'IA "devine" à chaque fois depuis zéro à partir des seuls noms de colonnes.

**Architecture proposée** :
- Migration : `projects` gagne `domain` (enum : `finance, marketing, hr, health, commerce, logistics, industry, research, administration, other`) et `objective` (enum : `dashboard, understand_sales, detect_anomalies, forecast, segment_customers, clean_data, build_report, explore, other`), plus deux champs texte libres `domain_other`/`objective_other`.
- Deux nouveaux enums PHP (`ProjectDomain`, `ProjectObjective`), même pattern que tous les autres enums du projet.
- UI : un petit écran en 2 questions au moment de la création du projet (modal existant `#createProjectModal` ou équivalent étendu), modifiable ensuite depuis la page projet — jamais bloquant, "Autre" toujours disponible.
- Câblage : `AiContextBuilder::projectContext()` **et** `tableContextForInsights()` ajoutent une ligne "Domaine : X. Objectif : Y." en tête du contexte envoyé à l'IA. Un seul point de modification irrigue automatiquement le chat, les insights, et tout ce qui consomme déjà `AiContextBuilder` — aucune duplication.

**Impact** : migration + 1 modal + ~10 lignes dans `AiContextBuilder`. Risque faible, effort faible, bénéfice transversal élevé. **Je recommande de faire ce point en premier, quel que soit l'ordre choisi pour le reste.**

---

## 2. Compréhension sémantique des colonnes

**État actuel : ⚠️ Partielle.** `analyze_structure.py` détecte un **type technique** (`integer`, `date`, `category`...) et des stats — jamais une signification métier. Rien ne relie `customer_id` à "Identifiant client".

**Architecture proposée** :
- Nouveau service `SemanticColumnService`, même famille que `AiInsightService` : un appel IA **par table** (pas par colonne — un seul appel groupé pour rester économique), qui reçoit noms de colonnes + échantillons + type détecté + contexte métier (point 1), et renvoie `{colonne: {label: "Identifiant client", reasoning: "Valeurs uniques au format alphanumérique, nommage explicite"}}`.
- Migration : `dataset_columns` gagne `semantic_label` (string, nullable) et `semantic_reasoning` (text, nullable).
- Déclenchement : greffé dans `TableOnboardingService::onboard()`, juste après l'audit qualité, **guardé par try/catch** comme tous les autres enrichissements IA (un échec ne bloque jamais l'import).
- UI : sous chaque nom de colonne dans la page dataset et la page analyse, un sous-texte discret ("Identifiant client") avec l'explication au survol.

**Impact** : 1 nouveau service + 1 migration + modification de 2 vues. Effort modéré, bénéfice immédiat en lisibilité — c'est la fonctionnalité qui rapproche le plus DataForge d'un "collègue qui lit vos données avec vous" plutôt qu'un outil qui affiche des types.

---

## 3. Diagnostic qualité narratif (pas juste un score)

**État actuel : ⚠️ Partiel.** `quality_audit.py` produit un score, un grade et un résumé structuré (`summary`/`details` en JSON) — mais aucune phrase rédigée du type "Votre dataset obtient 81/100. Les principaux problèmes sont...".

**Architecture proposée** :
- Migration : `quality_reports` gagne `narrative` (text, nullable).
- Le résumé JSON déjà calculé (`quality_audit.py`) est réinjecté dans un appel IA court (même mécanique que `AiInsightService`, un seul appel, guardé) qui transforme les chiffres en diagnostic rédigé, avec le contexte métier du point 1 pour adapter le ton ("pour une analyse RH, 18% de valeurs manquantes sur la colonne salaire est particulièrement problématique...").
- UI : affiché en tête de la page Qualité, au-dessus des graphiques existants — le score chiffré reste visible (utile pour comparer dans le temps), le narratif l'accompagne.

**Impact** : réutilise entièrement l'infrastructure IA existante (`AiProviderInterface`, prompt guardé), juste un nouveau prompt + un nouveau champ. Effort faible à modéré.

---

## 4. IA proactive avec actions concrètes

**État actuel : ⚠️ Partiel — et c'est le point le plus mal compris si on ne regarde pas le code.** Les insights IA sont **déjà générés automatiquement sans que l'utilisateur ne demande rien** (`AiInsightService`, déclenché après chaque analyse). La vraie proactivité existe. Ce qui manque, c'est le **pont entre l'insight et l'action** : aujourd'hui "Je recommande d'analyser la baisse de mars" est une phrase inerte ; l'utilisateur doit lui-même aller chercher où créer ce test.

**Architecture proposée** :
- Migration : `ai_insights` gagne `suggested_action` (json, nullable) : `{"type": "forecast"|"statistical_test"|"visualization"|"cleaning_step", "params": {...}}`.
- Le prompt `AiInsightService` est étendu pour émettre, quand c'est pertinent, une action structurée en plus du texte (le modèle reste contraint au JSON strict déjà en place, on ajoute juste une clé optionnelle).
- UI : quand `suggested_action` est présent, l'insight affiche un vrai bouton ("Analyser cette baisse →") au lieu d'une simple puce. Le bouton pointe vers la page concernée avec les paramètres pré-remplis (ex : formulaire de prévision ML pré-rempli avec la bonne colonne de date/valeur).
- Aucune action n'est **jamais exécutée automatiquement** — le bouton amène au formulaire pré-rempli, l'utilisateur valide toujours en dernier ressort (cohérent avec la règle déjà en place ailleurs : "rien n'est appliqué sans confirmation").

**Impact** : le plus gros effort de ce point est le pré-remplissage de formulaire par type d'action (4 types à couvrir). Effort modéré, bénéfice élevé — c'est la différence entre "l'IA parle" et "l'IA agit avec vous".

---

## 5. Pipeline proposé par l'IA (inversion du contrôle)

**État actuel : ❌ Absent.** L'utilisateur choisit aujourd'hui chaque opération de nettoyage/prétraitement dans un menu. Rien ne propose de séquence.

**Architecture proposée** (le changement le plus structurant de ce document) :
- Nouveau modèle `PipelineSuggestion` (project_id, dataset_table_id, step_type, params json, rationale text, status : `pending/accepted/rejected`, computed_at).
- Nouveau service `PipelineRecommendationService::propose(DatasetTable $table)` : combine qualité + sémantique des colonnes (points 2-3) + contexte métier (point 1) en un appel IA qui renvoie une **liste ordonnée** d'étapes recommandées avec justification (`"Supprimer la colonne 'commentaire' — 91% de valeurs vides, aucune valeur analytique"`).
- **Point d'architecture important** : ce service ne fait **que proposer**. Chaque suggestion acceptée par l'utilisateur est convertie en un appel classique au `PipelineStepService` déjà existant (`clean_data.py`/`preprocess.py` inchangés) — zéro duplication de la logique d'exécution, zéro nouveau risque sur le moteur qui fonctionne déjà.
- UI : nouveau panneau "Suggestions IA" sur la page Notebook (ou dataset), chaque ligne avec Accepter/Rejeter, et un "Tout accepter" pour les cas évidents.

**Impact** : le morceau le plus lourd (nouveau modèle, nouveau service, nouvelle UI), mais l'exécution réelle ne réinvente rien. Effort élevé, bénéfice le plus proche de "l'IA fait le travail répétitif à ma place".

---

## 6. Mémoire inter-projets (apprentissage des habitudes)

**État actuel : ❌ Absent.** Aucune préférence utilisateur ne survit d'un projet à l'autre.

**Architecture proposée** :
- Nouvelle table `user_pipeline_preferences` (user_id, step_type, pattern json — ex `{"column_name": "fax"}` —, times_applied int, last_applied_at).
- Chaque fois qu'un utilisateur applique **manuellement** une étape reconnaissable (typiquement `drop_column` sur un nom de colonne), le compteur du motif correspondant s'incrémente (ou se crée).
- Quand `PipelineRecommendationService` (point 5) tourne sur une nouvelle table, il croise les noms de colonnes avec l'historique de l'utilisateur (seuil : motif vu ≥ 2 fois, pour éviter le bruit d'un cas isolé) et ajoute une suggestion à confiance renforcée : "Vous supprimez habituellement cette colonne."

**Avertissement honnête** : c'est la fonctionnalité la plus séduisante sur le papier et la plus incertaine en valeur réelle à court terme — elle ne devient utile qu'après plusieurs projets similaires traités par le même utilisateur. Je recommande de la construire **après** le point 5 (dont elle dépend directement) et de mesurer si elle est réellement utilisée avant d'investir davantage dessus.

---

## 7. Notebook explicatif (le "pourquoi", pas juste le "quoi")

**État actuel : ⚠️ Partiel.** Chaque étape du pipeline est déjà journalisée avec horodatage, ordre et paramètres (`PipelineStep.label`) — c'est un historique factuel complet. Ce qui manque : la justification.

**Architecture proposée** :
- Migration : `pipeline_steps` gagne `rationale` (text, nullable).
- Pour une étape issue d'une suggestion IA (point 5) : le `rationale` de la suggestion est copié tel quel sur l'étape exécutée.
- Pour une étape manuelle : ajout d'un champ facultatif "Pourquoi ? (optionnel)" dans la modale de transformation existante — l'utilisateur documente lui-même s'il le souhaite.
- UI Notebook : chaque étape affiche son `rationale` s'il existe, sous le libellé factuel déjà présent.

**Impact** : très faible effort (1 champ + 1 colonne), dépend du point 5 pour être vraiment riche, mais reste utile même seul (auto-documentation manuelle).

---

## 8. Dashboard aux graphiques justifiés

**État actuel : ⚠️ Partiel.** `VisualizationService::generateDefaults()` **sait déjà** pourquoi il choisit chaque graphique (la logique existe dans le code : histogramme si numérique, barres si catégoriel, heatmap si 2+ colonnes numériques) — cette justification n'est simplement jamais écrite ni affichée.

**Architecture proposée** :
- Migration : `visualizations` gagne `rationale` (text, nullable).
- Pour les graphiques par défaut : la justification est **déterministe, zéro appel IA nécessaire** — il suffit de transformer en phrase la condition déjà utilisée dans `generateDefaults()` ("Histogramme choisi car la variable est continue").
- Pour les graphiques nés d'une suggestion IA (point 4, catégorie "Graphiques suggérés") : la justification est le texte déjà généré par l'IA à cette occasion, simplement conservé au lieu d'être jeté.
- UI : légende discrète sous chaque graphique.

**Impact** : le plus petit effort de tout ce document pour les graphiques par défaut (aucun appel IA, juste écrire ce que le code sait déjà) — je le classerais en priorité haute pour ce rapport effort/valeur.

---

## 9. Insights triés par sévérité, pas seulement par catégorie

**État actuel : ✅ Presque déjà là.** `InsightCategory` (9 catégories) et `InsightImportance` (Haute/Moyenne/Faible) existent déjà et sont déjà correctement assignés (Risque/Anomalie → Haute, Recommandation/Opportunité → Moyenne). Ce qui manque, c'est une **vue de triage** : aujourd'hui l'affichage groupe par catégorie, jamais par urgence transversale.

**Architecture proposée** : aucun changement de schéma. Ajout d'une bascule d'affichage ("Par catégorie" / "Par priorité") sur la page Analyse et l'encart insights de la page dataset — "Par priorité" trie tous les insights (toutes catégories confondues) par `importance` décroissante, avec les badges de sévérité déjà existants.

**Impact** : quasi gratuit — c'est une réorganisation d'affichage sur des données déjà correctement structurées.

---

## 10. Rapport narratif complet

**État actuel : ⚠️ Partiel.** Le rapport PDF actuel contient déjà qualité + EDA + insights + graphiques, mais organisé **table par table**, sans arc narratif global ni section Contexte/Objectif (qui n'existait pas avant le point 1).

**Architecture proposée** (dépend du point 1, bénéficie des points 2-3-7-8) :
- `ReportGenerationService` restructuré autour de l'arc demandé : Contexte & Objectif (point 1) → Qualité (agrégée sur toutes les tables, narrative du point 3) → Préparation (historique du pipeline avec justifications, point 7) → Résultats & Visualisations (avec légendes justifiées, point 8) → Insights triés par sévérité (point 9) → Recommandations (agrégées) → Conclusion (un dernier appel IA qui synthétise l'ensemble).
- Le template Blade `reports/pdf.blade.php` est réorganisé en conséquence ; aucun changement sur le moteur PDF (`dompdf`) ni sur le rendu des graphiques (`render_chart_image.py`, déjà fonctionnel).

**Impact** : à faire en dernier dans cette liste — c'est une resynthèse de tout ce qui précède plutôt qu'une brique indépendante. Le construire avant les autres points obligerait à le refaire.

---

## Proposition de priorisation

| Ordre | Point | Effort | Dépendances | Pourquoi cet ordre |
|---|---|---|---|---|
| 1 | Contexte métier (§1) | Faible | Aucune | Irrigue tout le reste, doit exister en premier |
| 2 | Graphiques justifiés — défauts (§8) | Très faible | Aucune | La justification existe déjà dans le code, juste à l'écrire |
| 3 | Insights par sévérité (§9) | Très faible | Aucune | Réorganisation d'affichage sur données déjà correctes |
| 4 | Notebook explicatif — champ manuel (§7) | Faible | Aucune | Utile seul, encore plus utile avec §5 |
| 5 | Compréhension sémantique (§2) | Modéré | §1 | Base pour §3, §4, §5 |
| 6 | Diagnostic qualité narratif (§3) | Modéré | §1, §2 | Réutilise l'infra IA existante |
| 7 | IA proactive avec actions (§4) | Modéré-élevé | §1, §2 | Le pont insight → action |
| 8 | Pipeline proposé par l'IA (§5) | Élevé | §1, §2, §3 | La pièce la plus lourde, mais réutilise l'exécution existante |
| 9 | Rapport narratif complet (§10) | Modéré | §1, §3, §7, §8, §9 | Resynthèse — à faire en dernier |
| 10 | Mémoire inter-projets (§6) | Élevé, valeur incertaine à court terme | §5 | À observer avant d'investir davantage |

**Je n'implémente rien de cette liste tant que vous n'avez pas validé l'ordre** (ou proposé le vôtre). Je recommande de commencer par les points 1 à 4 en un seul lot cohérent (fondation + deux gains "quasi gratuits") pour livrer de la valeur visible rapidement, puis d'enchaîner sur 5-7 une fois validés.
