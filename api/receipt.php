<?php
// api/receipt.php — Génération d'un reçu imprimable pour une vente
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
authorize(['admin', 'libraire']);

$id_vente = intval($_GET['id'] ?? 0);
if (!$id_vente) { http_response_code(400); exit('ID vente manquant.'); }

try {
    $vente = $pdo->prepare("
        SELECT v.*, COALESCE(c.nom,'Client de Passage') AS client_nom,
               c.email AS client_email, c.code_client, c.telephone AS client_tel
        FROM ventes v
        LEFT JOIN clients c ON v.id_client=c.id_client
        WHERE v.id_vente=:id
    ");
    $vente->execute([':id' => $id_vente]);
    $v = $vente->fetch();
    if (!$v) { http_response_code(404); exit('Vente introuvable.'); }

    $lignes = $pdo->prepare("
        SELECT lv.quantite, lv.prix_unitaire, lv.type_achat, l.titre, l.auteur, l.isbn
        FROM lignes_ventes lv JOIN livres l ON lv.isbn=l.isbn
        WHERE lv.id_vente=:id
    ");
    $lignes->execute([':id' => $id_vente]);
    $items = $lignes->fetchAll();

    $vendeur = $pdo->prepare("SELECT nom_complet FROM utilisateurs WHERE id_utilisateur=:id");
    $vendeur->execute([':id' => $v['id_vendeur']]);
    $v['vendeur_nom'] = $vendeur->fetch()['nom_complet'] ?? 'N/A';
} catch (\PDOException $e) {
    exit('Erreur : ' . $e->getMessage());
}

$mode_labels = ['especes'=>'Espèces','carte'=>'Carte bancaire','mobile_money'=>'Mobile Money','cheque'=>'Chèque'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu #<?= htmlspecialchars($v['code_transaction']) ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size:13px; color:#1e293b; background:#fff; padding:30px; }
        .receipt { max-width:520px; margin:0 auto; }
        .header { text-align:center; border-bottom:2px solid #6366f1; padding-bottom:16px; margin-bottom:16px; }
        .logo { font-size:24px; font-weight:800; color:#6366f1; letter-spacing:-0.5px; }
        .logo span { color:#1e293b; }
        .subtitle { font-size:11px; color:#64748b; margin-top:4px; }
        .title { font-size:16px; font-weight:700; margin:12px 0 4px; }
        .trx { font-family:monospace; background:#f1f5f9; padding:6px 14px; border-radius:20px; display:inline-block; font-size:13px; color:#6366f1; font-weight:700; }
        .meta { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:16px 0; }
        .meta-item label { font-size:10px; text-transform:uppercase; color:#94a3b8; font-weight:700; display:block; }
        .meta-item span { font-weight:600; color:#1e293b; }
        table { width:100%; border-collapse:collapse; margin:16px 0; }
        thead th { background:#f8fafc; padding:8px 10px; text-align:left; font-size:10px; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; }
        tbody td { padding:10px; border-bottom:1px solid #f1f5f9; }
        .item-title { font-weight:600; color:#1e293b; }
        .item-sub { font-size:11px; color:#94a3b8; }
        .text-right { text-align:right; }
        .totals { border-top:2px solid #e2e8f0; padding-top:12px; }
        .total-row { display:flex; justify-content:space-between; padding:4px 0; }
        .total-row.final { font-size:16px; font-weight:800; color:#6366f1; border-top:1px solid #e2e8f0; margin-top:8px; padding-top:8px; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .badge-success { background:#dcfce7; color:#15803d; }
        .badge-ebook { background:#dbeafe; color:#1d4ed8; }
        .footer { text-align:center; margin-top:24px; padding-top:16px; border-top:1px dashed #e2e8f0; color:#94a3b8; font-size:11px; }
        .stamp { display:inline-block; border:2px solid #15803d; color:#15803d; padding:4px 14px; border-radius:4px; font-weight:800; font-size:14px; letter-spacing:2px; margin:8px 0; transform:rotate(-3deg); }
        @media print {
            body { padding:10px; }
            .no-print { display:none !important; }
        }
    </style>
</head>
<body>
<div class="receipt">
    <!-- Header -->
    <div class="header">
        <div class="logo">📚 Lib<span>Gérant</span></div>
        <div class="subtitle">Librairie Générale — N'Djamena, Tchad</div>
        <div style="margin-top:12px">
            <div class="title">REÇU DE VENTE</div>
            <div class="trx">#<?= htmlspecialchars($v['code_transaction']) ?></div>
        </div>
    </div>

    <!-- Méta-infos -->
    <div class="meta">
        <div class="meta-item">
            <label>Date</label>
            <span><?= date('d/m/Y à H:i', strtotime($v['date_vente'])) ?></span>
        </div>
        <div class="meta-item">
            <label>Mode de règlement</label>
            <span><?= $mode_labels[$v['mode_reglement']] ?? $v['mode_reglement'] ?></span>
        </div>
        <div class="meta-item">
            <label>Client</label>
            <span><?= htmlspecialchars($v['client_nom']) ?></span>
        </div>
        <div class="meta-item">
            <label>Vendeur</label>
            <span><?= htmlspecialchars($v['vendeur_nom']) ?></span>
        </div>
        <?php if ($v['code_client']): ?>
        <div class="meta-item">
            <label>Code Client</label>
            <span><?= htmlspecialchars($v['code_client']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($v['client_tel']): ?>
        <div class="meta-item">
            <label>Téléphone</label>
            <span><?= htmlspecialchars($v['client_tel']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Détail articles -->
    <table>
        <thead>
            <tr>
                <th>Article</th>
                <th class="text-right">Qté</th>
                <th class="text-right">Prix unit.</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <div class="item-title"><?= htmlspecialchars($item['titre']) ?></div>
                    <div class="item-sub"><?= htmlspecialchars($item['auteur']) ?> — ISBN: <?= htmlspecialchars($item['isbn']) ?></div>
                    <span class="badge <?= $item['type_achat']==='E-book' ? 'badge-ebook' : 'badge-success' ?>"><?= $item['type_achat'] ?></span>
                </td>
                <td class="text-right"><?= $item['quantite'] ?></td>
                <td class="text-right"><?= number_format($item['prix_unitaire'],0,',',' ') ?> F</td>
                <td class="text-right"><?= number_format($item['prix_unitaire']*$item['quantite'],0,',',' ') ?> F</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totaux -->
    <div class="totals">
        <div class="total-row final">
            <span>TOTAL PAYÉ</span>
            <span><?= number_format($v['total_montant'],0,',',' ') ?> FCFA</span>
        </div>
    </div>

    <!-- Tampon -->
    <div style="text-align:center; margin:20px 0">
        <div class="stamp">✓ PAYÉ</div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Merci pour votre achat ! Ce reçu est votre preuve d'achat.</p>
        <p style="margin-top:6px">LibGérant — Gestion de librairie — <?= date('Y') ?></p>
    </div>

    <!-- Bouton impression -->
    <div class="no-print" style="text-align:center; margin-top:24px">
        <button onclick="window.print()" style="background:#6366f1;color:#fff;border:none;padding:12px 32px;border-radius:50px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(99,102,241,.4)">
            🖨️ Imprimer / Enregistrer en PDF
        </button>
        <button onclick="window.close()" style="background:#f1f5f9;color:#64748b;border:none;padding:12px 24px;border-radius:50px;font-size:14px;font-weight:600;cursor:pointer;margin-left:10px">
            Fermer
        </button>
    </div>
</div>
</body>
</html>
