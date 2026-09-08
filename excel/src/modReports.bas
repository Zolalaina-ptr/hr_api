Attribute VB_Name = "modReports"
Option Explicit

' Auteur : Arena | Date : 2026-09-08 | Description : Dashboard filtré en mémoire ; restauration systématique de l'état Excel.
Public Sub RefreshDashboard()
    Dim ws As Worksheet, data As Variant, leaves As Variant, i As Long, n As Long, birthdays As Long, count As Long
    Dim age As Long, totalAge As Double, departed As Long, opening As Long, women As Long, men As Long
    Dim startDate As Date, services As Object, key As Variant, oldEvents As Boolean, oldScreen As Boolean
    Dim stats As Worksheet, chart As ChartObject, button As Shape, own As Range
    oldEvents = Application.EnableEvents: oldScreen = Application.ScreenUpdating
    On Error GoTo Failed
    If SessionUser = "" Then Err.Raise 5, , "Connectez-vous."
    Application.EnableEvents = False: Application.ScreenUpdating = False
    ResetScope
    Set ws = ThisWorkbook.Worksheets("Accueil")
    ws.Range("D4:N10010").ClearContents
    ws.Range("D4").Value2 = "ESPACE RH / " & SessionUser & " / " & SessionRole
    ws.Range("D4:N4").Interior.Color = CLng(Setting("CouleurMarine"))
    ws.Range("D4:N4").Font.Color = vbWhite
    For Each button In ws.Shapes
        If Left$(button.Name, 4) = "nav_" Then button.Fill.ForeColor.RGB = CLng(Setting("CouleurMarine"))
    Next
    ws.Range("D9").Value2 = "ANNIVERSAIRES DU MOIS (salariés actifs)"
    ws.Range("D10:F10").Value = Array("Matricule", "Nom et prénom", "Jour")
    Set services = CreateObject("Scripting.Dictionary")
    startDate = DateSerial(Year(Date), 1, 1)
    If Not Table("Salaries").DataBodyRange Is Nothing Then
        data = Table("Salaries").DataBodyRange.Value2
        For i = 1 To UBound(data, 1)
            If CanRead(CStr(data(i, 1))) Then
                If CDate(data(i, 10)) < startDate Then
                    If data(i, 18) = "Actif" Then
                        opening = opening + 1
                    ElseIf CDate(data(i, 19)) >= startDate Then
                        opening = opening + 1
                    End If
                End If
                If data(i, 18) = "Sorti" Then
                    If CDate(data(i, 19)) >= startDate And CDate(data(i, 19)) <= Date Then departed = departed + 1
                ElseIf CDate(data(i, 10)) <= Date Then
                    count = count + 1
                    age = DateDiff("yyyy", CDate(data(i, 5)), Date)
                    If DateSerial(Year(Date), Month(CDate(data(i, 5))), Day(CDate(data(i, 5)))) > Date Then age = age - 1
                    totalAge = totalAge + age
                    If data(i, 22) = "F" Then women = women + 1
                    If data(i, 22) = "H" Then men = men + 1
                    key = CStr(data(i, 12)): services(key) = services(key) + 1
                    If Month(CDate(data(i, 5))) = Month(Date) Then
                        birthdays = birthdays + 1
                        ws.Cells(10 + birthdays, 4).Resize(1, 3).Value = Array(data(i, 1), data(i, 3) & " " & data(i, 4), Day(CDate(data(i, 5))))
                    End If
                End If
            End If
        Next
    End If
    ws.Range("D6").Value = "Effectif actif : " & count
    If count > 0 Then ws.Range("G6").Value = "Âge moyen : " & Round(totalAge / count, 1)
    ws.Range("J6").Value = "F : " & women & " / H : " & men & " / Autre ou non renseigné : " & count - women - men
    If opening + count > 0 Then ws.Range("D7").Value = "Taux de départ YTD : " & Format$(departed / ((opening + count) / 2), "0.0%")
    If SessionEmployee <> "" Then
        ws.Range("G7").Value = "Solde payé disponible : " & Balance(SessionEmployee)
        Set own = FindRow("Salaries", SessionEmployee)
        If Not own Is Nothing Then ws.Range("D8").Value = own.Cells(1, 3).Value2 & " " & own.Cells(1, 4).Value2 & " / " & own.Cells(1, 12).Value2 & " / " & own.Cells(1, 13).Value2 & " / Embauche : " & Format$(own.Cells(1, 10).Value, "dd/mm/yyyy")
    End If
    ws.Range("H9").Value = "CONGÉS / votre périmètre"
    ws.Range("H10:N10").Value = Array("Demande", "Salarié", "Début", "Fin", "Type", "Jours", "Statut")
    If Not Table("Conges").DataBodyRange Is Nothing Then
        leaves = Table("Conges").DataBodyRange.Value2
        For i = 1 To UBound(leaves, 1)
            If CanRead(CStr(leaves(i, 2))) Then
                n = n + 1
                If n <= 10000 Then ws.Cells(10 + n, 8).Resize(1, 7).Value = Array(leaves(i, 1), leaves(i, 2), CDate(leaves(i, 3)), CDate(leaves(i, 4)), leaves(i, 5), leaves(i, 6), leaves(i, 7))
            End If
        Next
    End If
    ws.Range("J11:K10010").NumberFormat = "dd/mm/yyyy"
    Set stats = ThisWorkbook.Worksheets("Dashboard_Stat")
    stats.Cells.ClearContents
    stats.Range("A1:B1").Value = Array("Service", "Effectif")
    n = 1
    For Each key In services.Keys
        n = n + 1: stats.Cells(n, 1).Resize(1, 2).Value = Array(key, services(key))
    Next
    For Each chart In stats.ChartObjects
        chart.Delete
    Next
    If n > 1 Then
        Set chart = stats.ChartObjects.Add(260, 40, 650, 340)
        chart.Chart.SetSourceData stats.Range("A1:B" & n)
        chart.Chart.ChartType = xlColumnClustered
        chart.Chart.HasTitle = True: chart.Chart.ChartTitle.Text = "Effectif actif par service"
    End If
Cleanup:
    Application.EnableEvents = oldEvents: Application.ScreenUpdating = oldScreen
    Exit Sub
Failed:
    MsgBox "Tableau de bord : " & Err.Description, vbExclamation
    Resume Cleanup
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Accès aux graphiques uniquement aux RH.
Public Sub ShowStats()
    On Error GoTo Failed
    RequireEditor
    RefreshDashboard
    ThisWorkbook.Unprotect Setting("Protection")
    ThisWorkbook.Worksheets("Dashboard_Stat").Visible = xlSheetVisible
    ThisWorkbook.Protect Password:=Setting("Protection"), Structure:=True
    ThisWorkbook.Worksheets("Dashboard_Stat").Activate
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Export minimal sans NSS, banque, salaire ou naissance ; filtre de rôle réappliqué.
Public Sub ExportEmployees()
    Dim output As Workbook, ws As Worksheet, data As Variant, i As Long, n As Long, choice As VbMsgBoxResult, path As Variant
    On Error GoTo Failed
    If SessionUser = "" Then Err.Raise 5, , "Connectez-vous."
    choice = MsgBox("Oui : PDF / Non : Excel / Annuler : quitter", vbYesNoCancel + vbQuestion)
    If choice = vbCancel Then Exit Sub
    If choice = vbYes Then
        path = Application.GetSaveAsFilename(Setting("DossierExports") & "Salaries_" & Format$(Now, "yyyymmdd_hhnnss") & ".pdf", "PDF (*.pdf), *.pdf")
    Else
        path = Application.GetSaveAsFilename(Setting("DossierExports") & "Salaries_" & Format$(Now, "yyyymmdd_hhnnss") & ".xlsx", "Excel (*.xlsx), *.xlsx")
    End If
    If VarType(path) = vbBoolean Then Exit Sub
    If Dir$(CStr(path)) <> "" Then
        If MsgBox("Remplacer le fichier existant ?", vbYesNo + vbExclamation) <> vbYes Then Exit Sub
    End If
    Set output = Workbooks.Add(xlWBATWorksheet): Set ws = output.Worksheets(1)
    ws.Name = "Salaries": ws.Cells.NumberFormat = "@"
    ws.Range("A1:G1").Value = Array("Matricule", "Nom", "Prénom", "Service", "Poste", "Email", "Statut")
    n = 1
    If Not Table("Salaries").DataBodyRange Is Nothing Then
        data = Table("Salaries").DataBodyRange.Value2
        For i = 1 To UBound(data, 1)
            If CanRead(CStr(data(i, 1))) Then
                If data(i, 18) = "Actif" Then
                    n = n + 1
                    ws.Cells(n, 1).Resize(1, 7).Value = Array(data(i, 1), data(i, 3), data(i, 4), data(i, 12), data(i, 13), data(i, 8), data(i, 18))
                End If
            End If
        Next
    End If
    ws.Rows(1).Font.Bold = True: ws.Columns("A:G").AutoFit
    With ws.PageSetup
        .Orientation = xlLandscape: .Zoom = False: .FitToPagesWide = 1: .FitToPagesTall = False
        .PrintTitleRows = "$1:$1": .PrintArea = "$A$1:$G$" & n
    End With
    If choice = vbYes Then
        ws.ExportAsFixedFormat xlTypePDF, CStr(path)
    Else
        output.SaveAs CStr(path), xlOpenXMLWorkbook
    End If
    output.Close False: Set output = Nothing
    Audit "Export salariés", IIf(choice = vbYes, "PDF", "Excel")
    MsgBox "Export terminé.", vbInformation
    Exit Sub
Failed:
    If Not output Is Nothing Then output.Close False
    MsgBox "Export impossible : " & Err.Description, vbExclamation
End Sub
