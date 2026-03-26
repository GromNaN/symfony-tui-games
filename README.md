# tui-jeu

Jeux en mode terminal construits avec le composant **Symfony TUI**.

> [!WARNING]
> **Le composant `symfony/tui` est expérimental et sa PR est en attente de review.**
> Il n'est pas encore fusionné dans Symfony. Voir : https://github.com/symfony/symfony-docs/pull/22201
>
> Ce projet embarque le composant en local via un dépôt `path` dans `composer.json`.
> Suivez le mode opératoire ci-dessous pour installer l'environnement.

---

## Prérequis

- PHP ≥ 8.4
- Composer
- Git

## Installation

```bash
# 1. Cloner ce dépôt
git clone <url-du-repo> tui-jeu
cd tui-jeu

# 2. Récupérer le composant TUI depuis la PR en attente de review
git clone --branch tui --single-branch \
    https://github.com/fabpot/symfony.git \
    vendor-src/symfony

# 3. Installer les dépendances (le composant TUI est chargé depuis vendor-src/ via un path repository)
composer install
```

> Le `composer.json` référence `vendor-src/symfony/src/Symfony/Component/Tui`
> comme dépôt de type `path`, donc aucune modification supplémentaire n'est requise.

---

## Jeux disponibles

| Commande | Description |
|----------|-------------|
| `php bin/console app:snake` | **Snake** — mangez les pommes, évitez les murs et votre queue. Vitesse croissante. |
| `php bin/console app:park` | **Terminal Park** — gestion de parc d'attractions à la RollerCoaster Tycoon. Construisez des chemins, des attractions, gérez l'argent et le bonheur des visiteurs. |
| `php bin/console app:space` | **Space Invaders** — défendez la Terre contre des vagues d'envahisseurs à sprites Unicode. |

### Contrôles communs

| Touche | Action |
|--------|--------|
| `Q` / `Ctrl+C` | Quitter |
| `P` / `Espace` | Pause (selon le jeu) |
| `R` | Recommencer (Snake, Space Invaders) |

---

## Contributing

Voir [CONTRIBUTING.md](CONTRIBUTING.md).
