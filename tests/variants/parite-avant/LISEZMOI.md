# Variante « avant les 5 correctifs de parité »

Ce dossier contient **trois fichiers du plugin tels qu'ils étaient avant les cinq
correctifs de parité** (version 0.1.0) :

| Fichier | Défaut qu'il reintroduit |
| --- | --- |
| `class-portfolio-studio-content-types.php` | BUG 1 (slugs d'URL des taxonomies), BUG 2 (`rewrite.with_front`), BUG 3 (`menu_position`), BUG 4 (17 libellés) |
| `class-portfolio-studio-lifecycle.php` | BUG 5 (`activate()` jetait `$network_wide`) |
| `portfolio-studio.php` | BUG 5 (aucun crochet `wpmu_new_blog` / `wp_initialize_site`) |

Ce ne sont **pas** des fichiers de développement : ce sont les fichiers publiés
dans le dépôt public avant les correctifs, sic. Ce sont les mêmes octets que le
commit `570fc90` du dépôt public.

Ils ne servent qu'à une chose : **la contre-épreuve**. Le script
`tests/run-parite-proof.sh` les recopie par-dessus le plugin installé, lance la
suite de parité, et **la suite doit devenir rouge** — une assertion qui ne peut
pas échouer ne prouve rien. Le script repose ensuite les fichiers corrigés et
relance la suite, qui doit devenir verte.

Aucun de ces fichiers n'entre dans le ZIP de soumission : le script
`build-zip.sh` ne prend que `/work/wp/portfolio-studio/`.
