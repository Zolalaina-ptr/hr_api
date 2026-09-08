Attribute VB_Name = "modLeave"
Option Explicit

' Auteur : Arena | Date : 2026-09-08 | Description : Jours entiers, bornes incluses ; week-end configurable et fériés dédupliqués.
Public Function WorkingDays(ByVal startDate As Date, ByVal endDate As Date) As Long
    Dim holidays As Object, row As ListRow, d As Date, weekdayNumber As Long, mode As String
    If endDate < startDate Then Err.Raise 5, , "La fin précède le début."
    If DateDiff("d", startDate, endDate) > 366 Then Err.Raise 5, , "Période limitée à 367 jours."
    mode = Setting("ModeJours")
    If mode <> "Ouvres" And mode <> "Ouvrables" Then Err.Raise 5, , "Mode de calcul invalide."
    Set holidays = CreateObject("Scripting.Dictionary")
    For Each row In Table("Feries").ListRows
        If IsDate(row.Range.Cells(1, 1).Value) Then holidays(CLng(CDate(row.Range.Cells(1, 1).Value))) = True
    Next
    For d = startDate To endDate
        weekdayNumber = Weekday(d, vbMonday)
        If weekdayNumber < 6 Or (mode = "Ouvrables" And weekdayNumber = 6) Then
            If Not holidays.Exists(CLng(d)) Then WorkingDays = WorkingDays + 1
        End If
    Next
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Solde payé : report + mois calendaires complets acquis - validés - réservations en attente.
Public Function Balance(ByVal employeeId As String, Optional ByVal excludeRequest As String = "") As Double
    Dim r As Range, row As ListRow, startDate As Date, cutoff As Date, period As Date, months As Long, cursor As Date
    Set r = FindRow("Salaries", employeeId)
    If r Is Nothing Then Err.Raise 5, , "Salarié introuvable."
    period = ParseDate(Setting("DebutExercice"))
    startDate = CDate(r.Cells(1, 10).Value)
    If startDate < period Then startDate = period
    cutoff = Date
    If cutoff > DateAdd("yyyy", 1, period) - 1 Then cutoff = DateAdd("yyyy", 1, period) - 1
    If r.Cells(1, 18).Value2 = "Sorti" Then
        If CDate(r.Cells(1, 19).Value) < cutoff Then cutoff = CDate(r.Cells(1, 19).Value)
    End If
    cursor = DateSerial(Year(startDate), Month(startDate), 1)
    If cursor < startDate Then cursor = DateAdd("m", 1, cursor)
    Do While DateAdd("m", 1, cursor) - 1 <= cutoff
        months = months + 1: cursor = DateAdd("m", 1, cursor)
    Loop
    Balance = CDbl(r.Cells(1, 21).Value2) + months * CDbl(Setting("AcquisitionMensuelle"))
    For Each row In Table("Conges").ListRows
        With row.Range
            If .Cells(1, 2).Value2 = employeeId And .Cells(1, 5).Value2 = "Payé" And .Cells(1, 1).Value2 <> excludeRequest Then
                If CDate(.Cells(1, 3).Value) >= period And CDate(.Cells(1, 4).Value) < DateAdd("yyyy", 1, period) Then
                    If .Cells(1, 7).Value2 = "Validé" Or .Cells(1, 7).Value2 = "En attente" Then Balance = Balance - CDbl(.Cells(1, 6).Value2)
                End If
            End If
        End With
    Next
    Balance = Round(Balance, 2)
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Demande avec réservation immédiate et contrôle des chevauchements.
Public Sub RequestLeave(ByVal employeeId As String, ByVal startText As String, ByVal endText As String, ByVal kind As String)
    Dim r As Range, row As ListRow, startDate As Date, endDate As Date, period As Date, days As Long, id As String
    If Not CanRead(employeeId) Then Err.Raise 5, , "Salarié hors périmètre."
    If SessionRole = "Manager" Or SessionRole = "Salarie" Or SessionRole = "User" Then
        If employeeId <> SessionEmployee Then Err.Raise 5, , "Vous ne pouvez soumettre que vos propres demandes."
    End If
    startDate = ParseDate(startText): endDate = ParseDate(endText)
    period = ParseDate(Setting("DebutExercice"))
    If startDate < period Or endDate >= DateAdd("yyyy", 1, period) Then Err.Raise 5, , "La demande doit rester dans l'exercice configuré. Scindez les périodes inter-exercices."
    Set r = FindRow("Salaries", employeeId)
    If r Is Nothing Then Err.Raise 5, , "Salarié inconnu."
    If r.Cells(1, 18).Value2 <> "Actif" Or startDate < CDate(r.Cells(1, 10).Value) Then Err.Raise 5, , "Salarié sorti ou période avant embauche."
    If Not InReference("Conge", kind) Then Err.Raise 5, , "Type de congé invalide."
    days = WorkingDays(startDate, endDate)
    If days = 0 Then Err.Raise 5, , "Aucun jour décomptable."
    For Each row In Table("Conges").ListRows
        With row.Range
            If .Cells(1, 2).Value2 = employeeId And .Cells(1, 7).Value2 <> "Refusé" Then
                If startDate <= CDate(.Cells(1, 4).Value) And endDate >= CDate(.Cells(1, 3).Value) Then Err.Raise 5, , "Chevauchement avec une demande existante."
            End If
        End With
    Next
    If kind = "Payé" And days > Balance(employeeId) Then Err.Raise 5, , "Solde payé disponible insuffisant (demandes en attente incluses)."
    If MsgBox("Soumettre " & days & " jour(s) ?", vbYesNo + vbQuestion) <> vbYes Then Exit Sub
    id = NextId("CompteurConges", "CON")
    Table("Conges").ListRows.Add.Range.Value = Array(id, employeeId, startDate, endDate, kind, days, "En attente", SessionUser, Now, "", "", "")
    Audit "Demande de congé", id
    RefreshDashboard
    MsgBox "Demande enregistrée : " & id, vbInformation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Transition unique, manager direct hors auto-validation ; RH habilité.
Public Sub DecideLeave()
    Dim id As String, r As Range, employee As Range, approved As VbMsgBoxResult, reason As String
    On Error GoTo Failed
    id = InputBox("Identifiant de la demande (voir Accueil)")
    If id = "" Then Exit Sub
    Set r = FindRow("Conges", id)
    If r Is Nothing Then Err.Raise 5, , "Demande inconnue."
    If Not CanRead(CStr(r.Cells(1, 2).Value2)) Then Err.Raise 5, , "Demande hors périmètre."
    If SessionRole = "Manager" Then
        If CStr(r.Cells(1, 2).Value2) = SessionEmployee Then Err.Raise 5, , "Auto-validation interdite au manager."
    Else
        RequireEditor
    End If
    If r.Cells(1, 7).Value2 <> "En attente" Then Err.Raise 5, , "Cette demande a déjà été traitée."
    approved = MsgBox("Oui : valider / Non : refuser / Annuler : ne rien changer", vbYesNoCancel + vbQuestion)
    If approved = vbCancel Then Exit Sub
    If approved = vbYes Then
        Set employee = FindRow("Salaries", CStr(r.Cells(1, 2).Value2))
        If employee Is Nothing Then Err.Raise 5, , "Salarié introuvable."
        If employee.Cells(1, 18).Value2 <> "Actif" Then Err.Raise 5, , "Dossier clôturé : refusez cette demande."
        If CDate(r.Cells(1, 3).Value) < ParseDate(Setting("DebutExercice")) Or CDate(r.Cells(1, 4).Value) >= DateAdd("yyyy", 1, ParseDate(Setting("DebutExercice"))) Then Err.Raise 5, , "Demande hors exercice courant."
        If r.Cells(1, 5).Value2 = "Payé" Then
            If CDbl(r.Cells(1, 6).Value2) > Balance(CStr(r.Cells(1, 2).Value2), id) Then Err.Raise 5, , "Solde devenu insuffisant."
        End If
        r.Cells(1, 7).Value2 = "Validé"
    Else
        reason = Trim$(InputBox("Motif du refus (obligatoire)"))
        If reason = "" Then Exit Sub
        r.Cells(1, 7).Value2 = "Refusé"
        r.Cells(1, 12).NumberFormat = "@": r.Cells(1, 12).Value2 = reason
    End If
    r.Cells(1, 10).Value2 = SessionUser: r.Cells(1, 11).Value = Now
    Audit "Congé " & r.Cells(1, 7).Value2, id
    RefreshDashboard
    MsgBox "Décision enregistrée.", vbInformation
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub
