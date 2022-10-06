# FHIRClient
A simple PHP client for SMART on FHIR, the standard API for integrating applications with any modern healthcare system.

### Installation
```bash
$ composer require faulkj/fhirclient
```

## Basic Usage


### EMR Ebedded Mode

On initial load:
```php
//Assumining this is the URL loaded by the EMR:  https://my.website.com/launch/?iss=https://my.fhirserver.com/FHIRProxy/api/FHIR/R4&launch=abc123

use FaulkJ\FHIRClient;
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

This will first get an Conformance Statement/SMART Configuration from _my.fhirserver.com/FHIRProxy/api/FHIR/R4_ to retrieve the authorization and token endpoints.  It will then request an authorization code from the authorization endpoint, triggering a redirect to _my.website.com_.

On _my.website.com_ when redirected:
```php
use FaulkJ\FHIRClient;
session_start();

$fc = new FHIRClient(...$_SESSION["fhirParams"]);
$fc->getAccessToken($_GET["code"]);

//You are now authenticated and may query the FHIR server
$obs = $fc->query("Observation?patient=12345678&code=12345-6");
if($obs->code == 200) echo $obs->body;
```

On subsequent page loads or AJAX calls, the FHIRClient will need to be reinstanciated before yoy can send a query:
```php
use FaulkJ\FHIRClient;
session_start();

$fc = new FHIRClient(...$_SESSION["fhirParams"]);
$pat = $fc->query("Patient/12345678");
if($pat->code == 200) echo $pat->body;
```


### Standalone Mode

On initial load:
```php
use FaulkJ\FHIRClient;
session_start();

$iss = parse_url($_GET["iss"]);
$_SESSION["fhirParams"] = [
   "https:/my.fhirserver.com",
   "1234-5678-9012-3456-7890",
   "https://my.website.com",
   [
      "state"    => base64_encode(rand()),
      "authURI"  => "FHIRProxy/oauth2/authorize",
      "tokenURI" => "FHIRProxy/oauth2/token"
   ]
];
$fc = new FHIRClient(...$_SESSION["fhirParams"]);
$fc->getConformance($_GET["iss"]);
$fc->getAuthCode();
```
This example includes a randomly generated _state_ parameter and will request an authorization code from _my.fhirserver.com/FHIRProxy/oauth2/authorize_, triggering a redirect to _my.website.com_.

On _my.website.com_ when redirected:
```php
use FaulkJ\FHIRClient;
session_start();

$fc = new FHIRClient(...$_SESSION["fhirParams"]);
$fc->getAccessToken($_GET["code"]);

//You are now authenticated and may query the FHIR server
$obs = $fc->query("Observation?patient=12345678&code=12345-6");
if($obs->code == 200) echo $obs->body;
```

On subsequent page loads or AJAX calls, the FHIRClient will need to be reinstanciated before yoy can send a query:
```php
use FaulkJ\FHIRClient;
session_start();

$fc = new FHIRClient(...$_SESSION["fhirParams"]);
$pat = $fc->query("Patient/12345678");
if($pat->code == 200) echo $pat->body;
```

### Backend Mode

```php
use FaulkJ\FHIRClient;

$fc = (new FHIRClient(
   "https:/fhir.server.com",
   "1234-5678-9012-3456-7890",
   "D:\\privatekey.pem",
   [
      "tokenURI" => "FHIRProxy/oauth2/token"
   ]
))->debug(true);

$fc->getAccessToken();

$response = $fc->query("FHIRProxy/path/to/api/");
if($response->code == 200) echo $response->body;
```
