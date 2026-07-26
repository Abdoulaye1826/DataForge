{{-- Comparer les exécutions (inspiré d'OctOpus) : réutilisé par la page ML
     et la page Analyse (tests statistiques) - voir resources/js/compare-runs.js.
     Chaque case à cocher [data-compare-checkbox] porte son propre
     data-compare-payload (JSON), ce composant ne fait qu'afficher la barre
     flottante et la modale de résultat, sans rien savoir du contenu comparé. --}}
<div class="df-compare-bar d-none" data-compare-bar>
    <span class="small"><span data-compare-count>0</span> sélectionnée(s)</span>
    <button type="button" class="btn btn-primary btn-sm" data-compare-open>Comparer</button>
</div>

<div class="modal fade" id="compareRunsModal" tabindex="-1" aria-hidden="true" data-compare-modal>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Comparaison des exécutions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="df-compare-table" data-compare-table></table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
