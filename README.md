# FHIRClient
A simple PHP client for SMART on FHIR

### Installation
```bash
$ composer require faulkj/fhirclient
```

## Basic Usage

On initial load:
```php
use FaulkJ\FHIRClient\FHIRClient;
$fhir = new FHIRClient("https://my.fhirserver.com", "1234-5678-9012-3456-7890", "https://my.website.com");
$fhir->getConformance("https://my.fhirserver.com/FHIRProxy/api/FHIR/R4");
$fhir->getAuthCode();
```

This will get an authorization code from _my.fhirserver.com/FHIRProxy/api/FHIR/R4_ and then return it to _my.website.com_.

On that second load:
```php
use FaulkJ\FHIRClient\FHIRClient;
$fhir = new FHIRClient("https://my.fhirserver.com", "1234-5678-9012-3456-7890", "https://my.website.com");
$fhir->getAccessToken($_GET["code"]);

//You are now authenticated and may query the FHIR server
$obs = $fhir->query("Observation?patient=12345678&code=12345-6");
```

On subsequent page loads or AJAX calls, the FHIRClient will need to be reinstanciatedbefore yoy can send a query:
```php
use FaulkJ\FHIRClient\FHIRClient;
$fhir = new FHIRClient("https://my.fhirserver.com", "1234-5678-9012-3456-7890", "https://my.website.com");
$pat = $fhir->query("Patient/12345678");
```
