<?php

   use PHPUnit\Framework\TestCase,
       FaulkJ\FHIRClient;

   class FHIRClientTest extends TestCase {

      public function testIsThereAnySyntaxError() {
         $var = new FHIRClient("https://launch.smarthealthit.org", "12345-67890-12345-67890", "-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAvYhGQUcQNRwizTzKHKscZ753NdvTiZj1DXAe0DITaLsm+a7J
7HTXz+PZX3Rt7y4t425McuYX8Syi07jhv8C6XdubgKMnsHSHsnocMvMx0v6sM1Ig
i1kwjogXqCnqagP3+fJgL2xbDViojz3GcDLiUcO9SLebGG9sjSg1b3A2A7F69Rub
jwwVbKmIDQAzqgGacT+49wwyMHiVxRY/LcTXoWWvKHEjB/xaCmYpkpkWLZgYLGyQ
VDGPiVYasDh5rsVVDcaKY6fQy3vKE287fD1altS3LU9nU/THuDqfIJiLIfLZl/Tm
GPzP57OAFKpalCoR8m5Rj4CCp7ImRlPgXCnU0wIDAQABAoIBAQCaMHcXO/zPeChH
16CFWh8ttyG8Sy34ztrtJ72pmlN37Gl0zuGu4A+CLNb2dP3Ki0MBtwWyo8XuimWc
4Nem69+x46rKJ/Ft+B8872JpgLeT82OcDMA1HUYHbmfwgskVnkzqpmfhrMEWRn46
qgL53CuKYTdoJRaj9mHVJFT0Z9FJhn1WZtY8zc/gmaWyTbXZt+Y7wBat0BgUmTP5
BQqFeTtqWdN0bWJ8RfmI9ZmeHdS6PhWYsch6KTPo4Q7VFZ2ynTYFONx+aQcN1/1z
FAxMuoCVmTzrbm8sd6sorxs1+02TqKSeZNUEBXNmTDG2dwIzLuZ5kAm9oArS+AUQ
MrMcYBGBAoGBAPNc9XhFxrVqoaQzw5FmRYHYWFEE3bsCb6qGOT5y51jKNHqhMz9T
BjCb0G2ohu6+g/sgNiDCkdrJxYcBIVLuT94ckLmMfucbK3LHme/NumV9RhPuY2Kx
5OkiYJsdrwi9AV11e/4MZf9P5emarqzaAxb8oxlDkZkyiqkjrk5HV6VDAoGBAMdf
vUmzTvSnFPpIHgmdug952wGgD77kgda0Evl/DuPAj7D0Z+/qAcrJ6ivoRKcd3G9y
xuZIa7lZCYUdhjBB/bECnc8xNeiPnDWEVKymeEqpxKIvrN/qfomia7vi/54r6Mh7
7ZFn3VLewL0gOphXD94DnDm/kMLDBZvdQXiUc1ExAoGABIIOUgII4kdtYxtKXiEk
3Hjjeey7JsGuy9vcp5l9S5nDSxo9Vsj07mWUgNOEXFvPGhHIruaryP+/1vZgZabg
d97Tl3xQxXstXNzxrw2CjGq7p5bc5HEjKmZmn7j3CxRlOBP7DgOwx//05FTnM3B+
aiiX5NnpkorrIqL0kaKkrv0CgYB+prL4Po/JmtoYo/dw5GFts1sMjUFzYnWYjov/
MlejFpAxORFNtrmsuNepTMNP5ghCRAdWAmtsMsN5bGfx//nImIDnPbuhIJl65bVk
d9uykmX3IZIQLEZ16FfH40u+juYxdYhU9kYCfr6xZefTHntV7bUweiDbmEfX25Xb
o3IeAQKBgQDyHZCHq/rjtJ1NqGCvk1HOq5pfYugDP0KShEdOgfEulBDTDGs5jZ9+
84IY97wpute11zJrVKEKNnoLo50f5+hDV2DYS6oLW+TLL01h/xkp2xFt3YZNkEox
vxzZy4uJov/ORBEb4IUif1O1zUR/MRbCuWq39Em7eErU09k6wF6J8g==
-----END RSA PRIVATE KEY-----", ["tokenURI" => "v/r4/auth/token"]);
         $this->assertTrue(is_object($var));
         unset($var);
      }

   }

?>