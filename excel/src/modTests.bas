Attribute VB_Name = "modTests"
Option Explicit

' Auteur : Arena | Date : 2026-09-08 | Description : Assertions de recette à lancer après compilation, sur copie de démonstration uniquement.
Public Sub RunSmokeTests()
    Dim originalMode As String, r As Range, caught As Boolean, d As Date, message As String
    On Error GoTo Failed
    RequireEditor True
    If MsgBox("Tests sur copie de démonstration avec fériés France 2026 uniquement. Continuer ?", vbYesNo + vbQuestion) <> vbYes Then Exit Sub
    Set r = FindRow("Configuration", "ModeJours")
    originalMode = CStr(r.Cells(1, 2).Value2)
    r.Cells(1, 2).Value2 = "Ouvres"
    AssertEqual WorkingDays(DateSerial(2026, 9, 7), DateSerial(2026, 9, 11)), 5, "Semaine ouvrée"
    AssertEqual WorkingDays(DateSerial(2026, 9, 12), DateSerial(2026, 9, 13)), 0, "Week-end"
    AssertEqual WorkingDays(DateSerial(2026, 7, 14), DateSerial(2026, 7, 14)), 0, "Férié"
    AssertEqual WorkingDays(DateSerial(2026, 7, 13), DateSerial(2026, 7, 17)), 4, "Semaine avec férié"
    r.Cells(1, 2).Value2 = "Ouvrables"
    AssertEqual WorkingDays(DateSerial(2026, 9, 7), DateSerial(2026, 9, 13)), 6, "Semaine ouvrable"
    AssertEqual CLng(ParseDate("29/02/2024")), CLng(DateSerial(2024, 2, 29)), "Année bissextile"
    On Error Resume Next
    d = ParseDate("31/02/2026")
    caught = (Err.Number <> 0): Err.Clear
    On Error GoTo Failed
    If Not caught Then Err.Raise 5, , "Date invalide acceptée."
    If Sha256("abc") <> "13e228567e8249fce53337f25d7970de3bd68ab2653424c7b8f9fd05e33caedf" Then Err.Raise 5, , "SHA-256 UTF-16LE incorrect."
    message = "8 contrôles réussis. Compléter impérativement la recette fonctionnelle et les tests 10 000 lignes."
Cleanup:
    If Not r Is Nothing Then r.Cells(1, 2).Value2 = originalMode
    MsgBox message, vbInformation
    Exit Sub
Failed:
    message = "Échec : " & Err.Description
    Resume Cleanup
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Assertion indépendante de Debug.Assert et du mode de compilation.
Private Sub AssertEqual(ByVal actual As Long, ByVal expected As Long, ByVal label As String)
    If actual <> expected Then Err.Raise 5, , label & " : attendu " & expected & ", obtenu " & actual
End Sub
