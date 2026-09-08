# Plan de recette Excel Windows — À EXÉCUTER

Date de préparation : 2026-09-08. Aucun résultat Excel/Word n'est revendiqué depuis Linux.
Conserver pour chaque test : date, testeur, version/build Excel, architecture 32/64 bits, résultat réel, capture ou fichier témoin et anomalie éventuelle.

## Préparation

- Copier le kit 1.0.1 dans un dossier Windows neuf ; assembler avec `tools/Build-Workbook.ps1`.
- Régression de l’erreur Open : renommer temporairement l’aperçu `examples/GestionnaireRH-modele.xlsx` sur la copie de recette, puis lancer le constructeur. Il doit afficher « création native Excel » et ne jamais demander d’ouvrir/réparer le modèle. Remettre le nom de l’aperçu après le test. Le script utilise le manifeste JSON, pas ce XLSX.
- Vérifier les dix feuilles, onze ListObjects, cinq salariés et zéro ligne métier dans Conges/Logs/Competences/Entretiens immédiatement après assemblage (avant connexion pour Logs). Une ligne d’amorçage ne doit jamais devenir un faux enregistrement.
- Si l’ancien fichier est examiné pour diagnostic, conserver le journal XML de réparation Excel et ne pas utiliser le fichier réparé comme application.
- Utiliser exclusivement des données fictives. Exercice 01/01/2026, acquisition 2,5, jours ouvrés, fériés France 2026 fournis.
- Compiler dans VBE ; aucune référence MANQUANT ne doit apparaître. Sources compatibles VBA7, déclarations `PtrSafe`/`LongPtr`, Word en late binding.
- Exécuter `RunSmokeTests` connecté comme Admin. Résultat attendu : 8 contrôles réussis. Ce test modifie temporairement ModeJours puis le restaure ; les fériés d'exemple sont requis.
- Créer les comptes manager (Manager, SAL000001), salarie (Salarie, SAL000002), user (User, SAL000003), rh (Gestionnaire). Mots de passe de recette distincts >= 12 caractères.

## Lot 1 : tests bloquants

| ID | Action | Résultat attendu | Statut |
|---|---|---|---|
| A01 | Ouvrir avec macros désactivées après sauvegarde | Accueil neutre ; toutes les tables VeryHidden ; pas de graphique contenant des noms | À exécuter |
| A02 | Login erroné 5 fois, puis correct immédiatement | Blocage 5 minutes dans l'instance ; pas de session ouverte | À exécuter |
| A03 | Login Admin, déconnexion, login Salarié | Anciennes vues effacées ; salarié limité à SAL000002 | À exécuter |
| A04 | User tente dossier, suppression, paramètres, comptes, document ou décision via Alt+F8 | Refus ; aucune mutation | À exécuter |
| A05 | Manager demande pour un autre, valide sa propre demande | Refus ; peut valider un collaborateur direct seulement | À exécuter |
| A06 | Manager hors périmètre tente une décision par CON deviné | Refus avant divulgation des dates ou mutation | À exécuter |
| A07 | Administrateur tente sa propre désactivation/rétrogradation | Refus ; réinitialisation de son mot de passe permise | À exécuter |
| S01 | Vérifier les 5 dossiers initiaux | SAL000001 à SAL000005, actifs, aucun NSS/RIB réel | À exécuter |
| S02 | Créer un dossier valide puis rechercher par matricule et nom | SAL000006 généré, une ligne, chargement complet et correct | À exécuter |
| S03 | Nom vide, 31/02/2026, naissance future, salaire négatif, email invalide, NSS alphabet | Chaque saisie refusée sans nouvelle ligne | À exécuter |
| S04 | Modifier service/email/report, enregistrer, fermer et rouvrir | Modifications persistées ; matricule inchangé | À exécuter |
| S05 | Clôture avec date avant embauche ou date future ; puis clôture valide | Deux refus puis Sorti ; masqué de recherche normale, visible avec archives | À exécuter |
| S06 | Supprimer un dossier non référencé, annuler puis confirmer | Annulation sans effet ; confirmation supprime ; matricule jamais réutilisé | À exécuter |
| S07 | Supprimer SAL000001 ou salarié avec congé/compétence/compte | Refus ; clôture recommandée | À exécuter |
| S08 | Saisir nom `=1+1` ou `+SUM(A1)` et rechercher `*` | Nom stocké littéralement ; * ne résout pas arbitrairement un identifiant | À exécuter |
| C01 | Du 07/09/2026 au 11/09/2026, Ouvres | 5 jours | À exécuter |
| C02 | Du 12/09/2026 au 13/09/2026 | 0 jour, demande refusée | À exécuter |
| C03 | Du 13/07/2026 au 17/07/2026 | 4 jours avec 14 juillet férié | À exécuter |
| C04 | Du 07/09/2026 au 13/09/2026, Ouvrables | 6 jours ; remettre Ouvres ensuite | À exécuter |
| C05 | Date inversée, avant embauche, hors exercice, dossier sorti | Refus sans ligne | À exécuter |
| C06 | Soumettre C03 pour SAL000002, puis soumettre période recouvrante en Maladie | Première en attente ; seconde refusée quel que soit le type | À exécuter |
| C07 | Vérifier solde à date du 08/09/2026 pour SAL000002 | 25 avant C03, 21 en attente, 21 après validation ; pas de double déduction | À exécuter |
| C08 | Nouvelle demande payée excédant le disponible | Refus ; autres types ne déduisent pas le compteur Payé | À exécuter |
| C09 | Refuser avec motif vide puis renseigné | Vide : aucun changement ; renseigné : Refusé, réservation libérée | À exécuter |
| C10 | Revalider/refuser une décision déjà prise | Refus, horodatage et décision inchangés | À exécuter |
| C11 | Embauche le 15/08/2026, report 0, calcul au 08/09/2026 | Aucun mois complet acquis ; solde 0 | À exécuter |
| C12 | Dupliquer un férié dans les données génératrices puis régénérer le manifeste de recette | Jour exclu une seule fois | À exécuter |
| R01 | Rafraîchir Admin au 08/09/2026 | Effectif 5 ; anniversaires Alice Martin et Emma Petit ; H=2/F=3 ; taux départ 0% | À exécuter |
| R02 | Export Excel puis PDF connecté salarié | Une seule ligne SAL000002 ; pas de NSS, RIB, salaire ou naissance | À exécuter |
| R03 | Export Admin, annuler dialogue, chemin interdit, fichier déjà ouvert | Annulation propre / erreur compréhensible ; aucune instance temporaire laissée | À exécuter |
| P01 | Sauver connecté Admin, fermer, ouvrir sans macros ; refaire Enregistrer sous | Vues nominatives non persistées, structure protégée et tables masquées | À exécuter |
| P02 | Fermer avec modifications non enregistrées puis annuler la fermeture | Données encore en mémoire ; reconnexion/rafraîchissement possible | À exécuter |
| P03 | Ouvrir le même fichier dans une seconde instance en lecture seule | Message et fermeture de cette copie ; aucune saisie concurrente | À exécuter |

Les soldes attendus C07 et C11 sont datés : sur une autre date, recalculer les mois calendaires complets selon le manuel et noter la date réelle. Ne pas modifier l'horloge du poste de production pour ces tests.

## Extensions et limites lot 2

| Test | Résultat attendu | Statut |
|---|---|---|
| Contrat/avenant DOCX avec Word ; PDF sans Word | Balises résolues, brouillon explicitement marqué ; fichier lisible | À exécuter |
| Document inconnu, chemin interdit, absence de Word | Erreur gérée ; aucune instance Word orpheline | À exécuter |
| Certificat avant/après clôture | Refus avant clôture, dates correctes après | À exécuter |
| Compétence Excel pour Comptable, niveau Expert ; nouvelle saisie Intermédiaire | Une attribution actualisée ; compétence inconnue refusée | À exécuter |
| Deux entretiens pour même salarié | Deux lignes distinctes ; aucun écrasement | À exécuter |
| Journal après création/modification/suppression/décision/export | Auteur, heure, action, cible ; aucun mot de passe, NSS ou salaire | À exécuter |
| Graphique services, sauvegarde puis ouverture sans macros | Graphique correct en session RH, aucun cache graphique persistant | À exécuter |

Non couverts : contrats juridiquement complets, historique avant/après, audit inviolable, pyramide des âges, absentéisme, matrice de polyvalence, affichage photo, UI de consultation des compétences/entretiens, import des données réelles, signature numérique et formation effective.

## Performance et acceptation

1. Sur une copie des sources de génération, préparer 10 000 salariés fictifs uniques puis régénérer le manifeste avec tools/build_assets.py avant assemblage (mêmes colonnes et plages redimensionnées). Prévoir un manager avec 9 999 collaborateurs ; n'utiliser aucune vraie donnée personnelle.
2. Mesurer trois ouvertures à froid (hors première installation), trois rafraîchissements, recherche nom/matricule, modification et export XLSX/PDF. Noter min/médiane/max, mémoire et configuration du poste.
3. Objectif ouverture < 5 s. Pour « sans ralentissement notable », faire approuver un seuil métier : proposition recherche/rafraîchissement < 2 s, hors export/impression. Seuil non garanti sans mesure.
4. Refaire les contrôles sur Excel Windows 32 bits et 64 bits. Inspecter également effets antivirus, dossiers réseau et politiques ActiveX/VBA.
5. Tester restauration d'une sauvegarde chiffrée et conservation du mot de passe de chiffrement en coffre-fort.

**Acceptation suspendue** tant que compilation, tests Lot 1, cinq dossiers/congés et absence d'erreurs non gérées ne sont pas validés sous Excel. Aucun résultat de test statique Python ne remplace cette recette.
