# Auteur : Arena | Date : 2026-09-08 | Description : Assemble un vrai .xlsm avec Excel Windows et MSForms.
# Prérequis : Excel 2016+ ; accès approuvé au modèle objet du projet VBA, temporairement.
[CmdletBinding()]
param(
    [string]$Output = (Join-Path $PSScriptRoot '..\livraison\GestionnaireRH.xlsm'),
    [string]$ParametersCsv = ''
)
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$manifestPath = Join-Path $PSScriptRoot 'workbook-data.json'
$initializerPath = Join-Path $PSScriptRoot 'Initialize-Workbook.ps1'
foreach ($required in @($manifestPath, $initializerPath, (Join-Path $PSScriptRoot 'forms.json'), (Join-Path $root 'src\ThisWorkbook.vba'))) {
    if (-not (Test-Path -LiteralPath $required -PathType Leaf)) { throw "Kit incomplet : $required. Extrayez toute la nouvelle archive dans un dossier vide." }
}
$manifest = Get-Content -LiteralPath $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
if ($manifest.schemaVersion -ne 1 -or $manifest.sheets.Count -ne 10 -or $manifest.sheets[0].name -ne 'Accueil') {
    throw 'Manifeste du classeur invalide ou version incompatible.'
}
. $initializerPath
$Output = [IO.Path]::GetFullPath($Output)
if ([IO.Path]::GetExtension($Output) -ne '.xlsm') { throw 'La destination doit être un fichier .xlsm.' }
if (Test-Path $Output) { throw 'La destination existe. Choisissez un nouveau chemin pour éviter de perdre des données.' }
$secret = Read-Host 'Mot de passe initial du compte admin (12 caractères minimum)' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try { $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) }
finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
if ($password.Length -lt 12) { $password = $null; $secret.Dispose(); throw 'Mot de passe trop court.' }
$excel = $null; $book = $null
$stage = "Démarrage Excel"
$temp = Join-Path ([IO.Path]::GetTempPath()) ([Guid]::NewGuid().ToString())
New-Item -ItemType Directory -Path $temp | Out-Null
try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false; $excel.DisplayAlerts = $false
    $excel.EnableEvents = $false
    # Ne pas réduire les protections Excel et ne pas demander de réparation automatique.
    $excel.AutomationSecurity = 3
    $stage = "Création du classeur vierge"
    Write-Host 'Constructeur v1.0.1 : création native Excel, sans ouverture du modèle XLSX.'
    $book = $excel.Workbooks.Add(-4167) # xlWBATWorksheet : une seule feuille
    $stage = "Accès au projet VBA"
    try { $project = $book.VBProject; $null = $project.VBComponents.Count }
    catch { throw "Activez temporairement Excel > Options > Centre de gestion de la confidentialité > Paramètres des macros > Accès approuvé au modèle d'objet du projet VBA. Puis relancez." }
    $stage = "Création des feuilles et tables natives"
    Initialize-RHWorkbook -Workbook $book -Manifest $manifest
    $stage = "Import des modules VBA"
    $encoding = [Text.Encoding]::GetEncoding(1252)
    foreach ($file in Get-ChildItem (Join-Path $root 'src') -Filter '*.bas') {
        $target = Join-Path $temp $file.Name
        [IO.File]::WriteAllText($target, [IO.File]::ReadAllText($file.FullName, [Text.Encoding]::UTF8), $encoding)
        $null = $project.VBComponents.Import($target)
    }
    $project.VBComponents.Item($book.CodeName).CodeModule.AddFromString([IO.File]::ReadAllText((Join-Path $root 'src\ThisWorkbook.vba'), [Text.Encoding]::UTF8))
    $forms = Get-Content (Join-Path $PSScriptRoot 'forms.json') -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach ($form in $forms) {
        $stage = "Création du formulaire $($form.name)"
        $component = $project.VBComponents.Add(3)
        $component.Name = $form.name
        $component.Properties.Item('Caption').Value = $form.caption
        $component.Properties.Item('Width').Value = $form.width
        $component.Properties.Item('Height').Value = $form.height
        foreach ($spec in $form.controls) {
            $control = $component.Designer.Controls.Add("Forms.$($spec.type).1", $spec.name, $true)
            foreach ($property in $spec.PSObject.Properties) {
                if ($property.Name -in @('type','name','items')) { continue }
                $control.($property.Name) = $property.Value
            }
        }
        $component.CodeModule.AddFromString([IO.File]::ReadAllText((Join-Path $root "src\$($form.name).vba"), [Text.Encoding]::UTF8))
    }
    $stage = "Configuration et compte administrateur"
    $config = $book.Worksheets.Item('Parametres').ListObjects.Item('Configuration')
    $protection = [Guid]::NewGuid().ToString('N')
    for ($i=1; $i -le $config.ListRows.Count; $i++) {
        if ($config.DataBodyRange.Cells.Item($i,1).Value2 -eq 'Protection') { $config.DataBodyRange.Cells.Item($i,2).Value2 = $protection }
    }
    if ($ParametersCsv) {
        $allowed = @('Entreprise','AcquisitionMensuelle','ModeJours','DebutExercice','CouleurMarine','DossierExports')
        foreach ($entry in Import-Csv -LiteralPath $ParametersCsv -Delimiter ';' -Encoding UTF8) {
            if ($entry.Cle -notin $allowed) { throw "Paramètre non autorisé : $($entry.Cle)" }
            for ($i=1; $i -le $config.ListRows.Count; $i++) {
                if ($config.DataBodyRange.Cells.Item($i,1).Value2 -eq $entry.Cle) {
                    if ($entry.Cle -in @('AcquisitionMensuelle','CouleurMarine')) {
                        $number = [double]::Parse(([string]$entry.Valeur).Replace(',', '.'), [Globalization.CultureInfo]::InvariantCulture)
                        $maximum = if ($entry.Cle -eq 'AcquisitionMensuelle') { 31 } else { 16777215 }
                        if ([double]::IsNaN($number) -or [double]::IsInfinity($number) -or $number -lt 0 -or $number -gt $maximum) { throw "Valeur numérique hors limites : $($entry.Cle)" }
                        $config.DataBodyRange.Cells.Item($i,2).NumberFormat = 'General'
                        $config.DataBodyRange.Cells.Item($i,2).Value2 = $number
                    } else {
                        if ($entry.Cle -eq 'ModeJours' -and $entry.Valeur -notin @('Ouvres','Ouvrables')) { throw 'ModeJours doit valoir Ouvres ou Ouvrables.' }
                        if ($entry.Cle -eq 'DebutExercice') { $null = [datetime]::ParseExact($entry.Valeur, 'dd/MM/yyyy', [Globalization.CultureInfo]::InvariantCulture) }
                        $config.DataBodyRange.Cells.Item($i,2).NumberFormat = '@'
                        $config.DataBodyRange.Cells.Item($i,2).Value2 = [string]$entry.Valeur
                    }
                }
            }
        }
    }
    $users = $book.Worksheets.Item('Utilisateurs').ListObjects.Item('Utilisateurs').DataBodyRange
    $salt = [Guid]::NewGuid().ToString('N')
    $sha = [Security.Cryptography.SHA256]::Create()
    try { $hash = -join ($sha.ComputeHash([Text.Encoding]::Unicode.GetBytes($salt + $password)) | ForEach-Object { $_.ToString('x2') }) }
    finally { $sha.Dispose(); $password = $null }
    $users.Cells.Item(1,4).Value2 = $salt
    $users.Cells.Item(1,5).Value2 = $hash
    $marine = 0
    for ($i=1; $i -le $config.ListRows.Count; $i++) {
        if ($config.DataBodyRange.Cells.Item($i,1).Value2 -eq 'CouleurMarine') { $marine = [int]$config.DataBodyRange.Cells.Item($i,2).Value2 }
    }
    $home = $book.Worksheets.Item('Accueil')
    $buttons = @(
        @('Connexion','Connect'), @('Dossier salarié','OpenEmployee'), @('Demander un congé','OpenLeave'),
        @('Valider / refuser','DecideLeave'), @('Rafraîchir','RefreshDashboard'), @('Exporter les salariés','ExportEmployees'),
        @('Graphique services','ShowStats'), @('Documents','GenerateDocument'), @('Compétences','RecordSkill'),
        @('Entretiens','RecordInterview'), @('Comptes et droits','OpenAccounts'), @('Paramètres','EditSettings'),
        @('Déconnexion','Logout')
    )
    $index = 0
    foreach ($button in $buttons) {
        $shape = $home.Shapes.AddShape(5, 20, (75 + 36 * $index), 170, 29)
        $shape.Name = 'nav_' + $button[1]
        $shape.TextFrame.Characters().Text = $button[0]
        $shape.TextFrame.Characters().Font.Color = 16777215
        $shape.TextFrame.Characters().Font.Size = 11
        $shape.Fill.ForeColor.RGB = $marine
        $shape.Line.Visible = 0
        # Nom de fichier qualifié, y compris espaces et apostrophes.
        $shape.OnAction = "'" + ([IO.Path]::GetFileName($Output).Replace("'", "''")) + "'!" + $button[1]
        $index++
    }
    foreach ($sheet in $book.Worksheets) {
        if ($sheet.Name -ne 'Accueil') { $sheet.Visible = 2 }
        $sheet.Protect($protection)
    }
    $book.Protect($protection, $true, $false)
    $home.Activate()
    New-Item -ItemType Directory -Force -Path (Split-Path $Output -Parent) | Out-Null
    $stage = "Enregistrement du XLSM"
    $book.SaveAs($Output, 52)
    Write-Host "Classeur assemblé : $Output"
    Write-Host 'À FAIRE dans Excel : Débogage > Compiler VBAProject ; lancer RunSmokeTests ; recette manuelle ; protection du projet VBA et chiffrement du fichier.'
    Write-Host "Le script ne certifie PAS la compilation ou la recette. Désactivez ensuite l'accès approuvé au modèle objet VBA."
}
catch {
    $detail = $_.Exception.Message
    $line = $_.InvocationInfo.ScriptLineNumber
    $code = '0x{0:X8}' -f $_.Exception.HResult
    throw "Échec à l'étape '$stage' (ligne $line, $code) : $detail"
}
finally {
    $password = $null
    # Les erreurs de nettoyage ne doivent jamais masquer la cause initiale.
    if ($secret) { $secret.Dispose() }
    if ($book) {
        try { $book.Close($false) } catch { Write-Warning "Fermeture du classeur : $($_.Exception.Message)" }
        try { [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($book) } catch { Write-Warning 'Libération du classeur COM incomplète.' }
    }
    if ($excel) {
        try { $excel.Quit() } catch { Write-Warning "Fermeture Excel : $($_.Exception.Message)" }
        try { [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($excel) } catch { Write-Warning 'Libération Excel COM incomplète.' }
    }
    Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue
    [GC]::Collect(); [GC]::WaitForPendingFinalizers()
}
