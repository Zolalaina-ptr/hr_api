Attribute VB_Name = "modSkills"
Option Explicit

' Auteur : Arena | Date : 2026-09-08 | Description : Ajoute ou actualise une compétence pour le poste du salarié.
Public Sub RecordSkill()
    Dim employeeId As String, skill As String, level As String, employee As Range, row As ListRow, target As Range, valid As Boolean
    On Error GoTo Failed
    RequireEditor
    employeeId = InputBox("Matricule")
    If employeeId = "" Then Exit Sub
    Set employee = FindRow("Salaries", employeeId)
    If employee Is Nothing Then Err.Raise 5, , "Salarié inconnu."
    skill = InputBox("Compétence (doit figurer dans la grille du poste)")
    If skill = "" Then Exit Sub
    For Each row In Table("Grille").ListRows
        If row.Range.Cells(1, 1).Value2 = employee.Cells(1, 13).Value2 And row.Range.Cells(1, 2).Value2 = skill Then valid = True
    Next
    If Not valid Then Err.Raise 5, , "Compétence absente de la grille du poste."
    level = InputBox("Niveau : Débutant, Intermédiaire ou Expert")
    If level = "" Then Exit Sub
    If level <> "Débutant" And level <> "Intermédiaire" And level <> "Expert" Then Err.Raise 5, , "Niveau invalide."
    For Each row In Table("Competences").ListRows
        If row.Range.Cells(1, 2).Value2 = employeeId And row.Range.Cells(1, 3).Value2 = skill Then Set target = row.Range
    Next
    If MsgBox("Enregistrer cette compétence ?", vbYesNo + vbQuestion) <> vbYes Then Exit Sub
    If target Is Nothing Then Set target = Table("Competences").ListRows.Add.Range
    target.NumberFormat = "@"
    target.Value = Array(employeeId & "|" & skill, employeeId, skill, level, Format$(Date, "dd/mm/yyyy"))
    Audit "Compétence actualisée", employeeId & "|" & skill
    MsgBox "Compétence enregistrée.", vbInformation
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Historisation des entretiens, sans écraser les précédents.
Public Sub RecordInterview()
    Dim employeeId As String, dateText As String, remarks As String, r As Range, d As Date, id As String
    On Error GoTo Failed
    RequireEditor
    employeeId = InputBox("Matricule")
    If employeeId = "" Then Exit Sub
    Set r = FindRow("Salaries", employeeId)
    If r Is Nothing Then Err.Raise 5, , "Salarié inconnu."
    dateText = InputBox("Date de l'entretien jj/mm/aaaa")
    If dateText = "" Then Exit Sub
    d = ParseDate(dateText)
    remarks = InputBox("Remarques (ne pas inscrire de données médicales)")
    If remarks = "" Then Exit Sub
    If MsgBox("Enregistrer cet entretien ?", vbYesNo + vbQuestion) <> vbYes Then Exit Sub
    id = NextId("CompteurEntretiens", "ENT")
    Set r = Table("Entretiens").ListRows.Add.Range
    r.NumberFormat = "@": r.Cells(1, 3).NumberFormat = "dd/mm/yyyy"
    r.Value = Array(id, employeeId, d, remarks, SessionUser)
    Audit "Entretien enregistré", id
    MsgBox "Entretien enregistré.", vbInformation
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub
