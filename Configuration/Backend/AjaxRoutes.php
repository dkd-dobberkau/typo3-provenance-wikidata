<?php

declare(strict_types=1);

use CmsProvenance\ProvenanceWikidata\Backend\LookupController;

return [
    'provenance_wikidata_search' => [
        'path' => '/provenance-wikidata/search',
        'target' => LookupController::class . '::searchAction',
        'methods' => ['GET'],
    ],
    'provenance_wikidata_statements' => [
        'path' => '/provenance-wikidata/statements',
        'target' => LookupController::class . '::statementsAction',
        'methods' => ['GET'],
    ],
];
