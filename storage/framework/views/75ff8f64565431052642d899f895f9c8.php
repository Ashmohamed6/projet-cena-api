<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PV Arrondissement</title>
    <style>
        @page {
            margin: 15mm 15mm 20mm 15mm;
            size: A4 landscape;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 7pt;
            line-height: 1.1;
            color: #000;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120pt;
            font-weight: bold;
            color: rgba(0, 0, 0, 0.02);
            z-index: -1;
        }

        .footer {
            position: fixed;
            bottom: 8mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 6.5pt;
            color: #666;
        }

        .page-number:before { content: counter(page); }
        .page-number:after { content: "/2"; }
        .page-number {
            position: fixed;
            bottom: 8mm;
            right: 15mm;
            font-size: 6.5pt;
            color: #666;
        }

        .header {
            text-align: center;
            margin-bottom: 2mm;
            position: relative;
        }

        .logo-left, .logo-right {
            position: absolute;
            top: 0;
            width: 45px;
            height: auto;
        }
        .logo-left { left: 0; }
        .logo-right { right: 0; }

        .header-title {
            font-weight: bold;
            font-size: 9pt;
            margin: 0.5mm 0;
        }

        .header-subtitle {
            font-size: 8pt;
            margin: 0.5mm 0;
        }

        .box {
            border: 2px solid #000;
            padding: 1.5mm;
            margin: 2mm 0;
        }

        .box-title {
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
        }

        .localisation-box {
            border: 1.5px solid #000;
            margin: 2mm 0;
        }

        .localisation-header {
            background: #e8e8e8;
            border-bottom: 1.5px solid #000;
            padding: 1.5mm;
            text-align: center;
            font-weight: bold;
            font-size: 7.5pt;
        }

        .localisation-table {
            width: 100%;
            border-collapse: collapse;
        }

        .localisation-table td {
            padding: 1.5mm 2mm;
            font-size: 7pt;
            border-bottom: 1px solid #ddd;
        }

        .localisation-table td:first-child {
            font-weight: bold;
            width: 45%;
            background: #f5f5f5;
        }

        .localisation-table tr:last-child td {
            border-bottom: none;
        }

        .text-justify {
            text-align: justify;
            font-size: 6.5pt;
            line-height: 1.3;
            margin: 2mm 0;
        }

        .banner {
            background: #d0d0d0;
            border: 1.5px solid #000;
            padding: 1.5mm;
            text-align: center;
            font-weight: bold;
            font-size: 7pt;
            margin: 2mm 0;
        }

        table.results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1mm 0;
            font-size: 6pt;
        }

        table.results-table, 
        table.results-table th, 
        table.results-table td {
            border: 1px solid #000;
        }

        table.results-table th {
            background: #d8d8d8;
            padding: 1.5mm 0.5mm;
            text-align: center;
            font-weight: bold;
            font-size: 5.5pt;
            line-height: 1.1;
        }

        table.results-table td {
            padding: 1.5mm 0.5mm;
            text-align: center;
        }

        table.results-table td.left {
            text-align: left;
            padding-left: 2mm;
            font-weight: bold;
        }

        .total-row {
            background: #f0f0f0;
            font-weight: bold;
        }

        .signature-box {
            border: 1.5px solid #000;
            padding: 2mm;
            margin-top: 3mm;
            margin-bottom: 12mm;
            text-align: center;
            font-weight: bold;
            font-size: 7pt;
            min-height: 12mm;
            position: relative;
        }

        .coordonnateur-nom {
            margin-top: 3mm;
            text-align: center;
            font-weight: normal;
        }

        .signature-status {
            position: absolute;
            bottom: 2mm;
            right: 2mm;
            font-size: 8pt;
            font-weight: bold;
            color: #000;
        }

        .page-break { page-break-after: always; }
        .delegue-row { height: 8mm; }
    </style>
</head>
<body>
    <div class="watermark">GRAFNET</div>
    <div class="footer">Document généré par la plateforme de GRAFNET le <?php echo e(now()->format('d/m/Y à H:i')); ?></div>
    <div class="page-number"></div>
    
    <div class="header">
        <?php $logoPath = public_path('logo/logo-cena.jpg'); ?>
        <?php if(file_exists($logoPath)): ?>
            <img src="<?php echo e($logoPath); ?>" alt="CENA" class="logo-left">
            <img src="<?php echo e($logoPath); ?>" alt="CENA" class="logo-right">
        <?php endif; ?>
        <div class="header-title">REPUBLIQUE DU BENIN</div>
        <div class="header-title">COMMISSION ELECTORALE NATIONALE AUTONOME</div>
        <div class="header-subtitle">ELECTION DES MEMBRES DE L'ASSEMBLEE NATIONALE</div>
        <div class="header-subtitle">SCRUTIN DU : 11 JANVIER 2026</div>
    </div>

    <div class="box">
        <div class="box-title">PROCES-VERBAL DE COMPILATION DES RESULTATS DE L'ARRONDISSEMENT OU DE LA ZONE</div>
    </div>

    <div class="localisation-box">
        <div class="localisation-header">LOCALISATION</div>
        <table class="localisation-table">
            <tr>
                <td>DEPARTEMENT :</td>
                <td><?php echo e(strtoupper($localisation['departement'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>COMMUNE :</td>
                <td><?php echo e(strtoupper($localisation['commune'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>ARRONDISSEMENT OU ZONE :</td>
                <td><?php echo e(strtoupper($localisation['zone'] ?? $localisation['arrondissement'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>COORDONNATEUR DE L'ARRONDISSEMENT OU DE LA ZONE :</td>
                <td><?php echo e($pv->coordonnateur ?? ''); ?></td>
            </tr>
        </table>
    </div>

    <div class="text-justify">
        L'an deux mil vingt six et le onze janvier .............................................. heures ................................................ minutes, 
        en exécution des dispositions des articles 7, 37, 60, 93 et 94 du code électoral, 
        moi <?php echo e($pv->coordonnateur ?? '...................................................................................................................................................'); ?> 
        Coordonnateur de l'arrondissement / zone en présence des présidents des postes de vote et des délégués des partis politiques en lice, 
        ai procédé à la compilation des résultats de tous les postes de vote, centre de vote par centre de vote pour obtenir les résultats 
        par village ou de quartier de ville et les résultats de tous les villages ou quartiers de ville de l'arrondissement et enfin, 
        tous les résultats de l'arrondissement.
    </div>

    <div class="banner">
        <?php echo e(strtoupper($localisation['departement'] ?? '')); ?> / 
        <?php echo e(strtoupper($localisation['commune'] ?? '')); ?> / 
        <?php echo e(strtoupper($localisation['zone'] ?? $localisation['arrondissement'] ?? '')); ?>

    </div>

    <table class="results-table">
        <thead>
            <tr>
                <th style="width: 15%;">VILLAGE/QUARTIER</th>
                <?php $__currentLoopData = $entites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <th style="width: <?php echo e(35 / count($entites)); ?>%;"><?php echo e($entite['sigle']); ?></th>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <th style="width: 8%;">BULLETINS NULS</th>
                <th style="width: 42%;">NOMS, PRENOM(S) ET SIGNATURES DES PRESIDENTS DE POSTES DE VOTE</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $lignes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ligne): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td class="left"><?php echo e(strtoupper($ligne['localisation'])); ?></td>
                <?php $__currentLoopData = $entites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <td><?php echo e($ligne['resultats'][$entite['id']]['nombre_voix'] ?? ''); ?></td>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <td><?php echo e($ligne['bulletins_nuls'] ?? ''); ?></td>
                <td></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <div class="page-break"></div>
    <div class="page-number"></div>

    <div class="banner" style="margin-top: 5mm;">
        <?php echo e(strtoupper($localisation['departement'] ?? '')); ?> / 
        <?php echo e(strtoupper($localisation['commune'] ?? '')); ?> / 
        <?php echo e(strtoupper($localisation['zone'] ?? $localisation['arrondissement'] ?? '')); ?>

    </div>

    <table class="results-table">
        <thead>
            <tr>
                <th style="width: 20%;">TOTAL DE L'ARRONDISSEMENT OU DE LA ZONE</th>
                <?php $__currentLoopData = $entites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <th style="width: <?php echo e(60 / count($entites)); ?>%;"><?php echo e($entite['sigle']); ?></th>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <th style="width: 20%;">BULLETINS NULS</th>
            </tr>
        </thead>
        <tbody>
            <tr class="total-row">
                <td class="left">TOTAL</td>
                <?php $__currentLoopData = $entites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $total = collect($resultatsGlobaux)->where('entite_id', $entite['id'])->first()['nombre_voix'] ?? 0;
                    ?>
                    <td><?php echo e($total); ?></td>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <td><?php echo e(collect($lignes)->sum('bulletins_nuls')); ?></td>
            </tr>
        </tbody>
    </table>

    <table class="results-table" style="margin-top: 3mm;">
        <thead>
            <tr>
                <th style="width: 18%;">PARTI POLITIQUE</th>
                <th style="width: 37%;">NOM ET PRENOM(S) DU DELEGUE</th>
                <th style="width: 25%;">SIGNATURE</th>
                <th style="width: 20%;">MOTIF D'ABSENCE DE SIGNATURE</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $entites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr class="delegue-row">
                <td><?php echo e($entite['sigle']); ?></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <div class="signature-box">
        NOM, PRENOM(S) ET SIGNATURE DU COORDONNATEUR DE L'ARRONDISSEMENT OU DE LA ZONE
        <div class="coordonnateur-nom">
            <?php echo e($pv->coordonnateur ?? ''); ?>

        </div>
        <?php if(strtolower(trim($pv->statut)) === 'valide' || strtolower(trim($pv->statut)) === 'validé' || strtolower(trim($pv->statut)) === 'signé'): ?>
            <div class="signature-status"></div>
        <?php endif; ?>
    </div>
</body>
</html><?php /**PATH C:\xampp\htdocs\cena\cena-projet-api\resources\views/exports/pv/arrondissement.blade.php ENDPATH**/ ?>