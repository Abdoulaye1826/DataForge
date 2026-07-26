@props(['project', 'dataset', 'table'])

<div class="modal fade" id="transformModal{{ $table->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('projects.datasets.tables.pipeline-steps.store', [$project, $dataset, $table]) }}" data-transform-form>
                @csrf
                <input type="hidden" name="return_to_data" value="0" data-return-to-data>
                <div class="modal-header">
                    <h5 class="modal-title" data-transform-title>Transformer « {{ $table->name }} »</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    {{-- Module config par colonne (inspiré de Power Query) : rempli et affiché
                         par resources/js/column-transform.js quand la modale est ouverte depuis
                         l'en-tête d'une colonne précise sur la page Données - masqué sinon. --}}
                    <div class="mb-3 d-none" data-column-suggestions-panel>
                        <p class="small fw-bold mb-2">🧠 Suggestions IA pour cette colonne</p>
                        <div data-column-suggestions-list class="d-flex flex-column gap-2 mb-2"></div>
                        <hr class="mt-1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Opération</label>
                        <select name="step_type" class="form-select df-transform-select" data-table="{{ $table->id }}" required>
                            <option value="">Choisir une opération...</option>
                            <optgroup label="Nettoyage">
                                <option value="dedupe">Supprimer les doublons</option>
                                <option value="trim_spaces">Supprimer les espaces inutiles</option>
                                <option value="fix_case">Corriger la casse</option>
                                <option value="fix_dates">Corriger les dates</option>
                            </optgroup>
                            <optgroup label="Prétraitement">
                                <option value="rename_column">Renommer une colonne</option>
                                <option value="drop_column">Supprimer une/des colonne(s)</option>
                                <option value="merge">Fusionner des colonnes</option>
                                <option value="split">Séparer une colonne</option>
                                <option value="filter">Filtrer les lignes</option>
                                <option value="create_column">Créer une colonne calculée</option>
                                <option value="convert_type">Convertir un type</option>
                                <option value="encode">Encoder une variable</option>
                                <option value="normalize">Normaliser</option>
                                <option value="standardize">Standardiser</option>
                                <option value="categorize">Créer des catégories</option>
                            </optgroup>
                        </select>
                    </div>

                    {{-- columns: multi-select, used by dedupe/trim_spaces/fix_case/drop_column/merge --}}
                    <div class="mb-3 df-field d-none" data-steps="dedupe,trim_spaces,fix_case,drop_column,merge">
                        <label class="form-label">Colonne(s)</label>
                        <select name="columns[]" class="form-select" multiple size="5" disabled>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Laisser vide pour "toutes les colonnes texte" (nettoyage uniquement).</div>
                    </div>

                    {{-- single column select: used by fix_dates/split/filter/convert_type/encode/normalize/standardize/categorize --}}
                    <div class="mb-3 df-field d-none" data-steps="fix_dates,split,filter,convert_type,encode,normalize,standardize,categorize">
                        <label class="form-label">Colonne</label>
                        <select name="column" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- rename_column --}}
                    <div class="mb-3 df-field d-none" data-steps="rename_column">
                        <label class="form-label">Colonne à renommer</label>
                        <select name="old_name" class="form-select" disabled>
                            <option value="">—</option>
                            @foreach ($table->columns as $column)
                                <option value="{{ $column->name }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="rename_column">
                        <label class="form-label">Nouveau nom</label>
                        <input type="text" name="new_name" class="form-control" disabled>
                    </div>

                    {{-- fix_case --}}
                    <div class="mb-3 df-field d-none" data-steps="fix_case">
                        <label class="form-label">Casse</label>
                        <select name="mode" class="form-select" disabled>
                            <option value="title">Title Case</option>
                            <option value="upper">MAJUSCULES</option>
                            <option value="lower">minuscules</option>
                        </select>
                    </div>

                    {{-- merge / split --}}
                    <div class="mb-3 df-field d-none" data-steps="merge,split">
                        <label class="form-label">Séparateur</label>
                        <input type="text" name="separator" class="form-control" value=" " disabled>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="merge">
                        <label class="form-label">Nom de la nouvelle colonne</label>
                        <input type="text" name="new_column" class="form-control" placeholder="ex: nom_complet" disabled>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="split">
                        <label class="form-label">Noms des nouvelles colonnes (séparés par une virgule)</label>
                        <input type="text" name="new_columns" class="form-control" placeholder="ex: prenom, nom" disabled>
                    </div>

                    {{-- filter --}}
                    <div class="mb-3 df-field d-none" data-steps="filter">
                        <label class="form-label">Opérateur</label>
                        <select name="operator" class="form-select" disabled>
                            <option value="eq">Égal à</option>
                            <option value="neq">Différent de</option>
                            <option value="gt">Supérieur à</option>
                            <option value="gte">Supérieur ou égal à</option>
                            <option value="lt">Inférieur à</option>
                            <option value="lte">Inférieur ou égal à</option>
                            <option value="contains">Contient</option>
                            <option value="is_null">Est vide</option>
                            <option value="is_not_null">N'est pas vide</option>
                        </select>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="filter">
                        <label class="form-label">Valeur</label>
                        <input type="text" name="value" class="form-control" disabled>
                        <div class="form-text">Les lignes qui NE correspondent PAS à la condition sont conservées.</div>
                    </div>

                    {{-- create_column --}}
                    <div class="mb-3 df-field d-none" data-steps="create_column">
                        <label class="form-label">Nom de la nouvelle colonne</label>
                        <input type="text" name="new_column" class="form-control" placeholder="ex: montant_ttc" disabled>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="create_column">
                        <label class="form-label">Formule</label>
                        <input type="text" name="expression" class="form-control" placeholder="ex: montant * 1.2" disabled>
                        <div class="form-text">Référencez les colonnes existantes par leur nom (+, -, *, /, comparaisons).</div>
                    </div>

                    {{-- convert_type --}}
                    <div class="mb-3 df-field d-none" data-steps="convert_type">
                        <label class="form-label">Type cible</label>
                        <select name="target_type" class="form-select" disabled>
                            <option value="integer">Entier</option>
                            <option value="float">Décimal</option>
                            <option value="string">Texte</option>
                            <option value="date">Date</option>
                            <option value="datetime">Date et heure</option>
                            <option value="boolean">Booléen</option>
                        </select>
                    </div>

                    {{-- encode --}}
                    <div class="mb-3 df-field d-none" data-steps="encode">
                        <label class="form-label">Méthode</label>
                        <select name="method" class="form-select" disabled>
                            <option value="label">Label encoding (une colonne numérique)</option>
                            <option value="onehot">One-hot (une colonne par catégorie)</option>
                        </select>
                    </div>

                    {{-- categorize --}}
                    <div class="mb-3 df-field d-none" data-steps="categorize">
                        <label class="form-label">Nombre de catégories</label>
                        <input type="number" name="bins" class="form-control" value="4" min="2" max="20" disabled>
                    </div>
                    <div class="mb-3 df-field d-none" data-steps="categorize">
                        <label class="form-label">Libellés (séparés par une virgule, optionnel)</label>
                        <input type="text" name="labels" class="form-control" placeholder="ex: bas, moyen, haut, très haut" disabled>
                    </div>

                    <hr class="my-3">
                    <div class="mb-0">
                        <label class="form-label">Pourquoi cette transformation ? <span class="text-secondary">(optionnel)</span></label>
                        <textarea name="rationale" class="form-control" rows="2" placeholder="ex : cette colonne est vide à 90% et n'apporte rien à l'analyse"></textarea>
                        <div class="form-text">Affiché dans le Notebook, pour se souvenir du raisonnement en revenant sur ce projet plus tard.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Appliquer</button>
                </div>
            </form>
        </div>
    </div>
</div>
