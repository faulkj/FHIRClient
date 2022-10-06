<?php namespace FaulkJ;
   /*
    * FHIR Client Class v2.0
    * Extends WebClient
    *
    * Kopimi 2022 Joshua Faulkenberry
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
      private $status        = "initializing";
      private $authenticated = false;
      private $state         = null;
      private $secret        = null;
      private $scope         = null;
      private $signingKey    = null;
      private $cliMode       = false;

      public function __construct(string $host, string $clientID, string $redirectURI, $options = []) {
         $options = (object) $options;
         $this->clientID = $clientID;
         $this->secret   = $options->secret || null;
         $this->state    = $options->state || null;
         $this->authURI  = $options->authURI || null;
         $this->tokenURI = $options->tokenURI || null;
         $this->scope    = $options->scope || null;
         $this->cliMode  = php_sapi_name() === "cli";

         if(strpos($redirectURI, "http://") === 0 || strpos($redirectURI, "https://") === 0) $this->redirectURI = $redirectURI;
         else $this->signingKey = $redirectURI;

         if(!$this->cliMode && session_status() == PHP_SESSION_NONE) session_start();

         if($this->session("tokenURI") && !$tokenURI) $this->tokenURI = $this->session("tokenURI");
         else if($this->tokenURI) $this->session("tokenURI", $this->tokenURI);
         if($this->session("iss")) $this->iss = $this->session("iss");

         if($this->session("param")) {
            if($this->state && $this->session("param", false, $this->state)) {
               $this->param = $this->session("param", false, $this->state);
               if($this->param->expires < time()) {
                  $this->param = null;
                  $this->session("param", false, $this->state, false, true);
                  $this->authenticated = false;
               }
               else $this->authenticated = true;
            }
            else if(is_object($this->session("param"))) {
               $this->param = $this->session("param");
               if($this->param->expires < time()) {
                  $this->param = null;
                  $this->session("param", false, false, false, true);
                  $this->authenticated = false;
               }
               else $this->authenticated = true;
            }
         }

         parent::__construct($host);
      }

      public function getConformance($iss, $hdr = null) {
         $this->iss = $iss;
         $this->session("iss", $this->iss);
         $param = [
            "target" => "{$iss}/metadata",
            "accept" => "application/fhir+json"
         ];
         if($hdr) $param["headers"] = $hdr;
         $response = $this->request($param);
         if($response->code == 200) $conf = json_decode($response->body);
         else {
            trigger_error("Error retrieving FHIR metadata!");
            die("Error retrieving FHIR metadata from {$this->protocol}://{$this->host}/{$iss}/metadata");
         }

         foreach($conf->rest[0]->security->extension[0]->extension as $ext) switch($ext->url) {
            case "authorize": $this->authURI = str_replace("{$this->protocol}://{$this->host}/", "", $ext->valueUri);
                              break;
            case "token"    : $this->tokenURI = str_replace("{$this->protocol}://{$this->host}/", "", $ext->valueUri);
                              break;
         }

         if(!$this->authURI)  trigger_error("No authorization URL!");

         $this->session("tokenURI", $this->tokenURI);
         return $this;
      }

      public function getAuthCode($launch = null, $challenge = null, $challengeMethod = null, $scope = "launch") {
         if($this->scope) $scope = $this->scope;
         $params = [
            "response_type" => "code",
            "client_id"     => $this->clientID,
            "redirect_uri"  => $this->redirectURI,
            "scope"         => $scope,
            "state"         => $this->state
         ];

         if($this->tokenURI) $params["aud"] = "{$this->protocol}://{$this->host}/{$this->tokenURI}";
         if($launch) $params["launch"] = $launch;
         if($challenge) $params["challenge"] = $challenge;
         if($challengeMethod) $params["challengeMethod"] = $challengeMethod;
         die(header("Location: {$this->protocol}://{$this->host}/{$this->authURI}?" . http_build_query($params)));
      }

      public function getAccessToken($code = null, $verifier = null) {
         if($this->signingKey) {
            $postData = [
               "grant_type"            => "client_credentials",
               "client_assertion_type" => "urn:ietf:params:oauth:client-assertion-type:jwt-bearer",
               "client_assertion"      => $this->createJWT()
            ];

            if(!$postData["client_assertion"]) return false;

            $reqData = [
               "target" => $this->tokenURI,
               "method" => "post",
               "data"   => $postData
            ];
         }
         else {
            $postData = [
               "grant_type"    => "authorization_code",
               "code"          => $code,
               "redirect_uri"  => $this->redirectURI
            ];
            if(!$this->secret) $postData["client_id"] = $this->clientID;
            if($verifier) $postData["code_verifier"] = $verifier;
            $reqData = [
               "target" => $this->tokenURI,
               "method" => "post",
               "data"   => $postData
            ];
            if($this->secret) $reqData["headers"] = "Authorization: Basic " . base64_encode(urlencode($this->clientID) . ":" . urlencode($this->secret));
         }

         $response = $this->request($reqData);

         if($response->code == 200) {
            $param = json_decode($response->body);
            if($this->secret) {
               $param->secret = $this->secret;
               $param->tokenURI = $this->tokenURI;
            }
            $param->expires = time() + $param->expires_in;
            $this->param = $param;
            $this->session("param", $param, $this->state);
            $this->session("tokenURI", null);
            $this->authenticated = true;
         }
         else {
            trigger_error("Unable to retreive FHIR access token!");
            die("Error retreiving FHIR access token.\n\n{$response->body}");
         }

         return $this;
      }

      public function refreshAccessToken() {
         if(!$this->secret) {
            if($this->state && $this->session("param", false, $this->state)) $this->session("param", null, $this->state);
            else if(is_object($this->session("param"))) $this->session("param", null);

            return (object) [
               "nosecret" => true
            ];
         }
         $postData = [
                        "grant_type"    => "refresh_token",
                        "refresh_token" => $this->param->refresh_token
                    ];
         $reqData = [
                        "target"  => "{$this->tokenURI}",
                        "method"  => "post",
                        "data"    => $postData,
                        "headers" => "Authorization: Basic " . base64_encode(urlencode($this->clientID) . ":" . urlencode($this->secret))
                    ];

         $response = $this->request($reqData);

         $param = json_decode($response->body);
         if($response->code == 200) {
            $this->session("param", $param->access_token, $this->state, "access_token");
            if(isset($param->refresh_token)) $this->session("param", $param->refresh_token, $this->state, "refresh_token");
            $this->session("param", $param->expires_in, $this->state, "expires_in");
            $this->session("param", time() + $param->expires_in, $this->state, "expires");
            return (object) [
               "success" => true,
               "expires" => $this->session("param", false, $this->state, "expires")
            ];
         }
         else {
            if($this->state && $this->session("param", false, $this->state)) $this->session("param", null, $this->state);
            else if(is_object($this->session("param"))) $this->session("param", null);

            $param->success = false;
            return $param;
         }
      }

      public function authenticated() {
         return $this->authenticated;
      }

      public function state() {
         return $this->state;
      }

      public function param($p = null) {
         if($p === null) return $this->param;
         return isset($this->param->$p) ? $this->param->$p : null;
      }

      public function query($params) {
         if(!$this->authenticated) trigger_error("Attempting to query when not authenticated.");
         if(is_string($params)) $params = [
            "target" => $params
         ];
         $params["target"] = "{$this->iss}/{$params["target"]}";
         if(!isset($params["accept"])) $params["accept"] = "application/fhir+json";
         if(!isset($params["headers"])) $params["headers"] = [];
         array_push($params["headers"], "Authorization: " . ucfirst($this->param->token_type) . " {$this->param->access_token}");
         return $this->request($params);
      }

      private function createJWT() {
         function base64url_encode($data) {
            $b64 = base64_encode($data);
            if($b64 === false) return false;
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
         if($this->debug) var_dump(file_get_contents($this->signingKey));
         if($pkeyid = openssl_pkey_get_private(file_get_contents($this->signingKey))) {
            $signature = null;
            $data = base64url_encode($header) . "." . base64url_encode($payload);
            if(openssl_sign($data, $signature, $pkeyid, "RSA-SHA384")) {
               $ok = openssl_verify($data, $signature, openssl_pkey_get_public(openssl_pkey_get_details($pkeyid)["key"]), "RSA-SHA384");
               if ($ok === 0) {
                  trigger_error("Invalid JWT signature.");
                  return false;
               } elseif ($ok !== 1) {
                  trigger_error("Invalid JWT signature: " . openssl_error_string());
                  return false;
               }

               return base64url_encode($header) . "." . base64url_encode($payload) . "." . base64url_encode($signature);
            }
            else {
               trigger_error("Unable to generate JWT signature.");
               return false;
            }
            openssl_free_key($pkeyid);
         }
         else {
            trigger_error("Unable to retrieve JWT signing key.");
            return false;
         }
      }

      private function session($param, $val = false, $state = false, $stateParam = false, $wipe = false) {
         if(!isset($_SESSION["FHIR"])) $_SESSION["FHIR"] = (object) [];

         if($wipe) {
            if($state) unset($_SESSION["FHIR"]->$param[$state]);
            else unset($_SESSION["FHIR"]);
         }
         else if($val === false && $state && !$stateParam) return isset($_SESSION["FHIR"]->$param[$state]) ? $_SESSION["FHIR"]->$param[$state] : null;
         else if($val === false && !$stateParam) return isset($_SESSION["FHIR"]->$param) ? $_SESSION["FHIR"]->$param : null;
         else if($val !== false && !$state) {
            if($val === null) unset($_SESSION["FHIR"]->$param);
            else $_SESSION["FHIR"]->$param = $val;
         }
         else if($val !== false && $stateParam) $_SESSION["FHIR"]->$param[$state]->$stateParam = $val;
         else if($val !== false && $state) {
            if(!isset($_SESSION["FHIR"]->$param)) $_SESSION["FHIR"]->$param = [];
            if($val === null) unset($_SESSION["FHIR"]->$param[$state]);
            else $_SESSION["FHIR"]->$param[$state] = $val;
         }
         else return isset($_SESSION["FHIR"]->$param[$state]->$stateParam) ? $_SESSION["FHIR"]->$param[$state]->$stateParam : null;
      }

   }
?>