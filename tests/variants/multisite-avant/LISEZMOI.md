# Variante « avant la correction multisite » (0.1.1)

Ce dossier contient **les deux fichiers du plugin tels qu'ils étaient en 0.1.1**,
c'est-à-dire **avant la correction multisite** :

| Fichier | Défaut qu'il réintroduit |
| --- | --- |
| `portfolio-studio.php` | écoute encore le crochet **déprécié** `wpmu_new_blog` (en plus de `wp_initialize_site`) : WordPress déclenche alors la création d'un site **deux fois** et lève un avis de dépréciation quand `WP_DEBUG` est actif |
| `class-portfolio-studio-lifecycle.php` | `activate_new_site()` **écrit dans le site créé** sans vérifier que le plugin est activé pour tout le réseau : un réseau où le plugin n'est actif que sur un seul site voit apparaître une catégorie « Uncategorized » et une option dans les sites qui ne l'ont pas demandé |

Ce ne sont **pas** des fichiers de développement : ce sont les octets exacts du
plugin 0.1.1 livré (`portfolio-studio.zip`, md5 `8f08f56c22a12ef72f6c2356a7435e26`).

Ils ne servent qu'à une chose : **la contre-épreuve**. Le script
`tests/run-parite-proof.sh` (phase 4) les recopie par-dessus le plugin installé,
lance la **même** suite de parité, et **la suite doit devenir rouge** — une
assertion qui ne peut pas échouer ne prouve rien. Le script repose ensuite les
fichiers corrigés et relance la suite, qui doit devenir verte.

Aucun de ces fichiers n'entre dans le ZIP de soumission : le script
`build-zip.sh` ne prend que `/work/wp/portfolio-studio/`.
