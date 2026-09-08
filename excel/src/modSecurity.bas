Attribute VB_Name = "modSecurity"
Option Explicit

Private Declare PtrSafe Function CryptAcquireContextW Lib "advapi32.dll" (ByRef provider As LongPtr, ByVal container As LongPtr, ByVal providerName As LongPtr, ByVal providerType As Long, ByVal flags As Long) As Long
Private Declare PtrSafe Function CryptCreateHash Lib "advapi32.dll" (ByVal provider As LongPtr, ByVal algorithm As Long, ByVal key As LongPtr, ByVal flags As Long, ByRef hash As LongPtr) As Long
Private Declare PtrSafe Function CryptHashData Lib "advapi32.dll" (ByVal hash As LongPtr, ByVal data As LongPtr, ByVal length As Long, ByVal flags As Long) As Long
Private Declare PtrSafe Function CryptGetHashParam Lib "advapi32.dll" (ByVal hash As LongPtr, ByVal param As Long, ByRef data As Byte, ByRef length As Long, ByVal flags As Long) As Long
Private Declare PtrSafe Function CryptDestroyHash Lib "advapi32.dll" (ByVal hash As LongPtr) As Long
Private Declare PtrSafe Function CryptReleaseContext Lib "advapi32.dll" (ByVal provider As LongPtr, ByVal flags As Long) As Long

Public SessionUser As String, SessionRole As String, SessionEmployee As String
Private Scope As Object
Private Attempts As Long
Private LockedUntil As Date

' Auteur : Arena | Date : 2026-09-08 | Description : SHA-256 Windows, encodage UTF-16LE identique au constructeur.
Public Function Sha256(ByVal value As String) As String
    Dim p As LongPtr, h As LongPtr, bytes(0 To 31) As Byte, n As Long, i As Long
    On Error GoTo Failed
    If CryptAcquireContextW(p, 0, 0, 24, &HF0000000) = 0 Then Err.Raise 5
    If CryptCreateHash(p, &H800C, 0, 0, h) = 0 Then Err.Raise 5
    If CryptHashData(h, StrPtr(value), LenB(value), 0) = 0 Then Err.Raise 5
    n = 32
    If CryptGetHashParam(h, 2, bytes(0), n, 0) = 0 Then Err.Raise 5
    For i = 0 To 31
        Sha256 = Sha256 & LCase$(Right$("0" & Hex$(bytes(i)), 2))
    Next
    CryptDestroyHash h
    CryptReleaseContext p, 0
    Exit Function
Failed:
    If h <> 0 Then CryptDestroyHash h
    If p <> 0 Then CryptReleaseContext p, 0
    Err.Raise vbObjectError + 1, , "Service cryptographique Windows indisponible."
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Authentification locale, verrouillage temporaire après cinq échecs.
Public Function Login(ByVal username As String, ByVal password As String) As Boolean
    Dim rows As Variant, i As Long
    On Error GoTo Failed
    ResetScope
    SessionUser = "": SessionRole = "": SessionEmployee = ""
    If Now < LockedUntil Then Err.Raise 5, , "Trop de tentatives. Réessayez dans cinq minutes."
    rows = Table("Utilisateurs").DataBodyRange.Value2
    For i = 1 To UBound(rows, 1)
        If StrComp(Trim$(username), CStr(rows(i, 1)), vbTextCompare) = 0 And rows(i, 6) = "Oui" Then
            If Sha256(CStr(rows(i, 4)) & password) = CStr(rows(i, 5)) Then
                SessionUser = rows(i, 1): SessionRole = rows(i, 2): SessionEmployee = rows(i, 3)
                Attempts = 0
                Audit "Connexion", SessionUser
                Login = True
                Exit Function
            End If
        End If
    Next
    Attempts = Attempts + 1
    If Attempts >= 5 Then LockedUntil = DateAdd("n", 5, Now): Attempts = 0
    MsgBox "Identifiants incorrects ou compte désactivé.", vbExclamation
    Exit Function
Failed:
    SessionUser = "": SessionRole = "": SessionEmployee = ""
    MsgBox Err.Description, vbExclamation
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Autorise la consultation selon le périmètre de la session.
Public Function CanRead(ByVal employeeId As String) As Boolean
    Dim data As Variant, i As Long
    If SessionUser = "" Then Exit Function
    If SessionRole = "Admin" Or SessionRole = "Gestionnaire" Then CanRead = True: Exit Function
    If employeeId = SessionEmployee And employeeId <> "" Then CanRead = True: Exit Function
    If SessionRole <> "Manager" Or SessionEmployee = "" Then Exit Function
    If Scope Is Nothing Then
        Set Scope = CreateObject("Scripting.Dictionary")
        If Not Table("Salaries").DataBodyRange Is Nothing Then
            data = Table("Salaries").DataBodyRange.Value2
            For i = 1 To UBound(data, 1)
                If CStr(data(i, 17)) = SessionEmployee Then Scope(CStr(data(i, 1))) = True
            Next
        End If
    End If
    CanRead = Scope.Exists(employeeId)
End Function

' Auteur : Arena | Date : 2026-09-08 | Description : Invalide le périmètre après connexion et modification.
Public Sub ResetScope()
    Set Scope = Nothing
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Contrôle métier, indépendant des boutons et de la visibilité des feuilles.
Public Sub RequireEditor(Optional ByVal adminOnly As Boolean = False)
    If SessionUser = "" Then Err.Raise 5, , "Connectez-vous."
    If adminOnly Then
        If SessionRole <> "Admin" Then Err.Raise 5, , "Action réservée à l'administrateur."
    Else
        If SessionRole <> "Admin" And SessionRole <> "Gestionnaire" Then Err.Raise 5, , "Droit de modification requis."
    End If
End Sub

' Auteur : Arena | Date : 2026-09-08 | Description : Déconnexion et nettoyage de toutes les vues.
Public Sub Logout()
    On Error GoTo Failed
    ResetScope
    LockWorkbook
    SessionUser = "": SessionRole = "": SessionEmployee = ""
    Exit Sub
Failed:
    SessionUser = "": SessionRole = "": SessionEmployee = ""
    MsgBox Err.Description, vbCritical
End Sub
