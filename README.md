# FHIRClient
## A simple PHP client for SMART on FHIR

### Usage

```php
use FaulkJ\FHIRClient\FHIRClient;
$fhir = new FHIRClient("https://my.fhirserver.com", "1234-5678-9012-3456-7890", "https://my.website.com/launch");
$fhir->getConformance("https://my.fhirserver.com/FHIRProxy/api/FHIR/R4");
$fhir->getAuthCode();
```
