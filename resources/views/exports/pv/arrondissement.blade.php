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
            margin: 15mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .logo {
            width: 80px;
            height: auto;
        }
        
        .header-center {
            text-align: center;
            flex: 1;
        }
        
        .header-center h1 {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .header-center h2 {
            font-size: 11pt;
            margin-bottom: 3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            font-size: 9pt;
        }
        
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .coordonnateur-section {
            margin-top: 30px;
            padding: 15px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
        }
        
        .signature-line {
            margin-top: 50px;
            border-top: 1px solid #000;
            width: 300px;
        }
    </style>
</head>
<body>
    <!-- ✅ HEADER AVEC LOGOS -->
    <div class="header">
        <img src="{{ public_path('images/logo-benin.png') }}" alt="Logo Bénin" class="logo">
        <div class="header-center">
            <h1>RÉPUBLIQUE DU BÉNIN</h1>
            <h2>COMMISSION ELECTORALE NATIONALE AUTONOME</h2>
            <h2>ELECTION DES MEMBRES DE L'ASSEMBLÉE NATIONALE</h2>
            <p style="margin-top: 5px;">SCRUTIN DU : 11 JANVIER 2026</p>
        </div>
        <img src="{{ public_path('images/logo-cena.png') }}" alt="Logo CENA" class="logo">
    </div>
    
    <h3 style="text-align: center; margin: 20px 0; font-size: 11pt;">
        PROCÈS-VERBAL DE COMPILATION DES RÉSULTATS DE L'ARRONDISSEMENT OU DE LA ZONE
    </h3>
    
    <!-- LOCALISATION -->
    <table style="margin-bottom: 20px;">
        <tr>
            <th style="width: 30%;">DÉPARTEMENT :</th>
            <td>{{ $pv->departement }}</td>
        </tr>
        <tr>
            <th>COMMUNE :</th>
            <td>{{ $pv->commune }}</td>
        </tr>
        <tr>
            <th>ARRONDISSEMENT OU ZONE :</th>
            <td>{{ $pv->arrondissement }}</td>
        </tr>
        <tr>
            <th>COORDONNATEUR DE L'ARRONDISSEMENT OU DE LA ZONE :</th>
            <td><!-- ✅ VIDE COMME DEMANDÉ --></td>
        </tr>
    </table>
    
    <!-- TABLEAU RÉSULTATS -->
    <table>
        <thead>
            <tr>
                <th>VILLAGE/QUARTIER</th>
                <th>FCBE</th>
                <th>LD</th>
                <th>BR</th>
                <th>MOELE BENIN</th>
                <th>UP-R</th>
                <th>BULLETINS NULS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lignes as $ligne)
            <tr>
                <td style="text-align: left;">{{ $ligne->villageQuartier->nom ?? 'N/A' }}</td>
                @foreach($ligne->resultats as $resultat)
                <td>{{ $resultat->nombre_voix }}</td>
                @endforeach
                <td>{{ $ligne->bulletins_nuls }}</td>
            </tr>
            @endforeach
            
            <!-- TOTAL -->
            <tr style="font-weight: bold; background-color: #e0e0e0;">
                <td>TOTAL</td>
                <td>{{ $pv->getTotalVoixParEntite(1) }}</td>
                <td>{{ $pv->getTotalVoixParEntite(2) }}</td>
                <td>{{ $pv->getTotalVoixParEntite(3) }}</td>
                <td>{{ $pv->getTotalVoixParEntite(4) }}</td>
                <td>{{ $pv->getTotalVoixParEntite(5) }}</td>
                <td>{{ $pv->nombre_bulletins_nuls }}</td>
            </tr>
        </tbody>
    </table>
    
    <!-- SIGNATURES -->
    <div class="coordonnateur-section">
        <p><strong>NOM, PRÉNOM(S) ET SIGNATURE DU COORDONNATEUR :</strong></p>
        <div class="signature-line"></div>
    </div>
    
    <!-- Footer -->
    <p style="text-align: center; margin-top: 30px; font-size: 8pt; color: #666;">
        Document généré par la plateforme CENA le {{ now()->format('d/m/Y à H:i') }}
    </p>
</body>
</html>