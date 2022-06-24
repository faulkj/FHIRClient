# FHIRClient
A simple PHP client for SMART on FHIR

## Usage

On initial load:
```php
use FaulkJ\FHIRClient\FHIRClient;
$fhir = new FHIRClient("https://my.fhirserver.com/", "1234-5678-9012-3456-7890", "https://my.website.com/");
$fhir->getConformance("https://my.fhirserver.com/FHIRProxy/api/FHIR/R4");
$fhir->getAuthCode();
```

This will get an authorization code from https://my.fhirserver.com/FHIRProxy/api/FHIR/R4 and then return it to https://my.website.com/launch.

On that load:
```php
use FaulkJ\FHIRClient\FHIRClient;
$fhir = new FHIRClient("https://my.fhirserver.com", "1234-5678-9012-3456-7890", "https://my.website.com/");
$fhir->getAccessToken($_GET["code"]);
```

You are now authenticated and may query the FHIR server:

```php
   $fhir->query("Observation?patient=12345678&code=76516-4");
```
