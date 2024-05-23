<?php namespace FaulkJ;

/*
 * FHIR Client Class v3.0
 * Extends WebClient
 *
 * Kopimi 2024 Joshua Faulkenberry
 * Unlicensed under The Unlicense
 * http://unlicense.org/
 */

class FHIRClient extends WebClient {

   private $clientID;
   private $redirectURI;
   private $authURI;
   private $tokenURI;
   private $iss;
   private $param;
   private $status       = 0;
   private $cliMode      = false;
   private $state        = null;
   private $secret       = null;
   private $signingKey   = null;
   private $statusChangedCallback = null;
   private $statuses     = [
      "initialized",
      "configured",
      "authorized",
      "authenticated",
      "expired"
   ];

   public function __construct(string $host, string $clientID, $options = []) {
      parent::__construct($host);
      $this->clientID = $clientID;
      foreach ($options as $opt => $val) if (isset($this->$opt) || is_null($this->$opt)) $this->$opt = $val;
      $this->cliMode = php_sapi_name() === "cli";

      if (!$this->cliMode && session_status() == PHP_SESSION_NONE) session_start();

      if ($this->sessionHost("tokenURI") && !isset($this->tokenURI)) $this->tokenURI = $this->sessionHost("tokenURI");
      else if ($this->tokenURI) $this->sessionHost("tokenURI", $this->tokenURI);

      if ($this->sessionHost("authURI") && !isset($this->authURI)) $this->authURI = $this->sessionHost("tokenURI");
      else if ($this->authURI) $this->sessionHost("authURI", $this->authURI);

      if ($this->sessionHost("iss")) $this->iss = $this->sessionHost("iss");

      if ($this->sessionParam("status")) $this->status = array_search($this->sessionParam("status"), $this->statuses);
      if (isset($_GET["code"])) $this->status(2);

      $this->param = $this->sessionParam(true);

      if (count((array) $this->param) && isset($this->param->expires)) {
         if ($this->param->expires < time()) {
            // expired
            $this->sessionParam(null);
            $this->status(4);
         } else $this->status(3);
      }
   }

   public function getConformance(string $iss, string | array $hdr = null) {
      $this->iss = $iss;
      $this->sessionHost("iss", $this->iss);
      $param = [
         "target" => "{$this->iss}/metadata",
         "accept" => "application/fhir+json"
      ];
      if ($hdr) $param["headers"] = (array) $hdr;

      $response = $this->request($param);
      if ($response->code == 200) $conf = json_decode($response->body);
      else throw new \Exception("Error retrieving FHIR metadata from {$this->protocol}://{$this->host}/{$this->iss}/metadata");

      foreach ($conf->rest[0]->security->extension[0]->extension as $ext) switch ($ext->url) {
         case "authorize":
            $this->authURI = str_replace("{$this->protocol}://{$this->host}/", "", $ext->valueUri);
            break;
         case "token":
            $this->tokenURI = str_replace("{$this->protocol}://{$this->host}/", "", $ext->valueUri);
            break;
      }

      if (!$this->authURI) throw new \Exception("No authorization URL!");

      $this->sessionHost("authURI", $this->authURI);
      $this->sessionHost("tokenURI", $this->tokenURI);
      $this->status(1);
      return $this;
   }

   public function getAuthCode(array | object $options = []) {
      $options = (object) $options;

      $params = [
         'client_id'       => $this->clientID,
         'redirect_uri'    => $this->redirectURI,
         'aud'             => $this->tokenURI ? "{$this->protocol}://{$this->host}/{$this->tokenURI}" : null,
         'state'           => $this->state,
         'response_type'   => 'code',
         'scope'           => 'launch',
         'launch'          => null,
         'challenge'       => null,
         'challengeMethod' => null
      ];
      if (isset($options->params)) $params = array_merge($params, $options->params);

      if (!$this->authURI) throw new \Exception("No authorization URL!");

      $redirectTo = "{$this->protocol}://{$this->host}/{$this->authURI}?" . http_build_query($params);

      if ($this->debug) die("\n\n{$redirectTo}");
      if (isset($options->redirect) && $options->redirect != true) return $redirectTo;
      else die(header("Location: {$redirectTo}"));
   }

   public function getAccessToken($code = null, $verifier = null) {
      if ($this->signingKey) {
         $postData = [
            "grant_type"            => "client_credentials",
            "client_assertion_type" => "urn:ietf:params:oauth:client-assertion-type:jwt-bearer",
            "client_assertion"      => $this->createJWT()
         ];

         if (!$postData["client_assertion"]) return false;

         $reqData = [
            "target" => $this->tokenURI,
            "method" => "post",
            "data"   => $postData
         ];
      } else {
         $postData = [
            "grant_type"    => "authorization_code",
            "code"          => $code,
            "redirect_uri"  => $this->redirectURI
         ];
         if (!$this->secret) $postData["client_id"] = $this->clientID;
         if ($verifier) $postData["code_verifier"] = $verifier;
         $reqData = [
            "target" => $this->tokenURI,
            "method" => "post",
            "data"   => $postData
         ];
         if ($this->secret) $reqData["headers"] = "Authorization: Basic " . base64_encode(urlencode($this->clientID) . ":" . urlencode($this->secret));
      }

      $response = $this->request($reqData);

      if ($response->code == 200) {
         $param = json_decode($response->body);
         if ($this->secret) {
            $param->secret = $this->secret;
            $param->tokenURI = $this->tokenURI;
         }
         $param->expires = time() + $param->expires_in;

         $this->sessionParam(null, $param);
         $this->sessionHost("tokenURI", null);
         $this->status(3);
      } else throw new \Exception("Unable to retreive FHIR access token!");

      return $this;
   }

   public function refreshAccessToken() {
      if (!$this->secret) {
         $this->sessionParam(null);

         return (object) ["nosecret" => true];
      }

      $postData = [
         "grant_type"    => "refresh_token",
         "refresh_token" => $this->param ? $this->param->refresh_token : null
      ];

      $reqData = [
         "target"  => "{$this->tokenURI}",
         "method"  => "post",
         "data"    => $postData,
         "headers" => "Authorization: Basic " . base64_encode(urlencode($this->clientID) . ":" . urlencode($this->secret))
      ];

      $response = $this->request($reqData);

      $param = json_decode($response->body);
      if ($response->code == 200) {
         $this->sessionParam("access_token", $param->access_token);

         if (isset($param->refresh_token)) $this->sessionParam("refresh_token", $param->refresh_token);
         $this->sessionParam("expires_in", $param->expires_in);
         $this->sessionParam("expires", time() + $param->expires_in);

         return (object) [
            "success" => true,
            "expires" => $this->param->expires
         ];
      } else {
         $this->sessionParam(null);

         $param->success = false;
         return $param;
      }
   }

   public function state() {
      return $this->state;
   }

   public function status(int $status = null) {
      if ($status !== null) {
         $changed = $status !== $this->status;
         $this->status = $status;
         $this->sessionParam("status", $this->statuses[$status]);
         $changed && $this->statusChanged();
      }
      return $this->status !== null ? $this->statuses[$this->status] : null;
   }

   public function statusChanged(callable $callback = null) {
      if ($callback) $this->statusChangedCallback = $callback;
      else if ($this->statusChangedCallback) call_user_func($this->statusChangedCallback, $this->status);
   }

   public function param($p = null) {
      if ($p === null) return $this->param;
      return isset($this->param->$p) ? $this->param->$p : null;
   }

   public function query($params) {
      $this->status != 3 && throw new \Exception("Attempting to query when not authenticated.");
      if (is_string($params)) $params = [ "target" => $params ];
      if (isset($params['version'])) {
         preg_match('/^(.*?\/?api)(?:\/|$)/', $this->iss, $matches);
         $params["target"] = "{$matches[1]}/{$params['version']}/{$params["target"]}";
      } else $params["target"] = "{$this->iss}/{$params["target"]}";
      if (!isset($params["accept"])) $params["accept"] = "application/fhir+json";
      if (!isset($params["headers"])) $params["headers"] = [];
      $params["headers"] = (array) $params["headers"];
      array_push($params["headers"], "Authorization: " . ucfirst($this->param->token_type) . " {$this->param->access_token}");

      return $this->request($params);
   }

   private function createJWT() {
      function base64url_encode($data) {
         $b64 = base64_encode($data);
         if ($b64 === false) return false;
         $url = strtr($b64, '+/', '-_');
         return rtrim($url, '=');
      }

      $header = json_encode([
         "alg" => "RS384",
         "typ" => "JWT"
      ]);

      $payload = json_encode([
         "iss" => $this->clientID,
         "sub" => $this->clientID,
         "aud" => "{$this->protocol}://{$this->host}/{$this->tokenURI}",
         "jti" => uniqid(time(), true),
         "exp" => time() + (5 * 60)
      ]);

      if ($this->debug) var_dump(file_get_contents($this->signingKey));
      if ($pkeyid = openssl_pkey_get_private(file_get_contents($this->signingKey))) {
         $signature = null;
         $data = base64url_encode($header) . "." . base64url_encode($payload);
         if (openssl_sign($data, $signature, $pkeyid, "RSA-SHA384")) {
            $ok = openssl_verify($data, $signature, openssl_pkey_get_public(openssl_pkey_get_details($pkeyid)["key"]), "RSA-SHA384");
            if ($ok === 0) {
               throw new \Exception("Invalid JWT signature.");
               return false;
            } elseif ($ok !== 1) {
               throw new \Exception("Invalid JWT signature: " . openssl_error_string());
               return false;
            }

            return base64url_encode($header) . "." . base64url_encode($payload) . "." . base64url_encode($signature);
         } else {
            throw new \Exception("Unable to generate JWT signature.");
            return false;
         }
      } else {
         throw new \Exception("Unable to retrieve JWT signing key.");
         return false;
      }
   }

   private function &initSession() {
      if (!isset($_SESSION["FHIRClient"])) $_SESSION["FHIRClient"] = [];
      if (!isset($_SESSION["FHIRClient"][$this->host])) $_SESSION["FHIRClient"][$this->host] = (object) [];
      return $_SESSION["FHIRClient"][$this->host];
   }

   private function &sessionHost($parameter = null, $value = false) {
      static $null = null;
      $session = &$this->initSession();

      if ($parameter === null) return $session;
      else if ($value === false) {
         if (isset($session->$parameter)) {
            $result = &$session->$parameter;
            return $result;
         } else {
            return $null;
         }
      } else if ($value === null) {
         unset($session->$parameter);
         return $null;
      } else {
         $session->$parameter = $value;
         $result = &$session->$parameter;
         return $result;
      }
      return $null;
   }

   private function &sessionParam($parameter = true, $value = false) {
      static $null = null;
      $session = &$this->initSession();
      $clear = function () use ($session) {
         if ($this->state) unset($session->param[$this->state]);
         else unset($session->param);
      };

      if ($this->state) {
         if (!isset($session->param)) $session->param = [];
         if (!isset($session->param[$this->state])) $session->param[$this->state] = (object)[];
         $this->param = &$session->param[$this->state];
      } else {
         if (!isset($session->param)) $session->param = (object)[];
         $this->param = &$session->param;
      }

      if ($parameter === true) {
         return $this->param;
      } else if ($parameter === null && $value !== false) {
         $clear();
         foreach ((array)$value as $key => $val) {
            $this->sessionParam($key, $val);
         }
      } else if ($parameter === null) $clear();
      else if ($value === false) {
         if (isset($this->param->$parameter)) {
            $result = &$this->param->$parameter;
            return $result;
         } else {
            return $null;
         }
      } else if ($value === null) {
         unset($this->param->$parameter);
         return $null;
      } else {
         $this->param->$parameter = $value;
         $result = &$this->param->$parameter;
         return $result;
      }
      return $null;
   }
}

?>