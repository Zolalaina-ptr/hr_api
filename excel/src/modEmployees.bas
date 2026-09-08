Attribute VB_Name = "modEmployees"
Option Explicit

' Auteur : Arena | Date : 2026-09-08 | Description : Remplit les listes de référence à partir du paramétrage.
Public Sub FillReference(ByVal control As Object, ByVal category As String)
    Dim row As ListRow
    control.Clear
    For Each row In Table("References").ListRows
        If row.Range.Cells(1, 1).Value2 = category Then control.AddItem row.Range.Cells(1, 2).Value2
    Next
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Validation des références métier.
Public Function InReference(ByVal category As String, ByVal value As String) As Boolean
    Dim row As ListRow
    For Each row In Table("References").ListRows
        If row.Range.Cells(1, 1).Value2 = category And row.Range.Cells(1, 2).Value2 = value Then InReference = True: Exit Function
    Next
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Recherche mémoire, inclut les sortants seulement sur demande.
Public Sub SearchEmployees(ByVal form As Object)
    Dim data As Variant, i As Long, term As String
    RequireEditor
    form.lstResults.Clear
    If Table("Salaries").DataBodyRange Is Nothing Then Exit Sub
    data = Table("Salaries").DataBodyRange.Value2
    term = LCase$(Trim$(form.q.Value))
    For i = 1 To UBound(data, 1)
        If form.chkArchives.Value Or data(i, 18) = "Actif" Then
            If InStr(1, LCase$(data(i, 1) & " " & data(i, 3) & " " & data(i, 4)), term) > 0 Then
                form.lstResults.AddItem data(i, 1)
                form.lstResults.List(form.lstResults.ListCount - 1, 1) = data(i, 3) & " " & data(i, 4)
            End If
        End If
    Next
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Chargement du dossier sélectionné.
Public Sub LoadEmployee(ByVal form As Object)
    Dim r As Range, i As Long
    RequireEditor
    If form.lstResults.ListIndex < 0 Then Err.Raise 5, , "Sélectionnez un salarié."
    Set r = FindRow("Salaries", form.lstResults.List(form.lstResults.ListIndex, 0))
    If r Is Nothing Then Err.Raise 5, , "Dossier introuvable."
    For i = 1 To 22
        If (i = 5 Or i = 10 Or i = 19) And r.Cells(1, i).Value2 <> "" Then
            form.Controls("f" & i).Value = Format$(r.Cells(1, i).Value, "dd/mm/yyyy")
        Else
            form.Controls("f" & i).Value = CStr(r.Cells(1, i).Value2)
        End If
    Next
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Validation complète avant écriture atomique de la ligne.
Public Sub SaveEmployee(ByVal form As Object)
    Dim values(1 To 1, 1 To 22) As Variant, i As Long, r As Range, existing As Range, id As String, nss As String
    RequireEditor
    For i = 1 To 22
        values(1, i) = Trim$(CStr(form.Controls("f" & i).Value))
    Next
    If values(1, 3) = "" Or values(1, 4) = "" Then Err.Raise 5, , "Nom et prénom obligatoires."
    values(1, 5) = ParseDate(values(1, 5)): values(1, 10) = ParseDate(values(1, 10))
    If values(1, 5) >= Date Or values(1, 5) >= values(1, 10) Then Err.Raise 5, , "Dates de naissance/embauche incohérentes."
    If Not InReference("Contrat", values(1, 11)) Or Not InReference("Service", values(1, 12)) Or Not InReference("Poste", values(1, 13)) Then Err.Raise 5, , "Contrat, service et poste requis dans les références."
    If values(1, 8) <> "" And Not values(1, 8) Like "?*@?*.?*" Then Err.Raise 5, , "Adresse e-mail invalide."
    nss = Replace(values(1, 6), " ", "")
    If nss <> "" Then
        If Len(nss) <> 15 Or nss Like "*[!0-9]*" Then Err.Raise 5, , "NSS : quinze chiffres attendus (contrôle basique, pas de certification)."
    End If
    values(1, 6) = nss
    For i = 14 To 21 Step 7
        If Not IsNumeric(values(1, i)) Then Err.Raise 5, , "Salaire et solde initial doivent être numériques."
        values(1, i) = CDbl(values(1, i))
    Next
    If values(1, 14) < 0 Then Err.Raise 5, , "Salaire négatif interdit."
    If values(1, 18) <> "Actif" And values(1, 18) <> "Sorti" Then Err.Raise 5, , "Statut : Actif ou Sorti."
    If values(1, 18) = "Sorti" Then
        values(1, 19) = ParseDate(values(1, 19))
        If values(1, 19) < values(1, 10) Or values(1, 19) > Date Then Err.Raise 5, , "La sortie doit être comprise entre l'embauche et aujourd'hui."
        If Not InReference("Motif", values(1, 20)) Then Err.Raise 5, , "Motif de sortie obligatoire."
    Else
        If values(1, 19) <> "" Or values(1, 20) <> "" Then Err.Raise 5, , "Un dossier actif ne doit pas contenir de sortie."
    End If
    id = values(1, 1)
    If values(1, 17) <> "" Then
        Set r = FindRow("Salaries", values(1, 17))
        If r Is Nothing Then Err.Raise 5, , "Matricule manager inexistant."
        If values(1, 17) = id Or r.Cells(1, 18).Value2 <> "Actif" Then Err.Raise 5, , "Manager invalide."
    End If
    If id <> "" Then
        Set existing = FindRow("Salaries", id)
        If existing Is Nothing Then Err.Raise 5, , "Dossier supprimé. Relancez la recherche."
    End If
    If MsgBox("Enregistrer ce dossier ?", vbYesNo + vbQuestion) <> vbYes Then Exit Sub
    If id = "" Then
        id = NextId("CompteurSalaries", "SAL")
        values(1, 1) = id
        Set existing = Table("Salaries").ListRows.Add.Range
    End If
    ' Colonnes textuelles protégées contre l'interprétation comme formules.
    existing.NumberFormat = "@"
    For i = 1 To 22
        If i = 5 Or i = 10 Or i = 19 Then existing.Cells(1, i).NumberFormat = "dd/mm/yyyy"
        If i = 14 Or i = 21 Then existing.Cells(1, i).NumberFormat = "0.00"
    Next
    existing.Value = values
    form.f1.Value = id
    Audit "Dossier enregistré", id
    RefreshDashboard
    MsgBox "Salarié enregistré avec succès.", vbInformation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Suppression Admin sans références ; sinon privilégier la clôture.
Public Sub DeleteEmployee(ByVal form As Object)
    Dim id As String, r As Range, row As ListRow, name As Variant, col As Long
    RequireEditor True
    id = form.f1.Value
    Set r = FindRow("Salaries", id)
    If r Is Nothing Then Err.Raise 5, , "Chargez un dossier."
    For Each name In Array("Conges", "Competences", "Entretiens", "Utilisateurs", "Salaries")
        col = 2
        If name = "Utilisateurs" Then col = 3
        If name = "Salaries" Then col = 17
        For Each row In Table(CStr(name)).ListRows
            If CStr(row.Range.Cells(1, col).Value2) = id Then Err.Raise 5, , "Dossier référencé : clôturez-le au lieu de le supprimer."
        Next
    Next
    If MsgBox("Suppression définitive de " & id & " ?", vbYesNo + vbExclamation) <> vbYes Then Exit Sub
    Table("Salaries").ListRows(r.Row - Table("Salaries").HeaderRowRange.Row).Delete
    Audit "Suppression dossier", id
    RefreshDashboard
    MsgBox "Dossier supprimé.", vbInformation
    Unload form
End Sub
