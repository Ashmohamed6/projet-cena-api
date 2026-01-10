<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PV <?php echo e($pv->code); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: A4 landscape;
            margin: 8mm 8mm 10mm 8mm; /* ✅ MARGES RÉDUITES de 15mm à 8mm */
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 8.5pt; /* ✅ LÉGÈREMENT RÉDUIT de 9pt */
            line-height: 1.3;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px; /* ✅ RÉDUIT de 20px à 10px */
        }
        
        .logo {
            width: 65px; /* ✅ RÉDUIT de 80px à 65px */
            height: auto;
        }
        
        .header-center {
            text-align: center;
            flex: 1;
        }
        
        .header-center h1 {
            font-size: 11pt; /* ✅ RÉDUIT de 12pt */
            font-weight: bold;
            margin-bottom: 3px; /* ✅ RÉDUIT de 5px */
        }
        
        .header-center h2 {
            font-size: 10pt; /* ✅ RÉDUIT de 11pt */
            margin-bottom: 2px; /* ✅ RÉDUIT de 3px */
        }
        
        .header-center p {
            font-size: 9pt;
            margin-top: 3px;
        }
        
        h3 {
            text-align: center;
            margin: 10px 0; /* ✅ RÉDUIT de 20px */
            font-size: 10pt; /* ✅ RÉDUIT de 11pt */
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0; /* ✅ RÉDUIT de 15px */
        }
        
        th, td {
            border: 1px solid #000;
            padding: 5px 4px; /* ✅ RÉDUIT de 8px */
            text-align: center;
            font-size: 8.5pt; /* ✅ RÉDUIT de 9pt */
        }
        
        th {
            background-color: #e8e8e8; /* ✅ LÉGÈREMENT PLUS FONCÉ */
            font-weight: bold;
            font-size: 8pt;
        }
        
        td.left-align {
            text-align: left;
            padding-left: 6px;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #d8d8d8;
        }
        
        .coordonnateur-section {
            margin-top: 18px; /* ✅ RÉDUIT de 30px */
            padding: 10px; /* ✅ RÉDUIT de 15px */
            border: 1px solid #000;
            background-color: #f9f9f9;
        }
        
        .coordonnateur-section p {
            font-size: 9pt;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .signature-line {
            margin-top: 35px; /* ✅ RÉDUIT de 50px */
            border-top: 1px solid #000;
            width: 280px; /* ✅ RÉDUIT de 300px */
            padding-top: 5px;
            text-align: center;
            font-size: 8pt;
        }
        
        .footer {
            position: fixed;
            bottom: 5mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7pt;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- HEADER AVEC LOGOS -->
    <div class="header">
        <?php $logoPath = public_path('images/logo-benin.png'); ?>
        <?php if(file_exists($logoPath)): ?>
            <img src="<?php echo e($logoPath); ?>" alt="Logo Bénin" class="logo">
        <?php else: ?>
            <div style="width: 65px;"></div>
        <?php endif; ?>
        
        <div class="header-center">
            <h1>RÉPUBLIQUE DU BÉNIN</h1>
            <h2>COMMISSION ÉLECTORALE NATIONALE AUTONOME</h2>
            <h2>ÉLECTION DES MEMBRES DE L'ASSEMBLÉE NATIONALE</h2>
            <p>SCRUTIN DU : 11 JANVIER 2026</p>
        </div>
        
        <?php $logoCenaPath = public_path('images/logo-cena.png'); ?>
        <?php if(file_exists($logoCenaPath)): ?>
            <img src="<?php echo e($logoCenaPath); ?>" alt="Logo CENA" class="logo">
        <?php else: ?>
            <div style="width: 65px;"></div>
        <?php endif; ?>
    </div>
    
    <h3>PROCÈS-VERBAL DE COMPILATION DES RÉSULTATS DE L'ARRONDISSEMENT OU DE LA ZONE</h3>
    
    <!-- LOCALISATION -->
    <table style="margin-bottom: 10px;">
        <tr>
            <th style="width: 28%; text-align: left; padding-left: 6px;">DÉPARTEMENT :</th>
            <td style="text-align: left; padding-left: 6px;"><?php echo e(strtoupper($localisation['departement'] ?? $pv->departement ?? '')); ?></td>
        </tr>
        <tr>
            <th style="text-align: left; padding-left: 6px;">COMMUNE :</th>
            <td style="text-align: left; padding-left: 6px;"><?php echo e(strtoupper($localisation['commune'] ?? $pv->commune ?? '')); ?></td>
        </tr>
        <tr>
            <th style="text-align: left; padding-left: 6px;">ARRONDISSEMENT OU ZONE :</th>
            <td style="text-align: left; padding-left: 6px;"><?php echo e(strtoupper($localisation['arrondissement'] ?? $pv->arrondissement ?? '')); ?></td>
        </tr>
        <tr>
            <th style="text-align: left; padding-left: 6px;">COORDONNATEUR DE L'ARRONDISSEMENT OU DE LA ZONE :</th>
            <td style="text-align: left; padding-left: 6px;"><?php echo e($pv->coordonnateur ?? ''); ?></td>
        </tr>
    </table>
    
    <!-- TABLEAU RÉSULTATS -->
    <table>
        <thead>
            <tr>
                <th style="width: 20%;">VILLAGE/QUARTIER</th>
                <?php if(isset($entites) && count($entites) > 0): ?>
                    <?php $__currentLoopData = $entites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <th style="width: <?php echo e(65 / count($entites)); ?>%;"><?php echo e($entite['sigle']); ?></th>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php else: ?>
                    <th>FCBE</th>
                    <th>LD</th>
                    <th>BR</th>
                    <th>MOELE BENIN</th>
                    <th>UP-R</th>
                <?php endif; ?>
                <th style="width: 15%;">BULLETINS NULS</th>
            </tr>
        </thead>
        <tbody>
            <?php if(isset($lignes)): ?>
                <?php $__currentLoopData = $lignes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ligne): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="left-align">
                        <?php if(is_array($ligne)): ?>
                            <?php echo e($ligne['localisation']); ?>

                        <?php else: ?>
                            <?php echo e($ligne->villageQuartier->nom ?? $ligne->localisation ?? 'N/A'); ?>

                        <?php endif; ?>
                    </td>
                    <?php if(isset($entites)): ?>
                        <?php $__currentLoopData = $entites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $voix = 0;
                                if(is_array($ligne) && isset($ligne['resultats'][$entite['id']])) {
                                    $voix = $ligne['resultats'][$entite['id']]['nombre_voix'] ?? 0;
                                } elseif(is_object($ligne) && isset($ligne->resultats)) {
                                    foreach($ligne->resultats as $resultat) {
                                        if($resultat->entite_politique_id == $entite['id']) {
                                            $voix = $resultat->nombre_voix;
                                            break;
                                        }
                                    }
                                }
                            ?>
                            <td><?php echo e($voix); ?></td>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php else: ?>
                        <?php if(is_object($ligne) && isset($ligne->resultats)): ?>
                            <?php $__currentLoopData = $ligne->resultats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $resultat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <td><?php echo e($resultat->nombre_voix); ?></td>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <td>
                        <?php if(is_array($ligne)): ?>
                            <?php echo e($ligne['bulletins_nuls'] ?? 0); ?>

                        <?php else: ?>
                            <?php echo e($ligne->bulletins_nuls ?? 0); ?>

                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
            
            <!-- TOTAL -->
            <tr class="total-row">
                <td class="left-align">TOTAL</td>
                <?php if(isset($entites) && isset($resultatsGlobaux)): ?>
                    <?php $__currentLoopData = $entites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entite): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $total = collect($resultatsGlobaux)->where('entite_id', $entite['id'])->first()['nombre_voix'] ?? 0;
                        ?>
                        <td><?php echo e($total); ?></td>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php else: ?>
                    <td><?php echo e($pv->getTotalVoixParEntite(1) ?? 0); ?></td>
                    <td><?php echo e($pv->getTotalVoixParEntite(2) ?? 0); ?></td>
                    <td><?php echo e($pv->getTotalVoixParEntite(3) ?? 0); ?></td>
                    <td><?php echo e($pv->getTotalVoixParEntite(4) ?? 0); ?></td>
                    <td><?php echo e($pv->getTotalVoixParEntite(5) ?? 0); ?></td>
                <?php endif; ?>
                <td><?php echo e(isset($lignes) ? collect($lignes)->sum('bulletins_nuls') : ($pv->nombre_bulletins_nuls ?? 0)); ?></td>
            </tr>
        </tbody>
    </table>
    
    <!-- SIGNATURES -->
    <div class="coordonnateur-section">
        <p>NOM, PRÉNOM(S) ET SIGNATURE DU COORDONNATEUR :</p>
        <div style="text-align: center;">
            <?php echo e($pv->coordonnateur ?? ''); ?>

            <?php if(isset($pv->statut) && in_array(strtolower(trim($pv->statut)), ['valide', 'validé', 'signé'])): ?>
                <div style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-weight: bold; font-size: 10pt;">SIGNÉ</div>
            <?php endif; ?>
        </div>
        <div class="signature-line"></div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        Document généré par la plateforme de GRAFNET le <?php echo e(now()->format('d/m/Y à H:i')); ?>

    </div>
</body>
</html><?php /**PATH C:\xampp\htdocs\cena\cena-projet-api\resources\views/exports/pv/arrondissement.blade.php ENDPATH**/ ?>