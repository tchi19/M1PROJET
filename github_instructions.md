# Instructions pour utiliser GitHub (M1PROJET)

Votre répertoire local `C:\wamp64\www\M1PROJET` est déjà configuré pour utiliser GitHub. Voici les commandes essentielles pour gérer votre projet.

## 1. Pour envoyer vos changements vers GitHub (Push)

Actuellement, vous avez des fichiers prêts à être enregistrés. Pour les envoyer sur GitHub, utilisez ces 3 étapes :

1.  **Ajouter les fichiers** (déjà fait pour la plupart, mais au cas où) :
    ```powershell
    git add .
    ```
2.  **Enregistrer les changements (Commit)** :
    ```powershell
    git commit -m "Premier envoi du projet M1PROJET"
    ```
3.  **Envoyer vers GitHub (Push)** :
    ```powershell
    git push origin main
    ```

---

## 2. Pour récupérer les changements depuis GitHub (Pull)

Si vous travaillez sur un autre ordinateur ou si quelqu'un d'autre modifie le code en ligne, utilisez cette commande pour mettre à jour votre dossier local :
```powershell
git pull origin main
```

---

## 3. Pour vérifier l'état de votre projet

Pour voir quels fichiers ont été modifiés, supprimés ou ajoutés :
```powershell
git status
```

---

## 4. Résumé des étapes fréquentes (Work-flow)

À chaque fois que vous finissez une session de travail :
1. `git add .`
2. `git commit -m "Description de ce que j'ai fait"`
3. `git push origin main`

---

**Lien du répertoire GitHub :** [https://github.com/tchi19/M1PROJET.git](https://github.com/tchi19/M1PROJET.git)
