Option Explicit


' Auteur : Arena | Date : 2026-09-08 | Description : Événement UserForm_Initialize, erreurs utilisateur contrôlées.
Private Sub UserForm_Initialize()
    On Error GoTo Failed
    FillReference Me.kind, "Conge"
    Me.employeeId.Value = SessionEmployee
    Me.employeeId.Locked = (SessionRole <> "Admin" And SessionRole <> "Gestionnaire")
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Événement cmdSubmit_Click, erreurs utilisateur contrôlées.
Private Sub cmdSubmit_Click()
    On Error GoTo Failed
    RequestLeave Me.employeeId.Value, Me.startDate.Value, Me.endDate.Value, Me.kind.Value
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub
