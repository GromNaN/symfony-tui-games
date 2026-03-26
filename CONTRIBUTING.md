# Contributing

## Architecture

Chaque jeu est structuré en trois couches :

```
src/
├── <Jeu>/
│   ├── <Jeu>Game.php     — logique pure (état, règles, pas de TUI)
│   ├── <Jeu>Widget.php   — rendu ANSI + gestion des touches
│   └── *.php             — enums, entités (Direction, TileType…)
└── Command/
    └── <Jeu>Command.php  — commande Symfony, tick loop
```

La séparation `Game` / `Widget` permet de tester la logique indépendamment du rendu.

## Ajouter un jeu

1. Créer un dossier `src/MonJeu/`.
2. Implémenter `MonJeuGame` (logique pure, sans dépendance TUI).
3. Créer `MonJeuWidget extends AbstractWidget implements FocusableInterface` :
   - `getDefaultKeybindings()` — déclarer les touches
   - `handleInput(string $data)` — réagir aux touches
   - `render(RenderContext $context): array` — retourner un tableau de lignes ANSI
     (largeur visible ≤ `$context->getColumns()`, pas de `\n`)
4. Créer `src/Command/MonJeuCommand.php` :
   - Annoter avec `#[AsCommand(name: 'app:mon-jeu')]`
   - Mettre en place le tick loop avec `$tui->onTick(...)` et `$event->setBusy()`
5. Documenter le jeu dans [README.md](README.md).

## Conventions

- **Rendu** : utiliser des codes ANSI directement (`\033[32m`…) ; réinitialiser avec `\033[0m`.
  Utiliser `mb_str_pad()` (PHP 8.3+) pour le padding de chaînes multibytes.
- **Largeur** : vérifier `$context->getColumns()` en début de `render()` et retourner un message d'erreur si le terminal est trop petit.
- **Tick loop** : accumuler `$event->getDeltaTime()` manuellement pour un pas fixe ;
  toujours appeler `$event->setBusy()` pour maintenir la boucle active.
- **Sprites Unicode** : préférer les caractères de dessin de blocs (`▀▄█▌▐░▒▓`) pour les sprites pixel-art.

## Dépendance sur le composant TUI

Le composant `symfony/tui` est embarqué localement dans `vendor-src/` (voir [README.md](README.md)).
Il n'est **pas** disponible sur Packagist. Ne pas tenter de le mettre à jour via `composer update symfony/tui` sans avoir préalablement mis à jour le dépôt source dans `vendor-src/symfony`.
