Attribute VB_Name = "modDocuments"
Option Explicit

' Auteur : Arena | Date : 2026-09-08 | Description : Modèles internes configurables, Word late binding ou PDF Excel ; brouillons à faire valider.
Public Sub GenerateDocument()
    Dim employeeId As String, kind As String, r As Range, template As Range, text As String, path As Variant
    Dim word As Object, doc As Object, ws As Worksheet, choice As VbMsgBoxResult
    On Error GoTo Failed
    RequireEditor
    employeeId = InputBox("Matricule du salarié")
    If employeeId = "" Then Exit Sub
    Set r = FindRow("Salaries", employeeId)
    If r Is Nothing Then Err.Raise 5, , "Salarié inconnu."
    kind = InputBox("Modèle : Contrat, Avenant, Certificat ou Attestation")
    If kind = "" Then Exit Sub
    Set template = FindRow("Modeles", kind)
    If template Is Nothing Then Err.Raise 5, , "Modèle introuvable."
    If kind = "Certificat" And r.Cells(1, 18).Value2 <> "Sorti" Then Err.Raise 5, , "Clôturez le dossier avant de produire un certificat."
    text = template.Cells(1, 2).Value2
    text = Replace(text, "[Entreprise]", Setting("Entreprise"))
    text = Replace(text, "[Nom]", r.Cells(1, 3).Value2)
    text = Replace(text, "[Prenom]", r.Cells(1, 4).Value2)
    text = Replace(text, "[Poste]", r.Cells(1, 13).Value2)
    text = Replace(text, "[Contrat]", r.Cells(1, 11).Value2)
    text = Replace(text, "[Salaire]", Format$(r.Cells(1, 14).Value2, "0.00"))
    text = Replace(text, "[Embauche]", Format$(r.Cells(1, 10).Value, "dd/mm/yyyy"))
    text = Replace(text, "[Sortie]", Format$(r.Cells(1, 19).Value, "dd/mm/yyyy"))
    text = Replace(text, "[Date]", Format$(Date, "dd/mm/yyyy"))
    If InStr(text, "[") > 0 Then Err.Raise 5, , "Le modèle contient des balises non résolues."
    choice = MsgBox("Oui : Word (doit être installé) / Non : PDF / Annuler", vbYesNoCancel + vbQuestion)
    If choice = vbCancel Then Exit Sub
    If choice = vbYes Then
        path = Application.GetSaveAsFilename(kind & "_" & employeeId & ".docx", "Word (*.docx), *.docx")
    Else
        path = Application.GetSaveAsFilename(kind & "_" & employeeId & ".pdf", "PDF (*.pdf), *.pdf")
    End If
    If VarType(path) = vbBoolean Then Exit Sub
    If Dir$(CStr(path)) <> "" Then
        If MsgBox("Remplacer ce document ?", vbYesNo + vbExclamation) <> vbYes Then Exit Sub
    End If
    If choice = vbYes Then
        Set word = CreateObject("Word.Application")
        Set doc = word.Documents.Add
        doc.Content.Text = text
        doc.SaveAs2 CStr(path), 16
        doc.Close False: Set doc = Nothing
        word.Quit: Set word = Nothing
    Else
        Set ws = ThisWorkbook.Worksheets("Documents")
        ws.Cells.ClearContents
        ws.Range("A1").NumberFormat = "@": ws.Range("A1").Value2 = text
        ws.Range("A1").WrapText = True: ws.Columns("A").ColumnWidth = 95: ws.Rows(1).RowHeight = 400
        ws.PageSetup.PrintArea = "$A$1": ws.PageSetup.Zoom = False
        ws.PageSetup.FitToPagesWide = 1: ws.PageSetup.FitToPagesTall = 1
        ws.ExportAsFixedFormat xlTypePDF, CStr(path)
        ws.Cells.ClearContents
    End If
    Audit "Document " & kind, employeeId
    MsgBox "Brouillon généré. Validation juridique requise avant signature.", vbInformation
    Exit Sub
Failed:
    On Error Resume Next
    If Not doc Is Nothing Then doc.Close False
    If Not word Is Nothing Then word.Quit
    If Not ws Is Nothing Then ws.Cells.ClearContents
    On Error GoTo 0
    MsgBox "Génération impossible. Vérifiez le modèle, Word et le chemin de destination.", vbExclamation
End Sub
