Attribute VB_Name = "modAccounts"
Option Explicit

' Auteur : Arena | Date : 2026-09-08 | Description : Gestion de comptes locaux via formulaire masquant le mot de passe.
Public Sub OpenAccounts()
    On Error GoTo Failed
    RequireEditor True
    frmAccount.Show
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Création/réinitialisation ; interdit de désactiver ou rétrograder son propre compte.
Public Sub SaveAccount(ByVal form As Object)
    Dim username As String, role As String, employeeId As String, password As String, r As Range, employee As Range, salt As String
    RequireEditor True
    username = Trim$(form.username.Value): role = form.role.Value
    employeeId = Trim$(form.employeeId.Value): password = form.password.Value
    If username = "" Or username Like "*[!a-zA-Z0-9_.-]*" Then Err.Raise 5, , "Identifiant : lettres ASCII, chiffres, point, tiret ou soulignement."
    If role <> "Admin" And role <> "Gestionnaire" And role <> "Manager" And role <> "Salarie" And role <> "User" Then Err.Raise 5, , "Rôle invalide."
    If StrComp(username, SessionUser, vbTextCompare) = 0 Then
        If role <> "Admin" Or Not form.active.Value Then Err.Raise 5, , "Impossible de désactiver/rétrograder votre compte."
    End If
    If employeeId <> "" Then
        Set employee = FindRow("Salaries", employeeId)
        If employee Is Nothing Then Err.Raise 5, , "Matricule inexistant."
    ElseIf role = "Manager" Or role = "Salarie" Or role = "User" Then
        Err.Raise 5, , "Matricule requis pour ce rôle."
    End If
    Set r = FindRow("Utilisateurs", username)
    If r Is Nothing And Len(password) < 12 Then Err.Raise 5, , "Mot de passe initial : 12 caractères minimum."
    If password <> "" And Len(password) < 12 Then Err.Raise 5, , "Mot de passe : 12 caractères minimum."
    If MsgBox("Créer/modifier le compte " & username & " ?", vbYesNo + vbQuestion) <> vbYes Then Exit Sub
    If r Is Nothing Then Set r = Table("Utilisateurs").ListRows.Add.Range
    r.NumberFormat = "@"
    r.Cells(1, 1).Value2 = username: r.Cells(1, 2).Value2 = role: r.Cells(1, 3).Value2 = employeeId
    If password <> "" Then
        salt = Sha256(Setting("Protection") & username & CStr(Now) & CStr(Timer))
        r.Cells(1, 4).Value2 = salt: r.Cells(1, 5).Value2 = Sha256(salt & password)
    End If
    r.Cells(1, 6).Value2 = IIf(form.active.Value, "Oui", "Non")
    Audit "Compte modifié", username
    form.password.Value = ""
    MsgBox "Compte enregistré. Mot de passe vide sur compte existant : inchangé.", vbInformation
End Sub
