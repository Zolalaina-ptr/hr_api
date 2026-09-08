Option Explicit


' Auteur : Arena | Date : 2026-09-08 | Description : Événement cmdLogin_Click, erreurs utilisateur contrôlées.
Private Sub cmdLogin_Click()
    On Error GoTo Failed
    If Login(Me.username.Value, Me.password.Value) Then
        Me.password.Value = ""
        Unload Me
    Else
        Me.password.Value = ""
    End If
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub
