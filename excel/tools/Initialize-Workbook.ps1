# Auteur : Arena | Date : 2026-09-08 | Description : Création native des feuilles et tables ; aucun fichier Excel à réparer.
function Initialize-RHWorkbook {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]$Workbook,
        [Parameter(Mandatory=$true)]$Manifest
    )
    $missing = [Type]::Missing
    $position = 0
    foreach ($spec in $Manifest.sheets) {
        Write-Host "  Feuille : $($spec.name)"
        if ($position -eq 0) {
            $sheet = $Workbook.Worksheets.Item(1)
        } else {
            $last = $Workbook.Worksheets.Item($Workbook.Worksheets.Count)
            $sheet = $Workbook.Worksheets.Add($missing, $last)
        }
        $sheet.Name = [string]$spec.name
        foreach ($cell in $spec.cells) {
            $target = $sheet.Range([string]$cell.address)
            switch ([string]$cell.type) {
                'date' {
                    $date = [datetime]::ParseExact([string]$cell.value, 'yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture)
                    $target.NumberFormat = 'dd/mm/yyyy'
                    $target.Value2 = [double]$date.ToOADate()
                }
                'number' {
                    $target.NumberFormat = 'General'
                    $target.Value2 = [double]$cell.value
                }
                'text' {
                    # Ne jamais interpréter les données du manifeste comme des formules.
                    $target.NumberFormat = '@'
                    $target.Value2 = [string]$cell.value
                }
                default { throw "Type de cellule non pris en charge : $($cell.type)" }
            }
        }
        foreach ($definition in $spec.tables) {
            Write-Host "    Table : $($definition.name)"
            # xlSrcRange=1, xlYes=1. Toujours au moins deux lignes à la création.
            $range = $sheet.Range([string]$definition.nativeRange)
            $table = $sheet.ListObjects.Add(1, $range, $missing, 1)
            $table.Name = [string]$definition.name
            $table.TableStyle = 'TableStyleMedium2'
            if ([int]$definition.dataRows -eq 0) {
                # Supprime la ligne d'amorçage via Excel : aucun faux congé/log/entretien.
                if ($table.ListRows.Count -gt 0) { $table.ListRows.Item(1).Delete() }
            }
            if ($table.ListRows.Count -ne [int]$definition.dataRows) {
                throw "Nombre de lignes inattendu dans $($definition.name)."
            }
            if ($table.ListColumns.Count -ne $definition.headers.Count) {
                throw "Nombre de colonnes inattendu dans $($definition.name)."
            }
            for ($c = 1; $c -le $definition.headers.Count; $c++) {
                if ($table.ListColumns.Item($c).Name -cne [string]$definition.headers[$c-1]) {
                    throw "En-tête modifié par Excel dans $($definition.name), colonne $c."
                }
            }
        }
        $sheet.UsedRange.Font.Name = 'Calibri'
        $sheet.UsedRange.Font.Size = 11
        foreach ($width in $spec.widths) {
            $sheet.Columns.Item([string]$width.column).ColumnWidth = [double]$width.width
        }
        $position++
    }
    $home = $Workbook.Worksheets.Item('Accueil')
    $home.Range('B2').Font.Bold = $true
    $home.Range('D2').Font.Size = 20
    $home.Range('D2').Font.Bold = $true
    $home.Activate()
    $Workbook.Windows.Item(1).DisplayGridlines = $false
    if ($Workbook.Worksheets.Count -ne $Manifest.sheets.Count) {
        throw 'Nombre de feuilles inattendu après initialisation.'
    }
}
