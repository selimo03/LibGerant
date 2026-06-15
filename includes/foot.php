<?php
// includes/foot.php
// Variables optionnelles :
//   $datatable_ids (array)  — IDs HTML des tables à activer avec DataTables
//   $extra_js      (string) — JavaScript supplémentaire (charts, etc.)
?>
</div><!-- /#page-content-wrapper -->
</div><!-- /#wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="../assets/js/script.js"></script>
<?php if (!empty($datatable_ids)): ?>
<script>
$(document).ready(function () {
    <?php foreach ($datatable_ids as $tid): ?>
    $('#<?= htmlspecialchars($tid) ?>').DataTable({
        language: {
            search: "Rechercher :",
            lengthMenu: "Afficher _MENU_ lignes",
            info: "_START_ à _END_ sur _TOTAL_ entrées",
            paginate: { first:"Début", last:"Fin", next:"Suivant", previous:"Précédent" },
            zeroRecords: "Aucun résultat trouvé",
            emptyTable: "Aucune donnée disponible"
        },
        pageLength: 15,
        responsive: true
    });
    <?php endforeach; ?>
});
</script>
<?php endif; ?>
<?php if (!empty($extra_js)) echo $extra_js; ?>
</body>
</html>
