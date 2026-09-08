# Gestionnaire RH — Excel / VBA

Application autonome ajoutée au dépôt, **sans modifier ni utiliser l’API Laravel**. Les données sont stockées dans des tables internes Excel ; aucune base SQL, Access ou dépendance web.

> **Statut de livraison : sources et kit d’assemblage, pas encore une application acceptée en production.** Le socle du lot 1 est implémenté. Excel Windows n’étant pas disponible dans l’environnement de développement, le `.xlsm` doit être assemblé, compilé, protégé et testé sur Windows. Aucun faux `.xlsm` obtenu en renommant un `.xlsx` n’est fourni.

## Correctif 1.0.1 — erreur de récupération du modèle

Un utilisateur a rencontré « We found a problem with some content » à l’ouverture de l’ancien modèle, puis une erreur COM `Workbooks.Open`. Le journal de récupération serait nécessaire pour identifier précisément la partie rejetée par Excel.

**Le constructeur n’ouvre désormais plus de modèle XLSX.** Il crée un classeur vierge avec `Workbooks.Add`, remplit les données de `tools/workbook-data.json`, puis fait créer les tables structurées par Excel lui-même. Les tables vides sont amorcées avec deux lignes, puis la ligne vide est retirée via Excel et les effectifs/colonnes sont vérifiés. Le XLSX fourni est maintenant un aperçu sans objets table, non utilisé par l’assemblage.

Extraire le kit **1.0.1 dans un nouveau dossier**, sans remplacer une application contenant de vraies données. Relancer `Creer-le-classeur.cmd` ou `tools/Build-Workbook.ps1`. Le script doit afficher « Constructeur v1.0.1 : création native Excel, sans ouverture du modèle XLSX ». Il ne demande pas de désactiver le mode protégé ou de réparer automatiquement un fichier. Les erreurs indiquent maintenant l’étape et la ligne en cause.

Cette correction supprime la dépendance à l’ancien fichier problématique ; elle **n’a pas pu être exécutée sous Excel Windows ici** et ne certifie pas la suite de l’assemblage VBA.

## Démarrage sur Windows

Prérequis : Excel de bureau 2016/2019/2021/Microsoft 365, Windows PowerShell 5.1 ou PowerShell Windows avec COM. Word de bureau uniquement pour les DOCX. Aucun Python nécessaire sur le poste utilisateur pour assembler le classeur.

1. Copier **tout le dossier `excel/`**, puis ouvrir PowerShell dans ce dossier.
2. Dans Excel : Options → Centre de gestion de la confidentialité → Paramètres des macros → activer **temporairement** « Accès approuvé au modèle d’objet du projet VBA ».
3. Exécuter :

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\Build-Workbook.ps1
   ```

4. Choisir le mot de passe initial `admin` au prompt masqué, **12 caractères minimum**. Aucun identifiant avec mot de passe `xxxx` ni accès caché n’est préinstallé.
5. Ouvrir `livraison/GestionnaireRH.xlsm` et autoriser les macros de ce fichier après revue de sa provenance.
6. **Alt+F11 → Débogage → Compiler VBAProject**. Se connecter comme `admin`, puis **Alt+F8 → RunSmokeTests** sur la copie de démonstration.
7. Exécuter [la recette Windows](docs/recette.md), puis verrouiller manuellement le projet VBA et **chiffrer le fichier**. Désactiver ensuite l’accès approuvé au modèle objet VBA. Le constructeur ne modifie pas les stratégies de sécurité du poste.

Destination et paramètres d’entreprise facultatifs :

```powershell
.\tools\Build-Workbook.ps1 `
  -Output 'C:\RH\GestionnaireRH.xlsm' `
  -ParametersCsv '.\examples\Parametres.csv'
```

Le constructeur refuse d’écraser une destination existante. **Ne pas l’utiliser pour migrer un fichier contenant des salariés réels** : il repart toujours des cinq dossiers fictifs du manifeste de données.

## Livrables disponibles

| Fichier | Contenu |
|---|---|
| [Manuel utilisateur PDF](docs/Manuel-utilisateur.pdf) | 8 pages : installation, droits, salariés, congés, exports, sécurité et formation |
| [Modèle Excel](examples/GestionnaireRH-modele.xlsx) | Aperçu des données et cinq salariés fictifs ; **sans macros ni tables structurées**, non utilisé par le constructeur |
| [Paramètres entreprise](examples/Parametres-entreprise.xlsx) | Exemple prérempli de configuration, références, fériés, grille et modèles |
| [Paramètres CSV](examples/Parametres.csv) | Six paramètres importables par le constructeur |
| `src/*.bas` | Modules métier VBA commentés, `Option Explicit` |
| `src/*.vba` | Événements du classeur et des quatre UserForms |
| `tools/workbook-data.json` | Données typées et schéma des feuilles/tables utilisés par le constructeur |
| `tools/Initialize-Workbook.ps1` | Création native des feuilles et ListObjects dans Excel, sans ouvrir de modèle externe |
| `tools/forms.json` | Description des contrôles MSForms, assemblés via le modèle objet VBA |
| `tools/Build-Workbook.ps1` | Assemblage local du véritable `.xlsm`, hash salé du mot de passe Admin et protection des feuilles |
| [Recette](docs/recette.md) | Tests fonctionnels, droits, persistance et performances à exécuter sur Excel |

## Fonctions implémentées dans les sources

### Lot 1

- **Dossiers salariés** : formulaire ajout/modification, recherche matricule/nom, champs administratifs, contrôles dates/NSS basique/email/salaire/références, clôture et archives. Suppression Admin avec confirmation et contrôle des références. Identifiants monotones non réutilisés.
- **Connexion** : Admin, Gestionnaire, Manager, Salarie et alias User ; formulaire avec mot de passe masqué ; gestion des comptes par Admin ; droits revérifiés dans les procédures métier.
- **Congés** : demandes, journées entières ouvrées ou ouvrables, fériés dédupliqués, interdiction des chevauchements, réservation des demandes en attente, validation/refus avec motif, interdiction de l’auto-validation Manager.
- **Accueil** : effectif actif, anniversaires du mois, résumé personnel, solde payé et journal des congés du périmètre. Vue Manager limitée aux collaborateurs directs et à lui-même.
- **Exports** : PDF ou XLSX des actifs autorisés, sans NSS, RIB, salaire ni date de naissance. Aucune feuille interne n’est copiée telle quelle.
- **Persistance** : vues et caches graphiques vidés avant sauvegarde ; toutes les tables VeryHidden ; protection des feuilles et de la structure ; données textuelles enregistrées comme texte pour éviter l’interprétation en formules.

### Bases du lot 2

- Génération Word/PDF à partir de modèles internes avec balises. **Brouillons à compléter et à faire valider juridiquement**, pas des contrats prêts à signer.
- Répartition par service en histogramme ; âge moyen, répartition H/F/autre et taux de départ YTD.
- Attribution/actualisation de compétences de la grille du poste ; historisation des entretiens.
- Journal des connexions réussies et actions applicatives, sans valeurs personnelles sensibles.

### Reste à réaliser / valider

| Exigence | Situation réelle |
|---|---|
| `.xlsm` compilé et projet VBA protégé | Assemblage fourni ; compilation et protection VBE manuelles à réaliser sur Windows |
| Lot 1 sans bug critique | **Non certifié** : recette Excel à exécuter |
| Ouverture < 5 s et 10 000 lignes | Traitements mémoire et restauration ScreenUpdating/EnableEvents implémentés ; mesures à effectuer |
| Photo dans formulaire | Chemin stocké, affichage/insertion non implémenté |
| Pyramide des âges / absentéisme / reporting avancé | Non implémenté |
| Matrice de polyvalence / consultation structurée des entretiens | Tables et saisies incluses, UI de consultation/matrice à développer |
| Audit détaillé avant/après et inviolable | Journal d’actions uniquement, non inviolable |
| Demi-journées, temps partiel, RTT acquis, report annuel automatique | Non implémenté |
| Formation de 2 heures | Programme fourni dans le manuel ; session non réalisée |

## Règles de congés explicites

- Exercice paramétrable de douze mois. Une demande ne traverse pas une limite d’exercice.
- Jours **inclusifs** ; `Ouvres` = lundi–vendredi, `Ouvrables` = lundi–samedi, hors fériés configurés.
- Acquisition par **mois calendaires complets**, arrêtée à aujourd’hui ou à la sortie, plafonnée à l’exercice. Aucun prorata du premier mois partiel ni anticipation des droits futurs.
- Disponible payé = report initial + acquisition − demandes payées validées − demandes payées en attente. Les refus libèrent la réservation ; une validation ne déduit pas une deuxième fois.
- RTT, maladie et sans solde sont journalisés mais ne consomment pas le compteur Payé.
- Changement d’exercice et report : procédure administrative manuelle documentée, pas une opération automatique.
- Les cinq dossiers initiaux ont une embauche avant 2026 et 5 jours de report. Au **08/09/2026**, 8 mois complets × 2,5 + 5 = **25 jours** avant toute demande.

Ces conventions sont des choix fonctionnels de démonstration ; les adapter à la convention collective et à la législation applicable avant exploitation.

## Architecture et maintenance

Tables : `Salaries`, `Conges`, `Configuration`, `References`, `Feries`, `Grille`, `Modeles`, `Utilisateurs`, `Logs`, `Competences`, `Entretiens`. Les colonnes et leur ordre sont contractuels : voir `tools/build_assets.py` et les tests de structure.

Les sources `.bas` sont en UTF-8 dans Git. Le constructeur les convertit en Windows-1252 avant import VBE ; les `.vba` et le JSON sont chargés explicitement en UTF-8. Les listes de ComboBox sont initialisées au lancement du formulaire, pas via des ressources binaires `.frx` opaques.

`tools/make_forms.py` est la source génératrice des UserForms et de leur description : y modifier les événements/dispositions avant régénération, sinon les modifications directes des `frm*.vba` seront écrasées. Les autres modules `.bas` et `ThisWorkbook.vba` se maintiennent directement.

`Parametres-entreprise.xlsx` est un support de préparation, **pas un import automatique complet**. Pour les références, fériés, grilles et modèles, le mainteneur modifie les données de `tools/build_assets.py`, régénère `tools/workbook-data.json` avec les commandes ci-dessous et relance les tests ; la CSV ne remplace que les six clés autorisées. Modifier le XLSX d’aperçu ne modifie plus le classeur assemblé. En exploitation, seuls ces six paramètres sont éditables par l’interface ; les migrations de références/données demandent un mainteneur et une sauvegarde.

## Contrôles exécutés ici

```bash
python -m venv .venv
# Linux/macOS : source .venv/bin/activate
# Windows : .\.venv\Scripts\Activate.ps1
pip install -r excel/requirements-dev.txt
python excel/tools/make_forms.py
python excel/tools/build_assets.py
python -m unittest discover -s excel/tests -v
```

**26 contrôles portables réussis** : tables/colonnes, cinq dossiers, références de code, contrôles et événements des formulaires, masquage initial, absence de mot de passe par défaut, vecteur SHA-256 UTF-16LE, menus, balises, déclarations Windows et PDF de huit pages ; cohérence du manifeste, plages natives non chevauchantes, suppression de la dépendance à Workbooks.Open et validation des tables vides.

Ces contrôles inspectent les artefacts et les sources : **ils n’exécutent ni VBA, ni PowerShell COM, ni Word et ne prouvent pas le fonctionnement sous Excel**. `RunSmokeTests` et la recette Windows sont fournis séparément et restent à exécuter.

## Sécurité et exploitation

**Ne pas distribuer le classeur maître à des salariés en espérant que le login VBA protège les autres dossiers.** Le masquage, les mots de passe de feuilles et le verrouillage du projet VBA sont contournables ; un lecteur de fichier peut extraire les tables d’un classeur non chiffré. SHA-256 salé est un hachage rapide, pas une défense robuste contre l’attaque hors ligne. Le compteur de tentatives est local à l’instance Excel.

Pour l’exploitation : chiffrement Excel à l’ouverture, ACL sur le répertoire, sauvegardes chiffrées, contrôle des exports et accès sur poste RH maîtrisé. Pour de véritables accès salariés isolés et simultanés, une architecture serveur serait nécessaire, contrairement à la contrainte 100 % Excel de ce cahier des charges.

Un seul utilisateur à la fois, **pas de coédition ni AutoSave**. Le journal interne n’est pas inviolable. Les exports ne sont pas chiffrés automatiquement. Aucune conformité RGPD ou juridique n’est certifiée par cette livraison.
