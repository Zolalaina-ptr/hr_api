Option Explicit

' Auteur : Arena | Date : 2026-09-08 | Description : Ouverture en mode exclusif, sans données affichées avant connexion.
Private Sub Workbook_Open()
    On Error GoTo Failed
    If Me.ReadOnly Then
        MsgBox "Classeur en lecture seule : ouvrez une copie autorisée ou attendez la libération du fichier. Utilisation simultanée non supportée.", vbExclamation
        Me.Close SaveChanges:=False
        Exit Sub
    End If
    Initialize
    Exit Sub
Failed:
    MsgBox "Ouverture : " & Err.Description, vbCritical
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Nettoie les vues avant toute sauvegarde, y compris Enregistrer sous.
Private Sub Workbook_BeforeSave(ByVal SaveAsUI As Boolean, Cancel As Boolean)
    On Error GoTo Failed
    LockWorkbook
    Exit Sub
Failed:
    Cancel = True
    MsgBox "Sauvegarde annulée : impossible de verrouiller les vues. " & Err.Description, vbCritical
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Restaure la vue seulement en mémoire après sauvegarde.
Private Sub Workbook_AfterSave(ByVal Success As Boolean)
    On Error GoTo Failed
    If SessionUser <> "" Then RefreshDashboard
    If Success Then Me.Saved = True
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Vide les vues avant fermeture sans cacher les modifications non enregistrées.
Private Sub Workbook_BeforeClose(Cancel As Boolean)
    Dim wasSaved As Boolean
    On Error GoTo Failed
    wasSaved = Me.Saved
    LockWorkbook
    If wasSaved Then Me.Saved = True
    Exit Sub
Failed:
    Cancel = True
    MsgBox "Fermeture annulée : " & Err.Description, vbCritical
End Sub
