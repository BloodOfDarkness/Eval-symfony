# Documentation : exigence d'entropie ≥ 60 bits pour les mots de passe

## Contexte et besoin

En tant que RSSI, les mots de passe des utilisateurs doivent avoir une
**entropie supérieure ou égale à 60 bits** afin de se protéger d'éventuelles
attaques (force brute, dictionnaire, etc.).

Avant cette modification, la seule contrainte appliquée sur les mots de passe
était `NotBlank` (mot de passe non vide) — et seulement sur certains
formulaires.

## Principe retenu

La méthode `estimateStrength()` de la classe
`Symfony\Component\Validator\Constraints\PasswordStrengthValidator` calcule
l'entropie d'un mot de passe (à partir de la taille du pool de caractères
utilisés et de la longueur) et la convertit en un score de 0 à 4 :

| Entropie    | Score | Constante                |
|-------------|-------|--------------------------|
| ≥ 120 bits  | 4     | `STRENGTH_VERY_STRONG`   |
| ≥ 100 bits  | 3     | `STRENGTH_STRONG`        |
| ≥ 80 bits   | 2     | `STRENGTH_MEDIUM`        |
| **≥ 60 bits** | **1** | **`STRENGTH_WEAK`**    |
| < 60 bits   | 0     | `STRENGTH_VERY_WEAK`     |

Exiger une entropie ≥ 60 revient donc à appliquer la contrainte
`PasswordStrength` avec `minScore: PasswordStrength::STRENGTH_WEAK` : le
validateur rejette tout mot de passe dont le score estimé est **strictement
inférieur** à 1 (soit une entropie < 60 bits).

Message d'erreur affiché à l'utilisateur :

> « Le mot de passe est trop faible : il doit avoir une entropie d'au moins 60 bits. »

## Fichiers modifiés

Commit : `9843d41` — *Exiger une entropie >= 60 bits pour les mots de passe*

### `src/Form/RegistrationFormType.php` (inscription)

- La contrainte `PasswordStrength` existait déjà mais était **mal imbriquée**
  dans un tableau supplémentaire (`[ [new PasswordStrength(...)] ]`) et sans
  message explicite.
- Corrigée en contrainte directe avec `minScore: STRENGTH_WEAK` et le message
  ci-dessus.
- La contrainte `NotBlank` est conservée.
- Suppression des imports inutilisés (`Length`, `PasswordStrengthValidator`).

### `src/Form/ChangePasswordFormType.php` (changement / réinitialisation)

- Ajout de `PasswordStrength` (`minScore: STRENGTH_WEAK`) sur le champ
  `plainPassword` (option `first_options` du `RepeatedType`), qui ne
  vérifiait que `NotBlank`.
- Ce formulaire étant réutilisé par `ChangePasswordController` **et** par
  `ResetPasswordController` (réinitialisation via le lien reçu par e-mail,
  où le champ « mot de passe actuel » est retiré), la règle couvre
  automatiquement les deux parcours.
- Import `PasswordStrength` ajouté.

### `src/Form/UserType.php` (création d'utilisateur)

- Le champ `password` n'avait **aucune** contrainte.
- Ajout de `NotBlank` + `PasswordStrength` (`minScore: STRENGTH_WEAK`).
- Imports ajoutés.

### `src/Form/ProfessorType.php` (création de professeur)

- Idem : ajout de `NotBlank` + `PasswordStrength` (`minScore: STRENGTH_WEAK`)
  sur le champ `password`, et des imports correspondants.

### `src/Form/StudentType.php` (création d'étudiant)

- Idem : ajout de `NotBlank` + `PasswordStrength` (`minScore: STRENGTH_WEAK`)
  sur le champ `password`, et des imports correspondants.

## Récapitulatif des champs couverts

| Formulaire              | Champ          | Avant                  | Après                            |
|-------------------------|----------------|------------------------|----------------------------------|
| `RegistrationFormType`  | `plainPassword`| `NotBlank` (+ contrainte mal imbriquée) | `NotBlank` + `PasswordStrength` (entropie ≥ 60) |
| `ChangePasswordFormType`| `plainPassword`| `NotBlank` seulement   | `NotBlank` + `PasswordStrength`  |
| `UserType`              | `password`     | aucune contrainte      | `NotBlank` + `PasswordStrength`  |
| `ProfessorType`         | `password`     | aucune contrainte      | `NotBlank` + `PasswordStrength`  |
| `StudentType`           | `password`     | aucune contrainte      | `NotBlank` + `PasswordStrength`  |

## Vérifications effectuées

- **Analyse syntaxique** : `php -l` sur les 5 fichiers modifiés — aucune erreur.
- **Test fonctionnel du validateur Symfony** (contrainte
  `PasswordStrength` avec `minScore: STRENGTH_WEAK`) :

| Mot de passe           | Score estimé | Entropie   | Résultat  |
|------------------------|--------------|------------|-----------|
| `abc`                  | 0            | < 60 bits  | ❌ rejeté |
| `Password1!`           | 1            | ≥ 60 bits  | ✅ accepté |
| `Azerty123!`           | 1            | ≥ 60 bits  | ✅ accepté |
| `C0rr3ct-Horse-Battery`| 3            | ≥ 100 bits | ✅ accepté |
| `Tr0ub4dor&3-Extra-Long!` | 4         | ≥ 120 bits | ✅ accepté |

## Réutilisation du même formulaire : changement vs réinitialisation du mot de passe

Un seul formulaire, `src/Form/ChangePasswordFormType.php`, sert aux deux
parcours :

- le **changement** du mot de passe d'un utilisateur **connecté**
  (`ChangePasswordController`, route `/changepassword`, protégée par
  `#[IsGranted('ROLE_USER')]`) ;
- la **réinitialisation** d'un mot de passe **oublié**, où l'utilisateur n'est
  pas connecté (`ResetPasswordController`, route `/reset-password/reset/{token}`,
  dont l'accès est sécurisé par un token unique envoyé par e-mail, et où le
  formulaire est construit par `$this->createForm(ChangePasswordFormType::class)`).

### La mécanique

Le formulaire déclare deux parties indépendantes :

- `plainPasswordCurrent` (mot de passe actuel), portant la contrainte
  `UserPassword` (vérifie que la saisie correspond au mot de passe haché de
  l'utilisateur) ;
- `plainPassword` (nouveau mot de passe, en `RepeatedType`), portant les
  contraintes `NotBlank` et `PasswordStrength` (entropie ≥ 60).

Les deux champs sont en `mapped => false` : ils ne sont liés à aucune
propriété de l'entité, la lecture et le hachage se font dans le contrôleur via
`$form->get('plainPassword')->getData()`. C'est ce découplage qui permet au
même formulaire de servir à deux contrôleurs aux logiques différentes.

### Pourquoi le champ « mot de passe actuel » n'apparaît pas lors d'un reset

Dans `ResetPasswordController::reset()`, juste après la création du
formulaire, le contrôleur exécute :

```php
$form->remove('plainPasswordCurrent');
```

Cet appel supprime le champ du `FormBuilder` **avant** `handleRequest()` et
avant le rendu : le champ n'est ni affiché dans le Twig, ni rempli depuis la
requête, et surtout sa contrainte `UserPassword` n'est jamais exécutée lors de
la validation (aucune violation « Mot de passe actuel incorrect » possible).
C'est indispensable, car lors d'un mot de passe oublié l'utilisateur n'est pas
authentifié : `UserPassword` s'appuie sur le jeton de sécurité de la session
pour retrouver l'utilisateur courant, qui n'existe pas ici. L'identité est
alors prouvée par le **token de réinitialisation** (validé par
`ResetPasswordHelper::validateTokenAndFetchUser()`), qui remplace le mot de
passe actuel comme preuve de possession du compte.

### Pourquoi le champ apparaît pour un utilisateur connecté

Dans `ChangePasswordController`, aucun `remove()` n'est effectué : le champ
`plainPasswordCurrent` est rendu et sa contrainte `UserPassword` s'exécute.
L'utilisateur étant connecté, le validateur peut retrouver l'utilisateur dans
le jeton de sécurité et comparer le mot de passe saisi (via le
`UserPasswordHasher`) au haché stocké en base. C'est une défense en profondeur
importante : elle empêche une personne utilisant une session laissée ouverte
de détourner le compte sans connaître le mot de passe d'origine.

---

# Fonctionnalité : page « Mes profs » pour l'étudiant

## Contexte et besoin

Connecté en tant qu'étudiant, le lien « Mes profs » de la navbar existait
déjà dans `templates/base.html.twig` mais pointait vers `#` : la page
n'était pas implémentée.

## Principe retenu

Dans le modèle de données :

- un `Professor` est rattaché à un ou plusieurs `ClassLevel` (relation
  ManyToMany `professor.classLevels`) ;
- un `Student` appartient à un `ClassLevel` (relation ManyToOne
  `student.classLevel`).

Les « professeurs de l'étudiant » sont donc les professeurs rattachés au
niveau de classe de l'étudiant.

## Fichiers modifiés / créés

### `src/Repository/ProfessorRepository.php` (modifié)

- Ajout de la méthode `findByClassLevel(?ClassLevel $classLevel) : array` :
  requête DQL qui joint `p.classLevels`, filtre sur la classe passée en
  paramètre et trie par nom. Si l'étudiant n'a pas de classe (`null`),
  retourne directement `[]` sans exécuter de requête.
- Import `App\Entity\ClassLevel` ajouté.

### `src/Controller/StudentController.php` (modifié)

- Nouvelle action `myProfessors` : route `GET /etudiant/{id}/profs`
  (nom `app_student_professors`), cohérente avec la route existante
  `app_student_notes` (`/etudiant/{id}/notes`).
- Elle récupère l'étudiant via le param converter Symfony, appelle
  `findByClassLevel($student->getClassLevel())` et rend le template avec
  l'étudiant et la liste des professeurs.
- Import `App\Repository\ProfessorRepository` ajouté.

### `templates/student/myprofessors.html.twig` (nouveau)

- Page « Mes profs — {nom de l'étudiant} », héritant de `base.html.twig`,
  dans le même style que `student/mygrades.html.twig`.
- Affiche la classe de l'étudiant, puis un tableau (Nom / Email / Matières)
  listant les professeurs, avec leurs matières séparées par des virgules
  (ou « — » si le professeur n'a pas de matière).
- Messages de repli : « Aucun professeur n'est rattaché à votre classe pour
  le moment » si la liste est vide.

### `templates/base.html.twig` (modifié)

- Le lien « Mes profs » de la navbar (bloc `ROLE_STUDENT`) pointe désormais
  vers `{{ path('app_student_professors', {id: app.user.id}) }}` au lieu de `#`.

## Vérifications effectuées

- `php -l` : aucune erreur de syntaxe sur les deux fichiers PHP modifiés.
- `php bin/console lint:twig` : syntaxe Twig valide sur les deux templates
  (une balise `{% endfor %}` manquante a été corrigée au passage).
- `php bin/console debug:router` : la route `app_student_professors`
  (`/etudiant/{id}/profs`) est bien enregistrée.
- La requête DQL a été exécutée contre la vraie base de données
  (`php bin/console doctrine:query:dql`) : elle renvoie bien les professeurs
  rattachés à la classe d'ID 1.
