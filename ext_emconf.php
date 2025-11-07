<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "sic_mosparo".
 *
 * Auto generated 25-07-2025 08:58
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
    'title' => 'SPAM Prevention with Mosparo for Powermail',
    'description' => 'Mosparo captcha extension for powermail',
    'category' => 'plugin',
    'version' => '1.0.1',
    'state' => 'stable',
    'uploadfolder' => false,
    'clearcacheonload' => false,
    'author' => 'SICOR Dev Team',
    'author_email' => 'dev@sicor-kdl.net',
    'author_company' => 'SICOR IT GmbH',
    'constraints' => [
        'depends' => [
            'powermail' => '8.0.0-',
            'typo3' => '11.0.0-13.9.99',
            'php' => '8.0.0-',
        ],
    ],
);
