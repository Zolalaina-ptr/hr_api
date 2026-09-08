Attribute VB_Name = "modCore"
Option Explicit

' Auteur : Arena | Date : 2026-09-08 | Description : Résolution des tables internes sans dépendance à la feuille active.
Public Function Table(ByVal name As String) As ListObject
    Dim ws As Worksheet, lo As ListObject
    For Each ws In ThisWorkbook.Worksheets
        For Each lo In ws.ListObjects
            If lo.Name = name Then Set Table = lo: Exit Function
        Next
    Next
    Err.Raise 5, , "Table introuvable : " & name
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Recherche exacte par identifiant texte.
Public Function FindRow(ByVal tableName As String, ByVal key As String) As Range
    Dim lo As ListObject, found As Range
    Set lo = Table(tableName)
    If lo.DataBodyRange Is Nothing Then Exit Function
    Set found = lo.ListColumns(1).DataBodyRange.Find(What:=Replace(Replace(Replace(key, "~", "~~"), "*", "~*"), "?", "~?"), LookIn:=xlValues, LookAt:=xlWhole, MatchCase:=False)
    If Not found Is Nothing Then Set FindRow = lo.DataBodyRange.Rows(found.Row - lo.DataBodyRange.Row + 1)
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Lecture du paramétrage entreprise.
Public Function Setting(ByVal key As String) As String
    Dim r As Range
    Set r = FindRow("Configuration", key)
    If r Is Nothing Then Err.Raise 5, , "Paramètre absent : " & key
    Setting = CStr(r.Cells(1, 2).Value2)
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Identifiant monotone conservé même après suppression.
Public Function NextId(ByVal counter As String, ByVal prefix As String) As String
    Dim r As Range
    Set r = FindRow("Configuration", counter)
    r.Cells(1, 2).Value2 = CLng(r.Cells(1, 2).Value2) + 1
    NextId = prefix & Format$(r.Cells(1, 2).Value2, "000000")
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Journal sans données sensibles ni mots de passe.
Public Sub Audit(ByVal action As String, ByVal target As String)
    Dim row As ListRow
    Set row = Table("Logs").ListRows.Add
    row.Range.Value = Array(Now, SessionUser, action, target, Environ$("USERNAME"))
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Date française stricte, indépendante des paramètres régionaux.
Public Function ParseDate(ByVal text As String) As Date
    Dim parts As Variant, d As Date
    parts = Split(text, "/")
    If UBound(parts) <> 2 Then Err.Raise 5, , "Date attendue : jj/mm/aaaa."
    If Len(parts(2)) <> 4 Then Err.Raise 5, , "Année sur quatre chiffres requise."
    d = DateSerial(CInt(parts(2)), CInt(parts(1)), CInt(parts(0)))
    If Format$(d, "dd/mm/yyyy") <> text Then Err.Raise 5, , "Date invalide : " & text
    ParseDate = d
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Réinitialise les protections UserInterfaceOnly à chaque ouverture.
Public Sub Initialize()
    Dim ws As Worksheet
    On Error GoTo Failed
    For Each ws In ThisWorkbook.Worksheets
        ws.Protect Password:=Setting("Protection"), UserInterfaceOnly:=True
    Next
    Logout
    Exit Sub
Failed:
    MsgBox "Initialisation impossible : " & Err.Description, vbCritical
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Persistance uniquement de vues vides, toutes les tables restent masquées.
Public Sub LockWorkbook()
    Dim ws As Worksheet, chart As ChartObject
    ThisWorkbook.Unprotect Setting("Protection")
    ThisWorkbook.Worksheets("Accueil").Visible = xlSheetVisible
    ThisWorkbook.Worksheets("Accueil").Activate
    For Each ws In ThisWorkbook.Worksheets
        If ws.Name <> "Accueil" Then ws.Visible = xlSheetVeryHidden
    Next
    ThisWorkbook.Worksheets("Accueil").Range("D4:N10010").ClearContents
    ThisWorkbook.Worksheets("Accueil").Range("D4").Value = "Connectez-vous pour consulter votre espace RH."
    For Each chart In ThisWorkbook.Worksheets("Dashboard_Stat").ChartObjects
        chart.Delete
    Next
    ThisWorkbook.Worksheets("Dashboard_Stat").Cells.ClearContents
    ThisWorkbook.Worksheets("Documents").Cells.ClearContents
    ThisWorkbook.Protect Password:=Setting("Protection"), Structure:=True
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Navigation centralisée et erreurs présentées à l'utilisateur.
Public Sub Connect()
    On Error GoTo Failed
    Logout
    frmLogin.Show
    If SessionUser <> "" Then RefreshDashboard
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Ouvre le dossier salarié pour les éditeurs.
Public Sub OpenEmployee()
    On Error GoTo Failed
    RequireEditor
    frmEmployee.Show
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Ouvre le formulaire de demande.
Public Sub OpenLeave()
    On Error GoTo Failed
    If SessionUser = "" Then Err.Raise 5, , "Connectez-vous."
    frmLeave.Show
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Paramètres éditables par Admin, validation des valeurs à leur utilisation.
Public Sub EditSettings()
    Dim key As String, value As String, r As Range
    On Error GoTo Failed
    RequireEditor True
    key = InputBox("Clé : Entreprise, AcquisitionMensuelle, ModeJours, DebutExercice, CouleurMarine ou DossierExports")
    If key = "" Then Exit Sub
    Select Case key
        Case "Entreprise", "AcquisitionMensuelle", "ModeJours", "DebutExercice", "CouleurMarine", "DossierExports"
        Case Else: Err.Raise 5, , "Clé non modifiable ici. Utilisez la procédure de maintenance."
    End Select
    value = InputBox("Nouvelle valeur", key, Setting(key))
    If value = "" Then Exit Sub
    If key = "AcquisitionMensuelle" Then
        If Not IsNumeric(value) Then Err.Raise 5, , "Nombre requis."
        If CDbl(value) < 0 Or CDbl(value) > 31 Then Err.Raise 5, , "Taux entre 0 et 31."
    End If
    If key = "ModeJours" And value <> "Ouvres" And value <> "Ouvrables" Then Err.Raise 5, , "Ouvres ou Ouvrables attendu."
    If key = "DebutExercice" Then Call ParseDate(value)
    If key = "CouleurMarine" Then
        If Not IsNumeric(value) Then Err.Raise 5, , "Couleur RGB numérique requise."
        If CDbl(value) < 0 Or CDbl(value) > 16777215 Then Err.Raise 5, , "Couleur hors limites."
    End If
    Set r = FindRow("Configuration", key): r.Cells(1, 2).Value2 = value
    Audit "Paramètre modifié", key
    MsgBox "Paramètre enregistré.", vbInformation
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub
