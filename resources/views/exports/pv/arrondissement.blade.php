<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PV {{ $pv->code }}</title>
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
        @php $logoPath = public_path('images/logo-benin.png'); @endphp
        @if(file_exists($logoPath))
            <img src="{{ $logoPath }}" alt="Logo Bénin" class="logo">
        @else
            <div style="width: 65px;"></div>
        @endif
        
        <div class="header-center">
            <h1>RÉPUBLIQUE DU BÉNIN</h1>
            <h2>COMMISSION ÉLECTORALE NATIONALE AUTONOME</h2>
            <h2>ÉLECTION DES MEMBRES DE L'ASSEMBLÉE NATIONALE</h2>
            <p>SCRUTIN DU : 11 JANVIER 2026</p>
        </div>
        
        @php $logoCenaPath = public_path('images/logo-cena.png'); @endphp
        @if(file_exists($logoCenaPath))
            <img src="{{ $logoCenaPath }}" alt="Logo CENA" class="logo">
        @else
            <div style="width: 65px;"></div>
        @endif
    </div>
    
    <h3>PROCÈS-VERBAL DE COMPILATION DES RÉSULTATS DE L'ARRONDISSEMENT OU DE LA ZONE</h3>
    
    <!-- LOCALISATION -->
    <table style="margin-bottom: 10px;">
        <tr>
            <th style="width: 28%; text-align: left; padding-left: 6px;">DÉPARTEMENT :</th>
            <td style="text-align: left; padding-left: 6px;">{{ strtoupper($localisation['departement'] ?? $pv->departement ?? '') }}</td>
        </tr>
        <tr>
            <th style="text-align: left; padding-left: 6px;">COMMUNE :</th>
            <td style="text-align: left; padding-left: 6px;">{{ strtoupper($localisation['commune'] ?? $pv->commune ?? '') }}</td>
        </tr>
        <tr>
            <th style="text-align: left; padding-left: 6px;">ARRONDISSEMENT OU ZONE :</th>
            <td style="text-align: left; padding-left: 6px;">{{ strtoupper($localisation['arrondissement'] ?? $pv->arrondissement ?? '') }}</td>
        </tr>
        <tr>
            <th style="text-align: left; padding-left: 6px;">COORDONNATEUR DE L'ARRONDISSEMENT OU DE LA ZONE :</th>
            <td style="text-align: left; padding-left: 6px;">{{ $pv->coordonnateur ?? '' }}</td>
        </tr>
    </table>
    
    <!-- TABLEAU RÉSULTATS -->
    <table>
        <thead>
            <tr>
                <th style="width: 20%;">VILLAGE/QUARTIER</th>
                @if(isset($entites) && count($entites) > 0)
                    @foreach($entites as $entite)
                        <th style="width: {{ 65 / count($entites) }}%;">{{ $entite['sigle'] }}</th>
                    @endforeach
                @else
                    <th>FCBE</th>
                    <th>LD</th>
                    <th>BR</th>
                    <th>MOELE BENIN</th>
                    <th>UP-R</th>
                @endif
                <th style="width: 15%;">BULLETINS NULS</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($lignes))
                @foreach($lignes as $ligne)
                <tr>
                    <td class="left-align">
                        @if(is_array($ligne))
                            {{ $ligne['localisation'] }}
                        @else
                            {{ $ligne->villageQuartier->nom ?? $ligne->localisation ?? 'N/A' }}
                        @endif
                    </td>
                    @if(isset($entites))
                        @foreach($entites as $entite)
                            @php
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
                            @endphp
                            <td>{{ $voix }}</td>
                        @endforeach
                    @else
                        @if(is_object($ligne) && isset($ligne->resultats))
                            @foreach($ligne->resultats as $resultat)
                            <td>{{ $resultat->nombre_voix }}</td>
                            @endforeach
                        @endif
                    @endif
                    <td>
                        @if(is_array($ligne))
                            {{ $ligne['bulletins_nuls'] ?? 0 }}
                        @else
                            {{ $ligne->bulletins_nuls ?? 0 }}
                        @endif
                    </td>
                </tr>
                @endforeach
            @endif
            
            <!-- TOTAL -->
            <tr class="total-row">
                <td class="left-align">TOTAL</td>
                @if(isset($entites) && isset($resultatsGlobaux))
                    @foreach($entites as $entite)
                        @php
                            $total = collect($resultatsGlobaux)->where('entite_id', $entite['id'])->first()['nombre_voix'] ?? 0;
                        @endphp
                        <td>{{ $total }}</td>
                    @endforeach
                @else
                    <td>{{ $pv->getTotalVoixParEntite(1) ?? 0 }}</td>
                    <td>{{ $pv->getTotalVoixParEntite(2) ?? 0 }}</td>
                    <td>{{ $pv->getTotalVoixParEntite(3) ?? 0 }}</td>
                    <td>{{ $pv->getTotalVoixParEntite(4) ?? 0 }}</td>
                    <td>{{ $pv->getTotalVoixParEntite(5) ?? 0 }}</td>
                @endif
                <td>{{ isset($lignes) ? collect($lignes)->sum('bulletins_nuls') : ($pv->nombre_bulletins_nuls ?? 0) }}</td>
            </tr>
        </tbody>
    </table>
    
    <!-- SIGNATURES -->
    <div class="coordonnateur-section">
        <p>NOM, PRÉNOM(S) ET SIGNATURE DU COORDONNATEUR :</p>
        <div style="text-align: center;">
            {{ $pv->coordonnateur ?? '' }}
            @if(isset($pv->statut) && in_array(strtolower(trim($pv->statut)), ['valide', 'validé', 'signé']))
                <div style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-weight: bold; font-size: 10pt;">SIGNÉ</div>
            @endif
        </div>
        <div class="signature-line"></div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        Document généré par la plateforme de GRAFNET le {{ now()->format('d/m/Y à H:i') }}
    </div>
</body>
</html>