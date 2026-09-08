@echo off
rem Auteur : Arena - 2026-09-08 - Lance le constructeur Excel Windows.
setlocal
cd /d "%~dp0"
echo Creation du Gestionnaire RH avec Excel Windows.
echo Excel doit etre installe. Lisez LIRE-AVANT-INSTALLATION.txt.
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\Build-Workbook.ps1"
if errorlevel 1 (
  echo.
  echo ECHEC : consultez le message ci-dessus. Aucun succes n'est garanti.
) else (
  echo.
  echo Assemblage termine. Voir livraison\GestionnaireRH.xlsm.
  echo Compilation et recette Excel restent a effectuer selon le manuel.
)
echo.
pause
endlocal
