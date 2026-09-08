"""Auteur: Arena | 2026-09-08. Génère les descriptions MSForms et leur code source UTF-8."""
import json
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
forms = []
def control(kind, name, x, y, width=190, height=22, **props):
    return dict(type=kind, name=name, left=x, top=y, width=width, height=height, **props)
def field(controls, name, label, y, kind='TextBox', x=18, **props):
    controls.append(control('Label', 'label_'+name, x, y, caption=label))
    controls.append(control(kind, name, x+150, y, **props))
def source(name, body):
    (ROOT/'src'/f'{name}.vba').write_text('Option Explicit\n\n'+body, encoding='utf-8')
def event(name, code):
    return f'''\n' Auteur : Arena | Date : 2026-09-08 | Description : Événement {name}, erreurs utilisateur contrôlées.
Private Sub {name}()
    On Error GoTo Failed
{code}
    Exit Sub
Failed:
    MsgBox Err.Description, vbExclamation
End Sub
'''
c=[]
field(c,'username','Utilisateur',20)
field(c,'password','Mot de passe',55,passwordChar='*')
c.append(control('CommandButton','cmdLogin',168,95,caption='Se connecter'))
forms.append(dict(name='frmLogin',caption='Connexion sécurisée • RH',width=390,height=165,controls=c))
source('frmLogin',event('cmdLogin_Click','    If Login(Me.username.Value, Me.password.Value) Then\n        Me.password.Value = ""\n        Unload Me\n    Else\n        Me.password.Value = ""\n    End If'))
labels=['Matricule','Civilité','Nom *','Prénom *','Naissance jj/mm/aaaa *','NSS (15 chiffres)','Téléphone','E-mail','Adresse','Embauche jj/mm/aaaa *','Contrat *','Service *','Poste *','Salaire brut *','RIB (optionnel)','Chemin photo','Matricule manager','Statut *','Sortie jj/mm/aaaa','Motif de sortie','Report congés payés *','Sexe H / F / Autre']
c=[control('TextBox','q',18,18,260),control('CommandButton','cmdSearch',290,18,120,caption='Rechercher'),control('CheckBox','chkArchives',430,18,230,caption='Inclure les sortants'),control('ListBox','lstResults',18,52,400,75,columnCount=2,columnWidths='85 pt;290 pt'),control('CommandButton','cmdLoad',440,52,130,caption='Charger'),control('CommandButton','cmdNew',580,52,130,caption='Nouveau')]
combos={2:['Mme','M.','Autre'],11:[],12:[],13:[],18:['Actif','Sorti'],20:[],22:['H','F','Autre','Non renseigné']}
for i,label in enumerate(labels,1):
    x=18 if i<=11 else 410;y=145+((i-1)%11)*31
    props={}
    if i in combos: props.update(items=combos[i],style=2)
    if i==1: props['locked']=True
    field(c,'f'+str(i),label,y,'ComboBox' if i in combos else 'TextBox',x=x,**props)
for name,label,x in [('cmdSave','Enregistrer',180),('cmdDelete','Supprimer (Admin)',385),('cmdClose','Fermer',590)]:
    c.append(control('CommandButton',name,x,505,190,28,caption=label))
forms.append(dict(name='frmEmployee',caption='Dossier salarié • Dates au format jj/mm/aaaa',width=805,height=580,controls=c))
body=event('UserForm_Initialize','''    RequireEditor
    FillReference Me.f11, "Contrat"
    FillReference Me.f12, "Service"
    FillReference Me.f13, "Poste"
    FillReference Me.f20, "Motif"
    cmdNew_Click''')
body+=event('cmdNew_Click','''    Dim i As Long
    For i = 1 To 22
        Me.Controls("f" & i).Value = ""
    Next
    Me.f18.Value = "Actif": Me.f14.Value = "0": Me.f21.Value = "0"
    Me.f10.Value = Format$(Date, "dd/mm/yyyy")''')
for evt,code in [('cmdSearch_Click','SearchEmployees Me'),('cmdLoad_Click','LoadEmployee Me'),('lstResults_DblClick','')]:
    if code: body+=event(evt,'    '+code)
for evt,code in [('cmdSave_Click','SaveEmployee Me'),('cmdDelete_Click','DeleteEmployee Me'),('cmdClose_Click','Unload Me')]:body+=event(evt,'    '+code)
source('frmEmployee',body)
c=[]
for name,label,y,kind in [('employeeId','Matricule',20,'TextBox'),('startDate','Début jj/mm/aaaa',55,'TextBox'),('endDate','Fin jj/mm/aaaa',90,'TextBox'),('kind','Type',125,'ComboBox')]:field(c,name,label,y,kind,**({'style':2} if kind=='ComboBox' else {}))
c.append(control('CommandButton','cmdSubmit',168,170,caption='Soumettre la demande'))
c.append(control('Label','hint',18,207,350,42,caption='Jours entiers. Le solde affiché déduit aussi les demandes en attente. Seul le type Payé consomme ce solde.'))
forms.append(dict(name='frmLeave',caption='Congés et absences',width=390,height=285,controls=c))
source('frmLeave',event('UserForm_Initialize','''    FillReference Me.kind, "Conge"
    Me.employeeId.Value = SessionEmployee
    Me.employeeId.Locked = (SessionRole <> "Admin" And SessionRole <> "Gestionnaire")''')+event('cmdSubmit_Click','    RequestLeave Me.employeeId.Value, Me.startDate.Value, Me.endDate.Value, Me.kind.Value'))
c=[]
field(c,'username','Identifiant',20)
field(c,'role','Rôle',55,'ComboBox',items=['Admin','Gestionnaire','Manager','Salarie','User'],style=2)
field(c,'employeeId','Matricule lié',90)
field(c,'password','Nouveau mot de passe',125,passwordChar='*')
c.append(control('CheckBox','active',168,160,caption='Compte actif',value=True))
c.append(control('CommandButton','cmdSave',168,195,caption='Créer / mettre à jour'))
c.append(control('Label','hint',18,230,355,40,caption='Compte existant : renseignez tous les droits. Mot de passe vide = inchangé. 12 caractères minimum sinon.'))
forms.append(dict(name='frmAccount',caption='Administration des comptes',width=390,height=315,controls=c))
source('frmAccount',event('UserForm_Initialize','    RequireEditor True')+event('cmdSave_Click','    SaveAccount Me'))
# Les listes MSForms ne sont pas sérialisées de manière fiable par AddItem au design-time.
# Les initialiser dans le code exécuté à chaque ouverture du formulaire.
for form in forms:
    additions = []
    for spec in form['controls']:
        for item in spec.get('items', []):
            additions.append('    Me.' + spec['name'] + '.AddItem "' + item.replace('"', '""') + '"')
    if additions:
        path = ROOT/'src'/f"{form['name']}.vba"
        text = path.read_text(encoding='utf-8')
        marker = 'Private Sub UserForm_Initialize()\n    On Error GoTo Failed'
        if marker in text:
            text = text.replace(marker, marker + '\n' + '\n'.join(additions))
        else:
            text += event('UserForm_Initialize', '\n'.join(additions))
        path.write_text(text, encoding='utf-8')
(ROOT/'tools/forms.json').write_text(json.dumps(forms,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
