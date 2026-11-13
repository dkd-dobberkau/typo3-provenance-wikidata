<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Content Provenance: Wikidata',
    'description' => 'Research Content Provenance facts in Wikidata from the fact dialog of the rich text editor.',
    'category' => 'be',
    'state' => 'experimental',
    'version' => '0.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'php' => '8.2.0-8.4.99',
            'provenance_facts' => '0.1.0-0.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
