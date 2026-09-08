Option Explicit


' Auteur : Arena | Date : 2026-09-08 | Description : Événement UserForm_Initialize, erreurs utilisateur contrôlées.
Private Sub UserForm_Initialize()
    On Error GoTo Failed
    Me.role.AddItem "Admin"
    Me.role.AddItem "Gestionnaire"
    Me.role.AddItem "Manager"
    Me.role.AddItem "Salarie"
    Me.role.AddItem "User"
    RequireEditor True
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Événement cmdSave_Click, erreurs utilisateur contrôlées.
Private Sub cmdSave_Click()
    On Error GoTo Failed
    SaveAccount Me
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub
