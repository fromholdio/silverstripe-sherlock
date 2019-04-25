# silverstripe-sherlock

A SilverStripe module that enables you to build a project-specific "search engine" with fulltext search with relevance and weighting.

This module by itself does not do much - it provides the framework to implement a 'search engine' specific to your project.

See [fromholdio/silverstripe-sherlock-pages](https://github.com/fromholdio/silverstripe-sherlock-pages) for an example implementation, which uses this core module and implements a search engine for all Pages, with sorting by relevance, and weights matches to Title and MenuTitle higher than matches within content.

Apologies, I haven't had the chance to write much documentation for this yet. See the source of the above-referenced module, or submit an issue with any question you may have in the meantime.

At a high level this aims to:

* Fulltext search
* Use fulltext relevance scores and order results by this
* Create and search multiple fulltext indexes in one search, add different weighting to each index's relevance score and order results accordingly
* Define search fields that go directly to the first found result if matched (say, if the search phrase is an exact page/dataobject title, or urlsegment, match - totally optional)
* Maintain search logs for future analytics/reporting
* Provide idempotent search engine build task, to maintain entries

## Requirements

* [silverstripe-cms](https://github.com/silverstripe/silverstripe-cms) ^4
* [symbiote/silverstripe-gridfieldextensions](https://github.com/symbiote/silverstripe-gridfieldextensions) ^3.0
* [sheadawson/silverstripe-dependentdropdownfield](https://github.com/sheadawson/silverstripe-dependentdropdownfield) ^2.0
* [fromholdio/silverstripe-commonancestor](https://github.com/fromholdio/silverstripe-commonancestor) ^1.0

## Recommended

* [fromholdio/silverstripe-fulltext-innodb](https://github.com/fromholdio/silverstripe-fulltext-innodb) ^1.0
* [fromholdio/silverstripe-fulltext-filters](https://github.com/fromholdio/silverstripe-fulltext-filters) ^1.0
* [fromholdio/silverstripe-sherlock-pages](https://github.com/fromholdio/silverstripe-sherlock-pages) ^1.0

## Installation

`composer require fromholdio/silverstripe-sherlock`

## Detail

Detail and usage examples to come.

See [fromholdio/silverstripe-sherlock-pages](https://github.com/fromholdio/silverstripe-sherlock-pages) for usage example.


## To Do

* Make use of the search logs in the form of intelligent/interesting search analytics reports
* Only require silverstripe-framework rather than silverstripe-cms
* Thorough documentation

## Thanks & Acknowledgements

Thanks to these most excellent projects for providing inspiration

* https://github.com/vulcandigital/silverstripe-search
* https://github.com/nglasl/silverstripe-extensible-search
* https://github.com/Firesphere/silverstripe-searcher/


