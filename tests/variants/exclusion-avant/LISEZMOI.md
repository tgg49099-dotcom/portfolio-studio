# Variante « avant correction » — exclusion des sites archive / poubelle / supprime

Ce dossier contient **le fichier tel qu'il etait** avant la correction du 10/11/2026 :
`class-portfolio-studio-lifecycle.php`, md5 `90255decce81f15ed8930dbdde43914c`.

La difference avec le fichier livre tient **uniquement** a la methode
`network_site_ids()` :

| | avant | apres |
|---|---|---|
| `get_sites()` | `array( 'fields' => 'ids', 'number' => 0 )` | `array( 'fields' => 'ids', 'number' => 0, 'archived' => 0, 'spam' => 0, 'deleted' => 0 )` |
| commentaire | « The archived, the spam and the deleted sites are left out » (affirmation fausse) | decrit exactement ce que fait le code |

Le reste du fichier est identique, octet pour octet.

**A quoi ca sert** : ce dossier est la contre-epreuve. La suite
`tests/suite-multisite-exclusion.php` doit **ECHouer** sur ces octets (16 assertions
rouges) et **reussir** sur le fichier livre (27 assertions vertes). Un test qui ne
peut pas echouer ne prouve rien.

Ne pas corriger ce fichier : il est fige, c'est la reference du « avant ».
