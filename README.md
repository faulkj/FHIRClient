# FHIRClient
A simple PHP client for SMART on FHIR

### Installation
```bash
$ composer require faulkj/fhirclient
```

## Basic Usage


### EMR Ebedded Mode

On initial load:
```php
//Assumining this is the URL loaded by the EMR:  https://my.website.com/launch/?iss=https://my.fhirserver.com/FHIRProxy/api/FHIR/R4&launch=abc123

use FaulkJ\FHIRClient\FHIRClient;
session_start();

$iss = parse_url($_GET["iss"]);
$_SESSION["fhirParams"] = [
   "{$iss['scheme']}://{$iss['host']}",
   "1234-5678-9012-3456-7890",
   "https://my.website.com"
];
$fhir = new FHIRClient(...$_SESSION["fhirParams"]);
$fhir->getConformance($_GET["iss"]);
$fhir->getAuthCode();
```

This will get an authorization code from _my.fhirserver.com/FHIRProxy/api/FHIR/R4_ and then return it to _my.website.com_.

On that second load:
```php
use FaulkJ\FHIRClient\FHIRClient;
session_start();

$fhir = new FHIRClient(...$_SESSION["fhirParams"]);
$fhir->getAccessToken($_GET["code"]);

//You are now authenticated and may query the FHIR server
$obs = $fhir->query("Observation?patient=12345678&code=12345-6");
if($obs->code == 200) echo $obs->body;
```

On subsequent page loads or AJAX calls, the FHIRClient will need to be reinstanciated before yoy can send a query:
```php
use FaulkJ\FHIRClient\FHIRClient;
session_start();

$fhir = new FHIRClient(...$_SESSION["fhirParams"]);
$pat = $fhir->query("Patient/12345678");
if($pat->code == 200) echo $pat->body;
```


### Standalone Mode

On initial load:
```php
use FaulkJ\FHIRClient\FHIRClient;
session_start();

$iss = parse_url($_GET["iss"]);
$_SESSION["fhirParams"] = ["{$iss['scheme']}://{$iss['host']}", "1234-5678-9012-3456-7890", "https://my.website.com"];
$fhir = new FHIRClient(...$_SESSION["fhirParams"]);
$fhir->getConformance($_GET["iss"]);
$fhir->getAuthCode();
```

### Backend Mode

```php
   use FaulkJ\FHIRClient\FHIRClient;

   $fc = (new FHIRClient(
      "https:/fhir.server.com",
      "1234-5678-9012-3456-7890",
      "D:\\privatekey.pem",
      null,
      null,
      null,
      "FHIRProxy/oauth2/token"
   ))->debug(true);

   $fc->getAccessToken();

   $response = $fc->query("FHIRProxy/path/to/api/");
   if($response->code == 200) echo $response->body;
```
