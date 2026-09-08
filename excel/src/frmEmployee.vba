Option Explicit


' Auteur : Arena | Date : 2026-09-08 | Description : Événement UserForm_Initialize, erreurs utilisateur contrôlées.
Private Sub UserForm_Initialize()
    On Error GoTo Failed
    Me.f2.AddItem "Mme"
    Me.f2.AddItem "M."
    Me.f2.AddItem "Autre"
    Me.f18.AddItem "Actif"
    Me.f18.AddItem "Sorti"
    Me.f22.AddItem "H"
    Me.f22.AddItem "F"
    Me.f22.AddItem "Autre"
    Me.f22.AddItem "Non renseigné"
    RequireEditor
    FillReference Me.f11, "Contrat"
    FillReference Me.f12, "Service"
    FillReference Me.f13, "Poste"
    FillReference Me.f20, "Motif"
    cmdNew_Click
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Événement cmdNew_Click, erreurs utilisateur contrôlées.
Private Sub cmdNew_Click()
    On Error GoTo Failed
    Dim i As Long
    For i = 1 To 22
        Me.Controls("f" & i).Value = ""
    Next
    Me.f18.Value = "Actif": Me.f14.Value = "0": Me.f21.Value = "0"
    Me.f10.Value = Format$(Date, "dd/mm/yyyy")
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Événement cmdSearch_Click, erreurs utilisateur contrôlées.
Private Sub cmdSearch_Click()
    On Error GoTo Failed
    SearchEmployees Me
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Événement cmdLoad_Click, erreurs utilisateur contrôlées.
Private Sub cmdLoad_Click()
    On Error GoTo Failed
    LoadEmployee Me
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Événement cmdSave_Click, erreurs utilisateur contrôlées.
Private Sub cmdSave_Click()
    On Error GoTo Failed
    SaveEmployee Me
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Événement cmdDelete_Click, erreurs utilisateur contrôlées.
Private Sub cmdDelete_Click()
    On Error GoTo Failed
    DeleteEmployee Me
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Événement cmdClose_Click, erreurs utilisateur contrôlées.
Private Sub cmdClose_Click()
    On Error GoTo Failed
    Unload Me
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub
